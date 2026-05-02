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

namespace Nata\ORM;

use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Utility\Inflector;
use RuntimeException;
use TableException;

/**
 * Provides a registry/factory for Table objects.
 *
 * This registry allows you to centralize the configuration for tables
 * their connections and other meta-data.
 */
class TableRegistry {

/**
 * Tables instances.
 *
 * @var array
 */
    private static $_instances = [];

/**
 * Table options.
 *
 * @var array
 */
    private static $_options = [];


/**
 * Get table class Instance.
 *
 * @param string $alias Table alias
 * @param array $options Table options
 * @return \Nata\ORM\Table
 * @throws \TableException
 */
    public static function get(string $alias, array $options = []) {
        if (static::exists($alias)) {
            if (!empty($options) && static::$_options[$alias] !== $options) {
                throw new RuntimeException(sprintf(
                    'You cannot configure "%s", it already exists in the registry.',
                    $alias
                ));
            }
            return static::$_instances[$alias];
        }

        $options += [
            'connection' => Configure::read('Database.config'),
            'className' => null,
            'table' => null,
            'tablePrefix' => null,
            'registryAlias' => null
        ];

        [$plugin, $modelClass] = pluginSplit($alias, true);

        if (empty($options['className'])) {
            $options['className'] = App::className($plugin . $modelClass, 'Model/Table');
        }

        if (!$options['className']) {
            $options['className'] = '\Nata\ORM\Table';
        }

        if (empty($options['table'])) {
            $_name = App::classShortName($modelClass);
            $options['table'] = Inflector::underscore($_name);
        }

        if (empty($options['table'])) {
            throw new TableException('Empty table name.');
        }

        $options['registryAlias'] = $alias;

        static::$_instances[$alias] = new $options['className']($options);
        static::$_options[$alias] = $options;

        return static::$_instances[$alias];
    }

/**
 * Set table instance.
 *
 * @param string $alias Table alias
 * @param \Nata\ORM\Table $instance Table instance
 */
    public static function set($alias, Table $instance) {
        static::$_instances[$alias] = $instance;
    }

/**
 * Check if table instance exists.
 *
 * @param string $alias Table alias
 * @return bool True if exists
 */
    public static function exists($alias) {
        return isset(static::$_instances[$alias]);
    }

/**
 * Remove table instance.
 *
 * @param string $alias Table alias
 */
    public static function remove($alias) {
        unset(static::$_options[$alias]);
        unset(static::$_instances[$alias]);
    }

/**
 * Clear all table instances.
 */
    public static function clear() {
        static::$_options = [];
        static::$_instances = [];
    }

}
