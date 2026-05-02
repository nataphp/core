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

use Nata\Utility\Text;

/**
 * Provides a registry/factory for group's row instances.
 */
class GroupRow {

/**
 * Form instance.
 *
 * @var Form
 */
    private $_form;

/**
 * Row ID.
 *
 * @var string
 */
    private $_id;

/**
 * Row title.
 *
 * @var string
 */
    private $_title;

/**
 * Default title.
 *
 * @var string
 */
    private $_defaultTitle;

/**
 * Title pattern.
 *
 * @var string
 */
    private $_titlePattern;

/**
 * Label as placeholder setting.
 *
 * @var bool
 */
    private $_labelAsPlaceholder;

/**
 * Element translate.
 *
 * @var bool
 */
    private $_translate;

/**
 * Theme.
 *
 * @var string
 */
    private $_theme;

/**
 * Element Layout.
 *
 * @var string
 */
    private $_layout;

/**
 * Required.
 *
 * @var bool
 */
    private $_required;

/**
 * Form horizontal layout column size.
 *
 * @var int
 */
    private $_columnSize;

/**
 * Element collection instance.
 *
 * @var \Nata\Form\ElementCollection
 */
    private $_elements;

/**
 * Group collection instance.
 *
 * @var \Nata\Form\GroupCollection
 */
    private $_groups;

/**
 * Data manager instance.
 *
 * @var \Nata\Form\DataManager
 */
    protected $_dataManager;

/**
 * Row index.
 *
 * @var integer
 */
    private $_index = 0;

/**
 * Event manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Elements array data.
 *
 * @var array
 */
    private $_toArray;

/**
 * Element list of HTML attributes.
 *
 * @var array
 */
    protected $_attributes;

/**
 * Validation result.
 *
 * @var bool
 */
    private $_isValid;

/**
 * Form submitted.
 *
 * @var bool
 */
    private $_submitted;


/**
 * Constructor.
 *
 * @param array $options Name of group
 * @return void
 */
    public function __construct(Form $form, array $config) {
        $this->_form = $form;
        $this->_dataManager = $config['data'];
        $this->_id = array_merge($config['id'], array($config['index']));
        $this->_index = $config['index'];
        $this->_title = $config['title'];
        $this->_submitted = $config['submitted'];
        $this->_required = $config['required'];
        $this->_labelAsPlaceholder = $config['labelAsPlaceholder'];
        $this->_defaultTitle = $config['defaultTitle'];
        $this->_theme = $config['theme'];
        $this->_translate = $config['translate'];
        $this->_layout = $config['layout'];
        $this->_columnSize = $config['columnSize'];
        $this->_eventManager = $config['eventManager'];
        $this->_setElements($config['elements']);
        if (strpos($this->_title, '{') !== false) {
            $this->_titlePattern = $this->_title;
        }
    }

/**
 * Load elements instances.
 *
 * @param array $elements Elements
 * @return void
 */
    private function _setElements($elements) {
        foreach ($elements as $config) {
            if (isset($config['group']) && !empty($config['group'])) {
                $collection = $this->groups();
                $method = 'group';
            } else {
                $collection = $this->elements();
                $method = 'name';
            }
            $propertyName = $config[$method];
            $collection->load(array(
                'id' => $this->_id,
                '_groupRow' => $this
            ) + $config);
        }
        if (!$this->elements()->has('id') && $this->_dataManager->model()->has('id')) {
            $this->elements()->load(array(
                'element' => 'hidden',
                'name' => 'id',
                '_groupRow' => $this
            ));
        }
    }

/**
 * Set/get group row title.
 *
 * @param string $title Title
 * @return string Group row title
 */
    public function id() {
        return $this->_id;
    }

/**
 * Set/get group row index.
 *
 * @return int Group row index
 */
    public function index() {
        return $this->_index;
    }

/**
 * Get fieldset id parts.
 *
 * @return array
 */
    public function eventManager() {
        return $this->_eventManager;
    }

/**
 * Translate fields.
 *
 * @param bool $translate Disable form
 * @return bool|$this
 */
    public function translate() {
        return $this->_translate;
    }

/**
 * Get/Set Group required setting.
 *
 * @param bool $required Required
 * @return bool Required
 */
    public function required() {
        return $this->_required;
    }

/**
 * Get/set label as placeholder.
 * Form setting.
 *
 * @param bool $labelAsPlaceholder Allowed field(s)
 * @return bool|$this
 */
    public function labelAsPlaceholder() {
        return $this->_labelAsPlaceholder;
    }

/**
 * Get theme.
 *
 * @return string
 */
    public function theme() {
        return $this->_theme;
    }

/**
 * Submittion state.
 *
 * @return bool True if form was submitted, false otherwise
 */
    public function submitted() {
        return $this->_submitted;
    }

/**
 * Get form layout.
 *
 * @return string
 */
    public function layout() {
        return $this->_layout;
    }

/**
 * Get column size value for 'horizontal' layout.
 *
 * @return int
 */
    public function columnSize() {
        return $this->_columnSize;
    }

/**
 * Get row visibility state.
 *
 * @return bool
 */
    public function open() {
        return;
    }

/**
 * Set/get group row title.
 *
 * @param string $title Title
 * @return string Group row title
 */
    public function defaultTitle() {
        return $this->_defaultTitle;
    }

/**
 * Get title pattern.
 *
 * @return string Title Pattern
 */
    public function titlePattern() {
        return $this->_titlePattern;
    }

/**
 * Set/get group row title.
 *
 * @param string $title Title
 * @return string Group row title
 */
    public function title($title = null) {
        if ($title === null) {
            $title = $this->_title;
            if (is_callable($title)) {
                $this->_title = $title($this);
            } elseif ($this->_titlePattern) {
                $this->_title = $this->_dynamicTitle();
            }
            if ($this->_title === null) {
                return $this->_defaultTitle;
            }
            return $this->_title;
        }
        $this->_title = $title;
        return $this;
    }

/**
 * Create dynamic title from data.
 *
 * @return string Group row title
 */
    public function _dynamicTitle() {
        return;
        preg_match_all("/\{(.+?)\}/", $this->_titlePattern, $matches);

        $insert = array();
        foreach ($matches[1] as $name) {
            if ($this->_elements->has($name)) {
                $element = $this->_elements->get($name);
                $insert[$name] = $element->__toString();
            }
        }
        return Text::insert($this->_titlePattern, $insert, array(
            'before' => '{',
            'after' => '}'
        ));
    }

/**
 * Elements Collection instance.
 *
 * @return \Nata\Form\ElementCollection
 */
    public function elements() {
        if ($this->_elements === null) {
            $this->_elements = new ElementCollection($this->_form, $this);
        }
        return $this->_elements;
    }

/**
 * Groups collection instance.
 *
 * @return \Nata\Form\GroupCollection
 */
    public function groups() {
        if ($this->_groups === null) {
            $this->_groups = new GroupCollection($this->_form, $this);
        }
        return $this->_groups;
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
 * Alias/short hand for attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attrs() {
        return $this->attributes();
    }

/**
 * Get group row data.
 *
 * @return mixed
 */
    public function data() {
        return $this->_dataManager->model()->extract();
    }

/**
 * Get form group \Nata\Form\DataManager instance.
 *
 * @return \Nata\Form\DataManager
 */
    public function dataManager() {
        return $this->_dataManager;
    }

/**
 * Elements validation.
 *
 * @return bool True if valid, false otherwise
 */
    public function isEmpty() {
        return $this->elements()->isEmpty() && $this->groups()->isEmpty();
    }

/**
 * Elements validation.
 *
 * @return bool True if valid, false otherwise
 */
    public function isValid() {
        if ($this->_isValid === null) {
            // Clear row data if it lost the ID
            if ($this->_dataManager->request()->has('id') && $this->_dataManager->request()->isEmpty('id')) {
                $this->_dataManager->model()->clear();
            }

            $valid = true;
            if (!$this->_required && $this->isEmpty()) {
                $this->_setNotRequired();
            }
            if ((!$this->elements()->isValid() || !$this->groups()->isValid()) && $valid) {
                $valid = false;
            }
            $this->_toArray = [
                'id' => $this->attributes()->id(),
                'isValid' => $valid,
                'elements' => $this->elements()->toArray(),
                'groups' => $this->groups()->toArray()
            ];
            $this->_isValid = $valid;

        }
        return $this->_isValid;
    }

/**
 * Elements to array.
 *
 * @return array
 */
    private function _setNotRequired() {
        foreach ($this->elements()->get() as $element) {
            $element->required(false);
        }
    }

/**
 * Elements to array.
 *
 * @return array
 */
    public function toArray() {
        if ($this->_toArray === null) {
            $this->_toArray = array(
                'id' => $this->attributes()->id(),
                'isValid' => $this->isValid(),
                'isEmpty' => $this->isEmpty(),
                'required' => $this->_required,
                'elements' => $this->elements()->toArray(),
                'groups' => $this->groups()->toArray()
            );
        }
        return $this->_toArray;
    }

/**
 * __debugInfo.
 *
 * @return array
 */
    public function __debugInfo() {
        return $this->toArray();
    }

/**
 * Render.
 *
 * @return string
 */
    public function render() {
        $html = $this->elements()->render();
        $html .= $this->groups()->render();
        return $html;
    }

}
