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
 * @copyright   Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link        http://nataphp.com NataPHP Project
 * @since       NataPHP 1.0.0
 * @license     http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Form;

use Nata\ORM\Entity;
use Nata\Core\NataObject;
use Nata\Collection\Collection;
use ArrayAccess;
use InvalidArgumentException;

/**
 * Data sets base class.
 * Extends functionality for form's data sets.
 */
class DataSet extends NataObject implements ArrayAccess {

/**
 * Holds all properties and
 * their values for this data set.
 *
 * @var array|object
 */
    protected $_data;

/**
 * Holds all properties and
 * their values for this data set.
 *
 * @var array
 */
    protected $_dirty = [];

/**
 * Holds all properties and
 * their values for this data set.
 *
 * @var array
 */
    protected $_original = [];


/**
 * Constructor.
 *
 * @param mixed $data Array of properties
 * @return void
 */
    public function __construct($data) {
        if (is_string($data) && strlen($data) > 0) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }
        $this->_data = $data;
    }

/**
 * Magic getter to access properties that have been set in this data set.
 *
 * @param string $property Name of the property to access
 * @return mixed
 */
    public function __get($property) {
        return $this->get($property);
    }

/**
 * Magic setter to add or edit a property in this data set.
 *
 * @param string $property The name of the property to set
 * @param mixed $value The value to set to the property
 * @return void
 */
    public function __set($property, $value) {
        $this->set($property, $value);
    }

/**
 * Returns whether this data set contains a property named $property
 * regardless of if it is empty.
 *
 * @param string $property The property to check.
 * @return bool
 * @see \Nata\ORM\Entity::has()
 */
    public function __isset($property) {
        return $this->has($property);
    }

/**
 * Removes a property from this data set.
 *
 * @param string $property The property to unset
 * @return void
 */
    public function __unset($property) {
        $this->unsetProperty($property);
    }

/**
 * Call method.
 *
 * @param string $name Method name
 * @param array $arguments Method arguments
 * @return mixed Method return
 */
    public function __call($name, array $arguments) {
        if (is_object($this->_data)) {
            return call_user_func_array(array($this->_data, $name), $arguments);
        }
    }

/**
 * Sets a single property inside this data set.
 *
 * @param string|array $property the name of property to set or a list of
 * properties with their respective values
 * @param mixed $value The value to set to the property or an array if the
 * first argument is also an array, in which case will be treated as $options
 * @return $this
 * @throws \InvalidArgumentException
 */
    public function set($property, $value = null, $translated = false) {
        // Format translations
        if ($translated === true && is_string($property) && $value) {
            $this->_setTranslations($property, $value);
            return $this;
        }

        if (!is_array($property) && (strlen($property) > 0 || is_bool($property))) {
            $property = [$property => $value];
            $value = null;
        }

        if (!is_array($property)) {
            throw new InvalidArgumentException('Cannot set an empty property');
        }

        if ($this->_data === null) {
            $this->_data = [];
        }

        foreach ($property as $name => $value) {
            // Handle dot separated named elements.
            if (strpos($name, '.') !== false) {
                $this->_setSplitted($name, $value);
                continue;
            }

            // Keep track of original property value
            if (isset($this->_data[$name]) && ($original = $this->_data[$name]) !== $value) {
                $this->_original[$name] = $original;
                $this->_dirty[$name] = true;
            }

            $this->_data[$name] = $value;
        }

        return $this;
    }

/**
 * Normalizes dot splitted name of property and value.
 * Maintains an \Nata\ORM\Entity instance if exists.
 *
 * @param string $name The dot splitted name of property to set or a list of
 * properties with their respective values
 * @param mixed $value The value to set to the property or an array if the
 * first argument is also an array, in which case will be treated as $options
 * @return array Property's root name and value
 */
    protected function _setSplitted($name, $value) {
        $dottedName = $name;
        // Set base property as name
        [$name] = $parts = explode('.', $name);

        // If trying to change the primary key, clear all associated data with it in model.
        // This is to prevent switching data from another record to another primary key.
        // IMPORTANT: It will clear ALL data after primary key change attempt.
        if (end($parts) === 'id') {
            // Clear all entity data if ID changes
            $currentPrimaryKey = $this->_get($this->_data, $dottedName);

            if (!empty($currentPrimaryKey) && $value != $currentPrimaryKey) {
                array_pop($parts);
                $this->_data = $this->_insert($this->_data, implode('.', $parts), [], $dottedName);
            }

            $value = (int)$value;
        }

        $this->_data = $this->_insert($this->_data, $dottedName, $value);
    }

/**
 * Normalizes dot splitted name of property and value.
 * Maintains an \Nata\ORM\Entity instance if exists.
 *
 * @param string $name The dot splitted name of property to set or a list of
 * properties with their respective values
 * @param mixed $value The value to set to the property or an array if the
 * first argument is also an array, in which case will be treated as $options
 * @return array Property's root name and value
 */
    protected function _setTranslations($property, $translations) {
        foreach ($translations as $lang => $text) {
            if ($this->_data instanceof Entity) {
                $this->_data->translation($lang)->set($property, $text);
                continue;
            }

            $this->_data['_translations'][$property][$lang] = $text;
        }
    }

/**
 * Insert $values into an array with the given $path.
 *
 * @param array $data The data to insert into.
 * @param string $path The path to insert at.
 * @param array $values The values to insert.
 * @param string $parentPath Parent path.
 * @return array The data with $values inserted.
 */
    protected function _insert($data, $path, $value, $parentPath = null) {
        if ($data instanceof Collection) {
            $data = $data->toArray();
        }

        if (empty($data)) {
            $data = [];
        }

        [$name] = $parts = explode('.', $path);

        // Has sub level?
        if (isset($parts[1])) {
            unset($parts[0]);

            if (!isset($data[$name])) {
                $data[$name] = [];
            }

            $data[$name] = $this->_insert($data[$name], implode('.', $parts), $value, $path);
        } else {
            // Workaround for when a string value is passed for a dotted name element
            // It will convert to array and place the error reference
            if (is_string($data) && strpos($parentPath, '.') !== false) {
                list($mainPath) = explode('.', $parentPath);
                $data = [
                    '_formerror' => sprintf('Expecting array for "%s", got a string ("%s")', $mainPath, $data)
                ];
            }

            $data[$name] = $value;
        }

        return $data;
    }

/**
 * Returns the value of a property by name.
 *
 * @param string $property the name of the property to retrieve
 * @return mixed
 * @throws \InvalidArgumentException if an empty property name is passed
 */
    public function get($property) {
        if (!strlen((string)$property)) {
            throw new InvalidArgumentException('Cannot get an empty property');
        }
        return $this->_get($this->_data, $property);
    }

/**
 * Get value in array in given dotted path.
 *
 * @param array $data The data to get from.
 * @param string $path The path to get from.
 * @return array The value in given path.
 */
    protected function _get($data, $path) {
        if ($data instanceof Collection) {
            $data = $data->toArray();
        }

        $value = null;

        $parts = explode('.', $path);

        // Don't get value if it's a hidden property
        if ($data instanceof Entity && in_array($parts[0], $data->hiddenProperties())) {
            return $value;
        }

        if (isset($parts[0]) && strlen($parts[0]) > 0 && isset($data[$parts[0]])) {
            $value = $data[$parts[0]];
            if (isset($parts[1])) {
                array_shift($parts);
                $value = $this->_get($value, implode('.', $parts));
            }
        }

        return $value;
    }

/**
 * Returns the value of an original property by name.
 *
 * @param string $property the name of the property for which original value is retrieved.
 * @return mixed
 * @throws \InvalidArgumentException if an empty property name is passed.
 */
    public function original($property = null) {
        if ($property === null) {
            return $this->_original;
        }

        if (!strlen((string)$property)) {
            throw new InvalidArgumentException('Cannot get an empty property');
        }

        if (isset($this->_original[$property])) {
            return $this->_original[$property];
        }

        return $this->get($property);
    }

/**
 * Sets the dirty status of a single property. If called with no second
 * argument, it will return whether the property was modified or not
 * after the object creation.
 *
 * When called with no arguments it will return whether or not there are any
 * dirty property in the entity
 *
 * @param string $property the field to set or check status for
 * @return bool whether the property was changed or not
 */
    public function dirty($property = null) {
        if ($property === null) {
            return !empty($this->_dirty);
        }
        return isset($this->_dirty[$property]);
    }

/**
 * This method is very similar to dirty(), but it will check
 * if property value changed to a different value.
 *
 * @param string $property the field to set or check status for
 * @param mixed $to Value to which it changed to
 * @param mixed $from Value to which it changed from
 * @return bool whether the property was changed or not
 */
    public function changed($property = null, $to = null, $from = null) {
        if (func_num_args() > 0) {
            $changed = $this->original($property) !== $this->get($property);
            switch (func_num_args()) {
                case 2:
                    return $changed && $this->get($property) === $to;
                case 3:
                    return $changed && $this->get($property) === $to && $this->original($property) === $from;
            }
            return $changed;
        }

        foreach ($this->extract($this->visibleProperties(), true) as $property) {
            if ($this->changed($property)) {
                return true;
            }
        }

    }

/**
 * Split dotted property.
 *
 * @param string $property Property name
 * @return array Splitted property name
 */
    protected function _splitProperty($property) {
        if (strpos($property, '.') === false) {
            return array($property, null);
        }
        return explode('.', $property);
    }

/**
 * Returns whether this data set contains a property named $property
 * regardless of if it is empty.
 *
 * When checking multiple properties. All properties must not be null
 * in order for true to be returned.
 *
 * @param string|array $property The property or properties to check.
 * @return bool
 */
    public function has($property) {
        foreach ((array)$property as $prop) {
            if ($this->get($prop) === null) {
                return false;
            }
        }
        return true;
    }

/**
 * Returns whether this data set is empty.
 *
 * When passing on the property is checks if property is empty.
 *
 * @param string|array $property The property or properties to check.
 * @return bool
 */
    public function isEmpty($property = null) {
        if ($property === null) {
            return empty($this->_data);
        }

        $value = $this->get($property);

        if (!is_array($value)) {
            return strlen($value) > 0 ? false : empty($value);
        }

        return empty($value);
    }

/**
 * Removes a property or list of properties from this data set.
 *
 * ### Examples:
 *
 * ```
 * $data->unsetProperty('name');
 * $data->unsetProperty(['name', 'last_name']);
 * ```
 *
 * @param string|array $property The property to unset.
 * @return $this
 */
    public function unsetProperty($property) {
        $property = (array)$property;
        foreach ($property as $p) {
            unset($this->_data[$p]);
        }
        return $this;
    }

/**
 * Get data source.
 *
 * @return mixed Original data source
 */
    public function extract() {
        return $this->_data;
    }

/**
 * To array.
 *
 * @return array Array with data
 */
    public function toArray() {
        if ($this->_data instanceof Entity) {
            return $this->_data->toArray();
        }
        return $this->_data;
    }

/**
 * Implements isset($data);
 *
 * @param mixed $offset The offset to check.
 * @return bool Success
 */
    public function offsetExists($offset): bool {
        return $this->has($offset);
    }

/**
 * Implements $data[$offset];
 *
 * @param mixed $offset The offset to get.
 * @return mixed
 */
    public function &offsetGet($offset): mixed {
        return $this->get($offset);
    }

/**
 * Implements $data[$offset] = $value;
 *
 * @param mixed $offset The offset to set.
 * @param mixed $value The value to set.
 * @return void
 */
    public function offsetSet($offset, $value): void {
        $this->set($offset, $value);
    }

/**
 * Implements unset($result[$offset);
 *
 * @param mixed $offset The offset to remove.
 * @return void
 */
    public function offsetUnset($offset): void {
        $this->unsetProperty($offset);
    }

/**
 * Returns a string representation of this object in a human readable format.
 *
 * @return string
 */
    public function __toString() {
        return json_encode($this->_data, JSON_PRETTY_PRINT);
    }

}