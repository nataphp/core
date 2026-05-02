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

namespace Nata\Core;

use Nata\Core\App;
use Nata\Database\ConnectionManager;
use Nata\Filesystem\Folder;
use Nata\Utility\Inflector;
use MissingPluginException;

/**
 * Plugin load management.
 */
class Plugin {

/**
 * Holds a list of all loaded plugins and their configuration.
 *
 * @var array
 */
    protected static $_plugins = [];


/**
 * Loads a plugin and optionally loads bootstrapping, routing files or loads a initialization function
 *
 * Examples:
 *
 *  `Nata\Core\Plugin::load('DebugKit')` will load the DebugKit plugin and will not load any bootstrap nor route files
 *  `Nata\Core\Plugin::load('DebugKit', array('bootstrap' => true, 'routes' => true))` will load the bootstrap.php and routes.php files
 *  `Nata\Core\Plugin::load('DebugKit', array('bootstrap' => false, 'routes' => true))` will load routes.php file but not bootstrap.php
 *  `Nata\Core\Plugin::load('DebugKit', array('bootstrap' => array('config1', 'config2')))` will load config1.php and config2.php files
 *  `Nata\Core\Plugin::load('DebugKit', array('bootstrap' => 'aCallableMethod'))` will run the aCallableMethod function to initialize it
 *
 * Bootstrap initialization functions can be expressed as a PHP callback type, including closures. Callbacks will receive two
 * parameters (plugin name, plugin configuration)
 *
 * It is also possible to load multiple plugins at once. Examples:
 *
 * `Nata\Core\Plugin::load(array('DebugKit', 'ApiGenerator'))` will load the DebugKit and ApiGenerator plugins
 * `Nata\Core\Plugin::load(array('DebugKit', 'ApiGenerator'), array('bootstrap' => true))` will load bootstrap file for both plugins
 *
 * {{{
 *  Nata\Core\Plugin::load(array(
 *      'DebugKit' => array('routes' => true),
 *      'ApiGenerator'
 *      ), array('bootstrap' => true))
 * }}}
 *
 * Will only load the bootstrap for ApiGenerator and only the routes for DebugKit
 *
 * @param string|array $plugin name of the plugin to be loaded in CamelCase format or array or plugins to load
 * @param array $config configuration options for the plugin
 * @throws MissingPluginException if the folder for the plugin to be loaded is not found
 * @return void
 */
    public static function load($plugin, $config = []) {
        if (is_array($plugin)) {
            foreach ($plugin as $name => $conf) {
                [$name, $conf] = (is_numeric($name)) ? [$conf, $config] : [$name, $conf];
                static::load($name, $conf);
            }
            return;
        }

        $userConfig = $config;

        $config += [
            'bootstrap' => false,
            'routes' => false,
            'public' => false,
            'namespace' => $plugin
        ];

        $resolvedPath = null;
        if (!empty($config['path'])) {
            $resolvedPath = App::ds(rtrim((string)$config['path'], '/\\')) . DS;
        } else {
            $candidates = [
                ROOT . 'plugins' . DS . $plugin . DS,
                App::path('Plugin/') . $plugin . DS,
            ];
            foreach ($candidates as $path) {
                if (is_dir($path)) {
                    $resolvedPath = App::ds(rtrim($path, '/\\')) . DS;
                    break;
                }
            }
        }

        if ($resolvedPath === null || !is_dir($resolvedPath)) {
            throw new MissingPluginException(['plugin' => $plugin]);
        }

        // Always store normalized path (`+` would not override caller `path`).
        $config['path'] = $resolvedPath;
        static::$_plugins[$plugin] = $config;

        if (!isset($userConfig['bootstrap'])) {
            static::$_plugins[$plugin]['bootstrap'] = static::_autoLoad($plugin, 'bootstrap');
        }
        if (!isset($userConfig['routes'])) {
            static::$_plugins[$plugin]['routes'] = static::_autoLoad($plugin, 'routes');
        }

        if (static::$_plugins[$plugin]['bootstrap']) {
            static::bootstrap($plugin);
        }
        if (static::$_plugins[$plugin]['routes']) {
            static::routes($plugin);
        }
        if (static::$_plugins[$plugin]['public']) {
            static::publicFiles($plugin);
        }
        unset($userConfig);
    }

/**
 * If bootstrap or routes load config setting are not set, will try to check if exists.
 *
 * @param string $plugin name of the plugin in CamelCase format
 * @param string $configFile Config file name to autoload
 * @return bool file exists
 */
    private static function _autoLoad($plugin, $configFile) {
        $path = static::$_plugins[$plugin]['path'] . 'Config' . DS . $configFile . '.inc.php';
        return file_exists($path);
    }

/**
 * Will load all the plugins located in the configured plugins folders.
 * If passed an options array, it will be used as a common default for all plugins to be loaded
 * It is possible to set specific defaults for each plugins in the options array. Examples:
 *
 * {{{
 *  Nata\Core\Plugin::loadAll(array(
 *      array('bootstrap' => true),
 *      'DebugKit' => array('routes' => true),
 *  ))
 * }}}
 *
 * The above example will load the bootstrap file for all plugins, but for DebugKit it will only load the routes file
 * and will not look for any bootstrap script.
 *
 * @param array $options
 * @return void
 */
    public static function loadAll($options = []) {
        $plugins = App::objects('Plugin');
        foreach ($plugins as $p) {
            if (strpos($p, '.disabled') !== false) {
                continue;
            }
            $opts = isset($options[$p]) ? $options[$p] : null;
            if ($opts === null && isset($options[0])) {
                $opts = $options[0];
            }
            static::load($p, (array)$opts);
        }
    }

/**
 * Return the namespace for a plugin.
 *
 * If a plugin is unknown, the plugin name will be used as the namespace.
 * This lets you access vendor libraries or unloaded plugins using `Plugin.Class`.
 *
 * @param string $plugin name of the plugin in CamelCase format
 * @return string namespace to the plugin
 */
    public static function getNamespace($plugin) {
        if (empty(static::$_plugins[$plugin])) {
            return $plugin;
        }
        return static::$_plugins[$plugin]['namespace'];
    }

/**
 * Returns the filesystem path for a plugin.
 *
 * @param string $plugin name of the plugin in CamelCase format
 * @return string path to the plugin folder
 * @throws MissingPluginException if the folder for plugin was not found or plugin has not been loaded
 */
    public static function path($plugin) {
        if (empty(static::$_plugins[$plugin])) {
            throw new MissingPluginException(['plugin' => $plugin]);
        }
        return App::ds(self::$_plugins[$plugin]['path']);
    }

/**
 * Loads the bootstrapping files for a plugin, or calls the initialization setup in the configuration
 *
 * @param string $plugin name of the plugin
 * @return mixed
 * @see Nata\Core\Plugin::load() for examples of bootstrap configuration
 */
    public static function bootstrap($plugin) {
        $config = static::$_plugins[$plugin];
        if ($config['bootstrap'] === false) {
            return false;
        }
        if (is_callable($config['bootstrap'])) {
            return call_user_func_array($config['bootstrap'], [$plugin, $config]);
        }
        $path = static::path($plugin);
        if ($config['bootstrap'] === true) {
            return include $path . 'Config' . DS . 'bootstrap.inc.php';
        }
        return true;
    }

/**
 * Loads the routes file for a plugin, or all plugins configured to load their respective routes file.
 *
 * @param string $plugin name of the plugin, if null will operate on all plugins having enabled the
 * loading of routes files
 * @return boolean
 */
    public static function routes($plugin = null) {
        if ($plugin === null) {
            foreach (static::loaded() as $p) {
                static::routes($p);
            }
            return true;
        }
        $config = static::$_plugins[$plugin];
        if ($config['routes'] === false) {
            return false;
        }
        return (bool)include static::path($plugin) . 'Config' . DS . 'routes.inc.php';
    }

/**
 * Copy the plugin's publicly accessible files to
 * app's public plugin folder.
 *
 * @param string $plugin Name of the plugin
 * @return bool True if successfull, false otherwise
 */
    public static function publicFiles($plugin) {
        $config = static::$_plugins[$plugin];
        if ($config['public'] === false) {
            return false;
        }

        $path = static::path($plugin);
        if ($config['public'] === true) {
            $sourcePath = $path . 'public' . DS;
            if (!is_dir($sourcePath)) {
                return true;
            }
            $pluginPath = App::path('public/plugin/');
            $pluginWebroot = $pluginPath . Inflector::dasherize($plugin);
            if (!is_dir($pluginPath)) {
                mkdir($pluginPath, 0755, true);
            }
            $folder = new Folder($sourcePath);
            return $folder->copy([
                'to' => $pluginWebroot,
                'scheme' => Folder::OVERWRITE
            ]);
        }
        return true;
    }

/**
 * Returns true if the plugin $plugin is already loaded.
 * If $plugin is null, it will return a list of all loaded plugins.
 *
 * @param string $plugin Plugin name
 * @return mixed boolean true if $plugin is already loaded.
 */
    public static function loaded($plugin = null) {
        if ($plugin) {
            return isset(static::$_plugins[$plugin]);
        }
        $return = array_keys(static::$_plugins ?? []);
        sort($return);
        return $return;
    }

/**
 * Forgets a loaded plugin or all of them if first parameter is null.
 *
 * @param string $plugin name of the plugin to forget
 * @return void
 */
    public static function unload($plugin = null) {
        if ($plugin === null) {
            static::$_plugins = [];
        } else {
            unset(static::$_plugins[$plugin]);
        }
    }

/**
 * Returns the list of available plugins.
 *
 * @throws MissingPluginException if the folder for plugin was not found or plugin has not been loaded
 */
    public static function list() {
        return static::loaded();
    }

/**
 * Get the source path for a plugin (works for both App and Composer-installed plugins).
 *
 * @param string $plugin Plugin name in CamelCase
 * @param string|null $composerPath Optional path when installed via Composer (e.g. vendor/maismls/menus)
 * @return string Full path to plugin folder
 */
    public static function getPluginSourcePath(string $plugin, ?string $composerPath = null): string {
        if ($composerPath !== null) {
            $path = ROOT . str_replace(['/', '\\'], DS, trim($composerPath, '/\\')) . DS;
            if (is_dir($path)) {
                return App::ds($path);
            }
        }
        if (static::loaded($plugin)) {
            return static::path($plugin);
        }
        $pluginsRoot = ROOT . 'plugins' . DS . $plugin . DS;
        if (is_dir($pluginsRoot)) {
            return App::ds($pluginsRoot);
        }

        return App::ds(App::path('Plugin/') . $plugin . DS);
    }

/**
 * Read plugin manifest (plugin.json) from plugin folder.
 *
 * @param string $plugin Plugin name in CamelCase
 * @param string|null $composerPath Optional Composer path for plugin source
 * @return array|null Manifest data or null if not found
 */
    public static function getManifest(string $plugin, ?string $composerPath = null): ?array {
        $path = static::getPluginSourcePath($plugin, $composerPath);
        $manifestFile = $path . 'plugin.json';
        if (!file_exists($manifestFile)) {
            return null;
        }
        $json = file_get_contents($manifestFile);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

/**
 * Run SQL schema file for a plugin.
 *
 * @param string $plugin Plugin name (used to resolve path if composerPath null)
 * @param string $sqlFile Path to SQL file relative to plugin root
 * @param string|null $composerPath Optional Composer path for plugin source
 * @param string $connection Database connection name
 * @return bool True on success
 */
    public static function runSchema(string $plugin, string $sqlFile, ?string $composerPath = null, string $connection = null): bool {
        if ($connection === null) {
            $connection = Configure::read('Database.config');
        }
        $path = static::getPluginSourcePath($plugin, $composerPath);
        $fullPath = $path . str_replace(['/', '\\'], DS, ltrim($sqlFile, '/\\'));
        if (!file_exists($fullPath)) {
            return false;
        }
        $sql = file_get_contents($fullPath);
        if (trim($sql) === '') {
            return true;
        }
        $conn = ConnectionManager::get($connection);
        $statements = preg_split('/;\s*\n/', $sql, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $conn->executeStatement($statement, []);
            }
        }
        return true;
    }

/**
 * Copy plugin public files to app public folder (works without loading plugin).
 *
 * @param string $plugin Plugin name in CamelCase
 * @param string|null $composerPath Optional Composer path for plugin source
 * @return bool True on success
 */
    public static function copyPublicFiles(string $plugin, ?string $composerPath = null): bool {
        $path = static::getPluginSourcePath($plugin, $composerPath);
        $sourcePath = $path . 'public' . DS;
        if (!is_dir($sourcePath)) {
            return true;
        }
        $pluginPath = ROOT . 'public' . DS . 'plugin' . DS;
        $pluginWebroot = $pluginPath . Inflector::dasherize($plugin);
        if (!is_dir($pluginPath)) {
            mkdir($pluginPath, 0755, true);
        }
        $folder = new Folder($sourcePath);
        return $folder->copy([
            'to' => $pluginWebroot,
            'scheme' => Folder::OVERWRITE
        ]);
    }


}
