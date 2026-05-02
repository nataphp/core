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

use Iterator;
use Nata\Core\App;
use Nata\Utility\Inflector;

/**
 * Holds the common collection instances.
 */
class BaseCollection implements Iterator {

/**
 * Form instance.
 *
 * @var \Nata\Form\Form
 */
    protected $_form;

/**
 * Form instance.
 *
 * @var \Nata\Form\Form
 */
    protected $_container;

/**
 * ID map.
 *
 * @var array
 */
    protected $_id;

/**
 * Theme.
 *
 * @var string
 */
    protected $_theme;

/**
 * Layout.
 *
 * @var string
 */
    protected $_layout;

/**
 * Label as placeholder.
 *
 * @var boolean
 */
    protected $_labelAsPlaceholder;

/**
 * Column size.
 *
 * @var int
 */
    protected $_columnSize;

/**
 * Event Manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Behavior instance.
 *
 * @var \Nata\ORM\Behavior
 */
    protected $_translate;

/**
 * Translation data.
 *
 * @var array
 */
    protected $_translations;

/**
 * Form submittion state.
 *
 * @var bool
 */
    protected $_submitted = false;

/**
 * Flatten form data.
 *
 * @var bool
 */
    protected $_flattenData = false;

/**
 * Data manager instance.
 *
 * @var \Nata\Form\DataManager
 */
    protected $_dataManager;

/**
 * Elements as array.
 *
 * @var array
 */
    protected $_toArray;

/**
 * Validation result.
 *
 * @var bool
 */
    protected $_isValid;

/**
 * Map of loaded objects.
 *
 * @var array
 */
    protected $_loaded = [];

/**
 * Map of dotted loaded objects.
 *
 * @var array
 */
    protected $_map;

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [];

/**
 * Index.
 *
 * @var int
 */
    protected $_index = 0;


/**
 * Construct.
 *
 * @param object $instance Instance
 * @return void
 */
    public function __construct(Form $form, $instance = null) {
        $this->_form = $form;
        $this->_container = $instance;

        if ($instance === null) {
            $instance = $form;
        }

        $this->_id = $instance->id();
        $this->_theme = $instance->theme();
        $this->_layout = $instance->layout();
        $this->_columnSize = $instance->columnSize();
        $this->_labelAsPlaceholder = $instance->labelAsPlaceholder();
        $this->_eventManager = $instance->eventManager();
        $this->_submitted = $instance->submitted();
        $this->_dataManager = $instance->dataManager();
        if ($instance === null) {
            $this->_flattenData = $form->flattenData();
        }
    }

/**
 * Get default configuration.
 *
 * @return array Default configuration
 */
    protected function _defaultConfig() {
        return [
            'id' => $this->_id,
            'theme' => $this->_theme,
            'layout' => $this->_layout,
            'columnSize' => $this->_columnSize,
            'labelAsPlaceholder' => $this->_labelAsPlaceholder,
            'eventManager' => $this->_eventManager,
            'submitted' => $this->_submitted,
            'translate' => $this->_translate,
            'flattenData' => $this->_flattenData
        ] + $this->_defaultConfig;
    }

/**
 * Load multiple instances.
 *
 * @param array $configs Multiple configurations to load
 * @return array Loaded instances
 */
    public function loadAll(array $configs) {
        foreach ($configs as $config) {
            if (!empty($config)) {
                $this->load($config);
            }
        }
        return $this->_loaded;
    }

/**
 * Get loaded instance(s).
 * If given name of instance.
 *
 * ## Example
 *
 * // Get all instances
 * $this->get();
 *
 * // Get single instance
 * $this->get('first_name');
 *
 * // Get single instance
 * $this->get('address.streetnumber');
 *
 * // Get all relative
 * $this->get('address.*');
 *
 * // Get by array of instance's names
 * $this->get(['first_name', 'last_name']);
 *
 * @param string|array $name Name(s) of loaded instance(s).
 * @return mixed Loaded instance(s)
 */
    public function get($name = null) {
        if ($name === null || $name === '*') {
            return $this->_loaded;
        } elseif (is_string($name)) {
            if (strpos($name, '.*') !== false) {
                return $this->_map($name);
            }
            return $this->has($name) ? $this->_loaded[$name] : null;
        }

        return array_intersect_key($this->_loaded, array_flip($name));
    }

/**
 * Extract dot named instances.
 *
 * @param string $name Name.
 * @return array
 */
    protected function _map($name) {
        $name = rtrim($name, '.*');
        $instances = [];
        foreach ($this->_loaded as $_name => $element) {
            if (strpos($_name, $name) === 0) {
                $instances[$_name] = $element;
            }
        }
        return $instances;
    }

/**
 * Get \Nata\Form\Group instance.
 *
 * @param mixed $instance Group name.
 * @return \Nata\Form\Group
 */
    protected function _set($name, $instance) {
        $this->_loaded[$name] = $instance;
    }

/**
 * Check if element is loaded.
 *
 * @param string $name Classname alias.
 * @return bool True if has, fals otherwise.
 */
    public function has($name = null) {
        if ($name === null) {
            return !empty($this->_loaded);
        }
        return isset($this->_loaded[$name]);
    }

/**
 * List of loaded instances names.
 *
 * @return array Loaded instances
 */
    public function loaded() {
        return array_keys($this->_loaded);
    }

/**
 * Check if given element is loaded.
 *
 * @param string $name Element name to check
 * @return bool True if exists, false otherwise
 */
    public function isLoaded($name) {
        return isset($this->_loaded[$name]);
    }

/**
 * Number of loaded instances.
 *
 * @return int Number of loaded instances
 */
    public function count() {
        return count($this->_loaded);
    }

/**
 * Remove loaded instances.
 *
 * @param string|array $names Instances alias
 * @return $this
 */
    public function unload($names) {
        if (!is_array($names)) {
            $names = array($names);
        }
        foreach ($names as $name) {
            if ($this->has($name)) {
                unset($this->_loaded[$name]);
            }
        }
        return $this;
    }

/**
 * @deprecated Use unload() instead
 */
    public function remove($names) {
        return $this->unload($names);
    }

/**
 * Clear all loaded instances.
 *
 * @return $this
 */
    public function clear() {
        $this->_loaded = [];
        return $this;
    }

/**
 * Check empty instances.
 *
 * @return bool True if empty
 */
    public function isEmpty() {
        $empty = true;
        foreach ($this->_loaded as $instance) {
            if ($this instanceof ElementCollection && $instance && ($instance->readOnly() || $instance->disabled())) {
                continue;
            }
            if (!$instance->isEmpty() && $empty) {
                $empty = false;
                break;
            }
        }
        return $empty;
    }

/**
 * Check if instance data is valid.
 *
 * @return bool True if valid, false otherwise
 */
    public function isValid() {
        if ($this->_isValid === null) {
            $valid = true;

            foreach ($this->_loaded as $index => $instance) {
                $isValid = $instance->isValid();

                if (!$isValid && $valid) {
                    $valid = false;
                }

                if ($isValid) {
                    if ($this instanceof ElementCollection && $instance) {
                        $this->_setValidValue($instance, $index);
                    } else {
                        $this->_dataManager->patch($index, $instance->data());
                    }
                }

                $this->_toArray[$instance->attrs()->id()] = $instance->toArray();
            }

            $this->_isValid = $valid;
        }

        return $this->_isValid;
    }

/**
 * Check if request data is valid, if so, passes it to the model data.
 *
 * @param \Nata\Form\Element $element Element instance
 * @param string $index Element collection index
 * @return void
 */
    protected function _setValidValue($element, $index) {
        if (!$element->isValid()) {
            return;
        }

        $requestData = $element->data()->request();
        $modelData = $element->data()->model();
        $defaultValue = $element->defaultValue();

        // If disabled/read only and entity/model is empty (possibly new record)
        // set default value in the input as model/entity property's value.
        if ($element->disabled() || $element->readOnly()) {
            if ($element->isEmpty($modelData) && !$element->isEmpty($defaultValue)) {
                $this->_dataManager->model()->set($index, $defaultValue);
            }
        } else {
            if ($element->isEmpty($requestData) && $element->isEmpty($modelData)) {
                $requestData = $defaultValue;
            }

            if (method_exists($element, '_multiple') && $element->multiple() && $element->isEmpty($requestData)) {
                $requestData = [];
            }

            if (($element->translate() && $this->_dataManager->model()->isNew()) || $requestData != $modelData) {
                $this->_dataManager->model()->set($index, $requestData, $element->translate());
            }

        }

    }

/**
 * Resolve a form lib classname.
 *
 * @param string $class Partial classname to resolve.
 * @return string|false Either the correct classname or false.
 */
    protected function _resolveClassName($class, $package, $default = true) {
        $className = null;

        if ($class) {
            $class = Inflector::camelize($class);
            $className = App::className($class, 'Form/' . $package);
        }

        if (!$className && $default) {
            $className = '\Nata\Form\\' . $package;
        }

        return $className;
    }

/**
 * Get instance data as array.
 *
 * @return array Instance data as array
 */
    public function toArray() {
        if ($this->_toArray === null) {
            foreach ($this->_loaded as $index => $instance) {
                $this->_toArray[$instance->attrs()->id()] = $instance->toArray();
            }
        }
        return $this->_toArray;
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function rewind(): void {
        $this->_index = 0;
    }

/**
 * \Iterator implementation.
 *
 * @return bool
 */
    public function valid(): bool {
        $loaded = array_values($this->_loaded);
        return isset($loaded[$this->_index]);
    }

/**
 * \Iterator implementation.
 *
 * @return int
 */
    public function key(): int {
        return $this->_index;
    }

/**
 * \Iterator implementation.
 *
 * @return Address
 */
    public function current(): mixed {
        $loaded = array_values($this->_loaded);
        return $loaded[$this->_index];
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function next(): void {
        $this->_index++;
    }

/**
 * Render loaded elements.
 *
 * @return string
 */
    public function render() {
        $html = '';
        foreach ($this->_loaded as $instance) {
            $html .= $instance->render();
        }
        return $html;
    }

}