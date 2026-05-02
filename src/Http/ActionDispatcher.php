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

namespace Nata\Http;

use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Utility\Inflector;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Controller\Controller;
use Nata\Controller\Exception\MissingControllerException;
use ReflectionClass;

/**
 * Dispatcher takes the URL information, parses it for parameters and
 * tells the involved controllers what to do.
 *
 * This is the heart of Nata's operation.
 *
 * Dispatcher converts Requests into controller actions. It uses the dispatched \Nata\Http\Request
 * to locate and load the correct controller. If found, the requested action is called on
 * the controller.
 */
class ActionDispatcher extends NataObject  {

/**
 * Event manager, used to handle dispatcher filters
 *
 * @var Nata\Event\Manager
 */
    protected $_eventManager;


/**
 * Constructor.
 */
    public function __construct($eventManager) {
        $this->_eventManager = $eventManager;
    }

/**
 * Dispatches and invokes given Request, handing over control to the involved controller. If the controller is set
 * to autoRender, via Controller::$autoRender, then Dispatcher will render the view.
 *
 * Actions in Nata can be any public method on a controller, that is not declared in Controller.  If you
 * want controller methods to be public and in-accessible by URL, then prefix them with a `_`.
 * For example `public function _loadPosts() { }` would not be accessible via URL.  Private and protected methods
 * are also not accessible via URL.
 *
 * If no controller of given name can be found, invoke() will throw an exception.
 * If the controller is found, and the action is not found an exception will be thrown.
 *
 * @param \Nata\Http\Request $request Request object to dispatch.
 * @param \Nata\Http\Response $response Response object to put the results of the dispatch into.
 * @return string|void if `$request['return']` is set then it returns response body, null otherwise
 * @throws MissingControllerException When the controller is missing.
 */
    public function dispatch(Request $request, Response $response) {
        $controller = $this->_getController($request, $response);

        if (!($controller instanceof Controller)) {
            $this->_throwMissingControllerException($request);
        }

        // Invoke various methods in the $controller object, including action and render the response
        return $this->_invoke($controller, $request, $response);
    }

/**
 * Initializes the components and models a controller will be using.
 * Triggers the controller action, and invokes the rendering if Controller::$autoRender is true and echo's the output.
 * Otherwise the return value of the controller action are returned.
 *
 * @param \Nata\Controller\Controller $controller Controller to invoke
 * @param \Nata\Http\Request $request The request object to invoke the controller for.
 * @param \Nata\Http\Response $response The response object to receive the output
 * @return Response the resulting response object
 */
    protected function _invoke(Controller $controller, Request $request, Response $response) {
        $controller->startupProcess();

        $render = true;

        $result = $controller->invokeAction($request);

        $response->header([
            'x-nataphp-prerendering-generated' => gentime('App', false, false)
        ]);

        if ($result instanceof Response) {
            $render = false;
            $response = $result;
        }

        if ($render && $controller->autoRender) {
            $response = $controller->render();
        } elseif ($response->body() === null) {
            $response->body($result);
        }

        $controller->shutdownProcess();

        return $response;
    }

/**
 * Get controller to use, either plugin controller or application controller.
 *
 * @param \Nata\Http\Request $request Request
 * @param \Nata\Http\Response $response Response
 * @return mixed name of controller if not loaded, or object if loaded
 */
    protected function _getController($request, $response) {
        $ctrlClass = $this->_loadController($request);
        if (!$ctrlClass) {
            return false;
        }

        $reflection = new ReflectionClass($ctrlClass);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return false;
        }

        return $reflection->newInstance($request, $response);
    }

/**
 * Load controller and return controller classname.
 *
 * @param \Nata\Http\Request $request Request
 * @return string|bool Name of controller class name
 */
    protected function _loadController($request) {
        $pluginName = $pluginPath = $controller = null;
        $namespace = 'Controller';

        if ($request->params['plugin']) {
            $pluginName = Inflector::camelize($request->params['plugin']);
            $pluginPath = $pluginName . '.';
        }

        if ($request->controller) {
            $controller = Inflector::camelize($request->params['controller']);
        }

        if ($prefix = $request->prefix) {
            $namespace .= '/' . Inflector::camelize($prefix);
        }

        if ($pluginPath . $controller) {
            $className = App::className($pluginPath . $controller, $namespace);
            if (!$className) {
                $className = App::className($controller, 'Controller');
            }
            return $className;
        }

        return false;
    }

/**
 * Throw missing controller exception.
 *
 * @param \Nata\Http\Request $request Request
 * @throws \MissingControllerException
 */
    protected function _throwMissingControllerException($request) {
        extract($request->params);

        $plugin = Inflector::camelize($plugin);
        $controller = Inflector::camelize($controller);

        $className = (!empty($plugin) ? $plugin : 'App');
        $className .= '\Controller';

        if (!empty($prefix)) {
            $prefix = Inflector::camelize($prefix);
            $className .= '\\' . $prefix;
        }

        $namespace = $className;
        $className .= '\\' . Inflector::camelize($request->params['controller']);

        throw new MissingControllerException([
            'class' => $className,
            'url' => $request->here(),
            'plugin' => $plugin
        ] + compact('plugin', 'controller', 'namespace'));
    }

}
