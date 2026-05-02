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

use Nata\Utility\Inflector;
use Nata\Utility\Html;
use FormException;

/**
 * Allows to manage HTML attributes.
 */
class Attributes {

/**
 * Instance.
 *
 * @var mixed
 */
    protected $_instance;

/**
 * Append '[]' to 'name' attribute.
 *
 * @var bool
 */
    protected $_multipleValues;

/**
 * Hidden attributes.
 *
 * The attributes in this list won't be rendered.
 *
 * @var array
 */
    protected $_hidden = [];

/**
 * Attributes.
 *
 * @var array
 */
    protected $_attrs = [
        'id' => null,
        'class' => [],
        'data' => []
    ];


/**
 * Construct.
 *
 * @param array $attributes Attributes
 * @return void
 */
    public function __construct($instance) {
        $this->_instance = $instance;
        if (method_exists($this->_instance, 'id')) {
            $this->id($this->_instance->id());
        }
    }

/**
 * Parse 'id' attribute.
 *
 * @param array|string $id Id attribute
 * @return $this|string
 */
    public function id($id = null) {
        if ($id === null) {
            return $this->_attrs['id'];
        }

        if (is_array($id)) {
            $id = array_map(function ($string) {
                if (str_contains($string, '__')) {
                    return $string;
                }
                return Inflector::underscore($string);
            }, $id);

            $id = implode('-', $id);
        }

        $this->set('id', $id);

        return $this;
    }

/**
 * Get/set multiple values.
 * If set to true, appends '[]' to 'name' attribute so that
 * allows multiple values.
 *
 * @param bool $multipleValues Allow multiple values
 * @return $this|bool
 */
    public function multipleValues($multipleValues = null) {
        if ($multipleValues === null) {
            return $this->_multipleValues;
        }
        $this->_multipleValues = $multipleValues;
        return $this;
    }

/**
 * Parse valid 'name' attribute.
 *
 * @param array|string $name Name attribute
 * @return $this|string
 */
    public function name($name = null) {
        if ($name === null) {
            return $this->has('name') ? $this->_attrs['name'] : null;
        }
        $name = $this->_normalizeName($name);
        $this->set('name', $name);
        return $this;
    }

/**
 * Normalize name attribute.
 *
 * @param string|array $name Attributes list or name
 * @return string Name attribute
 */
    private function _normalizeName($name) {
        if (is_array($name)) {
            list($base) = $name = (array)$name;
            array_shift($name);
            $name = $base . '[' . implode('][', $name) . ']';
        }

        if (!empty($name) && $this->_multipleValues) {
            $name .= '[]';
        }

        return $name;
    }

/**
 * Set attribute.
 *
 * @param string|array $name Attributes list or name
 * @param string $value Attribute value
 * @return $this
 */
    public function set($name, $value = null) {
        if (!is_array($name)) {
            $name = [$name => $value];
        }

        if (isset($name['class'])) {
            $name['class'] = $this->_normalizeClass($name['class']);
        }

        $this->_attrs = array_merge($this->_attrs, $name);

        return $this;
    }

/**
 * Normalize class's attribute value.
 *
 * @param string|array $class Classes
 * @return array
 */
    private function _normalizeClass($class) {
        if (is_string($class)) {
            $class = explode(' ', $class);
        }
        return $class;
    }

/**
 * Get attribute.
 *
 * @param string $name Attribute name to get
 * @return array|string
 */
    public function get($name = null) {
        if ($name === null) {
            return $this->_attrs;
        }
        return ($this->has($name) ? $this->_attrs[$name] : null);
    }

/**
 * Remove attribute.
 *
 * @param string $name Attribute name to remove
 * @return $this
 */
    public function remove($name) {
        unset($this->_attrs[$name]);
        return $this;
    }

/**
 * Check if attribute exists.
 *
 * @param string $name Attribute lis or name to get
 * @return bool True if exists, false otherwise
 */
    public function has($name) {
        return isset($this->_attrs[$name]);
    }

/**
 * Add class.
 *
 * @param string $class Class name
 * @return $this
 */
    public function addClass($class) {
        $this->_attrs['class'] = array_unique(
            array_merge($this->_attrs['class'], explode(' ', $class))
        );
        return $this;
    }

/**
 * Get class.
 *
 * @param string $default Default class
 * @return string
 */
    public function getClass(string $default = ''): string {
        return $this->_attrs['class'] ? implode(' ', $this->_attrs['class']) : $default;
    }

/**
 * Consume class.
 *
 * @param string $default Default class
 * @return string
 */
    public function consumeClass(string $default = ''): ?string {
        $class = $this->getClass($default);
        $this->_attrs['class'] = [];
        return $class;
    }

/**
 * Remove class.
 *
 * @param string $class Class name
 * @return $this
 */
    public function removeClass($class) {
        if ($this->hasClass($class)) {
            $key = array_search($class, $this->_attrs['class']);
            unset($this->_attrs['class'][$key]);
        }
        return $this;
    }

/**
 * Has class.
 *
 * @param string $class Class name
 * @return $this
 */
    public function hasClass($class) {
        return in_array($class, $this->_attrs['class']);
    }

/**
 * Get/set data attributes.
 *
 * @param string|array $data Data attribute key
 * @param mixed $value Data attribute value
 * @return $this|array
 */
    public function data($data = null, $value = null) {
        if ($data === null) {
            return $this->_attrs['data'];
        }

        if (func_num_args() === 1 && is_string($data)) {
            return $this->hasData($data) ? $this->get('data.' . $data) : null;
        }

        $this->_attrs['data'] = [];

        return $this->addData($data, $value);
    }

/**
 * Add data attribute-value.
 *
 * @param string|array $data Data attribute key
 * @param mixed $value Data attribute value
 * @return $this
 */
    public function addData($data, $value = null) {
        if (!is_array($data)) {
            $data = [$data => $value];
        }
        $this->_attrs['data'] = array_merge($this->_attrs['data'], $data);
        return $this;
    }

/**
 * Remove data attribute key.
 *
 * @param string $data Data attribute key to remove
 * @return $this
 */
    public function removeData($key) {
        if ($this->hasData($key)) {
            unset($this->_attrs['data'][$key]);
        }
        return $this;
    }

/**
 * Has data attribute key (and optionally, check key's value also).
 *
 * @param string $key Data attribute key to check
 * @param string $value Data attribute value to check
 * @return boolean True if exists, false otherwise
 */
    public function hasData($key, $value = null) {
        $exists = in_array($key, array_keys($this->_attrs['data']));
        if (func_num_args() === 1) {
            return $exists;
        }
        return in_array($value, array_values($this->_attrs['data'])) && $exists;
    }

/**
 * Get/set hidden attributes.
 *
 * @param string|array $attributes Hidden attributes
 * @return $this|array
 */
    public function hidden($attributes = null) {
        if ($attributes === null) {
            return $this->_hidden;
        }

        $this->_hidden = array_merge($this->_hidden, (array)$attributes);

        return $this;
    }

/**
 * Get the list of visible attributes.
 *
 * The list of visible attributes is all existing attributes minus hidden attributes.
 *
 * @param null|array $attributes Either an array of attributes to treat as visible or null to get attributes
 * @return $this|array Instance or list of attributes that are 'visible' in all
 *     representations.
 */
    public function visible($attributes = null) {
        if ($attributes === null) {
            return array_diff(array_keys($this->_attrs), $this->_hidden);
        }

        $this->_hidden = array_diff($this->_hidden, (array)$attributes);

        return $this;
    }

/**
 * Attributes array.
 *
 * @return array Visible attributes list
 */
    public function toArray() {
        $attrs = [];
        foreach ($this->visible() as $attrName) {
            if (!isset($this->_attrs[$attrName])) {
                continue;
            }
            $attrs[$attrName] = $this->_attrs[$attrName];
        }
        return $attrs;
    }

/**
 * Attributes to an HTML string.
 *
 * @return string Html attributes
 */
    public function toHtml() {
        return Html::attrs($this->toArray());
    }

/**
 * __toString
 *
 * @see Method Attributes::toHtml()
 */
    public function __toString() {
        return $this->toHtml();
    }

}
