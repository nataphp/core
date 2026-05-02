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

use Nata\I18n\I18n;
use Nata\Form\Element;
use Nata\Utility\Validation;

/**
 * Textarea element.
 */
class Textarea extends Element {

/**
 * Number of rows.
 *
 * @var int
 */
    protected $_rows = 3;

/**
 * Enable WYSIWYG editor.
 *
 * @var bool
 */
    protected $_editor = false;

/**
 * Allow URL's in textarea.
 *
 * @var bool
 */
    protected $_allowUrls = true;

/**
 * Textarea text/value.
 *
 * @var string
 */
    protected $_text;

/**
 * Translate-able field.
 *
 * @var bool
 */
    protected $_translate;

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
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'text' => null,
            'rows' => null,
            'editor' => null,
            'allowUrls' => null,
            'translate' => null,
            'prepend' => null,
            'append' => null,
            'prependButton' => null,
            'appendButton' => null
        );

        if ($config['rows']) {
            $this->_rows = $config['rows'];
        }

        if ($config['editor'] !== null) {
            $this->_editor = $config['editor'];
        }

        if ($config['translate'] !== null) {
            $this->_translate = $config['translate'];
        }

        if ($config['allowUrls'] !== null) {
            $this->_allowUrls = $config['allowUrls'];
        }

        if ($config['prepend']) {
            $this->prepend($config['prepend']);
        }

        if ($config['append']) {
            $this->append($config['append']);
        }

        if ($config['prependButton']) {
            $this->prependButton($config['prependButton']);
        }

        if ($config['appendButton']) {
            $this->appendButton($config['appendButton']);
        }

    }

/**
 * Get/set textarea number of rows.
 *
 * @param int $rows Textarea number of rows
 * @return $this|int
 */
    public function rows($rows = null) {
        if ($rows === null) {
            return $this->_rows;
        }

        $this->_rows = $rows;

        return $this;
    }

/**
 * Get/set text.
 *
 * @param string $text Text
 * @return $this|string
 */
    public function text($text = null) {
        return parent::value($text);
    }

/**
 * Get/Set allow URL's in textbox.
 *
 * @param boolean $allowUrls Text
 * @return $this|boolean
 */
    public function allowUrls($allowUrls = null) {
        return $this->_property('_allowUrls', $allowUrls);
    }

/**
 * Get/set WISIWYG setting.
 *
 * @param bool $editor Enable textarea as WISIWYG
 * @return $this|bool
 */
    public function editor($editor = null) {
        if ($editor === null) {
            return $this->_editor;
        }
        $this->_editor = $editor;
        return $this;
    }

/**
 * Get/set translate-able.
 *
 * @param bool $translate Multilanguage value
 * @return $this|bool
 */
    public function translate($translate = null) {
        if ($translate === null) {
            return $this->_translate;
        }
        $this->_translate = $translate;
        return $this;
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
 * Convert addons to array.
 *
 * @param string $property Property name
 * @param string $addon Addon to add
 * @return $this|string
 */
    protected function _addAddon($property, $addon) {
        if (!empty($appendButton) && !is_array($addon)) {
            $addon = [$addon];
        }
        return $this->_property('_' . $property, $addon);
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!$this->_allowUrls && Validation::containsUrl($data)) {
            $this->_error = __('URLs are not allowed.');
            return false;
        }

        return true;
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
            ->addClass('form-control' . ($this->_editor ? ' form-text-editor' : ''))
            ->id($id)
            ->set('disabled', ($this->disabled() ? true : null))
            ->name($name);

        $this->_setPlaceholderWithLabel();
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        $value = $this->text();

        if ($this->translate() && is_array($value)) {
            $value = $value[I18n::locale()];
        }

        return $value;
    }

}
