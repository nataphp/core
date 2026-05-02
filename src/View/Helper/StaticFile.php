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

namespace Nata\View\Helper;

use Exception;
use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Utility\Inflector;
use Nata\View\Helper;

/**
 * Static file helper.
 */
class StaticFile extends Helper {

/**
 * Mapping of available static files.
 * Preset package names that map to actual file paths.
 *
 * @var array
 */
    protected static $_libMap = [];

/**
 * Get file URL for a given file configuration.
 *
 * @param array $fileConfig File configuration
 * @param string $extension Extension name
 * @return string|false File URL or false if file doesn't exist
 */
    protected function _getFileUrl(array $fileConfig, string $extension) {
        if (Configure::read('debug') && !$this->_checkFileExists($fileConfig, $extension)) {
            throw new Exception(sprintf('File %s not found: %s', strtoupper($extension), $fileConfig['file']));
        }

        // Convert file path to URL
        $file = $fileConfig['file'];

        $url = '';
        // Plugin assets: /plugin/pluginname/path (e.g. /plugin/acl/vendor/treant-js/Treant.css)
        if (!empty($fileConfig['plugin'])) {
            $url .= '/plugin/' . Inflector::dasherize($fileConfig['plugin']) . '/';
        }

        // If it's a relative path (not starting with /) and the view is themed, build theme path
        if ($this->_view->themed() && empty($fileConfig['package'])) {
            $url .= '/theme/' . $this->_view->theme();
        }

        if (substr($file, 0, 1) !== '/') {
            $url .= '/' . $extension . '/';
        }

        $url .= $file;

        return $this->_output($url, $this->_defaultParams);
    }

/**
 * Check if file exists.
 *
 * @param array $fileConfig File configuration
 * @param string $extension Extension name
 * @return bool True if file exists, false otherwise
 */
    protected function _checkFileExists(array $fileConfig, string $extension) {
        return file_exists($this->_getFilePath($fileConfig, $extension));
    }

/**
 * Get absolute file path for a given file configuration.
 *
 * @param array $fileConfig File configuration
 * @return string Absolute file path
 */
    protected function _getFilePath($fileConfig, $extension) {
        $basePath = 'public';

        if ($this->_view->themed() && empty($fileConfig['package'])) {
            $basePath .= DS . 'theme' . DS . $this->_view->theme();
        }

        $filePath = App::path($basePath, $fileConfig['plugin'] ?? null);

        // Check if is a root path for public folder
        if (substr($fileConfig['file'], 0, 1) !== '/') {
            $filePath .= DS . $extension . DS;
        }

        // Remove URL query parameters
        [$file] = explode('?', $fileConfig['file']);
        $filePath .= App::ds($file);

        return $filePath;
    }

/**
 * Resolve file configuration from key and value.
 * Handles preset packages (including aliases) and direct file paths.
 *
 * @param string|int $key File key
 * @param string|array $file File path or configuration
 * @return array|false File configuration or false
 */
    protected function _resolveFile(string $key, string|array $file) {
        $config = [
            'file' => null,
            'plugin' => null,
            'package' => null
        ];

        // Handle preset package names
        if (is_string($file) && isset(static::$_libMap[$file])) {
            $packageData = static::$_libMap[$file];

            // Handle aliases (string references to other packages)
            if (is_string($packageData)) {
                $packageData = static::$_libMap[$packageData] ?? null;
                if (!$packageData) {
                    return false;
                }
            }

            $config['file'] = $packageData['file'];
            $config['package'] = $file;
            return $config;
        }

        // Handle direct file paths (string) - extract plugin from key when format is PluginName.identifier
        if (is_string($file)) {
            $config['file'] = $file;
            if (strpos($key, '.') !== false) {
                [$plugin, ] = pluginSplit($key);
                if ($plugin) {
                    $config['plugin'] = $plugin;
                }
            }
            return $config;
        }

        // Handle file configuration array
        if (is_array($file)) {
            $config = array_merge($config, $file);
            if (isset($config['file'])) {
                return $config;
            }
        }

        return false;
    }

}
