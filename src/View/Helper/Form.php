<?php
/**
 * NataPHP Framework
 *
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

namespace Nata\View\Helper;

use Nata\View\Helper;
use Nata\Form\FormRegistry;
use Nata\Form\Button;
use Nata\Form\Element;

/**
 * Helper for getting/rendering the Form's elements/groups/fieldsets.
 *
 * ##Examples
 *
 * // Render complete form
 * {Form->render form='Plugin.FormAlias'}
 *
 * // Get specific form instance
 * {Form->get form='Plugin.FormAlias' assign=form}
 * {$form->form()}
 *
 * // Get specific form element/field
 * {Form->get form='Plugin.FormAlias' field='first_name' assign=field}
 * {$field->attributes()}
 *
 * // Render specific fieldset
 * {Form->render form='Plugin.FormAlias' fieldset='basic'}
 *
 * // Open specific form
 * {Form->open form='Plugin.FormAlias'}
 * // <form ...>
 *
 * // After {Form->open ...} the called form instance stays
 * // In the example below we're still rendering an element/field from "Plugin.FormAlias"
 * {Form->render field='first_name'}
 *
 * // When you're done, just close...
 * {Form->close}
 *
 */
class Form extends Helper {

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'form' => null,
        'field' => null,
        'label' => null,
        'theme' => null,
        'config' => [],
        'fields' => null,
        'element' => null,
        'elements' => null,
        'layout' => null,
        'type' => null,
        'first' => null,
        'types' => null,
        'group' => null,
        'groups' => null,
        'fieldset' => null,
        'fieldsets' => null,
        'title' => null,
        'icon' => null,
        'buttons' => null,
        'attrs' => null,
        'class' => null,
        'add_class' => null
    ];

/**
 * Form instance.
 *
 * @var \Nata\Form\Form
 */
    protected $_form;

/**
 * Fieldset instance.
 *
 * @var \Nata\Form\Fieldset
 */
    protected $_fieldset;

/**
 * List of rendered objects.
 *
 * @var array
 */
    protected $_rendered = [];


/**
 * Render <form> or <fieldset> tag with respective attributes.
 *
 * @param array $params Smarty function parameters
 * @return string HTML
 */
    public function open($params, $smarty) {
        $params = $this->_params($params);
        if (!empty($params['fieldset'])) {
            $this->_fieldset = $this->_getFieldset($params, $this->_getForm($params));
            return $this->_openFieldset($this->_fieldset, $params);
        } elseif (!empty($params['form'])) {
            $this->_form = $this->_getForm($params);
            if ($params['config']) {
                $this->_form->config($params['config']);
            }
            $smarty->assign('form', $this->_form);
            return $this->_openForm($this->_form, $params);
        }
    }

/**
 * Render <form> tab and attributes.
 * All 'hidden' fields are also rendered.
 *
 * @param array $params Smarty function parameters
 * @return string Html form/field
 */
    protected function _openForm($form, $params) {
        $theme = $form->theme();
        if ($params['theme']) {
            $theme = $params['theme'];
        }

        $attrs = $form->attributes()->set('data-nata-form-theme', $theme);
        if ($params['class']) {
            $attrs->set('class', $params['class']);
        }

        if ($params['attrs']) {
            $attrs->set($params['attrs']);
        }

        $attrs->addData('detect-changes', $form->config('detectChanges'));

        $html = '<form ' . $attrs . '>';

        return $html;
    }

/**
 * Render <fieldset> tab and attributes.
 *
 * @param \Nata\Form\Fieldset $fieldset Fieldset
 * @return string Html fieldset
 */
    protected function _openFieldset($fieldset) {
        if (!$fieldset) {
            return '<div class="nonexisting-fieldset-container">';
        }

        $attr = $fieldset->attributes()->set([
            'title' => $fieldset->title()
        ]);
        $html = '<fieldset ' . $attr . '>';

        // Hidden fields
        /*
        $params['types'] = ['hidden'];
        $this->_get($params, function ($type) use (&$html) {
            $html .= $type->render();
        });
        */

        return $html;
    }

/**
 * Render </form> HTML.
 *
 * @return string Html form/field
 */
    public function close($params, $smarty) {
        $smarty->clearAssign('form');

        $params = $this->_params($params);
        if ($params['fieldset']) {
            if (!$this->_fieldset) {
                return '</div>';
            }
            $this->_fieldset = null;
            return '</fieldset>';
        }

        if ($this->_form === null && $params['form'] === null) {
            return;
        }

        // Hidden fields
        $params['types'] = ['hidden'];

        $html = '';
        $this->_get($params, function ($element) use (&$html) {
            if (in_array($element->name(), $this->_rendered)) {
                return;
            }

            $html .= $element->render();

            if ($element instanceof Element) {
                $this->_rendered[] = $element->name();
            }
        });

        $this->_form = null;

        $html .= '</form>';

        return $html;
    }

/**
 * Get the form's referer URL currently set.
 *
 * @param array $params Smarty function parameters
 * @return string Referer URL
 */
    public function refererUrl($params) {
        $params = $this->_params($params);
        $form = $this->_getForm($params);
        $refererUrl = $form->refererUrl();
        return $refererUrl ? $this->getRouteUrl($refererUrl) : $refererUrl;
    }

/**
 * Get elements.
 *
 * @param array $params Smarty function parameters
 * @return mixed Elements/Groups instance(s)
 */
    public function get($params) {
        $params = $this->_params($params);
        return $this->_get($params);
    }

/**
 * Render elements.
 *
 * @param array $params Smarty function parameters
 * @return string Html form/field
 */
    public function render($params, $smarty) {
        $params = $this->_params($params);
        $form = $this->_getForm($params);
        $html = '';

        $instances = $this->_get($params, function ($instance) use (&$html, $form, $params) {
            // Add form attribute to submit button
            if ($instance instanceof Button && $instance->type() == 'submit') {
                $instance->attributes()->set('form', $form->attributes()->get('id'));
            }

            if ($params['label'] === false) {
                $instance->label(false);
            }

            if ($instance instanceof Element) {
                $this->_rendered[] = $instance->name();
            }

            $html .= $instance->render();
        });

        if ($instances === false) {
            $html .= $this->open($params, $smarty);
            $html .= $this->_getForm($params)->render();
            $html .= $this->close($params, $smarty);
        }

        return $html;
    }

/**
 * Get elements.
 *
 * @param array $params Smarty function parameters
 * @param \Closure $each Element iteration
 * @return mixed Elements/Groups instance(s) or
 */
    protected function _get($params, $each = null) {
        $instances = $elements = [];

        $collection = $this->_getCollection($params);
        if (!empty($collection)) {

            if (!empty($params['types'])) {
                $params['fields'] = array_merge((array)$params['fields'], $this->_getByType($params, $collection));
            }

            if (!empty($params['buttons'])) {
                if ($params['buttons'] === true) {
                    $params['buttons'] = '*';
                }
                $instance = $this->_getForm($params)->buttons();
                $elements = $params['buttons'];
            } elseif (!empty($params['fields'])) {
                $instance = $collection->elements();
                $elements = $params['fields'];
            } elseif (!empty($params['groups'])) {
                $elements = $params['groups'];
                $instance = $collection->groups();
            } elseif (!empty($params['fieldsets'])) {
                $elements = $params['fieldsets'];
                $instance = $collection->fieldsets();
            } else {
                return false;
            }

            $theme = $params['theme'];
            $class = $params['class'];

            if (is_string($elements) && strpos($elements, '*') !== false) {
                $elements = $instance->get($elements);
            }

            foreach ($elements as $name => $element) {
                if (is_string($element) && $instance->has($element)) {
                    $name = $element;
                    $instances[$name] = $instance->get($element);
                } elseif (is_object($element)) {
                    $instances[$name] = $element;
                } else {
                    continue;
                }

                if ($params['layout'] !== null) {
                    $instances[$name]->layout($params['layout']);
                }

                if ($class) {
                    $instances[$name]->attributes()->set('class', $class);
                }
                if ($params['add_class']) {
                    $instances[$name]->attributes()->addClass($params['add_class']);
                }

                if ($theme && method_exists($instances[$name], 'theme')) {
                    $instances[$name]->theme($theme);
                }

                if ($each) {
                    $each($instances[$name]);
                }

                if ($params['first'] == true) {
                    break;
                }
            }
        }
        return $params['first'] ? array_shift($instances) : $instances;
    }

/**
 * Get elements by type.
 *
 * @param array $params Smarty function parameters
 * @param mixed $collection Collection instance that holds elements
 * @return array Array of elements names
 */
    protected function _getByType($params, $collection = null) {
        $types = (array)$params['types'];
        $fields = [];

        if ($collection === null) {
            $collection = $this->_getCollection($params);
        }

        $elements = $collection->elements();

        foreach ($types as $type) {
            $fields = array_merge($fields, (array)$elements->typeMap($type));
        }

        return $fields;
    }

/**
 * Get element's collection.
 *
 * @param array $params Smarty function parameters
 * @return mixed Form/Fieldset/Groups instance
 */
    protected function _getCollection($params) {
        $collection = $this->_getForm($params);
        if ($this->_fieldset || (!empty($params['fieldsets']) && (!empty($params['groups']) || !empty($params['fields']) || !empty($params['types'])))) {
            $collection = $this->_getFieldset($params, $collection);
        }
        if (!empty($params['groups']) && (!empty($params['fields']) || !empty($params['types']))) {
            $collection = $this->_getGroup($params, $collection);
        }
        return $collection;
    }

/**
 * Get group instance.
 *
 * @param array $params Smarty function parameters
 * @return \Nata\Form\Group Group instance
 */
    protected function _getGroup($params, $collection = null) {
        $group = array_shift($params['groups']);
        if ($collection && $collection->groups()->has($group)) {
            return $collection->groups()->get($group);
        }
    }

/**
 * Get fieldset instance.
 *
 * @param array $params Smarty function parameters
 * @return \Nata\Form\Fieldset Fieldset instance
 */
    protected function _getFieldset($params, $collection = null) {
        if ($this->_fieldset) {
            return $this->_fieldset;
        }

        $fieldset = array_shift($params['fieldsets']);

        if ($collection && $collection->fieldsets()->has($fieldset)) {
            return $collection->fieldsets()->get($fieldset);
        }
    }

/**
 * Get form instance.
 *
 * @param array $params Smarty function parameters
 * @return \Nata\Form\Form Form instance
 */
    protected function _getForm(array $params = null) {
        if ($this->_form) {
            return $this->_form;
        }

        if (!empty($params) && isset($params['form'])) {
            return FormRegistry::get($params['form'], (array)$params['config']);
        }

        return $this->_view->get('form');
    }

/**
 * Normalize parameters.
 *
 * @param array $params Smarty parameters
 * @return array Normalized parameters
 */
    protected function _params($params) {
        $params = $this->_normalizeParams($params);
        $params = $this->_normalizeParam('button', $params);
        $params = $this->_normalizeParam('field', $params);
        $params = $this->_normalizeParam('type', $params);
        $params = $this->_normalizeParam('group', $params);
        $params = $this->_normalizeParam('fieldset', $params);
        return $params;
    }

/**
 * Normalize parameter in given paramenters array.
 *
 * @param string $name Smarty parameter name
 * @param array $params Smarty function parameters
 * @return array Html form/field
 */
    protected function _normalizeParam($name, $params) {
        $plural = $name . 's';

        if (!empty($params[$name])) {
            $params[$plural] = $params[$name];
        }

        $param = $params[$plural];

        if (!empty($param) && is_string($param) && strpos($param, '*') === false) {
            $param = explode(',', $param);
            $param = array_map(function ($value) {
                return trim($value);
            }, $param);
        }

        $params[$plural] = $param;

        return $params;
    }

}
