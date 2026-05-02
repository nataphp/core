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

use Nata\Form\Attributes;

/**
 * Holds the form's collection of elements.
 */
class Option {

/**
 * Option label.
 *
 * @var string
 */
    protected $_label;

/**
 * Option value.
 *
 * @var string
 */
    protected $_value;

/**
 * Option disabled.
 *
 * @var bool
 */
    protected $_disabled = false;

/**
 * Option selected.
 *
 * @var bool
 */
    protected $_selected = false;

/**
 * Option description.
 *
 * @var string
 */
    protected $_description;

/**
 * Option ID.
 *
 * @var string
 */
    protected $_id;

/**
 * List of HTML attributes.
 *
 * @var array
 */
    protected $_attributes;

/**
 * Label's icon.
 *
 * @var string
 */
    protected $_icon;


/**
 * Construct.
 *
 * @param \Nata\Form\Form $form Form instance
 * @return void
 */
    public function __construct($config = array()) {
        $this->_label = $config['label'];
        $this->_value = $config['value'];

        if ($config['disabled']) {
            $this->disabled($config['disabled']);
        }

        if ($config['checked']) {
            $this->selected($config['checked']);
        }

        if ($config['selected']) {
            $this->selected($config['selected']);
        }

        if ($config['description']) {
            $this->description($config['description']);
        }

        if ($config['id']) {
            $this->id($config['id']);
        }

        if ($config['attrs']) {
            $this->attributes()->set($config['attrs']);
        }

        if ($config['icon'] !== null) {
            $this->_icon = $config['icon'];
        }
    }

/**
 * Get/set element's attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attributes() {
        if ($this->_attributes === null) {
            $this->_attributes = new Attributes($this);
        }
        return $this->_attributes;
    }

/**
 * Get/set option label.
 *
 * @param string $label Option label
 * @return $this|string
 */
    public function label($label = null) {
        return $this->_property('label', $label);
    }

/**
 * Get/set label icon.
 *
 * @param string $icon Option label
 * @return $this|string
 */
    public function icon($icon = null) {
        return $this->_property('icon', $icon);
    }

/**
 * Get/set option value.
 *
 * @param string $value Option value
 * @return $this|string
 */
    public function value($value = null) {
        return $this->_property('value', $value);
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function id($id = null) {
        return $this->_property('id', $id);
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function disabled($disabled = null) {
        return $this->_property('disabled', $disabled);
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function selected($selected = null) {
        return $this->_property('selected', $selected);
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function checked($checked = null) {
        return $this->selected($checked);
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function description($description = null) {
        return $this->_property('description', $description);
    }

/**
 * Get/set property.
 *
 * @param string $property Property name
 * @param mixed $value Property value
 * @return $this|mixed
 */
    private function _property($property, $value = null) {
        $var = '_' . $property;
        if ($value === null) {
            return $this->{$var};
        }
        $this->{$var} = $value;
        return $this;
    }

/**
 * Get/set option id.
 *
 * @param string $id Option id
 * @return $this|string
 */
    public function toArray() {
        return array(
            'label' => $this->_label,
            'value' => $this->_value,
            'disabled' => $this->_disabled,
            'checked' => $this->_selected,
            'selected' => $this->_selected,
            'description' => $this->_description,
            'icon' => $this->_icon,
            'id' => $this->_id
        );
    }

}