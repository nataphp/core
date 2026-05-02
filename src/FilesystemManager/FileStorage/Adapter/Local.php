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

use Nata\Core\App;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\FileStorage\Adapter;
use Nata\Routing\Router;

/**
 * Local file storage adapter.
 */
class Local extends Adapter {

/**
 * Default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'pathTemplate' => 'uploads/{mime_top_level}/{sha1_5}/{sha1}{extension}',
        'prefix' => '',
        'root' => 'public',
        'endpoint' => null,
    ];

/**
 * Directory exists cache.
 * Used to avoid checking for directory existence.
 *
 * @var array
 */
    protected $_dirExists = [];

/**
 * File already exists - return null and set error
 *
 * @var string
 */
    public const STRATEGY_ERROR = 'error';

/**
 * File already exists - return path as if saved
 *
 * @var string
 */
    public const STRATEGY_IGNORE = 'ignore';

/**
 * File already exists - overwrite existing file
 *
 * @var string
 */
    public const STRATEGY_OVERWRITE = 'overwrite';


/**
 * Get full file path.
 *
 * @param string $path File path/key
 * @param array $options Options
 * @return string|null
 */
    public function get(string $path, array $options = []): ?string {
        if ($this->_isFullFilePath($path)) {
            return $path;
        }
        $basePath = $this->_getBasePath();
        return $this->_getFullFilePath($basePath, $path, false);
    }

/**
 * Put file into local storage.
 *
 * Options:
 * - mime: File mime type
 * - dryRun: Return full path without writing file
 * - existingFileStrategy: Strategy to use when file already exists:
 *   - error: Return null and set error
 *   - ignore: Return path as if saved
 *   - overwrite: Overwrite existing file
 *
 *
 * @param string $path File path
 * @param string $contents File contents
 * @return string Full path to the file
 */
    public function put(string $path, string $contents, array $options = []): ?string {
        $options += [
            'mime' => null,
            'dryRun' => false,
            'existingFileStrategy' => self::STRATEGY_IGNORE,
        ];

        $basePath = $this->_getBasePath();
        $this->_lastUsedPath = $path;

        $fullPath = $this->_getFullFilePath($basePath, $path, false);
        if ($options['dryRun'] === true) {
            return $fullPath;
        }

        $fullPath = $this->_getFullFilePath($basePath, $path, true);
        if (file_exists($fullPath)) {
            if ($options['existingFileStrategy'] === self::STRATEGY_IGNORE) {
                return $fullPath;
            }
            if ($options['existingFileStrategy'] === self::STRATEGY_ERROR) {
                $this->_setError(sprintf("File '%s' already exists.", $fullPath));
                return null;
            }
        }

        if (!file_put_contents($fullPath, $contents)) {
            $this->_setError(sprintf("Error writing file '%s'.", $fullPath));
            return null;
        }

        return $fullPath;
    }

/**
 * Delete file.
 *
 * @param File|string $file File
 * @return bool
 */
    public function delete($file): bool {
        return false;
    }

/**
 * Check if file exists.
 *
 * @param string $path File path
 * @return bool
 */
    public function exists(string $path): bool {
        if (!$this->_isFullFilePath($path)) {
            $basePath = $this->_getBasePath();
            $path = $this->_getFullFilePath($basePath, $path);
        }
        return file_exists($path);
    }

/**
 * Get file public URL.
 *
 * @param string $path Path/key
 * @param array $options Options
 * @return string
 */
    public function url(string $path, array $options = []): ?string {
        $options += [
            'endpoint' => null,
        ];

        if (str_contains($path, '/public/')) {
            [$abs, $path] = explode('/public/', $path);
        }

        $path = '/' . ltrim($path, '/');

        $endpoint = $options['endpoint'] ?? $this->config('endpoint');
        if ($endpoint) {
            return $endpoint . $path;
        }

        return Router::url($path, true);
    }

/**
 * Get key from full path.
 *
 * @param string $fullPath Full path
 * @return string
 */
    public function path(string $fullPath): string {
        if (!$this->_isFullFilePath($fullPath)) {
            return $fullPath;
        }
        $basePath = $this->_getBasePath();
        $key = str_replace($basePath, '', $fullPath);
        return ltrim(App::normalize($key), '/');
    }

/**
 * Get free space in storage repository.
 *
 * @return int|null Size in bytes or null on failure
 */
    public function freeSpace(): ?int {
        return disk_free_space($this->_getBasePath());
    }

/**
 * Get full file path.
 *
 * It will make sure that all folders in relative path are present.
 *
 * @param string $basePath Source file/name
 * @param string $relativePath Source file/name
 * @param bool $createDir Create directory if not exists
 * @return string File full path in repository
 */
    private function _getFullFilePath(string $basePath, string $relativePath, bool $createDir = false): string {
        $basePath = rtrim($basePath, '/');
        $relativePath = App::normalize($relativePath);
        $relativePath = ltrim($relativePath, '/');

        $parts = explode('/', $relativePath);
        array_pop($parts);
        $path = '';
        for ($i = 0; $i < count($parts); $i++) {
            $path .= '/' . $parts[$i];
            if (isset($this->_dirExists[$path])) {
                continue;
            }

            if ($createDir === false) {
                continue;
            }

            $this->_makeDir($basePath . '/' . $path);

            $this->_dirExists[$path] = true;
        }
        return $basePath . '/' . $relativePath;
    }

/**
 * Check if given path is a full file path.
 *
 * @param string $path Source file/name
 * @return bool
 */
    private function _isFullFilePath(string $path): bool {
        $path = strtolower(App::normalize($path));
        if (App::isWindows() === true) {
            return str_contains($path, ':/');
        }
        return substr($path, 0, 1) === '/';
    }

/**
 * Make directory.
 *
 * @param string $path Directory path
 * @return void
 */
    private function _makeDir(string $path) {
        if (is_dir($path)) {
            return;
        }
        mkdir($path);
    }

/**
 * Get base path.
 *
 * @return string Base path
 */
    private function _getBasePath() {
        $basePath = $this->config('root');
        if (substr($basePath, 0, 1) === '/') {
            return $basePath;
        }
        return App::normalize(App::path($basePath));
    }

}
