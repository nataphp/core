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

use Nata\Core\NataObject;
use Nata\Utility\Validation;
use Nata\Utility\Inflector;
use Nata\Event\Listener;
use Nata\Event\Manager;
use Nata\Event\Event;
use Nata\View\ViewBuilder;
use Nata\I18n\I18n;
use FormException;
use Closure;
use Nata\Utility\Hash;
use Nata\Utility\Sanitize;

/**
 * Element object.
 */
class Element extends NataObject implements Listener {

/**
 * Form instance.
 *
 * @var Form
 */
    protected $_form;

/**
 * Element class name.
 *
 * @var string
 */
    protected $_className;

/**
 * Allow element to be rendered.
 *
 * @var bool
 */
    protected $_render;

/**
 * Element name.
 *
 * @var string
 */
    protected $_name;

/**
 * Element class alias.
 *
 * @var string
 */
    protected $_element;

/**
 * Element id.
 *
 * @var array
 */
    protected $_id;

/**
 * Element data.
 *
 * @var \Nata\Form\Data
 */
    protected $_data;

/**
 * Translatable data.
 *
 * @var bool
 */
    protected $_translate;

/**
 * List of languages.
 *
 * @var array
 */
    protected $_languages;

/**
 * Element type.
 *
 * @var string
 */
    protected $_labelAsPlaceholder;

/**
 * Element label.
 *
 * @var string
 */
    protected $_label;

/**
 * Element label required field cue.
 *
 * @var string
 */
    protected $_requiredLabelCue = '*';

/**
 * Element description.
 *
 * @var string
 */
    protected $_description;

/**
 * Element tooltip.
 *
 * @var string
 */
    protected $_tooltip;

/**
 * Element theme.
 *
 * @var string
 */
    protected $_theme;

/**
 * Element template.
 *
 * @var string
 */
    protected $_template;

/**
 * Element layout.
 *
 * @var string
 */
    protected $_layout;

/**
 * Horizontal layout column size.
 *
 * @var int
 */
    private $_columnSize;

/**
 * Element view path.
 *
 * @var string
 */
    protected $_viewPath = 'Form';

/**
 * Element value.
 *
 * @var mixed
 */
    protected $_value;

/**
 * Type cast value to given var type.
 *
 * @var string
 */
    protected $_valueType;

/**
 * Element default value.
 *
 * @var mixed
 */
    protected $_defaultValue;

/**
 * Element list of HTML attributes.
 *
 * @var array
 */
    protected $_attributes;

/**
 * Is required.
 *
 * @var bool
 */
    protected $_required = false;

/**
 * Form submitted.
 *
 * @var bool
 */
    protected $_submitted = false;

/**
 * Is empty custom callback.
 *
 * @var callback
 */
    protected $_empty;

/**
 * Is disabled.
 *
 * @var bool
 */
    protected $_disabled = false;

/**
 * Exact length required.
 *
 * @var int
 */
    protected $_length;

/**
 * Maximum string length.
 *
 * @var int
 */
    protected $_maxLength;

/**
 * Minimum string length.
 *
 * @var int
 */
    protected $_minLength;

/**
 * Between range of values.
 *
 * ### Example
 * $element->between([3, 10]);
 *
 * @var array
 */
    protected $_between;

/**
 * Match string or regex.
 *
 * ### Example
 * $element->match('this string');
 * $element->match('/[0-9]/');
 *
 * @var string
 */
    protected $_match;

/**
 * Custom validation.
 *
 * @var callable
 */
    protected $_validation;

/**
 * Parse incoming request value before validation.
 * Runs after typecast, when form is submitted.
 * Signature: function ($value, $element): mixed
 *
 * @var callable|null
 */
    protected $_parse;

/**
 * Format model value before display.
 * Runs when value comes from the model (not from a request).
 * Signature: function ($value, $element): mixed
 *
 * @var callable|null
 */
    protected $_format;

/**
 * Multiple value options.
 *
 * @var array|OptionRegistry
 */
    protected $_options;

/**
 * Error message.
 *
 * @var string
 */
    protected $_error;

/**
 * View instance.
 *
 * @var \Nata\View\View
 */
    protected $_View;

/**
 * Group row that element is part of.
 *
 * @var \Nata\Form\GroupRow
 */
    protected $_groupRow;

/**
 * Element validation state.
 *
 * @var bool
 */
    protected $_isValid;

/**
 * Read only.
 *
 * @var bool
 */
    protected $_readOnly = false;

/**
 * Flatten data.
 *
 * @var bool
 */
    protected $_flattenData = false;

/**
 * Data manager.
 *
 * @var DataManager
 */
    protected $_dataManager;

/**
 * Show when.
 *
 * @var array
 */
    protected $_showWhen;

/**
 * Hide when.
 *
 * @var array
 */
    protected $_hideWhen;

/**
 * Construtor.
 *
 * @param \Nata\Form\Form $form Form instance
 * @param array $config Element configuration
 * @param \Nata\Form\GroupRow|Fieldset|null $groupRow Parent instance
 */
    public function __construct(Form $form, array $config, GroupRow|Fieldset|null $groupRow = null) {
        $config += [
            'className' => null,
            'id' => null,
            'name' => null,
            'type' => null,
            'element' => null,
            'columnSize' => null,
            'value' => null,
            'valueType' => null,
            'label' => null,
            'row' => null,
            'description' => null,
            'labelAsPlaceholder' => null,
            'tooltip' => null,
            'template' => null,
            'required' => null,
            'empty' => null,
            'translate' => null,
            'languages' => null,
            'disabled' => null,
            'flattenData' => null,
            'readOnly' => null,
            'length' => null,
            'minLength' => null,
            'maxLength' => null,
            'between' => null,
            'match' => null,
            'validation' => null,
            'attrs' => array(),
            'options' => null,
            'data' => null,
            'eventManager' => null,
            'dataManager' => null,
            'theme' => null,
            'layout' => null,
            'requiredLabelCue' => null,
            'allowHtml' => false,
            'render' => true,
            'showWhen' => null,
            'hideWhen' => null,
            '_groupRow' => null,
            'parse' => null,
            'format' => null
        ];

        $this->_form = $form;
        // Ignore if iit's not group row instance
        $this->_groupRow = $groupRow instanceof GroupRow ? $groupRow : null;
        $this->_id = (array)$config['id'];
        $this->name($config['name']);
        $this->_data = $config['data'];
        $this->_element = $config['element'];
        $this->_columnSize = $config['columnSize'];
        $this->_className = $config['className'];
        $this->_labelAsPlaceholder = $config['labelAsPlaceholder'];
        $this->_required = $config['required'];
        $this->_empty = $config['empty'];
        $this->_translate = $config['translate'];
        $this->_languages = $config['languages'];
        $this->_defaultValue = $config['value'];
        $this->_dataManager = $config['dataManager'];
        $this->_flattenData = $config['flattenData'];
        $this->_render = $config['render'];
        $this->_showWhen = $config['showWhen'];
        $this->_hideWhen = $config['hideWhen'];

        if ($config['eventManager']) {
            $this->_eventManager = $config['eventManager'];
            $this->_eventManager->on($this);
        }

        if ($config['label']) {
            $this->_label = $config['label'];
        }
        if ($config['description'] !== null) {
            $this->_description = $config['description'];
        }
        if ($config['tooltip']) {
            $this->tooltip($config['tooltip']);
        }
        if ($config['theme']) {
            $this->_theme = $config['theme'];
        }
        if ($config['template']) {
            $this->_template = $config['template'];
        }
        if ($config['layout'] !== null) {
            $this->_layout = $config['layout'];
        }
        if ($config['disabled'] !== null) {
            $this->disabled($config['disabled']);
        }
        if ($config['readOnly'] !== null) {
            $this->readOnly($config['readOnly']);
        }
        if ($config['length']) {
            $this->length($config['length']);
        }
        if ($config['maxLength']) {
            $this->maxLength($config['maxLength']);
        }
        if ($config['minLength']) {
            $this->minLength($config['minLength']);
        }
        if ($config['between']) {
            $this->between($config['between']);
        }
        if ($config['match']) {
            $this->match($config['match']);
        }
        if ($config['validation']) {
            $this->_validation = $config['validation'];
        }
        if ($config['parse']) {
            $this->_parse = $config['parse'];
        }
        if ($config['format']) {
            $this->_format = $config['format'];
        }
        if ($config['attrs']) {
            $this->attributes()->set($config['attrs']);
        }
        if ($config['options']) {
            $this->_options = $config['options'];
        }
        if ($config['requiredLabelCue'] !== null) {
            $this->_requiredLabelCue = $config['requiredLabelCue'];
        }

        $this->valueType($config['valueType']);

        $this->initialize($config);

        $this->startup();
    }

/**
 * Pseudo-constructor.
 * This method is called after user and defaults are setup in class.
 *
 * @param array $config Element configuration
 * @return void
 */
    public function initialize($config) {
        $this->config($config);
    }

/**
 * Startup is called after all element data is set.
 *
 * @return void
 */
    public function startup() {}

/**
 * Validation.
 *
 * @param string|array $data Data to validate
 * @return bool
 */
    protected function _valid($data) {
        return true;
    }

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @param \Nata\Event\Manager $eventManager Event manager instance
 * @return \Nata\Event\Manager
 */
    public function eventManager($eventManager = null) {
        if ($eventManager === null) {
            if ($this->_eventManager === null) {
                $this->_eventManager = new Manager();
                $this->_eventManager->on($this);
            }
            return $this->_eventManager;
        }
        $this->_eventManager = $eventManager;
        return $this;
    }

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return array(
            'Form.beforeValidation' => 'beforeValidation',
            'Form.afterValidation' => 'afterValidation',
            $this->name() . '.beforeRender' => 'beforeRender',
            $this->name() . '.afterRender' => 'afterRender'
        );
    }

/**
 * Get element's id.
 *
 * @return array
 */
    public function id() {
        $name = explode('.', $this->name());
        return array_merge($this->_id, $name);
    }

/**
 * Get/set element's name.
 *
 * @param string $name Element name
 * @return $this|string
 */
    public function name($name = null) {
        if ($name === null) {
            if ($this->_name === null) {
                $this->_name = md5($this->_className . microtime());
            }
            return $this->_name;
        }
        $this->_name = $name;
        return $this;
    }

/**
 * Get/set render-only element.
 *
 * @param bool $readOnly Just render element
 * @return $this|bool
 */
    public function readOnly($readOnly = null) {
        return $this->_property('_readOnly', $readOnly);
    }

/**
 * Get/set disabled element.
 *
 * @param bool $disabled Disable element
 * @return $this|bool
 */
    public function disabled($disabled = null) {
        return $this->_property('_disabled', $disabled);
    }

/**
 * Get/set form instance.
 *
 * @param \Nata\Form\Form $form Form instance
 * @return $this|\Nata\Form\Form
 */
    public function form(Form $form = null) {
        if ($form === null) {
            return $this->_form;
        }
        $this->_form = $form;
        return $this;
    }

/**
 * Get element's type.
 *
 * @return string
 */
    public function element() {
        return $this->_element;
    }

/**
 * Get/set element's label.
 *
 * @param string $label Element label
 * @return $this|string
 */
    public function label($label = null) {
        return $this->_property('_label', $label);
    }

/**
 * Get/set if translatable element.
 *
 * @param bool $translate Translatable
 * @return $this|bool
 */
    public function translate($translate = null) {
        if ($translate !== null) {
            $this->data()->translate($translate);
        }
        return $this->_property('_translate', $translate);
    }

/**
 * Get/set element's description.
 *
 * @param string $description Element description
 * @return $this|string
 */
    public function description($description = null) {
        return $this->_property('_description', $description);
    }

/**
 * Get/set tooltip.
 *
 * @param string $tooltip Label tooltip
 * @return $this|string
 */
    public function tooltip($tooltip = null) {
        if ($tooltip === null) {
            return $this->_tooltip;
        }

        if ($tooltip instanceof Closure) {
            $_tooltip = $tooltip;
            $tooltip = new Tooltip();
            $_tooltip($tooltip, $this, $this->_groupRow);
        } else {
            $tooltip = new Tooltip(['content' => $tooltip]);
        }

        $this->_tooltip = $tooltip;

        return $this;
    }

/**
 * Get/set element's template.
 *
 * @param string $template Element template
 * @return $this|string
 */
    public function template($template = null) {
        if ($template === null) {
            if ($this->_template === null) {
                $this->_template = Inflector::underscore($this->_element);
            }
            return $this->_template;
        }
        $this->_template = $template;
        return $this;
    }

/**
 * Get/set element's theme.
 *
 * @param string $theme Element theme
 * @return $this|string
 */
    public function theme($theme = null) {
        return $this->_property('_theme', $theme);
    }

/**
 * Get/set element's layout.
 *
 * @param string $layout Element layout
 * @return $this|string
 */
    public function layout($layout = null) {
        if ($layout === null) {
            return $this->_layout;
        }

        $this->_layout = $layout;

        return $this;
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
 * Get/set element's error.
 *
 * @param string $error Element value
 * @return $this|string
 */
    public function error($error = null) {
        return $this->_property('_error', $error);
    }

/**
 * Get/set element requirement.
 *
 * @param bool|callable|string $required Required
 * @return $this|bool
 */
    public function required($required = null) {
        if ($required === null) {
            $required = $this->_required;

            if ($required !== null && $required instanceof Closure) {
                $required = $required($this, $this->_groupRow);
            }

            if ($required === true) {
                $required = __('This field is required!');
            }

            return $this->_required = $required;
        }

        $this->_required = $required;

        return $this;
    }

/**
 * Get/set length requirement.
 *
 * @param int $length Length
 * @return $this|int
 */
    public function length($length = null) {
        return $this->_property('_length', $length);
    }

/**
 * Get/set minimum length requirement.
 *
 * @param int $minLength Minimum length
 * @return $this|int
 */
    public function minLength($minLength = null) {
        return $this->_property('_minLength', $minLength);
    }

/**
 * Get/set maximum length requirement.
 *
 * @param int $maxLength Maximum length
 * @return $this|int
 */
    public function maxLength($maxLength = null) {
        return $this->_property('_maxLength', $maxLength);
    }

/**
 * Get/set minimum and maximum number.
 *
 * @param array $between Number
 * @return $this|int
 */
    public function between($between = null) {
        return $this->_property('_between', $between);
    }

/**
 * Get/set match string/regex.
 * Can be an regular expression or string.
 *
 * @param string $match String/regex to compare
 * @return $this|string
 */
    public function match($match = null) {
        return $this->_property('_match', $match);
    }

/**
 * Get/set valid validation.
 *
 * @param callable $validation Callable
 * @return $this|callable
 */
    public function validation(callable $validation = null) {
        if ($validation === null) {
            return $this->_validation;
        }
        return $this->_property('_validation', $validation);
    }

/**
 * Get/set parse callback.
 * Called on submitted value after typecast, before validation.
 *
 * @param callable $parse Callable
 * @return $this|callable
 */
    public function parse(callable $parse = null) {
        return $this->_property('_parse', $parse);
    }

/**
 * Get/set format callback.
 * Called on model value before display.
 *
 * @param callable $format Callable
 * @return $this|callable
 */
    public function format(callable $format = null) {
        return $this->_property('_format', $format);
    }

/**
 * Get/set label's required field cue.
 *
 * @param int $maxLength Required cue
 * @return $this|int
 */
    public function requiredLabelCue($requiredLabelCue = null) {
        return $this->_property('_requiredLabelCue', $requiredLabelCue);
    }

/**
 * Get/Set label as placeholder setting state.
 *
 * @param bool $labelAsPlaceholder
 * @return $this|bool
 */
    public function labelAsPlaceholder($labelAsPlaceholder = null) {
        return $this->_property('_labelAsPlaceholder', $labelAsPlaceholder);
    }

/**
 * Get/Set show when.
 *
 * @param bool $showWhen
 * @return $this|bool
 */
    public function showWhen(bool $showWhen = null) {
        return $this->_property('_showWhen', $showWhen);
    }

/**
 * Get/Set hide when.
 *
 * @param bool $hideWhen
 * @return $this|bool
 */
    public function hideWhen(bool $hideWhen = null) {
        return $this->_property('_hideWhen', $hideWhen);
    }

/**
 * Get/Set value type.
 *
 * @param string $valueType
 * @return $this|string
 */
    public function valueType($valueType = null) {
        if ($valueType !== null) {
            $this->data()->type($valueType);
        }
        return $this->_property('_valueType', $valueType);
    }

/**
 * Get/set property name in instance.
 *
 * @param bool $propertyName Name of the property to set/get
 * @param mixed $value Value of property
 * @return $this|bool
 */
    protected function _property($propertyName, $value = null) {
        if ($value === null) {
            $value = $this->{$propertyName};

            if ($value !== null && $value instanceof Closure) {
                $this->{$propertyName} = $value($this, $this->_groupRow);
            }

            return $this->{$propertyName};
        }
        $this->{$propertyName} = $value;
        return $this;
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
 * Get/set element's attributes.
 *
 * @return \Nata\Form\ElementAttribute
 */
    public function options() {
        if (!($this->_options instanceof OptionRegistry)) {
            $options = $this->_options;

            $this->_options = new OptionRegistry($this);

            if ($options !== null) {
                $this->_options->loadAll($options);
            }

            unset($options);
        }
        return $this->_options;
    }

/**
 * Check and set placeholder with the label.
 *
 * Fields elements uses this method to place label
 * as placeholder.
 *
 * @return void
 */
    protected function _setPlaceholderWithLabel() {
        if ($this->_labelAsPlaceholder && !empty($this->_label)) {
            $this->attrs()->set('placeholder', $this->_label . ($this->required() ? ' ' . $this->_requiredLabelCue : ''));
            $this->label(false);
        }
    }

/**
 * Get/set element's model value.
 *
 * @param mixed $value Value to set
 * @return $this|mixed
 */
    public function data($value = null) {
        if (func_num_args() === 0) {
            return $this->_data;
        }

        $this->_data->request($value);

        return $this;
    }

/**
 * Get element's input default value.
 *
 * @return mixed
 */
    public function defaultValue() {
        return $this->_defaultValue;
    }

/**
 * Get/set element's input value.
 *
 * @param mixed $value Input value to set
 * @return $this|mixed
 */
    public function value($value = null) {
        if ($value === null) {
            if ($this->_value === null) {
                if ($this->_data->submitted() && !$this->disabled() && !$this->readOnly()) {
                    $this->_value = $this->_data->typecast();
                    /*
                    if ($this->_parse !== null) {
                        $parse = $this->_parse;
                        $this->_value = $parse($this->_value, $this);
                    }
                    */
                } elseif ($this->_data->model() !== null) {
                    $this->_value = $this->_data->model();
                    /*
                    if ($this->_format !== null) {
                        $format = $this->_format;
                        $this->_value = $format($this->_value, $this);
                    }
                    */
                } else {
                    $this->_value = $this->_defaultValue;
                }
            }
            return $this->_value;
        }

        $this->_value = $value;

        return $this;
    }

/**
 * Sanitize the data.
 *
 * @param string|array $data Data to be sanitized
 * @param array $options Options
 * @return string|array Data sanitized
 */
    public function _sanitizer($data, array $options = []) {
        $options += [
            'connection' => false,
            'odd_spaces' => true,
            'remove_html' => false,
            'encode' => true,
            'dollar' => false,
            'carriage' => false,
            'unicode' => false,
            'escape' => false,
            'backslash' => false
        ];
        return Sanitize::clean($data, $options);
    }

/**
 * Get value output sanitized to be presented on HTML view/template.
 *
 * @param array $filter Filtering options
 * @return string|array Value output sanitized
 */
    public function getValueOutput(array $filter = []) {
        return $this->_sanitizer($this->value(), $filter);
    }

/**
 * Check if element is valid.
 *
 * @param bool $isValid Set validation state
 * @return bool
 */
    public function isValid(bool $isValid = null): bool|Element {
        if ($isValid === null) {
            if ($this->disabled() || $this->readOnly()) {
                return true;
            }

            if ($this->_isValid === null) {
                $requestData = $this->_data->request();
                $this->_isValid = $this->_isValid($requestData);
            }

            return $this->_isValid;
        }

        $this->_isValid = $isValid;

        return $this;
    }

/**
 * Call validation rules.
 *
 * @param mixed $data Data to validate
 * @return bool
 */
    protected function _isValid($data) {
        // Custom validation
        if (is_callable($this->_validation)) {
            $validation = $this->_validation;
            $isValid = $validation($this, $this->_groupRow);
            if (is_bool($isValid)) {
                return $isValid;
            }

            // If callback sets error with other than NULL,
            // it will return the result.
            if ($this->_error !== null) {
                return !$this->_error;
            }
        }

        if ($this->isEmpty($data)) {
            if ($this->isRequired()) {
                $this->_error = $this->required();
                return false;
            }
            return true;
        }

        // Check first if data is not empty.
        // No need to check length of number if/when is not even one.
        if (!$this->_valid($data)) {
            return false;
        }

        if ($this->_length && !$this->hasLength($data)) {
            return false;
        }

        if ($this->_minLength && !$this->hasMinLength($data)) {
            return false;
        }

        if ($this->_maxLength && !$this->hasMaxLength($data)) {
            return false;
        }

        if ($this->_between && !$this->isBetween($data)) {
            return false;
        }

        if ($this->_match && !$this->matches($data)) {
            return false;
        }

        return $this->_error === null;
    }

/**
 * Check if element has error.
 *
 * @return boolean
 */
    public function hasError() {
        return !empty($this->_error);
    }

/**
 * Check if field is required.
 *
 * @return boolean True if is required, false otherwise
 */
    public function isRequired() {
        $required = $this->required();
        return !empty($required);
    }

/**
 * Check if data is empty.
 *
 * @param string|array $data Data to check if it's empty
 * @return boolean True if is empty, false otherwise
 */
    public function isEmpty($data = null) {
        if ($data === ' ') {
            return true;
        }

        if (func_num_args() === 0) {
            $data = $this->_getData($data);
        }

        if ($this->_empty instanceof Closure) {
            $empty = $this->_empty;
            return $empty($data, $this, $this->_groupRow);
        }

        if (is_array($data)) {
            $data = Hash::filter($data);
            return empty($data);
        }

        if (is_bool($data)) {
            return false;
        }

        return !Validation::notEmpty($data);
    }

/**
 * Check if value matches given length
 *
 * @param mixed $data Check data length
 * @return boolean True if is has required length, false otherwise
 */
    public function hasLength($data = null) {
        $data = $this->_getData($data);

        if (is_array($data)) {
            $this->_throwArrayValueException(__FUNCTION__);
        }

        if ($this->_length && !(strlen($data) === $this->_length)) {
            $this->_error = __('Must have %d characters.', array($this->_length));
            return false;
        }

        return true;
    }

/**
 * Check if data has required minimum length.
 *
 * @param mixed $data Check data minimum length
 * @return boolean True if is has minimum length, false otherwise
 */
    public function hasMinLength($data = null) {
        $data = $this->_getData($data);

        if (is_array($data)) {
            $this->_throwArrayValueException(__FUNCTION__);
        }

        $min = $this->_minLength;

        if ($min && !Validation::minLength($data, $min)) {
            $this->_error = __('%s has %d characters. Must have at least %d characters.', array($this->_label, strlen($data), $min));
            return false;
        }

        return true;
    }

/**
 * Check if data has required maximum length.
 *
 * @param mixed $data Check data maximum length
 * @return boolean True if is has maximum length, false otherwise
 */
    public function hasMaxLength($data = null) {
        $data = $this->_getData($data);
        if (is_array($data)) {
            $this->_throwArrayValueException(__FUNCTION__);
        }
        $max = $this->_maxLength;
        if ($max && !Validation::maxLength($data, $max)) {
            $this->_error = __('%s has %d characters. Must have at most %d characters.', array($this->_label, strlen($data), $max));
            return false;
        }
        return true;
    }

/**
 * Check if characters count is between given min and max.
 *
 * @param mixed $data Check data between min and max length
 * @return boolean True if successful, false otherwise
 */
    public function isBetween($data = null) {
        $data = $this->_getData($data);
        if (is_array($data)) {
            $this->_throwArrayValueException(__FUNCTION__);
        }
        $between = $this->_between;
        if ($between) {
            list($min, $max) = $between;
            if (!Validation::between($data, $min, $max)) {
                $this->_error = __('%s must have between %d and %d characters. At the moment has %d.', array(
                    $this->_label,
                    $min,
                    $max,
                    strlen($data)
                ));
                return false;
            }
        }
        return true;
    }

/**
 * Check if value is equal to data or matches regex pattern given.
 *
 * @param mixed $data Check data matches regex rule
 * @return boolean True if matches, false otherwise
 */
    public function matches($data = null) {
        $data = $this->_getData($data);

        if (is_array($data)) {
            $this->_throwArrayValueException(__FUNCTION__);
        }

        $match = $this->_match;

        if (!empty($match)) {
            if ($match === $data) {
                return true;
            } elseif (!Validation::custom($data, $match)) {
                $this->_error = __('The value doesn\'t match.', array($this->_label, $match));
                return false;
            }
        }

        return true;
    }

/**
 * Get data to be validaded.
 *
 * @param mixed $data Data to validate
 * @return mixed
 */
    protected function _getData($data) {
        if ($data === null) {
            $data = $this->value();
        }
        return $data;
    }

/**
 * Throw array as value exception.
 *
 * @param string $method Method name
 * @throws \FormException
 */
    private function _throwArrayValueException($method) {
        throw new FormException(sprintf(
            '"%s" does not support an array as value in "%s".',
            $method,
            get_class($this)
        ));
    }

/**
 * Get/Set list of available languages.
 *
 * @param string|array $languages Languages
 * @return array
 */
    public function languages($languages = null) {
        if ($languages === null) {
            if ($this->_languages === null) {
                $this->_languages = I18n::available();
            }
            return $this->_languages;
        }
        return $this->_languages;
    }

/**
 * Array of some properties.
 *
 * @return array
 */
    public function toArray() {
        return [
            'isValid' => $this->isValid(),
            'isEmpty' => $this->isEmpty(),
            'vartype' => $this->data()->type(),
            'error' => $this->_error,
            'value' => $this->value(),
            'required' => $this->required(),
            'id' => $this->attributes()->id()
        ];
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
 * Get view instance.
 *
 * @return \Nata\View\View
 */
    protected function _getView() {
        if ($this->_View === null) {
            $this->_View = ViewBuilder::build()
                ->set('element', $this)
                ->templatePath('Form/' . $this->_theme)
                ->compileCheck(false)
                ->layout($this->_layout);
        }
        return $this->_View;
    }

/**
 * Render.
 * If translate-able, load Language helper.
 *
 * @param \Nata\View\View $view View instance
 * @return sring
 */
    public function render() {
        if ($this->_render === false) {
            return;
        }

        if ($this->_required) {
            $this->attributes()->set('required', ($this->_required ? true : null));
        }

        $showWhen = $this->_toggleVisibilityWhen($this->_showWhen);
        if (!empty($showWhen)) {
            $this->attributes()->addData('show-when', $showWhen);
        }

        $hideWhen = $this->_toggleVisibilityWhen($this->_hideWhen);
        if (!empty($hideWhen)) {
            $this->attributes()->addData('hide-when', $hideWhen);
        }

        $event = new Event($this->name() . '.beforeRender', $this);
        $this->eventManager()->dispatch($event);

        $view = $this->_getView();
        $render = $this->_getView()->render('element/' . $this->template());

        $event = new Event($this->name() . '.afterRender', $this);
        $this->eventManager()->dispatch($event);
        return $render;
    }

/**
 * Before validation.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    protected function _toggleVisibilityWhen($conditions) {
        $when = [];
        foreach ((array)$conditions as $fieldName => $value) {
            if (str_contains($fieldName, 'this.') && $this->_groupRow !== null) {
                [$dummy, $field] = explode('.', $fieldName);
                $element = $this->_groupRow->elements()->get($field);
            } else {
                $element = $this->_form->elements()->get($fieldName);
                if ($element === null) {
                    foreach ($this->_form->fieldsets() as $fieldset) {
                        if ($fieldset->elements()->has($fieldName)) {
                            $element = $fieldset->elements()->get($fieldName);
                            break;
                        }
                    }
                }
            }

            if ($element === null) {
                continue;
            }

            $when[$fieldName] = [
                'field' => $fieldName,
                'id' => $element->attributes()->id(),
                'value' => $value
            ];
        }
        return $when;
    }

/**
 * Before validation.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeValidation($event) {}

/**
 * After validation.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param bool $valid Form validation result
 * @return \Nata\Event\Event
 */
    public function afterValidation($event, $valid) {}

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {}

/**
 * After render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function afterRender($event) {}

}
