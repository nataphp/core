<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
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

namespace Nata\FilesystemManager\File\DataSource;

use Nata\I18n\Time;
use Nata\Http\Client;
use Nata\Utility\Validation;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\DataSourceInterface;
use InvalidArgumentException;
use Nata\FilesystemManager\Folder;

/**
 * Convenience class for reading remote files information.
 */
class Web extends Memory implements DataSourceInterface {

/**
 * File cache config.
 * If set 'true', uses config 'default'.
 *
 * @var string
 */
    protected $_cache;

/**
 * File exists.
 *
 * @var bool
 */
    protected $_exists;

/**
 * File URL.
 *
 * @var string
 */
    protected $_url;

/**
 * Read only.
 *
 * @var bool
 */
    protected $_readOnly = true;

/**
 * Last access.
 *
 * @var \Nata\I18n\Time
 */
    protected $_lastAccess;

/**
 * Last modified date.
 *
 * @var \Nata\I18n\Time
 */
    protected $_lastChange;

/**
 * HTTP response.
 *
 * @var \Nata\Http\Client\Response
 */
    private static $_response = [];


/**
 * Constructor.
 *
 * @param string $url File URL
 * @param array $options Options
 * @return void
 */
    public function __construct(string $url, array $options = []) {
        $options += [
            'cache' => null
        ];

        if (!Validation::url($url)) {
            throw new InvalidArgumentException(sprintf('Invalid URL given "%s"', $url));
        }

        $this->_cache = $options['cache'];
        $this->_url = $url;
    }

/**
 * Returns the real file name (with or ).
 *
 * @param bool $withExtension Whether to include the extension or not.
 * @return string The file name with or without extension.
 */
    public function realname(): ?string {
        $parts = parse_url($this->_url, PHP_URL_PATH);
        $parts = explode('/', $parts);
        $realname = array_pop($parts);
        if ($realname) {
            return $realname;
        }

        $response = $this->getResponse();
        $contentDisposition = $response->getHeader('Content-Disposition');
        $realname = null;
        if ($contentDisposition && preg_match("/filename=\"(.*)\"/", $contentDisposition, $match) > 0) {
            $realname = $match[1];
        }
        return $realname;
    }

/**
 * Get the mime type of the file.
 *
 * @return string The mimetype of the file, or false if reading fails.
 */
    public function mime(): ?string {
        $response = $this->getResponse();
        $contentType = $response->getHeader('Content-Type');
        if ($contentType === null) {
            return parent::mime();
        }
        [$mime] = explode(';', $contentType);
        return $mime;
    }

/**
 * Returns the file size.
 *
 * @return int Size of the file in bytes, or false in case of an error
 */
    public function size(): int {
        $response = $this->getResponse();
        return (int)$response->getHeader('Content-Length');
    }

/**
 * Creates an file resource/handle with the response body.
 *
 * @param string $mode For compatibility with parent method.
 * @param boolean $force For compatibility with parent method.
 * @return bool|resource Resource on success, false on failure
 */
    public function open($mode = 'r'): mixed {
        $response = $this->getResponse(true);
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        return $this->_createHandle($response->getBody());
    }

/**
 * Creates an file resource/handle with the response body.
 *
 * @param string $mode For compatibility with parent method.
 * @param boolean $force For compatibility with parent method.
 * @return boolean True on success, false on failure
 */
    public function getFolder(): ?Folder {
        return null;
    }

/**
 * Creates an file resource/handle with the response body.
 *
 * @param string $mode For compatibility with parent method.
 * @param boolean $force For compatibility with parent method.
 * @return boolean True on success, false on failure
 */
    public function getUrl(): ?string {
        return $this->_url;
    }

/**
 * Check if file exists.
 *
 * @return bool True if exists, false otherwise
 */
    public function exists(): bool {
        if ($this->_exists === null) {
            $response = $this->getResponse();
            $this->_exists = $response
                && $response->getStatusCode() >= 200
                && $response->getStatusCode() < 300;
        }
        return (bool)$this->_exists;
    }

/**
 * Returns last access time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastAccess(): ?Time {
        if (!$this->exists()) {
            return null;
        }
        $response = $this->getResponse();
        return new Time($response->getHeader('Date'));
    }

/**
 * Returns last modified time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastChange(): ?Time {
        if (!$this->exists()) {
            return null;
        }

        $response = $this->getResponse();
        $lastChange = $response->getHeader('Last-Modified');
        if ($lastChange) {
            $lastChange = new Time($lastChange);
        }
        return $lastChange;
    }

/**
 * Get HTTP client to check/download file.
 *
 * @return \Nata\Http\Client\Response
 */
    public function getResponse(): mixed {
        if (!isset(static::$_response[$this->_url])) {
            static::$_response[$this->_url] = $this->_getHttpGetRequest()->send();
        }
        return static::$_response[$this->_url];
    }

/**
 * Get HTTP client to check/download file.
 *
 * @return \Nata\Http\Client\Request
 */
    protected function _getHttpGetRequest() {
        return Client::get($this->_url)
            ->cache($this->_cache)
            ->options(CURLOPT_SSL_VERIFYPEER, false);
    }

/**
 * Creates the file.
 * Web files can't be created, so always returns false.
 *
 * @return boolean Success
 */
    public function create(): ?bool {
        return false;
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): ?string {
        return $this->realname(false);
    }

/**
 * Returns file permissions.
 * Web files don't have permissions, so always returns false.
 *
 * @return string|false Permissions
 */
    public function perms(): ?string {
        return null;
    }

/**
 * Returns if file is writable.
 * Web files are never writable.
 *
 * @return boolean Always false for web files
 */
    public function isWritable(): bool {
        return false;
    }

/**
 * Returns if file is executable.
 * Web files are never executable.
 *
 * @return boolean Always false for web files
 */
    public function isExecutable(): bool {
        return false;
    }

/**
 * Returns if file is readable.
 * Web files are always readable if they exist.
 *
 * @return boolean True if exists
 */
    public function isReadable(): bool {
        return $this->exists();
    }

/**
 * Returns file owner.
 * Web files don't have owners, so always returns false.
 *
 * @return int|false Always false for web files
 */
    public function owner(): ?int {
        return null;
    }

/**
 * Returns file group.
 * Web files don't have groups, so always returns false.
 *
 * @return int|false Always false for web files
 */
    public function group(): ?int {
        return null;
    }

/**
 * Returns the current file's extension.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string The file extension
 */
    public function extension(bool $dotPrepend = false): string {
        $extension = pathinfo($this->realname(), PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = parent::extension();
        }
        return ($dotPrepend && !empty($extension) ? '.' : '') . $extension;
    }

/**
 * Force extension based on mime type.
 *
 * @return $this
 */
    public function forceExtension(): self {
        // Web files can't be renamed, so this is a no-op
        return $this;
    }

/**
 * Rename file.
 * Web files can't be renamed, so always returns false.
 *
 * @param string $name New name
 * @return boolean Always false for web files
 */
    public function rename(string $name): bool {
        return false;
    }

/**
 * Clear stat cache.
 * Web files don't use stat cache, so this is a no-op.
 *
 * @param boolean $all Clear all cache
 * @return void
 */
    public function clearStatCache(bool $all = false): void {}

/**
 * Set file modification time.
 * Web files can't be modified, so always returns false.
 *
 * @param int|null $time Access time
 * @param int|null $accessTime Modification time
 * @return boolean Always false for web files
 */
    public function touch(?int $time = null, ?int $accessTime = null): bool {
        return false;
    }

/**
 * __destruct.
 *
 * @return void
 */
    public function __destruct() {}

/**
 * Returns the file data source as a string.
 *
 * @return string URL
 */
    public function __toString(): string {
        return $this->getUrl();
    }

}
