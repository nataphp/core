<?php
/**
 * NataPHP Framework
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

namespace Nata\Form;

use Nata\Core\App;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;
use MissingFormException;
use FormException;
use RuntimeException;

/**
 * Provides a registry/factory for Form objects.
 *
 * This registry allows you to centralize the configuration for forms
 * their options and other meta-data.
 */
class FormRegistry {

/**
 * Form instances.
 *
 * @var array
 */
    private static $_instances = [];

/**
 * Form configuration.
 *
 * @var array
 */
    private static $_options = [];


/**
 * Get form class instance.
 *
 * ### Example:
 *
 *  $instance = FormRegistry::get('Contact');
 *  // \App\Form\Contact instance
 *
 *  $instance = FormRegistry::get('Admin/Contact');
 *  // \App\Form\Admin\Contact instance
 *
 *  If we try to get the 'Contact' form instance from plugin 'AddressBook' with the route prefix
 *  'admin':
 *    $instance = FormRegistry::get('AddressBook.Admin/Contact');
 *  // \AddressBook\Form\Admin\Contact instance
 *
 * @param \Nata\ORM\Form|string $alias Form alias
 * @param array $config Form configuration
 * @return \Nata\Form\Form
 * @throws \MissingFormException
 * @throws \RuntimeException
 */
    public static function get($alias, array $config = []) {
        if (static::isLoaded($alias)) {
            if (!empty($config) && static::$_options[$alias] !== $config) {
                throw new RuntimeException(sprintf(
                    'You cannot configure "%s", it already exists in the registry.',
                    $alias
                ));
            }
            return static::$_instances[$alias];
        }

        $config += [
            'form' => null,
            'className' => null,
            'registryAlias' => null
        ];

        if (!$config['className']) {
            $config['className'] = static::_getFormclassName($alias);
        }

        [$plugin, $formClass] = pluginSplit($alias, true);

        if (empty($config['className'])) {
            throw new MissingFormException(sprintf(
                'Missing form class for alias "%s".',
                $formClass
            ));
        }

        if (empty($config['form'])) {
            $_name = App::classShortName($formClass);
            $config['form'] = str_replace('/', '__', Inflector::underscore($_name));
        }

        if (empty($config['form'])) {
            throw new FormException('Empty form name.');
        }

        $config['registryAlias'] = $alias;
        $config = Hash::expand($config);

        static::$_options[$alias] = $config;

        return static::$_instances[$alias] = new $config['className']($config);
    }

/**
 * Set form instance.
 *
 * @param string $alias Form alias
 * @param \Nata\ORM\Form $instance Form instance
 * @return void
 */
    protected static function _getFormClassName($alias) {
        [$plugin, $formClass] = pluginSplit($alias, true);
        return App::className($plugin . $formClass, 'Form');
    }

/**
 * Set form instance.
 *
 * @param string $alias Form alias
 * @param \Nata\ORM\Form $instance Form instance
 * @return void
 */
    public static function set($alias, Form $instance) {
        static::$_instances[$alias] = $instance;
    }

/**
 * Check if form class exists.
 *
 * @param string $alias Form alias
 * @return bool True if exists
 */
    public static function exists($alias): bool {
        return static::_getFormClassName($alias) !== false;
    }

/**
 * Check if form instance is loaded.
 *
 * @param string $alias Form alias
 * @return bool True if loaded
 */
    public static function isLoaded($alias): bool {
        return isset(static::$_instances[$alias]);
    }

/**
 * Get loaded instance's names.
 *
 * @return array Loaded instances
 */
    public static function loaded() {
        return array_keys(static::$_instances);
    }

/**
 * Remove form instance.
 *
 * @param string $alias Form alias
 * @return void
 */
    public static function remove($alias) {
        unset(static::$_options[$alias]);
        unset(static::$_instances[$alias]);
    }

/**
 * Clear all form instances.
 *
 * @return void
 */
    public static function clear() {
        static::$_options = array();
        static::$_instances = array();
    }

}