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

/**
 * Input element.
 */
class Input extends Element {

/**
 * Prepend HTML to input.
 *
 * @var string
 */
    protected $_prepend;

/**
 * Append HTML to input.
 *
 * @var string
 */
    protected $_append;

/**
 * Multiple values.
 *
 * @var bool
 */
    protected $_multiple = false;

/**
 * Prepend HTML button to input.
 *
 * @var string
 */
    protected $_prependButton;

/**
 * Append HTML button to input.
 *
 * @var string
 */
    protected $_appendButton;

/**
 * URL to clear input (navigates to this URL, e.g. form without filters).
 * When set, an optional X link is rendered to clear/reset.
 *
 * @var string|null
 */
    protected $_clearUrl;

/**
 * Preset, named regular expressions.
 *
 * @var array
 */
    protected $_namedRegex = [
        'alphanumeric' => '/^[\p{L}\p{N}\040\.-]+$/iu',
        'name' => '/^[\p{L}\040\.-]+$/iu'
    ];

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
        $config += [
            'prepend' => null,
            'append' => null,
            'multiple' => null,
            'prependButton' => null,
            'appendButton' => null,
            'clearUrl' => null,
            'regex' => null,
            'placeholder' => null
        ];

        if ($config['prepend'] !== null) {
            $this->prepend($config['prepend']);
        }

        if ($config['append'] !== null) {
            $this->append($config['append']);
        }

        if ($config['prependButton'] !== null) {
            $this->prependButton($config['prependButton']);
        }

        if ($config['appendButton'] !== null) {
            $this->appendButton($config['appendButton']);
        }

        if ($config['clearUrl'] !== null) {
            $this->clearUrl($config['clearUrl']);
        }

        if ($config['multiple'] !== null) {
            $this->multiple($config['multiple']);
        }

        if ($config['placeholder'] !== null) {
            $this->placeholder($config['placeholder']);
        }

        parent::initialize($config);
    }

/**
 * Get value output sanitized to be presented on HTML view/template.
 *
 * @param array $filter Filter options
 * @return string Value output sanitized
 */
    public function getValueOutput(array $filter = []) {
        $value = $this->value();

        $maxlength = $this->_maxLength;
        if ($this->_length) {
            $maxlength = $this->_length;
        }

        if ($maxlength && !empty($value)) {
            $value = substr($value, 0, $maxlength);
        }
        return parent::_sanitizer($value, $filter);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $attrs = $this->attributes();
        $id = $this->id();
        $name = $this->_flattenData === true ? $this->name() : $id;
        $maxlength = $this->_maxLength;

        $attrs
            ->set('multiple', ($this->_multiple ? true : null))
            ->set('readonly', ($this->readOnly() ? true : null))
            ->set('disabled', ($this->disabled() ? true : null))
            ->set('maxlength', $maxlength)
            ->set('value', $this->getValueOutput())
            ->name($name);

        // Add default class if none set
        $class = $attrs->get('class');
        if (empty($class)) {
            $attrs->addClass('form-control');
        }

        $placeholder = $this->placeholder();
        if (!$this->attributes()->has('placeholder') && $placeholder) {
           $this->attributes()->set('placeholder', $placeholder);
        }

        $this->_setLengthDescription();
        $this->_setPlaceholderWithLabel();
    }

/**
 * Get/Set prepended HTML to input.
 *
 * @param string $prepend Html string
 * @return $this|string
 */
    protected function _setLengthDescription() {
        if ($this->_description !== null) {
            return;
        }

        if ($this->_length) {
            $this->_description = __('Length of %d characters', $this->_length);
        } elseif ($this->_minLength) {
            $this->_description = __('Minimum length of %d characters', $this->_minLength);
        } elseif ($this->_maxLength) {
            $this->_description = __('Maximum length of %d characters', $this->_maxLength);
        } elseif ($this->_minLength && $this->_maxLength) {
            $this->_description = __('Minimum of %d and maximum of %d characters', [$this->_minLength, $this->_maxLength]);
        }
    }

/**
 * Get/Set prepended HTML to input.
 *
 * @param string $prepend Html string
 * @return $this|string
 */
    public function prepend($prepend = null) {
        return $this->_addAddon('prepend', $prepend);
    }

/**
 * Get/Set appended HTML to input.
 *
 * @param string $append Html string
 * @return $this|string
 */
    public function append($append = null) {
        return $this->_addAddon('append', $append);
    }

/**
 * Get/Set prepend HTML button to input.
 *
 * @param string $prependButton Html string
 * @return $this|string
 */
    public function prependButton($prependButton = null) {
        return $this->_addAddon('prependButton', $prependButton);
    }

/**
 * Get/Set append HTML button to input.
 *
 * @param string $appendButton Html string
 * @return $this|string
 */
    public function appendButton($appendButton = null) {
        return $this->_addAddon('appendButton', $appendButton);
    }

/**
 * Get/Set URL to clear input (link to reset form/filters).
 *
 * @param string|null $clearUrl URL to navigate when clearing
 * @return $this|string|null
 */
    public function clearUrl($clearUrl = null) {
        return $this->_property('_clearUrl', $clearUrl);
    }

/**
 * Convert addons to array.
 *
 * @param string $property Property name
 * @param string $addon Addon to add
 * @return $this|string
 */
    protected function _addAddon($property, $addon) {
        if (!empty($addon) && !is_array($addon)) {
            $addon = [$addon];
        }
        return $this->_property('_' . $property, $addon);
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
 * Get/set placeholder.
 *
 * @param string $placeholder Placeholder
 * @return $this|string
 */
    public function placeholder($placeholder = null) {
        if ($placeholder === null) {
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
        return $this->value();
    }

}
