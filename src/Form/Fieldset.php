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

use Nata\View\ViewBuilder;

/**
 * Provides a registry/factory for Form objects.
 *
 * This registry allows you to centralize the configuration for forms
 * their options and other meta-data.
 */
class Fieldset {

/**
 * Form instance.
 *
 * @var Form
 */
    private $_form;

/**
 * Fieldset name.
 *
 * @var string
 */
    private $_fieldset;

/**
 * Fieldset id.
 *
 * @var string
 */
    private $_id;

/**
 * Fieldset's title.
 *
 * @var string
 */
    private $_title;

/**
 * Fieldset's description.
 *
 * @var string
 */
    private $_description;

/**
 * Translate.
 *
 * @var bool
 */
    private $_translate;

/**
 * Label as placeholder setting.
 *
 * @var bool
 */
    private $_labelAsPlaceholder;

/**
 * Layout.
 *
 * @var string
 */
    private $_layout;

/**
 * Theme.
 *
 * @var string
 */
    private $_theme;

/**
 * Horizontal layout column size.
 *
 * @var int
 */
    private $_columnSize;

/**
 * Template.
 *
 * @var string
 */
    private $_template = 'fieldset';

/**
 * ElementCollection instance.
 *
 * @var \Nata\Form\ElementCollection
 */
    private $_elements;

/**
 * GroupCollection instance.
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
 * Form submittion state.
 *
 * @var bool
 */
    private $_submitted;

/**
 * Fieldset attributes.
 *
 * @var \Nata\Form\Attributes
 */
    private $_attributes;

/**
 * Event manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Fieldset data is valid.
 *
 * @var bool
 */
    private $_isValid;

/**
 * Fieldset data.
 *
 * @var array
 */
    private $_data;


/**
 * Fieldset constructor.
 *
 * @param array $config Fieldset options
 * @return void
 */
    public function __construct(Form $form, $config) {
        $this->_form = $form;
        $this->_fieldset = $config['fieldset'];
        $this->_dataManager = $config['data'];
        $this->_columnSize = $config['columnSize'];
        $this->_submitted = !$this->_dataManager->request()->isEmpty();
        $this->_id = (array)$config['id'];
        if ($config['title']) {
            $this->title($config['title']);
        }
        if ($config['description']) {
            $this->description($config['description']);
        }
        if ($config['theme']) {
            $this->_theme = $config['theme'];
        }
        if ($config['template']) {
            $this->_template = $config['template'];
        }
        if ($config['layout']) {
            $this->_layout = $config['layout'];
        }
        if ($config['labelAsPlaceholder']) {
            $this->_labelAsPlaceholder = $config['labelAsPlaceholder'];
        }
        if ($config['translate']) {
            $this->_translate = $config['translate'];
        }
        if ($config['eventManager']) {
            $this->_eventManager = $config['eventManager'];
        }
        if ($config['elements']) {
            $this->elements()->loadAll($config['elements']);
        }
        if ($config['groups']) {
            $this->groups()->loadAll($config['groups']);
        }

        $id = array_merge((array)$config['id'], array('fieldset', $this->_fieldset));
        $this->attributes()->id($id);
    }

/**
 * Get fieldset id parts.
 *
 * @return array
 */
    public function id() {
        return $this->_id;
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
 * Get/Set fieldset theme.
 *
 * @return mixed fieldset id
 */
    public function theme() {
        return $this->_theme;
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
 * Get table instance.
 *
 * @return \Nata\ORM\Table
 */
    public function submitted() {
        return $this->_submitted;
    }

/**
 * Get/set element's template.
 *
 * @param string $template Element template
 * @return $this|string
 */
    public function template() {
        return $this->_template;
    }

/**
 * Add element(s) parameters.
 *
 * @param array $elementsParams Row of elements parameters.
 * @return \Nata\Form\Fieldset|array
 */
    public function layout($layout = null) {
        return $this->_layout;
    }

/**
 * Get/Set fieldset name.
 *
 * @param string $fieldset Fieldset name
 * @return mixed \Nata\Form\Fieldset or fieldset name
 */
    public function fieldset($fieldset = null) {
        if ($fieldset === null) {
            return $this->_fieldset;
        }
        $this->_fieldset = $fieldset;
        return $this;
    }

/**
 * Get/Set Fieldset title.
 *
 * @param string $title Fieldset title
 * @return mixed \Nata\Form\Fieldset or fieldset title
 */
    public function title($title = null) {
        if ($title === null) {
            return $this->_title;
        }
        $this->_title = $title;
        return $this;
    }

/**
 * Get/Set Fieldset description.
 *
 * @param string $title Fieldset title
 * @return mixed \Nata\Form\Fieldset or fieldset title
 */
    public function description($description = null) {
        if ($description === null) {
            return $this->_description;
        }
        $this->_description = $description;
        return $this;
    }

/**
 * Get/Set fieldset data.
 *
 * @return mixed fieldset id
 */
    public function data() {
        if ($this->_submitted && $this->_isValid) {
            return array_merge(
                $this->elements()->data(),
                $this->groups()->data()
            );
        }
        return $this->_data;
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
 * Get/set attributes.
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
 * Array of elements properties.
 *
 * @return array
 */
    public function isValid() {
        if (!$this->submitted()) {
            return false;
        }

        if ($this->_isValid === null) {
            $valid = true;
            if ((!$this->elements()->isValid() || !$this->groups()->isValid()) && $valid) {
                $valid = false;
            }
            $this->_isValid = $valid;
        }

        return $this->_isValid;
    }

/**
 * Form to array.
 *
 * @return array
 */
    public function toArray() {
        return array(
            'isValid' => $this->isValid(),
            'elements' => $this->elements()->toArray(),
            'groups' => $this->groups()->toArray()
        );
    }

/**
 * Alias/short hand for attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function render() {
        $view = ViewBuilder::build()->compileCheck(false);
        $view->set('fieldset', $this);
        return $view->render('/Form/' . $this->_theme . '/' . $this->template());
    }

}
