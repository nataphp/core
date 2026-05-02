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
use Nata\Utility\Hash;

/**
 * Select element.
 */
class Select extends Element {

/**
 * Multiple options.
 *
 * @var bool
 */
    protected $_multiple = false;

/**
 * Enable jQuery chosen plugin.
 *
 * @var bool
 */
    protected $_enableChosen = false;

/**
 * Message to show when there's no options.
 *
 * @var string
 */
    protected $_noOptionsMessage;

/**
 * Placeholder option.
 * Defaults to '-- Select --'
 *
 * @var array
 */
    protected $_placeholder;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'multiple' => null,
            'noOptionsMessage' => null,
            'enableChosen' => false,
            'placeholder' => null
        );

        if ($config['multiple'] !== null) {
            $this->multiple($config['multiple']);
        }

        if ($config['noOptionsMessage']) {
            $this->_noOptionsMessage = $config['noOptionsMessage'];
        }

        if ($config['enableChosen']) {
            $this->enableChosen($config['enableChosen']);
        }

        if ($config['placeholder']) {
            $this->_placeholder = $config['placeholder'];
        }

        // Remove empty values
        if ($this->_multiple && $this->_data->request()) {
            $data = $this->_data->request();
            $data = array_map('trim', $data);
            $data = Hash::filter($data);
            $data = array_values($data);
            $this->_data->request($data);
            unset($data);
        }

    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $id = $this->id();
        $name = $this->_flattenData === true ? $this->name() : $id;

        if ($this->readOnly()) {
            $id[] = 'readonly';
        }

        $attr = $this->attributes()
            ->addClass('form-control')
            ->id($id)
            ->multipleValues($this->_multiple)
            ->set('disabled', ($this->disabled() || $this->readOnly() ? true : null))
            ->set('multiple', ($this->_multiple ? true : null))
            ->name($name);

        if (!$this->_multiple && $this->placeholder()) {
            $this->options()->load($this->_placeholderOption());
        }

        if ($this->_enableChosen) {
            $placeholder = $this->placeholder();
            if ($placeholder) {
               $attr->set('data-placeholder', $placeholder);
            }
            $attr->addClass('form-select-chosen');
        }

        $this->_setPlaceholderWithLabel();
    }

/**
 * If placeholder exists, create placeholder option.
 *
 * @return array
 */
    private function _placeholderOption() {
        $placeholderOption = null;
        $placeholder = $this->placeholder();

        if ($placeholder) {
            return [
                'prepend' => true,
                'label' => $placeholder,
                'value' => ' '
            ];
        }

        return $placeholderOption;
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
 * Get/set jQuery Chosen plugin.
 *
 * @param bool $value Element data
 * @return $this|bool
 * @link https://harvesthq.github.io/chosen/
 */
    public function enableChosen($enableChosen = null) {
        if ($enableChosen === null) {
            return $this->_enableChosen;
        }
        $this->_enableChosen = $enableChosen;
        return $this;
    }

/**
 * Get/set message for when there's no options to show.
 *
 * @param string $noOptionsMessage Message
 * @return $this|string
 */
    public function noOptionsMessage($noOptionsMessage = null) {
        if ($noOptionsMessage === null) {
            if ($this->_noOptionsMessage === null) {
                $this->_noOptionsMessage = __('No options');
            }
            return $this->_noOptionsMessage;
        }
        $this->_noOptionsMessage = $noOptionsMessage;
        return $this;
    }

/**
 * Get/set message for when there's no options to show.
 *
 * @param string $noOptionsMessage Message
 * @return $this|string
 */
    public function placeholder($placeholder = null) {
        if ($placeholder === null) {
            if ($this->_labelAsPlaceholder) {
                $label = $this->_label;
                if ($label && $this->_required) {
                    $label .= ' *';
                }
                return $label;
            }
            return $this->_placeholder;
        }
        $this->_placeholder = $placeholder;
        return $this;
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        $values = $this->value();

        if ($values) {
            $values = (array)$values;
            $options = $this->options();
            $text = array();

            foreach ($values as $value) {
                if ($options->has($value)) {
                    $text[] = $options->get($value)->label();
                }
            }

            return implode(', ', $text);
        }

    }

}
