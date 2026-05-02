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
 * - `acl` ACL
 *
 * @var array
 */
    protected $_defaultConfig = [
        'pathTemplate' => 'ufiles/{mime_top_level}/{sha1_5}/{sha1}{extension}',
        'version' => 'latest',
        'bucket' => null,
        'region'  => null,
        'endpoint' => null,
        'usePathStyleEndpoint' => false,
        'publicKey' => null,
        'secretKey' => null,
        'acl' => 'private'
    ];

/**
 * Concurrency limit.
 *
 * @var int
 */
    protected $_concurrencyLimit = 25;


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

        $request = $s3Client->createPresignedRequest($command, $options['expires']);
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
            'acl' => 'public-read',
            'cdn' => true
        ];

        $key = $this->_normalizeKey($key);
        if ($options['dryRun']) {
            return $this->_getObjectUrl(parent::config('bucket'), $key, $options);
        }

        try {
            $s3Client = $this->_loadS3Client();
            $result = $s3Client->putObject([
                'Bucket' => parent::config('bucket'),
                'Key' => $key,
                'ContentType' => $options['mime'] ?? Mimetype::detect($contents),
                'Body' => $contents,
                'ACL' => $this->_getNormalizedAcl($options['acl']),
                'Metadata' => [
                    'original-filename' => $options['name'] ?? pathinfo($key, PATHINFO_BASENAME)
                ] + $options['metadata']
            ]);
            $objectUrl = $result->get('ObjectURL');
        } catch (S3Exception $e) {
            $this->_setError($e->getMessage());
            return null;
        }
        return $objectUrl;
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

        if ($commands) {
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
        }

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
 * Get file public URL.
 *
 * @param string $key File path/key
 * @param array $options Options
 * @return string
 */
    public function url(string $key, array $options = []): string {
        if (str_contains($key, 'http')) {
            return $key;
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
 * Check if file exists.
 *
 * @param string $key File key/path
 * @return bool
 */
    public function exists(string $key): bool {
        try {
            $s3Client = $this->_loadS3Client();
            // Use the HeadObject method to check if the object exists
            $result = $s3Client->headObject([
                'Bucket' => parent::config('bucket'),
                'Key' => $this->_normalizeKey($key)
            ]);
            return true;
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            $this->_setError($e->getMessage());
        }
        return false;
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
        return $type . '; filename="' . $options['name'] ?? pathinfo($key, PATHINFO_BASENAME) . '"';
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
 * Get ACL normalized for AWS S3.
 *
 * @param string $acl ACL
 * @return string
 */
    protected function _getNormalizedAcl(?string $acl) {
        if ($acl === null || $acl == 'public') {
            $acl = 'public-read';
        }
        return $acl;
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
 * Instantiate an Amazon S3 client.
 *
 * @return S3Client
 */
    protected function _loadS3Client() {
        return new S3Client([
            'version' => parent::config('version'),
            'region'  => parent::config('region'),
            'endpoint' => Text::insert(parent::config('endpoint'), parent::config()),
            'use_path_style_endpoint' => parent::config('usePathStyleEndpoint'),
            'credentials' => [
                'key' => parent::config('publicKey'),
                'secret' => parent::config('secretKey'),
            ]
        ]);
    }

}
