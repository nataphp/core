<?php
/**
 * NataPHP Framework.
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

use Nata\Log\Log;
use Nata\Utility\Hash;
use Nata\Event\Event;
use Nata\Event\Manager;
use Nata\Event\Listener;
use Nata\Routing\Router;
use Nata\ORM\TableRegistry;
use Nata\Service\ServiceBuilder;
use Nata\ORM\Table;
use MissingModelException;
use BadMethodCallException;
use Exception;
use Closure;

/**
 * Object class provides a few generic methods used in several subclasses.
 *
 * Also includes methods for logging, load models and/or services.
 */
class NataObject implements Listener {

/**
 * Configuration map.
 *
 * @var array
 */
    protected $_config = [];

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [];

/**
 * @todo Strict configuration.
 *
 * If true, it will check default Config
 * parameters and throw an exception if given
 * config parameter to get/set is not valid.
 *
 * @var bool
 */
    protected $_strictConfig = false;

/**
 * Event Manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;


/**
 * Constructor, no-op.
 */
    public function __construct() {}

/**
 * Get/Set configuration.
 *
 * It can be reset if an array is passed.
 *
 * @param array|string $varName Parameter name.
 * @param mixed $value Parameter value.
 * @param bool $merge True to merge, false to replace
 * @return $this|array|mixed
 */
    public function config($varName = null, $value = null, $merge = true) {
        if ($varName === null) {
            return $this->_config;
        }

        if (func_num_args() === 1) {
            if (!is_array($varName)) {
                $value = Hash::get($this->_config, $varName);
                if ($value instanceof Closure) {
                    $value = $value();
                }
                return $value;
            }

            $this->_config = array_merge($this->_defaultConfig, $this->_config, $varName);
        } else {
            $this->_config = Hash::insert($this->_config, $varName, $value);
        }

        foreach ($this->_config as $k => $v) {
            if (is_string($v) && str_starts_with($v, 'env:')) {
                $v = getenv(substr($v, 4)) ?? null;
            }
            $this->_config[$k] = $v;
        }

        return $this;
    }

/**
 * __call.
 *
 * Using virtual methods as simple setter/getter for
 * config parameters.
 *
 * ## Example
 * $this->myConfigParam(true)->myOtherConfigParam('nice_option');
 *
 * echo $this->myConfigParam(); // true
 * echo $this->myOtherConfigParam(); // 'nice_option'
 *
 * @param string $name Method name
 * @param array $args Method arguments
 * @return mixed
 * @throws \BadMethodCallException
 */
    public function __call($name, array $args) {
        if (count($args) < 2 && in_array($name, array_keys($this->_defaultConfig))) {
            if (empty($args)) {
                return $this->_config[$name];
            }

            $this->config($name, $args[0]);

            return $this;
        }

        throw new BadMethodCallException(sprintf("Method '%s' doesn't exist in '%s'.", $name, get_class($this)));
    }

/**
 * Get route url.
 *
 * @see \Nata\Routing\Router::url()
 * @return string Url
 */
    public function getRouteUrl($url = null, $full = false) {
        return Router::url($url, $full);
    }

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @param \Nata\Event\Manager $eventManager Event manager instance
 * @return \Nata\Event\Manager
 */
    public function eventManager($eventManager = null) {
        if ($eventManager === null) {
            if ($this->_eventManager === null) {
                $this->_eventManager = new Manager();
                $this->_eventManager->on($this);
            }
            return $this->_eventManager;
        }

        $this->_eventManager = $eventManager;
        return $this;
    }

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {}

/**
 * Wrapper for creating and dispatching events.
 * Returns a dispatched event.
 *
 * @param string $event Event name
 * @param array $data Passed event data
 * @return \Nata\Event\Event
 */
    public function dispatchEvent($event, $data = [], $subject = null) {
        if ($subject === null) {
            $subject = $this;
        }
        return $subject->eventManager()->dispatch(new Event($event, $subject, $data));
    }

/**
 * Calls a method on this object with the given parameters.
 * Provides an OO wrapper for `call_user_func_array`
 *
 * @param string $method Name of the method to call
 * @param array $params Parameter list to use when calling $method
 * @return mixed Returns the result of the method call
 */
    public function dispatchMethod($method, $params = []) {
        switch (count($params)) {
            case 0:
                return $this->{$method}();
            case 1:
                return $this->{$method}($params[0]);
            case 2:
                return $this->{$method}($params[0], $params[1]);
            case 3:
                return $this->{$method}($params[0], $params[1], $params[2]);
            case 4:
                return $this->{$method}($params[0], $params[1], $params[2], $params[3]);
            case 5:
                return $this->{$method}($params[0], $params[1], $params[2], $params[3], $params[4]);
            default:
                return call_user_func_array([&$this, $method], $params);
        }
    }

/**
 * Stop execution of the current script. Wraps exit() making
 * testing easier.
 *
 * @param integer|string $status see http://php.net/exit for values
 */
    protected function _stop($status = 0) {
        exit($status);
    }

/**
 * Convenience method to write a message to Nata\Log\Log. See Nata\Log\Log::write()
 * for more information on writing to logs.
 *
 * @param string $msg Log message
 * @param integer $level Error level constant.
 * @return boolean Success of log write
 */
    public function log($msg, $level = LOG_ERR) {
        if (!is_string($msg)) {
            $msg = print_r($msg, true);
        }
        return Log::write($level, $msg);
    }

/**
 * Allows setting of multiple properties of the object in a single line of code. Will only set
 * properties that are part of a class declaration.
 *
 * @param array $properties An associative array containing properties and corresponding values.
 */
    protected function _set($properties = []) {
        if (!is_array($properties) || empty($properties)) {
            return false;
        }

        $vars = get_object_vars($this);
        foreach ($properties as $key => $val) {
            if (array_key_exists($key, $vars)) {
                $this->{$key} = $val;
            }
        }

    }

/**
 * Merges this objects $property with the property in $class' definition.
 * This classes value for the property will be merged on top of $class'
 *
 * This provides some of the DRY magic CakePHP provides. If you want to shut it off, redefine
 * this method as an empty function.
 *
 * @param array $properties The name of the properties to merge.
 * @param string $class The class to merge the property with.
 * @param boolean $normalize Set to true to run the properties through Hash::normalize() before merging.
 */
    protected function _mergeVars($properties, $class, $normalize = true) {
        $classProperties = get_class_vars($class);

        foreach ($properties as $var) {
            if (
                isset($classProperties[$var]) &&
                !empty($classProperties[$var]) &&
                is_array($this->{$var}) &&
                $this->{$var} != $classProperties[$var]
            ) {
                if ($normalize) {
                    $classProperties[$var] = Hash::normalize($classProperties[$var]);
                    $this->{$var} = Hash::normalize($this->{$var});
                }
                $this->{$var} = Hash::merge($classProperties[$var], $this->{$var});
            }
        }

    }

/**
 * Loads and returns models required.
 *
 * Short hand for TableRegistry::get().
 *
 * @param string $modelClass Name of model class to load
 * @return \Nata\ORM\Table The model instance created.
 */
    public function loadModel($modelClass) {
        [$p, $modelClass] = pluginSplit($modelClass, true);
        return TableRegistry::get($p . $modelClass);
    }

/**
 * Loads and instantiates models into this instance.
 * If the model is non existent, it will throw a missing database table error, as NataPHP generates
 * dynamic models for the time being.
 *
 * @param string $modelClass Name of model class to load
 * @param string $alias Model alias for property
 * @return \Nata\ORM\Table The model instance created.
 */
    public function loadModelIn(string $modelClass, string $propertyName = null): Table {
        if ($propertyName === null) {
            [$p, $propertyName] = pluginSplit($modelClass, true);
        }

        if (isset($this->{$propertyName})) {
            return $this->{$propertyName};
        }

        $this->{$propertyName} = TableRegistry::get($modelClass);

        return $this->{$propertyName};
    }

/**
 * Alias for loadModelIn.
 *
 * @deprecated Use loadModelIn instead.
 * @param string $modelClass Name of model class to load
 * @param string $alias Model alias for property
 * @return \Nata\ORM\Table The model instance created.
 */
    public function loadInModel(string $modelClass, string $propertyName = null): Table {
        return $this->loadModelIn($modelClass, $propertyName);
    }

/**
 * Add a service to the current instance's registry.
 *
 * This method will also set the service to a property.
 * For example:
 *
 * ```
 * $this->loadService('Shop.Order/Invoice');
 * ```
 *
 * If 'assignProperty' is true, will result in a `OrderInvoice` property being set.
 *
 * @param string $name The name of the service to load.
 * @param array $options Config/options for the service.
 * @return \Nata\Service\Service Service instance
 */
    public function loadService($name, array $options = []) {
        $serviceInstance = ServiceBuilder::build($name, $options);

        $options += [
            'assignProperty' => false
        ];

        if (!$options['assignProperty']) {
            return $serviceInstance;
        }

        list($p, $prop) = pluginSplit($name);

        $prop = str_replace('/', '', $prop);

        if (isset($this->{$prop})) {
            throw new Exception(sprintf('%s::$%s is already in use.', $this, $prop));
        }

        $this->{$prop} = $serviceInstance;

        return $serviceInstance;
    }

/**
 * Object-to-string conversion.
 * Each class can override this method as necessary.
 *
 * @return string The name of this class
 */
    public function __toString() {
        return get_class($this);
    }

}
