<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) SÃ©rgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) SÃ©rgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\FilesystemManager;

use Nata\Core\App;
use Nata\Core\Configure;
use Nata\FilesystemManager\Exception\FileStorageException;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\FileStorage\Adapter;
use Nata\FilesystemManager\FileStorage\RelativePathBuilder;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;

/**
 * File storage manager.
 *
 * Provides a simple way to store and retrieve files.
 * It uses adapters to store files in different locations.
 * It also provides a way to move files between stores.
 */
class FileStorage {

/**
 * Default store name.
 *
 * @const string
 */
    public const DEFAULT_STORE = '__default__';

/**
 * Use preset path template to generate path.
 *
 * @const string
 */
    public const GENERATE_PATH_FROM_TEMPLATE = '__genpathfromtpl__';

/**
 * Default config.
 *
 * @var array
 */
    protected static $_defaultConfig = [
        'accept' => '*/*',
        'validTypes' => [
            'application',
            'audio',
            'example',
            'image',
            'message',
            'model',
            'multipart',
            'text',
            'video'
        ]
    ];

/**
 * Configuration.
 *
 * @var array
 */
    protected static $_config;

/**
 * Stores instances.
 *
 * @var Adapter
 */
    protected static $_stores;

/**
 * Last error.
 *
 * @var string
 */
    protected static $_lastError;

/**
 * Loaded files.
 *
 * @var array
 */
    protected static $_loadedFiles = [];

/**
 * URI parsing cache.
 *
 * @var array
 */
    protected static $_uriCache = [];


/**
 * Get file from storage.
 *
 * @param string $path File key/path
 * @param string $store Store name
 * @param array $options File options
 * @return mixed File/Image/etc instance
 */
    public static function get(string $path, string $store = null, array $options = []) {
        $options += [
            'name' => null,
            'asObject' => false,
            'metadata' => []
        ];

        [$path, $store] = static::_extractPathAndStore($path, $store);

        $store = static::getStoreName($store);
        $adapter = static::store($store);
        $fullPath = $adapter->get($path, $options);
        if (!$fullPath) {
            return null;
        }
        if ($options['asObject'] === false) {
            return $fullPath;
        }

        return static::_loadAsObject($fullPath, $path, $store, $options);
    }

/**
 * Load file as File instance.
 *
 * @param string $fullPath Full path to the file
 * @param string $path File path/key
 * @param string $store Store name
 * @param array $options File options
 * @return File File instance
 */
    public static function _loadAsObject(string $fullPath, string $path, string $store, array $options = []): File {
        $options['metadata'] = (array)$options['metadata'];
        $options['metadata']['store'] = $store;
        $options['metadata']['path'] = $path;
        $options['metadata']['storageuri'] = static::buildUri($path, $store);

        $cacheKey = $store . ltrim($fullPath, '/');
        if (static::$_loadedFiles[$cacheKey] ?? false) {
            return static::$_loadedFiles[$cacheKey];
        }
        return static::$_loadedFiles[$cacheKey] = FileFactory::build($fullPath, $options);
    }

/**
 * Put file into storage.
 *
 * ### Options
 * - `dryRun` (bool): Return full path without storing file
 * - `name` (string): File name
 * - `overwrite` (bool): Overwrite file if exists
 * - `allowEmpty` (bool): Allow empty file
 * - `accept` (string): Accepted file mimetype
 * - `visibility` (string): File visibility
 * - `asObject` (bool): Return file as File instance
 * - `throwException` (bool): Throw exception on error
 *
 * @param string $contents File contents
 * @param string $store Store name
 * @param string $path File path to store (if not, it will be generated automatically)
 * @param array $options Options
 * @return File|bool|string File instance or false on failure
 */
    public static function put(string $contents, string $store, string $path = self::GENERATE_PATH_FROM_TEMPLATE, array $options = []) {
        $options += [
            'dryRun' => false,
            'name' => null,
            'mimeFallback' => null,
            'existingFileStrategy' => 'ignore',
            'allowEmpty' => false,
            'accept' => '*/*',
            'visibility' => 'public',
            'asObject' => true,
            'throwException' => false,
            'metadata' => []
        ];

        // Backward compatibility
        if (isset($options['preview'])) {
            $options['dryRun'] = $options['preview'];
        }

        unset(static::$_loadedFiles[$store . ltrim($path, '/')]);

        $store = static::getStoreName($store);
        $adapter = static::store($store);

        $mimetype = Mimetype::detect($contents) ?? $options['mimeFallback'];
        if (!$mimetype) {
            static::_setError(__('File mimetype is not detected.'));
            return false;
        }
        $extension = Mimetype::getExtension($mimetype);
        if ($path === self::GENERATE_PATH_FROM_TEMPLATE) {
            $pathTemplate = $adapter->config('pathTemplate');
            if ($pathTemplate === null) {
                static::_setError(__('Path is not set for store "%s".', $store));
                return false;
            }

            $path = RelativePathBuilder::build([
                'name' => $options['name'] ?? null,
                'sha1' => sha1($contents),
                'mime' => $mimetype,
                'ext' => $extension,
                'extension' => $extension,
            ], $pathTemplate);
        }

        // Check empty file
        $size = strlen($contents);
        if ($options['allowEmpty'] === false && $size === 0) {
            static::_setError(__('Source file "%s" is empty.', $path));
            return false;
        }

        // Check free space
        $freeSpace = $adapter->freeSpace();
        if ($freeSpace !== null && $size > $freeSpace) {
            static::_setError(__('Not enough free space to store file "%s" in "%s".', $path, $adapter->getStoreName()));
            return false;
        }

        // Validate file mimetype
        $accept = $options['accept'] ?? $adapter->config('accept');
        $mimetype = Mimetype::detect($contents, $options['mimeFallback']);
        if (!$mimetype) {
            static::_setError(__('File mimetype is not detected.'));
            return false;
        } elseif (!Mimetype::is($mimetype, $accept)) {
            static::_setError(__('File mimetype "%s" is not accepted.', $mimetype));
            return false;
        }

        $fullPath = $adapter->put($path, $contents, $options);
        if ($fullPath === null) {
            static::_setError($adapter->getLastError());
            return false;
        }

        if ($options['asObject'] === false) {
            return $fullPath;
        }

        return static::_loadAsObject($fullPath, $path, $store, $options);
    }

/**
 * Import existing file into storage.
 *
 * ### Example:
 * ```
 * FileStorage::import('/path/to/file.jpg', 'local', 'path/to/store/file.jpg');
 * ```
 * ### Example with options:
 * ```
 * FileStorage::import('/path/to/file.jpg', 'local', 'path/to/store/file.jpg', [
 *    'dryRun' => true,
 *    'name' => 'new-name.jpg',
 *    'accept' => 'image/*',
 *    'visibility' => 'private',
 *    'deleteSource' => true,
 *    'asObject' => true,
 *    'throwException' => true
 * ]);
 * ```
 *
 * @param File|string $source Source file/path to store
 * @param string $store Store name
 * @param string $path File path to store
 * @param array $options Options
 * @return File|bool File instance or false on failure
 */
    public static function import(File|string $source, string $store, string $path = self::GENERATE_PATH_FROM_TEMPLATE, array $options = []) {
        $options += [
            'name' => null,
            'mimeFallback' => null,
            'accept' => '*/*',
            'visibility' => 'public',
            'deleteSource' => false,
            'asObject' => true,
            'throwException' => false,
            'dryRun' => false,
            'fixOrientation' => false
        ];

        $store = static::getStoreName($store);
        $source = static::_loadAsFile($source);
        $options['name'] = $options['name'] ?? $source->basename();

        if ($options['dryRun'] === false && $options['fixOrientation'] === true) {
            $source = static::_fixImageOrientation($source);
        }

        if ($options['dryRun'] === true) {
            $source->metadata('path', $path);
            $source->metadata('store', $store);
            return $source;
        }

        $file = static::put(
            contents:$source->read(),
            store:$store,
            path:$path,
            options:$options
        );
        if ($options['dryRun'] === false && $file && $options['deleteSource'] === true) {
            $source->delete();
        }
        return $file;
    }

/**
 * Copy file between stores.
 *
 * @param string $path File path to copy~
 * @param string $sourceStore Source store name
 * @param string $targetStore Target store name
 * @param string $newPath New file path
 * @param array $options Put one-time options (it will overwrite the )
 * @return string Full path to the new file
 */
    public static function copy(string $path, string $sourceStore, string $targetStore, ?string $newPath = null, array $options = []) {
        $options += [
            'name' => null,
            'overwrite' => false,
            'metadata' => []
        ];

        $file = static::get(path:$path, store:$sourceStore, options:['asObject' => true]);
        if (!$file) {
            return false;
        }

        $contents = $file->read();
        $putOptions = $options;
        $putOptions['mimeFallback'] = $options['mimeFallback'] ?? $file->mime();
        $putOptions['name'] = $options['name'] ?? $file->basename();
        $newFile = static::put(
            contents: $contents,
            store: $targetStore,
            path: $newPath ?? $path,
            options: $putOptions
        );
        if (!$newFile) {
            return false;
        }
        return $newFile;
    }
/**
 * Move file between stores.
 *
 * @param string $path File path to move
 * @param string $newAdapterConfig Adapter alias name to move file to
 * @param array $options Put one-time options (it will overwrite the )
 * @return FileHandler File handler for the moved file
 */
    public static function move(string $path, string $oldStore, string $newStore, string $newPath = null, array $options = []) {
        $newFile = static::copy($path, $oldStore, $newStore, $newPath, $options);
        if (!$newFile) {
            return false;
        }
        static::delete($path, $oldStore);
        return $newFile;
    }

/**
 * Delete file.
 *
 * @param string $path File identifier to delete
 * @param string $store Store name
 * @return bool True if successful, false otherwise
 */
    public static function delete(string $path, string $store = null) {
        [$path, $store] = static::_extractPathAndStore($path, $store);
        return static::store($store)->delete($path);
    }

/**
 * File exists.
 *
 * @param string $path File identifier to check
 * @param string $store Store name
 * @return bool True if exists, false otherwise
 */
    public static function exists(string $path, string $store = null): bool {
        [$path, $store] = static::_extractPathAndStore($path, $store);
        return static::store($store)->exists($path);
    }

/**
 * Get path/key from full path.
 *
 * @param string $fullPath File full path/url
 * @param string $store Store name
 * @return string File's path
 */
    public static function path(string $fullPath, string $store = null): string {
        [$path, $store] = static::_extractPathAndStore($fullPath, $store);
        return static::store($store)->path($path);
    }

/**
 * Get public URL to to given file.
 *
 * #### Example
 * ```php
 * FileStorage::url('path/to/file.jpg', 'local');
 * ```
 *
 * @param string|File $path File identifier to check
 * @param string $store Store name
 * @return string File's relative URL
 */
    public static function url(string|File $path, string $store = null, array $options = []): ?string {
        if (is_string($path) && str_contains($path, 'http') === true) {
            return $path;
        }

        [$path, $store] = static::_extractPathAndStore($path, $store);

        return static::store($store)->url($path, $options);
    }

/**
 * Get presigned URL for form upload.
 *
 * @param string $path File path/key
 * @param string $store Store name
 * @param string $mimeType Mime type
 * @param array $options Options
 * @return string Presigned URL
 */
    public static function presignedFormUploadUrl(string $path, string $store, string $mimeType, array $options = []): ?string {
        return static::store($store)->presignedFormUploadUrl($path, $mimeType, $options);
    }

/**
 * Get store adapter.
 *
 * @param string $name Store name
 * @return Adapter
 */
    public static function store(string $name): Adapter {
        $name = static::getStoreName($name);
        if (!isset(static::$_stores[$name])) {
            $stores = static::config('stores');
            $config = $stores[$name] ?? null;
            if ($name !== 'local' && !$config) {
                throw new FileStorageException(sprintf('Store "%s" is not set.', $name));
            }

            $className = App::className($config['adapter'], 'FilesystemManager/FileStorage/Adapter');
            if (!$className) {
                throw new FileStorageException(sprintf('Adapter "%s" not supported (missing adapter class "%s").', $name, 'FilesystemManager\\FileStorage\\Adapter\\' . Inflector::classify($config['adapter'])));
            }

            if (!is_subclass_of($className, '\Nata\FilesystemManager\FileStorage\Adapter')) {
                throw new FileStorageException(sprintf('Adapter "%s" does not extend Nata\FilesystemManager\FileStorage\Adapter.', $className));
            }

            static::$_stores[$name] = new $className($name, $config);
        }
        return static::$_stores[$name];
    }

/**
 * Storage configuration.
 *
 * @param string $name Key name
 * @param mixed $value Value
 * @return mixed
 */
    public static function config($name, $value = null) {
        if ($value === null) {
            if (static::$_config === null) {
                static::$_config = (array)Configure::read('Filesystem.storage') + static::$_defaultConfig;
            }
            return static::$_config[$name] ?? Hash::get(static::$_config, $name);
        }

        static::$_config = Hash::insert(static::$_config, $name, $value);

        return $value;
    }

/**
 * Fix image orientation.
 *
 * @param File $file Source file to fix
 * @return File File
 */
    protected static function _fixImageOrientation(File $file): File {
        if ($file->is('image') && $file instanceof Image && $file->is('jpg') && $file->exif()->orientation > 1) {
            $file = $file->editor()->fixOrientation()->getFile();
        }
        return $file;
    }

/**
 * Load file as File instance.
 *
 * @param File|string $sourceFile Source file to validate
 * @return File File
 */
    protected static function _loadAsFile(File|string $file): File {
        if (!($file instanceof File)) {
            $file = FileFactory::build($file);
        }
        return $file;
    }

/**
 * Get path and store from path.
 *
 * @param string|File $path Path
 * @param string|null $store Store name
 * @return array Path and store
 */
    private static function _extractPathAndStore(string|File $path, string|null $store): array {
        if (str_contains($path, 'natafs://') === true) {
            $parts = static::parseUri($path);
            $path = $parts['path'];
            $store = $parts['store'];
        } elseif ($path instanceof File) {
            if ($storageuri = $path->metadata('storageuri')) {
                return static::_extractPathAndStore($storageuri, $store);
            }

            $file = $path;
            $path = $file->metadata('path');
            $store = $store ?? $file->metadata('store');
            $file = null;
        }

        if ($store === null) {
            static::_setError('Store name not provided.');
        }

        return [$path, $store];
    }

/**
 * Get store name.
 *
 * @param string $store Store name
 * @return string Store name
 */
    public static function getStoreName(string $store): string {
        if ($store === static::DEFAULT_STORE) {
            return Configure::read('Filesystem.storage.defaultStore');
        }
        return $store;
    }

/**
 * Parse file storage URI.
 *
 * @param string $uri URI to parse
 * @param int $component Component to return
 * @return array|string Parsed URI
 */
    public static function parseUri(string $uri, int $component = -1): array|string {
        if (str_contains($uri, 'natafs://') === false) {
            return $uri;
        }
        if (isset(static::$_uriCache[$uri]) && $component === -1) {
            return static::$_uriCache[$uri];
        }

        $parts = parse_url($uri, $component);
        if ($component > -1) {
            return $parts;
        }
        return static::$_uriCache[$uri] = [
            'scheme' => 'natafs',
            'store' => $parts['host'],
            'path' => ltrim($parts['path'], '/'),
        ];
    }

/**
 * Build file storage URI.
 *
 * @param string $path Path to build URI from
 * @param string $store Store name
 * @return string File storage URI
 */
    public static function buildUri(string $path, string $store): string {
        $stores = static::config('stores');
        $storeConfig = $stores[$store] ?? null;
        if (!$storeConfig) {
            throw new FileStorageException(sprintf('Store "%s" is not set.', $store));
        }
        return 'natafs://' . $store . '/' . ltrim($path, '/');
    }

/**
 * Set error.
 *
 * @param string $string Error
 * @return void
 */
    protected static function _setError($error) {
        return static::$_lastError = $error;
    }

/**
 * Check if there's an error.
 *
 * @return bool Has error
 */
    public static function hasError(): bool {
        return static::$_lastError !== null;
    }

/**
 * Get last error.
 *
 * @return string Last error
 */
    public static function getLastError() {
        return static::$_lastError;
    }

}
