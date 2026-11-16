<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\FilesystemManager\FileStorage\Adapter;

use Aws\S3\S3Client;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Aws\S3\Exception\S3Exception;
use Nata\FilesystemManager\Exception\FileStorageException;
use Nata\FilesystemManager\Mimetype;
use Nata\FilesystemManager\FileStorage\Adapter;
use Nata\Utility\Text;

/**
 * An interface for AWS S3.
 *
 * @todo Needs to be refactored to use the new FileStorage API
 */
class AwsS3 extends Adapter {

/**
 * Default config.
 *
 * - `pathTemplate` Path template
 * - `version` AWS SDK version
 * - `bucket` Bucket name
 * - `region` Region
 * - `endpoint` Endpoint
 * - `usePathStyleEndpoint` Use path style endpoint
 * - `publicKey` Public key
 * - `secretKey` Secret key
 * - `acl` ACL of the files put without one
 * - `handler` HTTP handler of the SDK client (tests: Aws\MockHandler)
 *
 * @var array
 */
    protected $_defaultConfig = [
        'pathTemplate' => 'uploads/{mime_top_level}/{sha256_5}/{sha256}{extension}',
        'version' => 'latest',
        'bucket' => null,
        'region'  => null,
        'endpoint' => null,
        'usePathStyleEndpoint' => false,
        'publicKey' => null,
        'secretKey' => null,
        'acl' => 'private',
        'handler' => null
    ];

/**
 * Concurrency limit.
 *
 * @var int
 */
    protected $_concurrencyLimit = 25;

/**
 * SDK client, created on first use.
 *
 * @var \Aws\S3\S3Client|null
 */
    protected $_client;


/**
 * Get file path from storage.
 *
 * ### Options
 * - `cdn` Use CDN endpoint (default: false)
 * - `host` Custom host
 * - `download` Download file
 * - `name` File name
 * - `expires` Expiration time
 * - `responseHeader` Response header
 *
 * @param string $key Key
 * @param array $options File options
 * @return string
 */
    public function get(string $key, array $options = []): ?string {
        $options += [
            'cdn' => true,
            'host' => null,
            'download' => false,
            'name' => null,
            'expires' => null,
            'responseHeader' => null
        ];

        $key = $this->_normalizeKey($key);

        if (!str_contains(parent::config('acl'), 'private') && !$options['expires'] && !$options['download'] && !$options['responseHeader'] && !$options['name']) {
            return $this->_getObjectUrl(parent::config('bucket'), $key, $options);
        }

        // Creating a presigned URL
        $s3Client = $this->_loadS3Client();
        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => parent::config('bucket'),
            'Key' => $key,
            'ResponseContentDisposition' => $this->_getContentDisposition($key, $options),
        ]);

        // Without an expiry the SDK signs a negative X-Amz-Expires, which S3/R2 refuse (400).
        $request = $s3Client->createPresignedRequest($command, $options['expires'] ?: (parent::config('expires') ?: '+15 minutes'));
        // Get the actual presigned-url
        $objectUrl = (string)$request->getUri();
        if ($options['host']) {
            $objectUrl = $this->_getObjectUrlWithCustomEndpoint($objectUrl, $options['host']);
        }
        return $objectUrl;
    }

/**
 * Put file.
 *
 * ### Options
 * - `mime` Content type (default: detected)
 * - `dryRun` Return the URL without storing
 * - `acl` ACL; without it, `visibility` ("public" or "private"), then the
 *   `acl` of the store
 * - `metadata` User metadata; only scalar values are sent (as strings)
 * - `checksum` Send the SHA-256 of the contents: the storage refuses the
 *   file if it does not arrive with it
 *
 * @param string $key Key
 * @param string $contents Contents
 * @param array $options Options
 * @return string|null
 */
    public function put(string $key, string $contents, array $options = []): ?string {
        $options += [
            'mime' => null,
            'dryRun' => false,
            'host' => null,
            'acl' => null,
            'visibility' => null,
            'name' => null,
            'metadata' => [],
            'checksum' => false,
            'cdn' => true
        ];

        $key = $this->_normalizeKey($key);
        if ($options['dryRun']) {
            return $this->_getObjectUrl(parent::config('bucket'), $key, $options);
        }

        $params = [
            'Bucket' => parent::config('bucket'),
            'Key' => $key,
            'ContentType' => $options['mime'] ?? Mimetype::detect($contents),
            'Body' => $contents,
            'ACL' => $this->_resolveAcl($options['acl'], $options['visibility']),
            'Metadata' => $this->_normalizeMetadata([
                'original-filename' => $options['name'] ?? pathinfo($key, PATHINFO_BASENAME)
            ] + (array)$options['metadata'])
        ];
        if ($options['checksum']) {
            $params['ChecksumSHA256'] = base64_encode(hash('sha256', $contents, true));
        }

        try {
            $result = $this->_loadS3Client()->putObject($params);
        } catch (AwsException $e) {
            $this->_setError($e->getMessage());
            return null;
        }
        if (empty($result->get('ETag'))) {
            $this->_setError('PutObject succeeded but returned no ETag — object may not have been stored.');
            return null;
        }
        return $this->_getObjectUrl(parent::config('bucket'), $key, $options);
    }

/**
 * Put batch of file handlers.
 *
 * @todo Needs to be refactored to use the new FileStorage API
 * @param array $paths File paths
 * @return array
 */
    public function _putMany(array $paths): array {
        $s3Client = $this->_loadS3Client();
        $bucket = parent::_getRequiredConfigParam('bucket');

        $handlers = $paths;
        $commands = [];
        $indexMap = [];
        foreach ($handlers as $index => $handler) {
            $sourceFile = $handler->get('sourceFile');

            $key = $handler->get('relativePath');
            if (!$key) {
                $key = parent::generateRelativePath($sourceFile);
                $handler->set('relativePath', $key);
            }

            $indexMap[count($commands)] = $index;

            $commands[] = $s3Client->getCommand('PutObject', [
                'Bucket' => $bucket,
                'Key' => $key,
                'ContentType' => $sourceFile->mime(),
                'Body' => $sourceFile->read(),
                'ACL' => $this->_getNormalizedAcl($handler->get('acl')),
                'Metadata' => [
                    'original-filename' => $sourceFile->basename()
                ]
            ]);
        }

        if (!$commands) {
            return [];
        }

        // Create a command pool with a concurrency of N
        $pool = new CommandPool($s3Client, $commands, [
            'concurrency' => $this->_negotiateOptimalConcurrencyLimit(count($commands)),
            // Before
            'before' => function (CommandInterface $command, int $index) use ($handlers, $indexMap) {
                $handler = $handlers[$indexMap[$index]];
                $this->dispatchEvent('FileRepository.Adapter.beforePut', [$handler]);
                $this->dispatchEvent('FileRepository.Adapter.putStatus', [$handler, 'uploading']);
            },
            // Success!
            'fulfilled' => function (ResultInterface $result, int $index) use ($handlers, $indexMap) {
                $handler = $handlers[$indexMap[$index]];
                $this->dispatchEvent('FileRepository.Adapter.afterPut', [$handler, $result->get('ObjectURL')]);
            },
            // Something wrong happened...
            'rejected' => function (S3Exception $reason, int $index) use ($handlers, $indexMap) {
                $handler = $handlers[$indexMap[$index]];
                $this->dispatchEvent('FileRepository.Adapter.putError', [$handler, $reason->getMessage()]);
            },
        ]);

        // clear some memory?
        $commands = [];

        $pool->promise()->wait();

        return [];
    }

/**
 * Delete file.
 *
 * @param string $key Key
 * @return bool
 */
    public function delete(string $key): bool {
        try {
            $s3Client = $this->_loadS3Client();
            // Delete the object
            $result = $s3Client->deleteObject([
                'Bucket' => parent::config('bucket'),
                'Key' => $this->_normalizeKey($key)
            ]);

            // Successful deletion
            return true;
        } catch (AwsException $e) {
            parent::_setError("Error deleting object: " . $e->getMessage());
        }
        return false;
    }

/**
 * Generate presigned URL for form upload.
 *
 * @param string $key Key
 * @param string $mimeType Mime type
 * @return string Presigned URL
 */
    public function presignedFormUploadUrl(string $key, string $mimeType): string {
        $s3 = $this->_loadS3Client();
        $command = $s3->getCommand('PutObject', [
            'Bucket' => parent::config('bucket'),
            'Key' => $this->_normalizeKey($key),
            // 'ACL' => $this->_getNormalizedAcl(parent::config('acl')),
            'ContentType' => $mimeType, // optional but often important
        ]);
        $request = $s3->createPresignedRequest($command, '+1 hour');
        $url = (string)$request->getUri();
        return $url;
    }

/**
 * Get free space in storage repository.
 *
 * @return int
 */
    public function freeSpace(): ?int {
        return null;
    }

/**
 * Get file URL. In a private store (acl "private") the object URL would be
 * refused, so a presigned URL is returned instead (see get()).
 *
 * @param string $key File path/key
 * @param array $options Options
 * @return string
 */
    public function url(string $key, array $options = []): string {
        if (str_contains($key, 'http')) {
            return $key;
        }
        if (str_contains((string)parent::config('acl'), 'private')) {
            return (string)$this->get($key, $options);
        }
        return $this->_getObjectUrl(parent::config('bucket'), $this->_normalizeKey($key), $options);
    }

/**
 * Get key from full path.
 *
 * @param string $fullPath Full path
 * @return string
 */
    public function path(string $fullPath): string {
        if (!str_contains($fullPath, 'http')) {
            return $fullPath;
        }
        return ltrim(parse_url($fullPath, PHP_URL_PATH), '/');
    }

/**
 * Check if file exists. Only a 404 means it does not: any other error
 * (credentials, network) throws, so it is never taken for a missing file.
 *
 * @param string $key File key/path
 * @return bool
 * @throws \Nata\FilesystemManager\Exception\FileStorageException When the storage cannot be asked.
 */
    public function exists(string $key): bool {
        return $this->info($key) !== null;
    }

/**
 * Information about a stored file, from one HEAD request.
 *
 * - `size` Size in bytes
 * - `etag` ETag, without quotes (the MD5 of the contents for a single part
 *   put, in S3 and R2)
 * - `mime` Content type
 * - `modified` Last modified (Unix timestamp)
 * - `metadata` User metadata
 * - `checksumSha256` SHA-256 of the contents (hex), when it was put with
 *   `checksum` and the storage returns it
 *
 * @param string $key File key/path
 * @return array|null Information, or null when the file does not exist
 * @throws \Nata\FilesystemManager\Exception\FileStorageException When the storage cannot be asked.
 */
    public function info(string $key): ?array {
        try {
            $result = $this->_loadS3Client()->headObject([
                'Bucket' => parent::config('bucket'),
                'Key' => $this->_normalizeKey($key),
                'ChecksumMode' => 'ENABLED'
            ]);
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw new FileStorageException(sprintf('Unable to read "%s" in store "%s": %s', $key, $this->getStoreName(), $e->getMessage()), 0, $e);
        }

        $modified = $result->get('LastModified');
        $checksum = $result->get('ChecksumSHA256');
        return [
            'size' => (int)$result->get('ContentLength'),
            'etag' => $result->get('ETag') === null ? null : trim((string)$result->get('ETag'), '"'),
            'mime' => $result->get('ContentType'),
            'modified' => $modified instanceof \DateTimeInterface ? $modified->getTimestamp() : null,
            'metadata' => (array)$result->get('Metadata'),
            'checksumSha256' => $checksum ? bin2hex((string)base64_decode((string)$checksum)) : null,
        ];
    }

/**
 * Contents of a stored file.
 *
 * @param string $key File key/path
 * @return string|null Contents, or null when the file does not exist
 * @throws \Nata\FilesystemManager\Exception\FileStorageException When the storage cannot be asked.
 */
    public function read(string $key): ?string {
        try {
            $result = $this->_loadS3Client()->getObject([
                'Bucket' => parent::config('bucket'),
                'Key' => $this->_normalizeKey($key)
            ]);
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw new FileStorageException(sprintf('Unable to read "%s" in store "%s": %s', $key, $this->getStoreName(), $e->getMessage()), 0, $e);
        }
        return (string)$result->get('Body');
    }

/**
 * Get content disposition.
 *
 * @param string $key Key
 * @param array $options Options
 * @return string
 */
    protected function _getContentDisposition(string $key, array $options): string {
        $type = $options['download'] ? 'attachment' : 'inline';
        $name = $options['name'] ?? pathinfo($key, PATHINFO_BASENAME);
        // Quotes and line breaks would break out of the quoted filename.
        $name = str_replace(['"', '\\', "\r", "\n"], ['', '', '', ''], (string)$name);

        return $type . '; filename="' . $name . '"';
    }

/**
 * Get number of concurrent uploads.
 *
 * @param int $commandCount Number of commands to execute
 * @return int Number of concurrent uploads
 */
    protected function _negotiateOptimalConcurrencyLimit(int $commandCount) {
        if ($commandCount < $this->_concurrencyLimit) {
            return $commandCount;
        }
        return $this->_concurrencyLimit;
    }

/**
 * Get ACL normalized for AWS S3: "public" is "public-read", and any other
 * value with "private" in it (e.g. "private-read") is "private".
 *
 * @param string $acl ACL
 * @return string
 */
    protected function _getNormalizedAcl(?string $acl) {
        if ($acl === null || $acl == 'public') {
            $acl = 'public-read';
        } elseif (str_contains($acl, 'private')) {
            $acl = 'private';
        }
        return $acl;
    }

/**
 * ACL of a put: the one given, else the visibility ("public" or
 * "private"), else the ACL of the store.
 *
 * @param string|null $acl ACL given
 * @param string|null $visibility Visibility given
 * @return string ACL
 */
    protected function _resolveAcl(?string $acl, ?string $visibility): string {
        if ($acl === null && in_array($visibility, ['public', 'private'], true)) {
            $acl = $visibility;
        }
        return $this->_getNormalizedAcl($acl ?? (string)parent::config('acl'));
    }

/**
 * User metadata as S3 accepts it: string values only. Booleans and numbers
 * become strings; arrays, objects and nulls are left out (S3 refuses the
 * whole request for one of them).
 *
 * @param array $metadata Metadata
 * @return array<string, string>
 */
    protected function _normalizeMetadata(array $metadata): array {
        $normalized = [];
        foreach ($metadata as $name => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_scalar($value)) {
                $normalized[(string)$name] = (string)$value;
            }
        }
        return $normalized;
    }

/**
 * Get object URL with custom endpoint.
 *
 * @param string $objectUrl Object URL
 * @param string $endpoint Endpoint
 * @return string Object URL with custom endpoint
 */
    protected function _getObjectUrlWithCustomEndpoint(string $objectUrl, ?string $endpoint): string {
        if (!$endpoint) {
            return $objectUrl;
        }
        $objectHost = parse_url($objectUrl, PHP_URL_HOST);
        $endpointHost = parse_url($endpoint, PHP_URL_HOST);
        return str_replace($objectHost, $endpointHost, $objectUrl);
    }

/**
 * Get object URL.
 *
 * @param string $bucket Bucket
 * @param string $key Key
 * @return string
 */
    protected function _getObjectUrl(string $bucket, string $key, array $options): string {
        return $this->_loadS3Client()->getObjectUrl($bucket, $key);
    }

/**
 * Normalize key.
 *
 * @param string $key Key
 * @return string Normalized key
 */
    protected function _normalizeKey(string $key): string {
        return ltrim($key, '/');
    }

/**
 * Amazon S3 client, created once per adapter.
 *
 * @return S3Client
 */
    protected function _loadS3Client() {
        if ($this->_client === null) {
            $options = [
                'version' => parent::config('version'),
                'region'  => parent::config('region'),
                'endpoint' => Text::insert((string)parent::config('endpoint'), array_filter(parent::config(), 'is_scalar')),
                'use_path_style_endpoint' => parent::config('usePathStyleEndpoint'),
                'credentials' => [
                    'key' => parent::config('publicKey'),
                    'secret' => parent::config('secretKey'),
                ]
            ];
            if (parent::config('handler') !== null) {
                $options['handler'] = parent::config('handler');
            }
            $this->_client = new S3Client($options);
        }
        return $this->_client;
    }

}
