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

namespace Nata\Database;

use MissingDatabaseConfigException;

/**
 * Management and retrieval of database configuration/connections.
 */
class ConnectionManager {

/**
 * Connection configuration stack.
 * Keeps the permanent/default settings for each connection.
 * These settings are used to reset the connection after temporary modification.
 *
 * @var array
 */
    protected static $_config = [];

/**
 * Connection instances stack
 * Keeps the permanent instances for each connection.
 *
 * @var array
 */
    private static $_connection = [];


/**
 * Get/Set the database configuration to use.
 *
 * Create new configurations or the settings for already configured
 * configurations.
 *
 * @see config/database.inc.php for configuration settings
 * @param string $name Name of the configuration
 * @param array $settings Optional associative array of settings
 * @return void
 */
    public static function config($config, $settings = []) {
        $current = [];
        if (isset(static::$_config[$config])) {
            $current = static::$_config[$config];
        }

        if (!empty($config) && empty($settings)) {
            return $current;
        }

        $settings['driver'] = strtolower($settings['driver']);

        if (!empty($settings)) {
            static::$_config[$config] = array_merge($current, $settings);
        }

    }

/**
 * Get connection for given configuration name.
 *
 * @param string $config Name of configuration
 * @return Connection Connection instance
 */
    public static function get(string $config = 'default'): Connection {
        if (!isset(static::$_config[$config])) {
            throw new MissingDatabaseConfigException([$config]);
        }

        if (isset(static::$_connection[$config])) {
            return static::$_connection[$config];
        }

        $dbconfig = static::$_config[$config];
        if (empty($dbconfig['dbname'])) {
            throw new MissingDatabaseConfigException(sprintf(
                'Missing database name (dbname) in "%s" configuration in database.inc.php.',
                $config
           ));
        }

        static::$_connection[$config] = new Connection($dbconfig + ['_configName' => $config]);
        return static::$_connection[$config];
    }

/**
 * Drops a connection config. Deletes the connection configuration information.
 *
 * @param string $name A currently configured connection config you wish to remove.
 * @return void
 */
    public static function drop($name) {
        unset(static::$_config[$name], static::$_connection[$name]);
    }

/**
 * Create connection at runtime.
 *
 * @param array|string $config Database configuration name
 * @param array $settings Database configuration
 * @return Connection
 */
    public static function create($config, array $settings = []) {
        if (is_array($config)) {
            $settings = $config;
            $config = rand(10, 100);
        }

        static::config($config, $settings);

        return static::get($config);
    }

}
