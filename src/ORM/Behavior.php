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

use Nata\Core\NataObject;
use Nata\Event\Event;
use Nata\Event\Manager;
use Nata\Event\Listener;
use ReflectionClass;
use ReflectionMethod;

/**
 * Common behavior class logic
 */
class Behavior extends NataObject implements Listener {

/**
 * The table using this registry.
 *
 * @var \Nata\ORM\Table
 */
    protected $_table;

/**
 * Reflection cache.
 *
 * @var array
 */
    protected static $_reflectionCache = [];

/**
 * Event manager.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;


/**
 * Constructor
 *
 * @param \Nata\ORM\Table $table The table this registry is attached to
 */
    public function __construct(Table $table, $config = []) {
        $this->config($config);
        $this->_table = $table;
        $this->initialize($config);
    }

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        $eventMap = [
            'Model.afterStartup' => 'afterStartup',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeInsert' => 'beforeInsert',
            'Model.afterInsert' => 'afterInsert',
            'Model.beforeUpdate' => 'beforeUpdate',
            'Model.afterUpdate' => 'afterUpdate',
            'Model.beforeUpdateAll' => 'beforeUpdateAll',
            'Model.afterUpdateAll' => 'afterUpdateAll',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
            'Model.beforeDeleteAll' => 'beforeDeleteAll',
            'Model.afterDeleteAll' => 'afterDeleteAll',
            'Model.shutdown' => 'beforeShutdown'
        ];

        $config = $this->config();
        $priority = isset($config['priority']) ? $config['priority'] : null;

        $events = [];
        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }

            if ($priority === null) {
                $events[$event] = $method;
            } else {
                $events[$event] = [
                    'callable' => $method,
                    'priority' => $priority
                ];
            }
        }
        return $events;
    }

/**
 * implementedFinders
 *
 * Provides an alias->methodname map of which finders a behavior implements. Example:
 *
 * ```
 *  [
 *    'this' => 'findThis',
 *    'alias' => 'findMethodName'
 *  ]
 * ```
 *
 * With the above example, a call to `$Table->find('this')` will call `$Behavior->findThis()`
 * and a call to `$Table->find('alias')` will call `$Behavior->findMethodName()`
 *
 * It is recommended, though not required, to define implementedFinders in the config property
 * of child classes such that it is not necessary to use reflections to derive the available
 * method list. See core behaviors for examples
 *
 * @return array
 */
    public function implementedFinders() {
        $methods = $this->config('implementedFinders');

        if (isset($methods)) {
            return $methods;
        }

        return $this->_reflectionCache()['finders'];
    }

/**
 * implementedMethods
 *
 * Provides an alias->methodname map of which methods a behavior implements. Example:
 *
 * ```
 *  [
 *    'method' => 'method',
 *    'aliasedmethod' => 'somethingElse'
 *  ]
 * ```
 *
 * With the above example, a call to `$Table->method()` will call `$Behavior->method()`
 * and a call to `$Table->aliasedmethod()` will call `$Behavior->somethingElse()`
 *
 * It is recommended, though not required, to define implementedFinders in the config property
 * of child classes such that it is not necessary to use reflections to derive the available
 * method list. See core behaviors for examples
 *
 * @return array
 */
    public function implementedMethods() {
        $methods = $this->config('implementedMethods');

        if (isset($methods)) {
            return $methods;
        }

        return $this->_reflectionCache()['methods'];
    }

/**
 * Gets the methods implemented by this behavior
 *
 * Uses the implementedEvents() method to exclude callback methods.
 * Methods starting with `_` will be ignored, as will methods
 * declared on Cake\ORM\Behavior
 *
 * @return array
 */
    protected function _reflectionCache() {
        $class = get_class($this);
        if (isset(static::$_reflectionCache[$class])) {
           return static::$_reflectionCache[$class];
        }

        $events = $this->implementedEvents();
        $eventMethods = [];
        foreach ($events as $e => $binding) {
            if (is_array($binding) && isset($binding['callable'])) {
                $binding = $binding['callable'];
            }
            $eventMethods[$binding] = true;
        }

        $baseClass = 'Nata\ORM\Behavior';

        if (isset(static::$_reflectionCache[$baseClass])) {
            $baseMethods = static::$_reflectionCache[$baseClass];
        } else {
            $baseMethods = get_class_methods($baseClass);
            static::$_reflectionCache[$baseClass] = $baseMethods;
        }

        $return = [
            'finders' => [],
            'methods' => []
        ];

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            if (in_array($methodName, $baseMethods) ||
                isset($eventMethods[$methodName])
                ) {
                continue;
            }

            if (substr($methodName, 0, 4) === 'find') {
                $return['finders'][lcfirst(substr($methodName, 4))] = $methodName;
            } else {
                $return['methods'][$methodName] = $methodName;
            }

        }

        return static::$_reflectionCache[$class] = $return;
    }

/**
 * Instance initialization
 *
 * @param array $config Event instance
 * @return void
 */
    public function initialize($config) {}

/**
 * Instance initialization
 *
 * @param array $config Event instance
 * @return void
 */
    public function startup($config) {}

/**
 * Returns an array that can be used to describe the internal state of this
 * object.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'table' => $this->_table
        ];
    }

}