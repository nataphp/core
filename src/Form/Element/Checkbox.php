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

namespace Nata\Form\Element;

use Nata\Form\Element;
use Closure;

/**
 * Checkbox element.
 */
class Checkbox extends Element {

/**
 * Inline options.
 *
 * @var bool
 */
    protected $_inline = false;

/**
 * Multiple options.
 *
 * @var bool
 */
    protected $_multiple = true;


/**
 * Pseudo-constructor.
 * This method is called after user and defaults are setup in class.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'inline' => null,
            'multiple' => null
        );

        if ($config['inline'] !== null) {
            $this->_inline = $config['inline'];
        }

        if ($config['multiple'] !== null) {
            $this->multiple($config['multiple']);
        }

        // This is required until ORM can save 'null' value
        if ($this->_data->submitted()) {
            /*
             * A single checkbox is rendered as a hidden companion carrying 0
             * followed by the box itself carrying 1, so a client that collects
             * repeated keys into an array -- which is what nata.form.js did
             * before the fix in _appendNestedValue(), and what a browser still
             * holding that bundle in cache keeps doing -- submits both.
             *
             * Collapsed HERE, on the request, rather than only where it is read:
             * the value goes on to be validated and saved, and an array reaching
             * an integer column is a write nobody can make sense of. The last
             * one is the state of the box, which is what PHP would itself have
             * kept from a plain form post.
             */
            $submitted = $this->_data->request();
            if (!$this->_multiple && is_array($submitted)) {
                $this->_data->request($submitted === [] ? '' : end($submitted));
            }

            $value = $this->value();

            if ($this->isEmpty($value) && !$this->_multiple) {
                $this->_data->request(0);
            }

        }

    }

/**
 * Check if data is empty.
 *
 * @param string|array $data Data to check if it's empty
 * @return boolean True if is empty, false otherwise
 */
    public function isEmpty($data = null) {
        if (!$this->_multiple && !($this->_empty instanceof Closure)) {
            $data = $this->_getData($data);
        }

        if (!$this->_multiple) {
            // Composites are collapsed on the request in initialize(); this
            // only has to survive one arriving from somewhere else.
            if (is_array($data)) {
                $data = $data === [] ? '' : end($data);
            }

            $data = trim((string)$data);
        }

        return parent::isEmpty($data);
    }

/**
 * Get/set inline option.
 *
 * @param bool $inline Checkbox inline
 * @return void
 */
    public function inline($inline = null) {
        return $this->_property('_inline', $inline);
    }

/**
 * Get/Set accept multiple values.
 *
 * @param bool $multiple Multiple values
 * @return $this|bool
 */
    public function multiple($multiple = null) {
        if ($multiple === null) {
            return $this->_multiple;
        }

        $this->_multiple = $multiple;

        return $this;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $id = $this->id();
        $name = $this->_flattenData === true ? $this->name() : $id;
        $this->attributes()
            ->id($id)
            ->multipleValues($this->_multiple)
            ->name($name);
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        $value = $this->value();

        if ($value) {
            $options = $this->options();
            if ($options->has($value)) {
                return $options->get($value)->label();
            }
        }

    }

}
