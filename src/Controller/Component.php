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

namespace Nata\Controller;

use Nata\Core\NataObject;
use Nata\Event\Listener;

/**
 * Adds common methods across all controller components.
 */
class Component extends NataObject implements Listener {

/**
 * Controller.
 *
 * @var \Nata\Controller\Controller
 */
    protected $_controller;

/**
 * Request.
 *
 * @var \Nata\Http\Request
 */
    protected $_request;

/**
 * Response instance
 *
 * @var \Nata\Http\Response
 */
    protected $_response;

/**
 * Default component configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [];


/**
 * Component constructor.
 *
 * @param \Nata\Controller\Controller $controller Controller instance.
 * @param array $config Component configuration
 * @return void
 */
    public function __construct(Controller $controller = null, $config = []) {
        $this->_controller = $controller;

        if ($this->_controller) {
            $this->_request = $controller->request;
            $this->_response = $controller->response;
        }

        $this->config($config + $this->_defaultConfig);
        $this->initialize($config);

        $this->_controller->eventManager()->on($this);
    }

/**
 * Get current controller instance.
 *
 * @render \Nata\Controller\Controller Controller instance.
 */
    public function getController() {
        return $this->_controller;
    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Startup.
 *
 * @return void
 */
    public function startup($event) {}

/**
 * Called after the controller action is run, but before the view is rendered. You can use this method
 * to perform logic or set view variables that are required on every request.
 *
 * @return void
 */
    public function beforeRender($event) {}

/**
 * Called before the controller action.  You can use this method to configure and customize components
 * or perform logic that needs to happen before each controller action.
 *
 * @return void
 * @link http://book.cakephp.org/2.0/en/controllers.html#request-life-cycle-callbacks
 */
    public function beforeFilter($event) {}

/**
 * Called on controller shutdown.
 *
 * @return void
 */
    public function shutdown($event) {}

/**
 * @see Controller::beforeRedirect()
 */
    public function beforeRedirect($event, $url, $status = null, $exit = true) {}

/**
 * Returns a list of all events that will fire in the controller during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return array(
            'Controller.beforeFilter' => 'beforeFilter',
            'Controller.startup' => 'startup',
            'Controller.beforeRender' => 'beforeRender',
            'Controller.beforeRedirect' => 'beforeRedirect',
            'Controller.shutdown' => 'shutdown'
        );
    }

}
