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
use Nata\Event\Listener;
use Nata\ORM\Entity;
use Nata\Http\Request;
use Nata\Routing\Router;
use JsonSerializable;
use Nata\I18n\Time;

/**
 * Extends funcionality for form objects.
 */
class Form extends NataObject implements Listener, JsonSerializable {

/**
 * Default config.
 *
 * @var array
 */
    protected $_defaultConfig = [];

/**
 * Class name.
 *
 * @var string
 */
    private $_className;

/**
 * Form name.
 *
 * @var string
 */
    private $_form;

/**
 * Request instance.
 *
 * @var \Nata\Http\Request
 */
    private $_request;

/**
 * Form action.
 * Accepts any valid Router::url() parameter.
 *
 * @var string|array
 */
    protected $_action;

/**
 * Request method.
 *
 * @var string
 * @link https://www.w3.org/TR/html401/interact/forms.html#adef-method
 */
    protected $_method = 'post';

/**
 * Enable AJAX form submittion.
 *
 * @var bool
 */
    protected $_ajax = true;

/**
 * Duplicate data.
 *
 * @var bool
 */
    protected $_duplicate;

/**
 * Form id.
 *
 * @var string
 */
    protected $_id;

/**
 * Form theme.
 *
 * @var string
 */
    protected $_theme = 'nata';

/**
 * Form layout.
 *
 * @var string
 */
    protected $_layout = 'default';

/**
 * Labels column size.
 * Used when layout is set to 'horizontal'
 *
 * @var int
 */
    protected $_columnSize;

/**
 * Label as placeholder setting.
 *
 * @var bool
 */
    protected $_labelAsPlaceholder = false;

/**
 * Clear form if is valid.
 *
 * @var bool
 */
    protected $_clear = false;

/**
 * Disable form if is valid.
 *
 * @var bool
 */
    protected $_disable = false;

/**
 * Allow.
 *
 * @var bool
 */
    protected $_allow = false;

/**
 * Enable multi-language input on all
 * elements that support it.
 *
 * @var bool
 */
    protected $_translate = false;

/**
 * Repository data container.
 *
 * @var \Nata\Form\DataManager
 */
    protected $_dataManager;

/**
 * Data container.
 *
 * @var \Nata\ORM\Entity|array
 */
    protected $_data;

/**
 * Request form data.
 *
 * @var array
 */
    protected $_submittedData;

/**
 * Timestamp.
 *
 * @var int
 */
    protected $_timestamp;

/**
 * Form data flatten (not being keyed with form name).
 *
 * @var array
 */
    protected $_flattenData;

/**
 * If set to true, will check if user made changes to form
 * and if so, warn of possible data loss by leaving the
 * page without saving changes.
 *
 * @var bool
 */
    protected $_confirmPageLeave = false;

/**
 * ElementCollection instance.
 *
 * @var \Nata\Form\ElementCollection
 */
    private $_elements;

/**
 * ElementCollection instance.
 *
 * @var \Nata\Form\ElementCollection
 */
    private $_fieldsets;

/**
 * GroupCollection instance.
 *
 * @var \Nata\Form\GroupCollection
 */
    private $_groups;

/**
 * ButtonCollection instance.
 *
 * @var \Nata\Form\ButtonCollection
 */
    private $_buttons;

/**
 * Attributes instance.
 *
 * @var \Nata\Form\Attributes
 */
    private $_attributes;

/**
 * Form is valid.
 *
 * @var bool
 */
    protected $_isValid;

/**
 * Form class alias in registry.
 *
 * @var string
 */
    private $_registryAlias;


/**
 * Construtor.
 *
 * @param array $config Form configuration
 * @return void
 */
    public function __construct(array $config = []) {
        $config += [
            'className' => null,
            'form' => null,
            'id' => null,
            'data' => null,
            'timestamp' => null,
            'submittedData' => null,
            'action' => null,
            'method' => null,
            'ajax' => null,
            'duplicate' => null,
            'layout' => null,
            'theme' => null,
            'columnSize' => null,
            'labelAsPlaceholder' => null,
            'confirmPageLeave' => null,
            'refererUrl' => null,
            'flattenData' => false,
            'detectChanges' => false,
            'clear' => null,
            'disable' => null,
            'translate' => null,
            'registryAlias' => null,
            'fieldsets' => null,
            'elements' => null,
            'groups' => null,
            'buttons' => null,
            'attrs' => []
        ];

        $this->_className = $config['className'];
        $this->_registryAlias = $config['registryAlias'];
        $this->_form = $config['form'];
        $this->_data = $config['data'];
        $this->_request = Router::getRequest();
        $this->_submittedData = $config['submittedData'];
        $this->_duplicate = $config['duplicate'];
        $this->_confirmPageLeave = $config['confirmPageLeave'];
        $this->_flattenData = $config['flattenData'];

        if ($config['id']) {
            $this->id($config['id']);
        }
        if ($config['layout']) {
            $this->layout($config['layout']);
        }
        if ($config['theme']) {
            $this->theme($config['theme']);
        }
        if ($config['columnSize']) {
            $this->columnSize($config['columnSize']);
        }
        if ($config['labelAsPlaceholder']) {
            $this->labelAsPlaceholder($config['labelAsPlaceholder']);
        }
        if ($config['clear']) {
            $this->clear($config['clear']);
        }
        if ($config['disable']) {
            $this->disable($config['disable']);
        }
        if ($config['translate']) {
            $this->translate($config['translate']);
        }
        if ($config['method']) {
            $this->_method = $config['method'];
        }
        if ($config['timestamp']) {
            $this->timestamp($config['timestamp']);
        }
        if ($config['ajax']) {
            $this->_ajax = $config['ajax'];
        }
        if ($config['action']) {
            $this->action($config['action']);
        }
        if ($config['attrs']) {
            $this->attributes()->set($config['attrs']);
        }

        if ($this->_request) {
            $this->_autoSetRefererUrl($config['refererUrl']);
        }

        $this->config($config);

        $this->initialize();
        $this->startup();

        if ($this->hasEntity() && $this->_data->id > 0) {
            $this->attributes()->addClass('edit');
        }

    }

/**
 * Pseudo-constructor.
 *
 * @param array $config Form configuration
 * @return void
 */
    public function initialize() {}

/**
 * Startup.
 *
 * @return void
 */
    public function startup() {}

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return array(
            'Form.initialize' => 'initialize',
            'Form.startup' => 'startup',
            'Form.beforeValidation' => 'beforeValidation',
            'Form.afterValidation' => 'afterValidation',
            'Form.beforeRender' => 'beforeRender',
            'Form.afterRender' => 'afterRender',
            'Form.shutdown' => 'beforeShutdown'
        );
    }

/**
 * Get form class name.
 *
 * @return string Form class name
 */
    public function className() {
        return $this->_className;
    }

/**
 * Get registry alias.
 *
 * @return string Registry alias
 */
    public function registryAlias() {
        return $this->_registryAlias;
    }

/**
 * Get/Set form name.
 *
 * @param string $form Form name
 * @return string Form name
 */
    public function form($form = null) {
        if ($form === null) {
            return $this->_form;
        }
        $this->_form = $form;
        return $this;
    }

/**
 * Get/Set HTTP request instance.
 *
 * @param \Nata\Http\Request $request Request
 * @return \Nata\Http\Request Request
 */
    public function request(Request $request = null) {
        if ($request === null) {
            return $this->_request;
        }
        $this->_request = $request;
        return $this;
    }

/**
 * Get/set horizontal layout column size.
 * This setting is used in horizontal layout.
 *
 * @param int $columnSize Column size
 * @return int|$this
 */
    public function columnSize($columnSize = null) {
        if ($columnSize === null) {
            return $this->_columnSize;
        }
        $this->_columnSize = $columnSize;
        return $this;
    }

/**
 * Clear form after validation.
 *
 * @param bool $clear Clear form
 * @return bool|$this
 */
    public function clear($clear = null) {
        if ($clear === null) {
            return $this->_clear;
        }
        $this->_clear = $clear;
        return $this;
    }

/**
 * Disable form after validation.
 *
 * @param bool $disable Disable form
 * @return bool|$this
 */
    public function disable($disable = null) {
        if ($disable === null) {
            return $this->_disable;
        }
        $this->_disable = $disable;
        return $this;
    }

/**
 * Duplicate form data.
 *
 * @param bool $duplicate Duplicate form data
 * @return bool|$this
 */
    public function duplicate($duplicate = null) {
        if ($duplicate === null) {
            return $this->_duplicate;
        }
        $this->_duplicate = $duplicate;
        return $this;
    }

/**
 * Translate fields.
 *
 * @param bool $translate Disable form
 * @return bool|$this
 */
    public function translate($translate = null) {
        if ($translate === null) {
            return $this->_translate;
        }
        $this->_translate = $translate;
        return $this;
    }

/**
 * Get/set label as placeholder.
 * Form setting.
 *
 * @param bool $labelAsPlaceholder Allowed field(s)
 * @return bool|$this
 */
    public function labelAsPlaceholder($labelAsPlaceholder = null) {
        if ($labelAsPlaceholder === null) {
            return $this->_labelAsPlaceholder;
        }
        $this->_labelAsPlaceholder = $labelAsPlaceholder;
        return $this;
    }

/**
 * Get/Set form data timestamp.
 *
 * @param int|string|Time $timestamp Data timestamp
 * @return int|string|Time|$this
 */
    public function timestamp($timestamp = null) {
        if ($timestamp === null) {
            return $this->_timestamp;
        }

        if (!($timestamp instanceof Time)) {
            $timestamp = new Time($timestamp);
        }

        $this->_timestamp = $timestamp;

        $this->elements()->load([
            'type' => 'hidden',
            'name' => '___timestamp',
            'value' => $timestamp->timestamp()
        ]);

        return $this;
    }

/**
 * Get/Set list of field names that are allowed to be added
 * to 'data'.
 *
 * @param string|array $allow Allowed field(s)
 * @return array|$this
 */
    public function allow($allow = null) {
        if ($allow === null) {
            return $this->_allow;
        }
        $this->_allow = $allow;
        return $this;
    }

/**
 * Get/Set list of fields that are allowed .
 *
 * @param string $form Form name
 * @return string Form name
 */
    public function deny($deny = null) {
        return $this;
    }

/**
 * Get/Set form action.
 * Valid Router::url() argument.
 *
 * @param string|array $action Form action
 * @return string|\Nata\Form\Form
 */
    public function action($action = null) {
        if ($action === null) {
            if ($this->_action === null) {
                $this->_action = Router::url();
                $query = $this->_request->query();
                if (!empty($query)) {
                    $this->_action .= '?' . http_build_query($query);
                }
            }
            return $this->_action;
        }
        $this->_action = Router::url($action);
        return $this;
    }

/**
 * Get/Set request method.
 *
 * @param string $method Form method
 * @return string|\Nata\Form\Form
 */
    public function method($method = null) {
        if ($method === null) {
            return $this->_method;
        }
        $this->_method = $method;
        return $this;
    }

/**
 * Get/Set AJAX option state.
 * Allows to enable/disable form AJAX submittion.
 *
 * @param bool $ajax Form AJAX submittion
 * @return \Nata\Form\Form|bool
 */
    public function ajax($ajax = null) {
        if ($ajax === null) {
            return $this->_ajax;
        }
        $this->_ajax = $ajax;
        return $this;
    }

/**
 * Get/Set form id.
 *
 * @param string $form Form name
 * @return string Form name
 */
    public function id($id = null) {
        if ($id === null) {
            if ($this->_id === null) {
                $this->_id = $this->form();
            }
            return $this->_id;
        }
        $this->_id = $id;
        return $this;
    }

/**
 * Get/Set layout.
 * Valid values are 'default', 'horizontal' and 'inline'.
 *
 * @param string $layout Layout
 * @return string|\Nata\Form\Form
 */
    public function layout($layout = null) {
        if ($layout === null) {
            return $this->_layout;
        }
        $this->_layout = $layout;
        return $this;
    }

/**
 * Get/Set form theme.
 *
 * @param string $form Form name
 * @return string|$this Form name
 */
    public function theme($theme = null) {
        if ($theme === null) {
            return $this->_theme;
        }
        $this->_theme = $theme;
        return $this;
    }

/**
 * Get/Set confirm page leave setting.
 *
 * @param bool $confirmPageLeave Confirm page leave
 * @return bool|$this true if confirm page leave, false otherwise
 */
    public function confirmPageLeave($confirmPageLeave = null) {
        if ($confirmPageLeave === null) {
            return $this->_confirmPageLeave;
        }
        $this->_confirmPageLeave = $confirmPageLeave;
        return $this;
    }

/**
 * Check if form data is an entity.
 *
 * @return bool True if is entity, false otherwise
 */
    public function hasEntity() {
        return ($this->_data instanceof Entity);
    }

/**
 * Get form data from \Nata\Form\DataManager.
 *
 * @param string $property Property data to get
 * @return mixed
 */
    public function data($property = null) {
        $manager = $this->dataManager();
        $data = $manager->model();
        if ($property === null) {
            return $data->extract();
        }
        if (is_string($property)) {
            return $data->get($property);
        }
        if ($this->_dataManager) {
            $manager->model($property);
        } else {
            $this->_data = $property;
        }
        return $this;
    }

/**
 * Get form \Nata\Form\DataManager instance.
 *
 * @return \Nata\Form\DataManager
 */
    public function dataManager() {
        if ($this->_dataManager === null) {
            $submittedData = $this->submittedData();
            $this->_dataManager = new DataManager(array(
                'duplicate' => $this->_duplicate,
                'model' => $this->_data,
                'request' => $submittedData
            ));
        }
        return $this->_dataManager;
    }

/**
 * Get/set form request data.
 *
 * @return array Request's data
 */
    public function submittedData($submittedData = null) {
        if (func_num_args() === 0) {
            $form = $this->form();
            if ($this->_submittedData === null) {
                $request = $this->_request;
                if ($request->is('get')) {
                    $this->_submittedData = $this->_flattenData ? $request->query() : $request->query($form);
                } else {
                    $this->_submittedData = $this->_flattenData ? $request->data() : $request->data($form);
                    if ($this->_submittedData && $this->_flattenData === false) {
                        $request->data($this->_submittedData);
                        $request->data($form, null);
                    }
                }
            } elseif ($this->_flattenData === false && isset($this->_submittedData[$form])) {
                $this->_submittedData = $this->_submittedData[$form];
            }
            return $this->_submittedData;
        }

        $this->_submittedData = $submittedData;

        return $this;
    }

/**
 * True to process data without the form name as key.
 *
 * @param bool $flattenData Flatten data
 * @return bool Flatten data
 */
    public function flattenData($flattenData = null) {
        if ($flattenData === null) {
            return $this->_flattenData;
        }
        $this->_flattenData = $flattenData;
        return $this;
    }

/**
 * Add element/group/button/fieldset to form.
 *
 * @param string|array $name Name or array with config
 * @param array $elements Fieldset/Group elements configuration
 * @param array $config Fieldset/Group configuration
 * @return \Nata\Form\Form
 */
    public function add($name, $elements = [], $config = []) {
        if (!empty($elements) || (is_array($name) && isset($name['group']))) {
            if (!is_array($name)) {
                $name = array(
                    'group' => $name,
                    'elements' => $elements
                ) + $config;
            }
            return $this->groups()->load($name);
        } elseif (isset($name['button']) || (isset($name['type']) && in_array($name['type'], array('button', 'submit', 'link')))) {
            return $this->buttons()->load($name);
        } else {
            return $this->elements()->load($name);
        }
    }

/**
 * Add new fieldset.
 *
 * @param string|array $fieldset Fieldset name string or config
 * @param array $config Fieldset configuration
 * @return void
 */
    public function addFieldset($fieldset, $config = array()) {
        $this->fieldsets()->load($fieldset, $config);
    }

/**
 * Add new element.
 *
 * @param string|array $element Element unique name or config array
 * @param array $config Element configuration
 * @return void
 */
    public function addElement($element, $config = array()) {
        $this->elements()->load($element, $config);
    }

/**
 * Add new group.
 *
 * @param string|array $group Group unique name or config array
 * @param array $config Group configuration
 * @return void
 */
    public function addGroup($group, $config = array()) {
        $this->groups()->load($group, $config);
    }

/**
 * Add new button.
 *
 * @param string|array $button Button unique name or config array
 * @param array $config Button configuration
 * @return void
 */
    public function addButton($button, $config = array()) {
        $this->buttons()->load($button, $config);
    }

/**
 * Fieldset collection instance.
 *
 * @return \Nata\Form\FieldsetCollection
 */
    public function fieldsets() {
        if ($this->_fieldsets === null) {
            $this->_fieldsets = new FieldsetCollection($this);
        }
        return $this->_fieldsets;
    }

/**
 * Elements Collection instance.
 *
 * @return \Nata\Form\ElementCollection
 */
    public function elements() {
        if ($this->_elements === null) {
            $this->_elements = new ElementCollection($this);
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
            $this->_groups = new GroupCollection($this);
        }
        return $this->_groups;
    }

/**
 * Buttons collection instance.
 *
 * @return \Nata\Form\ButtonCollection
 */
    public function buttons() {
        if ($this->_buttons === null) {
            $this->_buttons = new ButtonCollection($this);
        }
        return $this->_buttons;
    }

/**
 * Get/set form's attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attributes() {
        if ($this->_attributes === null) {
            $this->_attributes = new Attributes($this);
            $this->_attributes->set([
                'action' => $this->action(),
                'method' => $this->method(),
                'name' => $this->form(),
                'class' => [
                    'nata',
                    'form-' . $this->form(),
                    'form-' . $this->layout()
                ],
                'role' => 'form',
                'novalidate' => true,
                'data-form' => $this->form(),
                'data-confirm-page-leave' => $this->confirmPageLeave(),
                'id' => 'form-' . $this->id()
            ]);

            if ($this->disable() === true) {
                $this->_attributes->set('disabled', 'disabled');
            }

            if ($this->ajax()) {
                $this->_attributes->addClass('form-ajax');
            }
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
 * Render form.
 *
 * @return string
 */
    public function render() {
        $this->attributes()->addData('detect-changes', $this->config('detectChanges'));

        $this->dispatchEvent('Form.beforeRender');

        $html = '';
        if ($this->fieldsets()->has()) {
            $html .= $this->fieldsets()->render();
        } else {
            $html .= $this->elements()->render();
            $html .= $this->groups()->render();
        }
        $html .= $this->buttons()->render();

        $this->dispatchEvent('Form.afterRender');
        return $html;
    }

/**
 * Check if form is empty.
 *
 * @return bool
 */
    public function isEmpty() {
        if (!$this->submitted()) {
            return true;
        }
        $empty = true;

        if (!$this->fieldsets()->isEmpty() && $empty) {
            $empty = false;
        }
        if (!$this->elements()->isEmpty() && $empty) {
            $empty = false;
        }
        if (!$this->groups()->isEmpty() && $empty) {
            $empty = false;
        }
        return $empty;
    }

/**
 * Check if form was submitted.
 *
 * @return bool
 */
    public function submitted() {
        return $this->submittedData() !== null;
    }

/**
 * Check if form data is outdated.
 * This will use the 'timestamp' option.
 *
 * @return bool
 */
    public function outdated() {
        $timestamp = $this->timestamp();
        if (!$timestamp || !$this->submitted()) {
            return false;
        }
        $formTimestamp = $this->dataManager()->request->get('___timestamp');
        if (!($formTimestamp > 0)) {
            return true;
        }

        return $timestamp->timestamp() > $formTimestamp;
    }

/**
 * Check if form was validated already.
 *
 * @return bool True if validated, false otherwise
 */
    public function validated() {
        return is_bool($this->_isValid);
    }

/**
 * Form validation.
 *
 * @param callable $callback Anonymous function to execute if form validates successfully
 * @return boolean True if valid, false otherwise
 */
    public function isValid($callback = null) {
        if ($this->_isValid === null) {
            $valid = true;
            if (!$this->submitted()) {
                return false;
            }

            $this->dispatchEvent('Form.beforeValidation');

            if (!$this->fieldsets()->isValid() && $valid) {
                $valid = false;
            }
            if (!$this->elements()->isValid() && $valid) {
                $valid = false;
            }
            if (!$this->groups()->isValid() && $valid) {
                $valid = false;
            }

            if (!empty($_FILES)) {
                $valid = false;
            }

            $event = $this->dispatchEvent('Form.afterValidation', compact('valid'));
            $this->_isValid = $event->data('valid');
        }
        return $this->_isValid;
    }

/**
 * Set/get referer URL.
 *
 * This is useful to save the URL that brought
 * the user to this form and get used on cancel buttons or
 * simply redirect to referer page after submittion.
 *
 * @param string $refererUrl Referer URL
 * @return string Referer URL
 */
    public function refererUrl($refererUrl = null) {
        $key = 'Form.' . $this->form() . '.refererUrl';
        $session = $this->_request->getSession();

        if ($refererUrl === null) {
            return $session->read($key);
        }

        $session->write($key, $refererUrl);

        return $this;
    }

/**
 * Consume referer URL.
 *
 * @return string Referer URL
 */
    public function consumeRefererUrl() {
        $session = $this->_request->getSession();

        $key = 'Form.' . $this->form() . '.refererUrl';
        $redir = $session->read($key);
        if ($redir) {
            $session->delete($key);
        }
        return $redir;
    }

/**
 * Set automatically the referer URL.
 *
 * This is useful to save the URL that brought
 * the user to this form and get used on cancel buttons or
 * simply redirect to referer page after submittion.
 *
 * @param string $refererUrl Referer URL
 * @return void
 */
    protected function _autoSetRefererUrl($refererUrl) {
        if ($this->submitted()) {
            return;
        }

        if ($refererUrl !== null) {
            return $this->refererUrl($refererUrl);
        }

        $session = $this->_request->getSession();

        $key = 'Form.' . $this->form() . '.refererUrl';
        $redir = $session->read($key);
        $referer = $this->_request->referer();
        $here = $this->_request->here(true);

        // Same
        if ($this->_getUrIPath($referer) === $this->_getUrIPath($here)) {
            return;
        }

        $session->write($key, $referer);
    }

/**
 * Set/get referer URL.
 *
 * This is useful to save the URL that brought
 * the user to this form and get used on cancel buttons or
 * simply redirect to referer page after submittion.
 *
 * @param string $refererUrl Referer URL
 * @return string Referer URL
 */
    protected function _getUrIPath($uri) {
        return parse_url($uri, PHP_URL_PATH);
    }

/**
 * Form to array.
 *
 * @return array
 */
    public function toArray() {
        return [
            'form' => $this->_form,
            'timestamp' => $this->timestamp() ? $this->timestamp()->timestamp() : null,
            'clear' => $this->_clear,
            'disable' => $this->_disable,
            'isValid' => $this->_isValid,
            'refererUrl' => $this->refererUrl(),
            'fieldsets' => $this->fieldsets()->toArray(),
            'elements' => $this->elements()->toArray(),
            'groups' => $this->groups()->toArray()
        ];
    }

/**
 * JSON serialization.
 *
 * @return array
 */
    public function jsonSerialize(): array {
        return $this->toArray();
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
