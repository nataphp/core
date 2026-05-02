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

namespace Nata\Core;

/**
 * Acts as a runtime registry for object instances.
 */
class Registry {

/**
 * Map of set objects.
 *
 * @var array
 */
    protected static $_objects = array();


/**
 * Get cached object instance.
 *
 * @param string $name Name of object.
 * @return object|null Object instance if cached else null.
 */
    public static function get($name) {
        if (isset(static::$_objects[$name])) {
            return static::$_objects[$name];
        }
    }

/**
 * Set an object directly into the registry by name.
 *
 * If this collection implements events, the passed object will
 * be attached into the event manager
 *
 * @param string $objectName The name of the object to set in the registry.
 * @param object $object instance to set in cache
 * @return void
 */
    public static function set($objectName, $object) {
        list(, $name) = pluginSplit($objectName);
        static::$_objects[$name] = $object;
    }

/**
 * Check whether or not a given object is loaded.
 *
 * @param string $name The object name to check for.
 * @return bool True is object is loaded else false.
 */
    public static function has($name) {
        return isset(static::$_objects[$name]);
    }

/**
 * Clear cached instances.
 *
 * @return void
 */
    public static function reset() {
        foreach (array_keys(static::$_objects) as $name) {
            static::unload($name);
        }
    }

/**
 * Remove an object from cache.
 *
 * @param string $objectName The name of the object to remove from the registry.
 * @return void
 */
    public static function unload($objectName) {
        unset(static::$_objects[$objectName]);
    }

}
