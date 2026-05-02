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
 * @copyright   Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link        http://nataphp.com NataPHP Project
 * @since       NataPHP 1.0.0
 * @license     http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM;

use Nata\Core\App;
use Nata\Core\ObjectRegistry;
use MissingBehaviorException;
use BadMethodCallException;
use LogicException;

/**
 * Behaviors registry loader.
 */
class BehaviorRegistry extends ObjectRegistry {

/**
 * The controller that this collection was initialized with.
 *
 * @var \Nata\Controller\Controller
 */
    protected $_table;

/**
 * The controller event manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Method mappings.
 *
 * @var array
 */
   protected $_methodMap = [];

/**
 * Finder method mappings.
 *
 * @var array
 */
   protected $_finderMap = [];


/**
 * Construct component registry.
 *
 * @param \Nata\Controller\Controller $controller Controller instance.
 * @return void
 */
    public function __construct(Table $table = null) {
        if ($table) {
            $this->setTable($table);
        }
    }

/**
 * Get the controller associated with the collection.
 *
 * @return \Nata\Controller\Controller Controller instance
 */
    public function getTable() {
        return $this->_table;
    }

/**
 * Set the controller associated with the collection.
 *
 * @param \Nata\Controller\Controller Controller instance
 * @return void
 */
    public function setTable(Table $table) {
        $this->_table = $table;
        $this->_eventManager = $table->eventManager();
    }

/**
 * Resolve a component classname.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 *
 * @param string $class Partial classname to resolve.
 * @return string|false Either the correct classname or false.
 */
   protected function _resolveClassName($class) {
      $className = App::className($class, 'Model/Behavior');
      if (!$className) {
         $className = App::className($class, 'ORM/Behavior');
      }
      return $className;
   }

/**
 * Throws an exception when a component is missing.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 *
 * @param string $class The classname that is missing.
 * @param string $plugin The plugin the component is missing in.
 * @return void
 * @throws \Nata\Controller\Exception\MissingComponentException
 */
   protected function _throwMissingClassError($class, $plugin) {
      throw new MissingBehaviorException([
         'class' => 'Table\Behavior\\' . $class,
         'plugin' => $plugin
      ]);
   }

/**
 * Create the component instance.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 * Enabled components will be registered with the event manager.
 *
 * @param string $class The classname to create.
 * @param string $alias The alias of the component.
 * @param array $config An array of config to use for the component.
 * @return \Nata\Controller\Component The constructed component class.
 */
   protected function _create($class, $alias, $config) {
      $instance = new $class($this->_table, $config);
      $enable = isset($config['enabled']) ? $config['enabled'] : true;
      if ($enable) {
         $this->_eventManager->on($instance);
      }
      $methods = $this->_getMethods($instance, $class, $alias);
      $this->_methodMap += $methods['methods'];
      $this->_finderMap += $methods['finders'];
      return $instance;
    }


/**
 * Get the behavior methods and ensure there are no duplicates.
 *
 * Use the implementedEvents() method to exclude callback methods.
 * Methods starting with `_` will be ignored, as will methods
 * declared on Cake\ORM\Behavior
 *
 * @param \Nata\ORM\Behavior $instance The behavior to get methods from.
 * @param string $class The classname that is missing.
 * @param string $alias The alias of the object.
 * @return array A list of implemented finders and methods.
 * @throws \LogicException when duplicate methods are connected.
 */
   protected function _getMethods(Behavior $instance, $class, $alias) {
      $finders = array_change_key_case($instance->implementedFinders());
      $methods = array_change_key_case($instance->implementedMethods());

      foreach ($finders as $finder => $methodName) {
         if (isset($this->_finderMap[$finder]) && $this->has($this->_finderMap[$finder][0])) {
            $duplicate = $this->_finderMap[$finder];
            $error = sprintf(
               '%s contains duplicate finder "%s" which is already provided by "%s"',
               $class,
               $finder,
               $duplicate[0]
            );
            throw new LogicException($error);
         }
         $finders[$finder] = [$alias, $methodName];
      }

      foreach ($methods as $method => $methodName) {
         if (isset($this->_methodMap[$method]) && $this->has($this->_methodMap[$method][0])) {
            $duplicate = $this->_methodMap[$method];
            $error = sprintf(
               '%s contains duplicate method "%s" which is already provided by "%s"',
               $class,
               $method,
               $duplicate[0]
            );
            throw new LogicException($error);
         }
         $methods[$method] = [$alias, $methodName];
      }
      return compact('methods', 'finders');
   }

/**
 * Check if any loaded behavior implements a method.
 *
 * Will return true if any behavior provides a public non-finder method
 * with the chosen name.
 *
 * @param string $method The method to check for.
 * @return bool
 */
   public function hasMethod($method) {
      $method = strtolower($method);
      return isset($this->_methodMap[$method]);
   }

/**
 * Check if any loaded behavior implements the named finder.
 *
 * Will return true if any behavior provides a public method with
 * the chosen name.
 *
 * @param string $method The method to check for.
 * @return bool
 */
   public function hasFinder($method) {
      $method = strtolower($method);
      return isset($this->_finderMap[$method]);
   }

/**
 * Invoke a method on a behavior.
 *
 * @param string $method The method to invoke.
 * @param array $args The arguments you want to invoke the method with.
 * @return mixed The return value depends on the underlying behavior method.
 * @throws \BadMethodCallException When the method is unknown.
 */
   public function call($method, array $args = []) {
      $method = strtolower($method);
      if ($this->hasMethod($method) && $this->has($this->_methodMap[$method][0])) {
         list($behavior, $callMethod) = $this->_methodMap[$method];
         return call_user_func_array([$this->_loaded[$behavior], $callMethod], $args);
      }

      throw new BadMethodCallException(
         sprintf('Cannot call "%s" it does not belong to any attached behavior.', $method)
      );
   }

/**
 * Invoke a finder on a behavior.
 *
 * @param string $type The finder type to invoke.
 * @param array $args The arguments you want to invoke the method with.
 * @return mixed The return value depends on the underlying behavior method.
 * @throws \BadMethodCallException When the method is unknown.
 */
   public function callFinder($type, array $args = []) {
      $type = strtolower($type);

      if ($this->hasFinder($type) && $this->has($this->_finderMap[$type][0])) {
         list($behavior, $callMethod) = $this->_finderMap[$type];
         return call_user_func_array([$this->_loaded[$behavior], $callMethod], $args);
      }

      throw new BadMethodCallException(
         sprintf('Cannot call finder "%s" it does not belong to any attached behavior.', $type)
      );
   }

}
