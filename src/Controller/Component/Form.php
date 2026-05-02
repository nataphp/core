<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\Controller\Component;

use Nata\Form\FormRegistry;
use Nata\Controller\Component;

/**
 * Provides an controller interface to form instances in
 * registry/factory.
 */
class Form extends Component {

/**
 * CSRF field set.
 *
 * @var boolean
 */
    protected $_csrfSet = false;

/**
 * Submitted form registration alias.
 *
 * @var string
 */
    protected $_submitted;


/**
 * Initialization hook method.
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config) {
        $controller = $this->_controller;
        $controller->viewBuilder()->loadHelpers(['Form', 'Alert']);
    }

/**
 * Get form instance.
 *
 * @param string $form Form name
 * @param array $options Form options
 * @return \Nata\Form\Form
 */
    public function get($form = null, array $options = []) {
        $options += [
            'csrfToken' => true
        ];

        $instance = FormRegistry::get($form, $options);
        if (!$instance->ajax() && $options['csrfToken']) {
            $this->_setCsrfField($instance);
        }

        if ($instance->submitted()) {
            $this->_submitted = $form;
        }

        return $instance;
    }

/**
 * Check if form exists.
 *
 * @param string $form Form name
 * @param array $options Form options
 * @return \Nata\Form\Form
 */
    public function exists($form) {
        return FormRegistry::exists($form);
    }

/**
 * Get form alias and options.
 * If form alias is empty, will use current routing parameters to
 * create the alias.
 *
 * @param \Nata\Form\Form $form Form name
 * @param array $options Form options
 * @return array
 */
    protected function _setCsrfField($form) {
        $controller = $this->_controller;

        if (!$controller->components()->has('Csrf')) {
            $controller->loadComponent('Csrf')->validate();
        }

        $request = $controller->request;
        $csrfToken = $request->_csrfToken;

        if (!empty($csrfToken) && !$this->_csrfSet) {
            $form->elements()->load([
                'type' => 'hidden',
                'name' => '_csrfToken',
                'value' => $csrfToken
            ])->attributes()->set('name', '_csrfToken');
            $this->_csrfSet = true;
        }
    }

/**
 * Called after the controller action is run, but before the view is rendered. You can use this method
 * to perform logic or set view variables that are required on every request.
 *
 * @return void
 */
    public function beforeRender($event) {
        if ($this->_submitted) {
            $form = FormRegistry::get($this->_submitted);

            $this->_controller->response->type('json');
            $this->_controller->set('form', $form);

            unset($form);
        }
    }

}
