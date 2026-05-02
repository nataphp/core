<?php
/**
 * NataPHP Framework
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

namespace Nata\Log;

use Nata\Log\Engine\Base;
use Nata\Log\EngineRegistry;
use Nata\Log\Exception\LogException;
use Nata\Log\Exception\MissingLogEngineException;
use InvalidArgumentException;

/**
 * Logs messages to configured Log adapters. One or more adapters
 * can be configured using NataLogs's methods. If you don't
 * configure any adapters, and write to the logs a default
 * FileLog will be autoconfigured for you.
 *
 * ### Configuring Log adapters
 *
 * You can configure log adapters in your applications `bootstrap.php` file.
 * A sample configuration would look like:
 *
 * {{{
 * \Nata\Log\Log::config('my_log', array('className' => 'File'));
 * }}}
 *
 * See the documentation on \Nata\Log\Log::config() for more detail.
 *
 * ### Writing to the log
 *
 * You write to the logs using \Nata\Log\Log::write(). See its documentation for more
 * information.
 *
 * ### Logging Levels
 *
 * By default NataLog supports all the log levels defined in
 * RFC 5424. When logging messages you can either use the named methods,
 * or the correct constants with `write()`:
 *
 * {{{
 * \Nata\Log\Log::error('Something horrible happened');
 * \Nata\Log\Log::write(LOG_ERR, 'Something horrible happened');
 * }}}
 *
 * If you require custom logging levels, you can use \Nata\Log\Log::levels() to
 * append additoinal logging levels.
 *
 * ### Logging scopes
 *
 * When logging messages and configuring log adapters, you can specify
 * 'scopes' that the logger will handle.  You can think of scopes as subsystems
 * in your application that may require different logging setups.  For
 * example in an e-commerce application you may want to handle logged errors
 * in the cart and ordering subsystems differently than the rest of the
 * application.  By using scopes you can control logging for each part
 * of your application and still keep standard log levels.
 *
 *
 * See \Nata\Log\Log::config() and \Nata\Log\Log::write() for more information
 * on scopes.
 */

class Log {

/**
 * EngineRegistry class.
 *
 * @var \Nata\Log\EngineRegistry
 */
    protected static $_registry;

/**
 * Default logging configurations loaded.
 *
 * @var boolean
 */
    protected static $_defaultConfigLoaded = false;

/**
 * Handled levels.
 *
 * @var array
 */
    protected static $_levels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug'
    ];

/**
 * Default log levels as detailed in RFC 5424
 * http://tools.ietf.org/html/rfc5424
 *
 * @var array
 */
    protected static $_levelMap = [
        'emergency' => LOG_EMERG,
        'alert' => LOG_ALERT,
        'critical' => LOG_CRIT,
        'error' => LOG_ERR,
        'warning' => LOG_WARNING,
        'notice' => LOG_NOTICE,
        'info' => LOG_INFO,
        'debug' => LOG_DEBUG
    ];

/**
 * Configure and add a new logging engine to NataLog
 * You can use add loggers from app/Log/Engine use app.loggername, or any
 * plugin/Log/Engine using plugin.loggername.
 *
 * ### Usage:
 *
 * {{{
 * \Nata\Log\Log::config('second_file', [
 *     'engine' => 'File',
 *     'path' => '/var/logs/my_app/'
 * ]);
 * }}}
 *
 * Will configure a FileLog instance to use the specified path.
 * All options that are not `engine` are passed onto the logging adapter,
 * and handled there.  Any class can be configured as a logging
 * adapter as long as it implements the methods in NataLogInterface.
 *
 * ### Logging levels
 *
 * When configuring loggers, you can set which levels a logger will handle.
 * This allows you to disable debug messages in production for example:
 *
 * {{{
 * \Nata\Log\Log::config('default', [
 *     'engine' => 'File',
 *     'path' => LOGS,
 *     'levels' => ['error', 'critical', 'alert', 'emergency']
 * ]);
 * }}}
 *
 * The above logger would only log error messages or higher. Any
 * other log messages would be discarded.
 *
 * ### Logging scopes
 *
 * When configuring loggers you can define the active scopes the logger
 * is for. If defined only the listed scopes will be handled by the
 * logger. If you don't define any scopes an adapter will catch
 * all scopes that match the handled levels.
 *
 * {{{
 * \Nata\Log\Log::config('payments', [
 *     'engine' => 'File',
 *     'levels' => ['info', 'error', 'warning'],
 *     'scopes' => ['payment', 'order']
 * ]);
 * }}}
 *
 * The above logger will only capture log entries made in the
 * `payment` and `order` scopes. All other scopes including the
 * undefined scope will be ignored. Its important to remember that
 * when using scopes you must also define the `levels` of log messages
 * that a logger will handle. Failing to do so will result in the logger
 * catching all log messages even if the scope is incorrect.
 *
 * @param string $key The keyname for this logger, used to remove the
 *    logger later.
 * @param array $config Array of configuration information for the logger
 * @return boolean success of configuration.
 * @throws NataLogException
 */
    public static function config($key, $config) {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $key)) {
            throw new LogException('Invalid key name');
        }

        $config += [
            'engine' => 'File',
            'levels' => static::$_levels
        ];

        if (empty($config['engine'])) {
            throw new LogException('Missing logger classname');
        }

        static::_getRegistry()->load($key, $config);

        return true;
    }

/**
 * Returns the keynames of the currently active engines.
 *
 * @return array Array of configured log engines.
 */
    public static function configured(): array {
        return static::_getRegistry()->loaded();
    }

/**
 * Gets log levels.
 *
 * @return array Log levels
 */
    public static function levels(): array {
        return static::$_levels;
    }

/**
 * Reset log instance.
 *
 * @return void
 */
    public static function reset() {
        static::$_registry = null;
    }

/**
 * Removes a engine from the active engines. Once a engine has been removed
 * it will no longer have messages sent to it.
 *
 * @param string $name Key name of a configured engine to remove.
 * @return void
 */
    public static function drop($name) {
        static::_getRegistry()->unload($name);
    }

/**
 * Checks wether $name is enabled.
 *
 * @param string $name to check
 * @return bool
 * @throws NataLogException
 */
    public static function enabled($name): bool {
        if (!isset(static::_getRegistry()->{$name})) {
            throw new MissingLogEngineException([$name]);
        }
        return static::_getRegistry()->enabled($name);
    }

/**
 * Enable engine. Engines that were previously disabled
 * can be re-enabled with this method.
 *
 * @param string $name to enable
 * @return void
 * @throws \Nata\Log\Exception
 */
    public static function enable($name) {
        if (!isset(static::_getRegistry()->{$name})) {
            throw new MissingLogEngineException([$name]);
        }

        static::_getRegistry()->enable($name);
    }

/**
 * Disable engine. Disabling a engine will
 * prevent that log engine from receiving any messages until
 * its re-enabled.
 *
 * @param string $name to disable
 * @return void
 * @throws \Nata\Log\MissingLogEngineException
 */
    public static function disable($name) {
        if (!isset(static::_getRegistry()->{$name})) {
            throw new MissingLogEngineException([$name]);
        }
        static::_getRegistry()->disable($name);
    }

/**
 * Gets the logging engine from the active engines.
 *
 * @see \Nata\Log\Engine\Base
 * @param string $name Key name of a configured engine to get.
 * @return $mixed instance of BaseLog or false if not found
 */
    public static function engine($name) {
        if (!empty(static::_getRegistry()->{$name})) {
            return static::_getRegistry()->{$name};
        }
        return false;
    }

/**
 * Writes the given message and level to all of the configured log adapters.
 * Configured adapters are passed both the $level and $message variables. $level
 * is one of the following strings/values.
 *
 * ### Levels:
 *
 * - `LOG_EMERG` => 'emergency',
 * - `LOG_ALERT` => 'alert',
 * - `LOG_CRIT` => 'critical',
 * - `LOG_ERR` => 'error',
 * - `LOG_WARNING` => 'warning',
 * - `LOG_NOTICE` => 'notice',
 * - `LOG_INFO` => 'info',
 * - `LOG_DEBUG` => 'debug',
 *
 * ### Usage:
 *
 * Write a message to the 'warning' log:
 *
 * `\Nata\Log\Log::write('warning', 'Stuff is broken here');`
 *
 * @param integer|string $level Level of message being written. When value is an integer
 *    or a string matching the recognized levels, then it will
 *    be treated log levels. Otherwise it's treated as scope.
 * @param string $message Message content to log
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function write($level, $message, $scope = []): bool {
        if (is_int($level) && in_array($level, static::$_levelMap)) {
            $level = array_search($level, static::$_levelMap);
        }

        if (!in_array($level, static::$_levels)) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s"', $level));
        }

        if (!empty($scope)) {
            if (!is_array($scope)) {
                $scope = [$scope];
            }
            if (!isset($scope['scope'])) {
                $scope = ['scope' => $scope];
            }
        }

        $scope += [
            'scope' => []
        ];

        if (empty($scope['scope']) && !static::$_defaultConfigLoaded) {
            static::_loadDefaultConfig();
        }

        $logged = false;
        $registry = static::_getRegistry();
        foreach ($registry->enabled() as $name) {
            $logger = $registry->{$name};

            $levels = $scopes = $config = [];

            if ($logger instanceof Base) {
                $config = $logger->config();
            }

            if (isset($config['levels'])) {
                $levels = $config['levels'];
            }

            if (isset($config['scopes'])) {
                $scopes = $config['scopes'];
            }

            $inScope = (empty($scopes) && empty($scope['scope'])) || (count(array_intersect($scope['scope'], $scopes)) > 0);
            $correctLevel = empty($levels) || in_array($level, $levels);

            if ($inScope && $correctLevel) {
                $logger->write($level, $message);
                $logged = true;
            }

        }

        return $logged;
    }

/**
 * Convenience method to log emergency messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function emergency($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log alert messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function alert($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log critical messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function critical($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log error messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function error($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log warning messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function warning($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log notice messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function notice($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log debug messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function debug($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Convenience method to log info messages.
 *
 * @param string $message log message
 * @param string|array $scope The scope(s) a log message is being created in.
 *    See \Nata\Log\Log::config() for more information on logging scopes.
 * @return boolean Success
 */
    public static function info($message, $scope = []): bool {
        return static::write(__FUNCTION__, $message, $scope);
    }

/**
 * Returns Registry instance.
 *
 * @return \Nata\Log\Registry Registry instance.
 */
    protected static function _getRegistry() {
        if (static::$_registry === null) {
            static::$_registry = new EngineRegistry();
        }
        return static::$_registry;
    }

/**
 * Configures the automatic/default engine a FileLog.
 *
 * @return void
 */
    protected static function _loadDefaultConfig() {
        $configs = static::configured();

        // Debug config
        if (!in_array('debug', $configs)) {
            static::_getRegistry()->load('debug', [
                'className' => 'File',
                'path' => LOGS,
                'levels' => ['notice', 'info', 'debug'],
                'file' => 'debug',
            ]);
        }

        // Error config
        if (!in_array('error', $configs)) {
            static::_getRegistry()->load('error', [
                'className' => 'File',
                'path' => LOGS,
                'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
                'file' => 'error'
            ]);
        }

        static::$_defaultConfigLoaded = true;
    }

}
