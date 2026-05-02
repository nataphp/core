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

namespace Nata\FilesystemManager\FileStorage;

use Nata\Core\ErrorAwareTrait;
use Nata\Core\NataObject;

/**
 * File storage adapter abstract class.
 */
abstract class Adapter extends NataObject {

 use ErrorAwareTrait;

/**
 * Adapter name.
 *
 * @var string
 */
    protected $_name;

/**
 * Path used in the last operation.
 *
 * @var string
 */
    protected $_lastUsedPath;

/**
 * Store name.
 *
 * @var string
 */
    protected $_storeName;

/**
 * Constructor.
 *
 * @param string $store Store name
 * @param array $config Configuration
 * @return void
 */
    public function __construct(string $store, array $config = []) {
        $config += ['accept' => '*/*'];
        $this->config($config);
        $this->_storeName = $store;
    }

/**
 * Get adapter's registry alias.
 *
 * @return string
 */
    public function getStoreName(): string {
        return $this->_storeName;
    }

/**
 * Load file path from given identifier.
 *
 * @param string $path File path/key
 * @param array $options Options
 * @return string
 */
    abstract public function get(string $path, array $options = []): ?string;

/**
 * Put file.
 *
 * @param string $path File path/key
 * @param string $contents File contents
 * @param array $options Options
 * @return string|null
 */
    abstract public function put(string $path, string $contents, array $options = []): ?string;

/**
 * Delete file.
 *
 * @param string $path File path
 * @return bool
 */
    abstract public function delete(string $path): bool;

/**
 * Get file path/key from full path.
 *
 * @param string $fullPath Full path
 * @return string File path/key
 */
    abstract public function path(string $fullPath): string;

/**
 * Get file URL.
 *
 * @param string $path File path
 * @return string
 */
    abstract public function url(string $path): ?string;

/**
 * Check if file exists.
 *
 * @param string $file File path
 * @return bool
 */
    abstract public function exists(string $path): bool;

/**
 * Get free space in storage repository.
 *
 * @return int|null Size in bytes or null on failure
 */
    abstract public function freeSpace(): ?int;

/**
 * Get last used path.
 *
 * @return string|null
 */
    public function lastUsedPath(): ?string {
        return $this->_lastUsedPath;
    }

/**
 * Consume last used path.
 *
 * @return string|null
 */
    public function consumeLastUsedPath(): ?string {
        $path = $this->_lastUsedPath;
        $this->_lastUsedPath = null;
        return $path;
    }

/**
 * Check if string is a valid uniqid.
 *
 * @param string $identifier Uniqid
 * @return bool
 */
    protected function _isUniqid($identifier): bool {
        if (!is_string($identifier)) {
            return false;
        }

        if (strpos($identifier, '.') !== false) {
            [$identifier] = explode('.', $identifier);
        }

        return preg_match("/^[a-fA-F0-9]{40}$/", $identifier) === 1;
    }

/**
 * Prepare endpoint.
 *
 * @param string $endpoint Endpoint
 * @param bool $ssl SSL
 * @return string
 */
    protected function _prepareEndpoint(string $endpoint, $ssl = true): string {
        if (!str_contains($endpoint, '://')) {
            $endpoint = 'http://' . $endpoint;
        }

        if ($ssl) {
            $endpoint = str_replace('http://', 'https://', $endpoint);
        }
        $endpoint = rtrim($endpoint, '/');
        return $endpoint;
    }

}
