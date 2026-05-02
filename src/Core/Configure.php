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
use Nata\Core\Configure\Engine\PhpConfig;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;
use Nata\Error\Handler;
use Exception;

/**
 * Nata configuration class.
 */
class Configure {

/**
 * Loaded engines.
 *
 * @var array
 */
    protected static $_engines = [];

/**
 * Loaded environment files.
 *
 * @var array
 */
    protected static $_loadedEnvFiles = [];

/**
 * Configuration values.
 *
 * @var array
 */
    private static $_values = [
        'debug' => true
    ];


/**
 * Used to store a dynamic variable in Configure.
 *
 * Usage:
 * ```
 * Configure::write('One.key1', 'value of the Configure::One[key1]');
 * Configure::write(['One.key1' => 'value of the Configure::One[key1]']);
 * Configure::write('One', [
 *     'key1' => 'value of the Configure::One[key1]',
 *     'key2' => 'value of the Configure::One[key2]'
 * ]);
 *
 * Configure::write([
 *     'One.key1' => 'value of the Configure::One[key1]',
 *     'One.key2' => 'value of the Configure::One[key2]'
 * ]);
 * ```
 *
 * @param string|array $config The key to write, can be a dot notation value.
 * Alternatively can be an array containing key(s) and value(s).
 * @param mixed $value Value to set for var (ignored when $var is an array).
 * @param bool $replace When $var is an array, replace all configuration instead of merging into existing values.
 * @return bool True if write was successful
 * @link https://book.cakephp.org/3.0/en/development/configuration.html#writing-configuration-data
 */
    public static function write($var, $value = null, $replace = false) {
        if (is_array($var)) {
            static::$_values = $replace ? $var : Hash::merge(static::$_values, $var);
        } else {
            static::$_values = Hash::insert(static::$_values, $var, $value);
        }
        return true;
    }

/**
 * Used to read information stored in Configure. It's not
 * possible to store `null` values in Configure.
 *
 * Usage:
 * ```
 * Configure::read('Name'); will return all values for Name
 * Configure::read('Name.key'); will return only the value of Configure::Name[key]
 * ```
 *
 * @param string|null $var Variable to obtain. Use '.' to access array elements.
 * @param mixed $default The return value when the configure does not exist
 * @return mixed Value stored in configure, or null.
 * @link https://book.cakephp.org/3.0/en/development/configuration.html#reading-configuration-data
 */
    public static function read($var = null, $default = null) {
        if ($var === null) {
            return static::$_values;
        }

        if (strpos($var, '.') === false) {
            return static::$_values[$var] ?? $default;
        }

        return Hash::get(static::$_values, $var, $default);
    }

/**
 * Returns true if given variable is set in Configure.
 *
 * @param string $var Variable name to check for
 * @return bool True if variable is there
 */
    public static function check($var) {
        if (empty($var)) {
            return false;
        }

        return static::read($var) !== null;
    }

/**
 * Used to delete a variable from Configure.
 *
 * Usage:
 * ```
 * Configure::delete('Name'); will delete the entire Configure::Name
 * Configure::delete('Name.key'); will delete only the Configure::Name[key]
 * ```
 *
 * @param string $var the var to be deleted
 * @return void
 * @link https://book.cakephp.org/3.0/en/development/configuration.html#deleting-configuration-data
 */
    public static function delete($var) {
        static::$_values = Hash::remove(static::$_values, $var);
    }

/**
 * Loads stored configuration information from a resource. You can add
 * config file resource engines with `Configure::config()`.
 *
 * Loaded configuration information will be merged with the current
 * runtime configuration. You can load configuration files from plugins
 * by preceding the filename with the plugin name.
 *
 * `Configure::load('Users.user', 'default')`
 *
 * Would load the 'user' config file using the default config engine. You can load
 * app config files by giving the name of the resource you want loaded.
 *
 * ```
 * Configure::load('setup', 'default');
 * ```
 *
 * If using `default` config and no engine has been configured for it yet,
 * one will be automatically created using PhpConfig
 *
 * @param string $key name of configuration resource to load.
 * @param string $config Name of the configured engine to use to read the resource identified by $key.
 * @param bool $merge if config files should be merged instead of simply overridden
 * @return bool False if file not found, true if load successful.
 * @link https://book.cakephp.org/3.0/en/development/configuration.html#reading-and-writing-configuration-files
 */
    public static function load($key, $config = 'default', $merge = true) {
        $engine = static::_getEngine($config);
        if (!$engine) {
            return false;
        }

        $values = $engine->read($key);
        if ($merge) {
            $values = Hash::merge(static::$_values, $values);
        }

        return static::write($values, null, true);
    }

/**
 * Dump data currently in Configure into $key. The serialization format
 * is decided by the config engine attached as $config. For example, if the
 * 'default' adapter is a PhpConfig, the generated file will be a PHP
 * configuration file loadable by the PhpConfig.
 *
 * ### Usage
 *
 * Given that the 'default' engine is an instance of PhpConfig.
 * Save all data in Configure to the file `my_config.php`:
 *
 * ```
 * Configure::dump('my_config', 'default');
 * ```
 *
 * Save only the error handling configuration:
 *
 * ```
 * Configure::dump('error', 'default', ['Error', 'Exception'];
 * ```
 *
 * @param string $key The identifier to create in the config adapter.
 *   This could be a filename or a cache key depending on the adapter being used.
 * @param string $config The name of the configured adapter to dump data with.
 * @param array $keys The name of the top-level keys you want to dump.
 *   This allows you save only some data stored in Configure.
 * @return bool Success
 * @throws \Exception if the adapter does not implement a `dump` method.
 */
    public static function dump($key, $config = 'default', $keys = []) {
        $engine = static::_getEngine($config);
        if (!$engine) {
            throw new Exception(sprintf('There is no "%s" config engine.', $config));
        }

        $values = static::$_values;
        if (!empty($keys)) {
            $values = array_intersect_key($values, array_flip((array)$keys));
        }

        return (bool)$engine->dump($key, $values);
    }

/**
 * Get engine instance.
 *
 * @param string $config Config name
 * @return \Nata\Core\Configure\Engine|false
 */
    protected static function _getEngine($config) {
        if (!isset(static::$_engines[$config])) {
            if ($config !== 'default') {
                return false;
            }
            static::config($config, new PhpConfig());
        }
        return static::$_engines[$config];
    }

/**
 * Add a new engine to Configure. Engines allow you to read configuration
 * files in various formats/storage locations. NataPHP comes with two built-in engines
 * PhpConfig and IniConfig. You can also implement your own engine classes in your application.
 *
 * To add a new engine to Configure:
 *
 * ```
 * Configure::config('ini', new IniConfig());
 * ```
 *
 * @param string $name The name of the engine being configured. This alias is used later to
 *   read values from a specific engine.
 * @param \Nata\Core\Configure\Engine|string $engine The engine to append.
 * @return void
 */
    public static function config($config, $engine = null) {
        if (is_string($engine)) {
            $engineClass = Inflector::camelize($engine);
            $resolved = App::className($engineClass, 'Core/Configure/Engine');
            if (!$resolved) {
                throw new Exception(sprintf('Missing configure engine "%s"', $engineClass));
            }
            $engine = $resolved;
        }
        static::$_engines[$config] = $engine;
    }

/**
 * Gets the names of the configured Engine objects.
 *
 * @param string|null $name Engine name.
 * @return array|bool Array of the configured Engine objects, bool for specific name.
 */
    public static function configured($name = null) {
        if ($name !== null) {
            return isset(static::$_engines[$name]);
        }
        return array_keys(static::$_engines);
    }

/**
 * Load environment variables from a .env file into $_ENV/$_SERVER.
 *
 * This is a lightweight loader to avoid adding external dependencies.
 * The file is read only once per path; calling loadEnv again for the same path returns true (no-op).
 *
 * Supported syntax:
 *  - KEY=value
 *  - KEY="value with spaces"
 *  - KEY='value with spaces'
 *  - Lines starting with # are ignored
 *  - Leading "export " is accepted
 *  - Unquoted values: inline comments after whitespace + # are stripped (e.g. KEY=value # note)
 *
 * All values are stored as strings. Cast at the read site (e.g. via env() helper).
 *
 * @param string $path Absolute path to .env file
 * @param bool $overwrite Overwrite existing variables (default false)
 * @return bool True if the file was loaded successfully, false otherwise
 */
    public static function loadEnv(string $path, bool $overwrite = false): bool {
        if (isset(static::$_loadedEnvFiles[$path])) {
            return true;
        }
        if (!is_file($path)) {
            return false;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
                $val = preg_replace('/\s+#.*$/', '', $val);
                $val = rtrim($val);
            }
            if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
                $quote = $val[0];
                if ($val[strlen($val) - 1] === $quote) {
                    $val = substr($val, 1, -1);
                }
            }

            $hasServer = array_key_exists($key, $_SERVER);
            $hasEnv = array_key_exists($key, $_ENV);
            $hasGetenv = getenv($key) !== false;
            if ($overwrite || (!$hasServer && !$hasEnv && !$hasGetenv)) {
                $_ENV[$key] = $val;
                if ($overwrite || !$hasServer) {
                    $_SERVER[$key] = $val;
                }
                putenv($key . '=' . $val);
            }
        }

        static::$_loadedEnvFiles[$path] = true;
        return true;
    }

/**
 * Shorcut to check if config value matches given value.
 *
 * Example:
 *  // Check if cache is disabled
 *  echo Configure::is('Cache.disabled', true);
 *
 * @param string $path Path to parameter
 * @param mixed $against Comparison value
 * @return bool True if $against matches configuration value
 */
    public static function is($path, $against) {
        return static::read($path) === $against;
    }

/**
 * Get NataPHP version.
 *
 * @return string NataPHP version
 */
    public static function version() {
        return 'NataPHP ' . VERSION . ' - PHP ' . PHP_VERSION;
    }

/**
 * Set error and exception handlers.
 *
 * @return void
 */
    public static function setErrorHandlers() {
        $error = static::read('Error');
        $exception = static::read('Exception');

        $level = -1;
        if (isset($error['level'])) {
            error_reporting($error['level']);
            $level = $error['level'];
        }

        if (!empty($error['handler'])) {
            set_error_handler($error['handler'], $level);
        }

        if (!empty($exception['handler'])) {
            set_exception_handler($exception['handler']);

            register_shutdown_function(function () {
                if (PHP_SAPI === 'cli') {
                    return;
                }

                $error = error_get_last();
                if (!is_array($error)) {
                    return;
                }

                $fatals = [
                    E_ERROR,
                    E_PARSE,
                    E_USER_ERROR,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR,
                    E_RECOVERABLE_ERROR
                ];

                if (!in_array($error['type'], $fatals, true)) {
                    return;
                }

                Handler::handleFatalError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            });
        }
    }

}
