<?php
/**
 * NataPHP Framework
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\Controller;

use Nata\Routing\Router;
use Nata\Core\NataObject;
use Nata\Core\Configure;
use Nata\Utility\Inflector;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Event\Listener;
use Nata\Utility\Hash;
use Nata\Utility\Text;
use Nata\ORM\Table;
use Nata\View\ViewBuilder;
use RuntimeException;
use ReflectionMethod;
use ReflectionException;
use PrivateActionException;
use MissingActionException;
use Nata\View\Json;

class Controller extends NataObject implements Listener {

/**
 * The name of this controller.
 * Controller names are plural, named after the model they manipulate.
 *
 * @var string
 */
    public $name;

/**
 * The name of the action that will be executed.
 *
 * @var string
 */
    public $action;

/**
 * The name of the action that will be executed.
 *
 * @var string
 */
    public $passedArgs = [];

/**
 * Request instance.
 *
 * @var \Nata\Http\Request
 */
    public Request $request;

/**
 * Response instance.
 *
 * @var \Nata\Http\Response
 */
    public Response $response;

/**
 * Methods.
 *
 * @var array
 */
    public $methods = [];

/**
 * List of Components a controller will use
 *
 * @var \Nata\Controller\ComponentRegistry
 */
    private $_components;

/**
 * Name of the plugin.
 *
 * @var string
 */
    public $plugin;

/**
 * Render the view.
 *
 * @var boolean
 */
    public $render = true;

/**
 * Set to true to automatically render the view
 * after action logic.
 *
 * @var boolean
 */
    public $autoRender = true;

/**
 * View class instance.
 *
 * @var \Nata\View\View
 */
    protected $_View;

/**
 * View class instance
 *
 * @var array
 */
    protected $_viewVars = [];

/**
 * View class to be built.
 *
 * @var string
 */
    public $viewClass;

/**
 * List of view helpers to load.
 *
 * @var array
 */
    public $helpers = [];

/**
 * Model alias class name for controller.
 *
 * @var string
 */
    public $modelClass;

/**
 * Load model class by reference in controller.
 * If set to 'true' the model is lazy loaded no matter the
 * current controller name. *
 * If 'false', lazy loads the model only in the controller with same name.
 *
 * @var bool
 */
    protected $_lazyLoadModel = true;

/**
 * Page title.
 *
 * @var array
 */
    public $title = [];

/**
 * Page description.
 *
 * @var string
 */
    public $description;

/**
 * Array with pagination options (overwrites default settings).
 *
 * @var array
 */
    public $paginate = [];


/**
 * Constructor.
 *
 * @param \Nata\Http\Request $request Request instance.
 * @param \Nata\Http\Response $response Response instance.
 * @return void
 */
    public function __construct(Request $request, Response $response) {
        if ($this->name === null) {
            $this->name = Inflector::camelize($request->params['controller']);
        }

        $childMethods = get_class_methods($this);
        $parentMethods = get_class_methods('Nata\Controller\Controller');
        $this->methods = array_diff($childMethods, $parentMethods);

        if ($request instanceof Request) {
            $this->setRequest($request);
        }

        if ($response instanceof Response) {
            $this->response = $response;
        }

        $this->modelClass = ($this->plugin ? $this->plugin . '.' : '') . $this->name;

    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @return void
 */
    public function initialize() {}

/**
 * Build view instance.
 *
 * @param string $viewClass View class name
 * @return \Nata\View\View
 */
    public function viewBuilder($viewClass = null) {
        if ($viewClass !== null || $this->_View === null) {
            $view = ViewBuilder::build(
                    $this->_viewClass($viewClass),
                    $this->request,
                    $this->response
                )
                ->plugin($this->plugin)
                ->loadHelpers($this->helpers)
                ->templatePath($this->name)
                ->template(Inflector::underscore($this->request->action))
                ->layout('layout');

            if ($prefix = $this->request->prefix) {
                $view->basePath(Inflector::camelize($prefix));
            }

            // htmx: return only the template fragment (no layout) when request sends HX-Request header
            if ($this->request->header('HX-Request') === 'true') {
                $view->layout(false);
            }

            $this->_View = $view;
        }
        return $this->_View;
    }

/**
 * Get view class to be built.
 *
 * @param string $viewClass View class name
 * @return void
 */
    protected function _viewClass($viewClass) {
        if ($viewClass === null) {
            $viewClass = $this->viewClass;
        }

        if ($viewClass === null) {
            if (!empty($this->request->ext)) {
                $viewClass = $this->request->ext;
            } elseif ($this->request->is('ajax')) {
                $accepts = $this->request->accepts();
                if (!$accepts) {
                    $accepts = ['text/html'];
                }
                [$acceptsType] = $accepts;
                [$foo, $acceptsType] = explode('/', $acceptsType);

                if ($acceptsType === '*' || strtolower($acceptsType) === 'html') {
                    $acceptsType = 'View';
                }

                $viewClass = $acceptsType;
            }
        }

        return $viewClass;
    }

/**
 * Get the component registry for this controller.
 *
 * @return \Nata\Controller\ComponentRegistry
 */
    public function components() {
        if ($this->_components === null) {
            $this->_components = new ComponentRegistry($this);
        }
        return $this->_components;
    }

/**
 * Loads the defined components using the Component factory.
 *
 * @return void
 */
    protected function _loadComponents() {
        if (empty($this->components)) {
            return;
        }

        $components = $this->components()->normalizeArray($this->components);
        foreach ($components as $properties) {
            $this->loadComponent($properties['class'], $properties['config']);
        }
    }

/**
 * Add a component to the controller's registry.
 *
 * This method will also set the component to a property.
 * For example:
 *
 * ```
 * $this->loadComponent('Acl.Permission');
 * ```
 *
 * Will result in a `Permission` property being set.
 *
 * @param string $name The name of the component to load.
 * @param array $config The config for the component.
 * @return \Nata\Controller\Component
 */
    public function loadComponent($name, array $config = []) {
        [$foo, $prop] = pluginSplit($name);
        $this->{$prop} = $this->components()->load($name, $config);
        return $this->{$prop};
    }

/**
 * Lazy loading of Table or Component or Service.
 *
 * @param string $name Class name
 * @return Table|Component
 */
    public function __get($name) {
        if ($this->components()->exists($name)) {
            return $this->{$name} = $this->components()->load($name);
        }

        if (!$this->_lazyLoadModel) {
            [$plugin, $class] = pluginSplit($this->modelClass, true);
            if ($class !== $name) {
                return false;
            }
        }

        $plugin = ($this->plugin ? $this->plugin . '.' : '');

        return $this->loadInModel($plugin . $name);
    }

/**
 * Perform the startup process for this controller.
 * Fire the Components and Controller callbacks in the correct order.
 *
 * - Initializes components, which fires their `initialize` callback
 * - Calls the controller `beforeFilter`.
 * - triggers Component `startup` methods.
 */
    public function startupProcess() {
        $this->dispatchEvent('Controller.initialize');
        $this->dispatchEvent('Controller.startup');

        $this->_loadComponents();
    }

/**
 * Returns a list of all events that will fire in the controller during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return [
            'Controller.beforeStartup' => 'beforeStartup',
            'Controller.initialize' => 'beforeFilter',
            'Controller.startup' => 'startup',
            'Controller.beforeRender' => 'beforeRender',
            'Controller.afterRender' => 'afterRender',
            'Controller.shutdown' => 'beforeShutdown',
            'Controller.beforeRedirect' => [
                'callable' => 'beforeRedirect',
                'passParams' => true
            ]
        ];
    }

/**
 * Perform the various shutdown processes for this controller.
 * Fire the Components and Controller callbacks in the correct order.
 *
 * - triggers the component `shutdown` callback.
 * - calls the Controller's `afterFilter` method.
 *
 * @return void
 */
    public function shutdownProcess() {
        $this->dispatchEvent('Controller.shutdown');
    }

/**
 * Sets the request objects and configures a number of controller properties
 * based on the contents of the request.  The properties that get set are
 *
 * - $this->request - To the $request parameter
 * - $this->plugin - To the $request->params['plugin']
 * - $this->view - To the $request->params['action']
 * - $this->autoLayout - To the false if $request->params['bare']; is set.
 * - $this->autoRender - To false if $request->params['return'] == 1
 * - $this->passedArgs - The the combined results of params['pass]
 *
 * @param Request $request
 */
    public function setRequest(Request $request) {
        $this->request = $request;
        $this->plugin = $request->plugin ? Inflector::camelize($request->plugin) : null;
        if (isset($request->params['pass'])) {
            $this->passedArgs = $request->params['pass'];
        }
    }

/**
 * Dispatches the controller action. Checks that the action
 * exists and isn't private.
 *
 * @param Request $request
 * @return mixed The resulting response.
 * @throws PrivateActionException When actions are not public or prefixed by _
 * @throws MissingActionException When actions are not defined and scaffolding is
 *    not enabled.
 */
    public function invokeAction(Request $request) {
        try {
            $action = Inflector::variable($request->params['action']);
            $method = new ReflectionMethod($this, $action);
            if ($this->_isPrivateAction($method, $request)) {
                throw new PrivateActionException([
                    'action' => $action,
                    'class' => get_class($this),
                    'controller' => Inflector::camelize($request->params['controller'])
                ]);
            }

            $this->action = $action;

            return $method->invokeArgs($this, $request->params['pass']);
        } catch (ReflectionException $e) {
            $this->_throwMissingAction($action);
        }

    }

/**
 * Throws missing action.
 *
 * @param string $action Action name
 * @throws \MissingActionException When actions are not defined and scaffolding is
 *    not enabled.
 */
    private function _throwMissingAction($action) {
        throw new MissingActionException([
            'action' => $action,
            'class' => get_class($this),
            'controller' => Inflector::camelize($this->request->params['controller'])
        ]);
    }

/**
 * Check if the request's action is marked as private, with an underscore,
 * or if the request is attempting to directly accessing a prefixed action.
 *
 * @param ReflectionMethod $method The method to be invoked.
 * @param Request $request The request to check.
 * @return boolean
 */
    protected function _isPrivateAction(ReflectionMethod $method, Request $request) {
        $privateAction = (
            $method->name[0] === '_' ||
            !$method->isPublic() ||
            !in_array($method->name,  $this->methods)
        );

        $prefixes = Router::prefixes();
        if (!$privateAction && !empty($prefixes)) {
            if (empty($request->params['prefix']) && strpos($request->params['action'], '_') > 0) {
                [$prefix] = explode('_', $request->params['action']);
                $privateAction = in_array($prefix, $prefixes);
            }
        }

        return $privateAction;
    }

/**
 * Internally redirects one action to another. Does not perform another HTTP request unlike Controller::redirect()
 *
 * Examples:
 *
 * {{{
 * setAction('another_action');
 * setAction('action_with_parameters', $parameter1);
 * }}}
 *
 * @param string $action The new action to be 'redirected' to
 * @param mixed  Any other parameters passed to this method will be passed as
 *    parameters to the new action.
 * @return mixed Returns the return value of the called action
 */
    public function setAction($action) {
        $this->request->params['action'] = $action;

        $this->viewBuilder()->template($action);

        $args = func_get_args();
        unset($args[0]);

        $action = Inflector::variable($action);

        if (!in_array($action, $this->methods)) {
            $this->_throwMissingAction($action);
        }

        return call_user_func_array([&$this, $action], $args);
    }

/**
 * Method to check that an action is accessible from a URL.
 *
 * Override this method to change which controller methods can be reached.
 * The default implementation disallows access to all methods defined on Nata\Controller\Controller,
 * and allows all public methods on all subclasses of this class.
 *
 * @param string $action The action to check.
 * @return bool Whether or not the method is accessible from a URL.
 */
    public function isAction($action) {
        $action = Inflector::variable($action);

        try {
            $method = new ReflectionMethod($this, $action);
        } catch (ReflectionException $e) {
            return false;
        }

        if (!$method->isPublic()) {
            return false;
        }

        if ($method->getDeclaringClass()->name === '\Nata\Controller\Controller') {
            return false;
        }

        return true;
    }

/**
 * Returns the referring URL for this request.
 *
 * @param string $default Default URL to use if HTTP_REFERER cannot be read from headers
 * @param boolean $local If true, restrict referring URLs to local server
 * @return string Referring URL
 * @link http://book.cakephp.org/2.0/en/controllers.html#Controller::referer
 */
    public function referer($default = null, $local = false) {
        if ($this->request) {
            $referer = $this->request->referer($local);

            if ($referer === '/' && $default !== null) {
                return Router::url($default, true);
            }

            return $referer;
        }

        return '/';
    }

/**
 * Handles pagination of records in Table objects.
 *
 * Will load the referenced Table object, and have the PaginatorComponent
 * paginate the query using the request date and settings defined in `$this->paginate`.
 *
 * This method will also make the PaginatorHelper available in the view.
 *
 * @param \Nata\ORM\Table|string|\Nata\ORM\Query|null $object Table to paginate
 * (e.g: Table instance, 'TableName' or a Query object)
 * @return \Nata\ORM\Entity|array Query results
 * @link http://book.cakephp.org/3.0/en/controllers.html#Controller::paginate
 * @throws \RuntimeException When no compatible table object can be found.
 */
    public function paginate($object = null) {
        if (is_object($object)) {
            $table = $object;
        }

        if (is_string($object) || $object === null) {
            $try = [$object, $this->modelClass];

            foreach ($try as $tableName) {
                if (empty($tableName)) {
                    continue;
                }

                $table = $this->loadModel($tableName);
                break;
            }
        }

        $this->loadComponent('Paginator');

        if (empty($table)) {
            throw new RuntimeException('Unable to locate an object compatible with paginate.');
        }

        return $this->Paginator->paginate($table, $this->paginate);
    }

/**
 * Redirects to given $url, after turning off $this->autoRender.
 * Script execution is halted after the redirect.
 *
 * @param string|array $url A string or array-based URL pointing to another location within the app,
 *     or an absolute URL
 * @param integer $status Optional HTTP status code (eg: 404)
 * @param boolean $exit If true, exit() will be called after the redirect
 * @return mixed void if $exit = false. Terminates script if $exit = true
 * @link http://book.cakephp.org/2.0/en/controllers.html#Controller::redirect
 */
    public function redirect($url, $status = 302, $exit = true) {
        $this->autoRender = false;

        if (is_array($status)) {
            extract($status, EXTR_OVERWRITE);
        }

        $event = $this->dispatchEvent('Controller.beforeRedirect', [$url, $status, $exit]);
        $event->data('objectCollectionOptions', [
            'break' => true,
            'breakOn' => false,
            'collectReturn' => true
        ]);

        $this->eventManager()->dispatch($event);

        if ($event->isStopped()) {
            return;
        }

        $response = $event->result;

        [
            'url' => $url,
            'status' => $status,
            'exit' => $exit
        ] = $this->_parseBeforeRedirect($response, $url, $status, $exit);

        if (is_string($status)) {
            $codes = array_flip($this->response->httpCodes());

            if (isset($codes[$status])) {
                $status = $codes[$status];
            }

        }

        // Verify if Auth component is loaded and if it is, if is authenticated.
        //
        // @todo Implement JWT for AJAX/JSON requests
        // @see https://jwt.io/introduction/
        if ($this->request->is('ajax')) {
            $this->response->header('Nata-Status', $status);
            $this->response->header('Nata-Location', Router::url($url, true));
            $status = 201;
            $authLoaded = $this->components()->has('Auth');
            if (!$authLoaded || ($authLoaded && !$this->Auth->user())) {
                $this->response->body('');
            }

            if ($this->_View instanceof Json || $this->request->accepts('json') || $this->response->type() === 'application/json') {
                if ($this->response->body() === '') {
                    $this->response->body('{}');
                }
            }
        } else if ($url !== null) {
            $this->response->header('Location', Router::url($url, true));
        }

        if ($status) {
            $this->response->statusCode($status);
        }

        if ($exit) {
            $this->response->send();
            $this->_stop();
        }
    }

/**
 * Parse beforeRedirect Response
 *
 * @param mixed $response Response from beforeRedirect callback
 * @param string|array $url The same value of beforeRedirect
 * @param integer $status The same value of beforeRedirect
 * @param boolean $exit The same value of beforeRedirect
 * @return array Array with keys url, status and exit
 */
    protected function _parseBeforeRedirect($response, $url, $status, $exit) {
        if (is_array($response) && array_key_exists(0, $response)) {

            foreach ($response as $resp) {
                if (is_array($resp) && isset($resp['url'])) {
                    extract($resp, EXTR_OVERWRITE);
                } elseif ($resp !== null) {
                    $url = $resp;
                }
            }

        } elseif (is_array($response)) {
            extract($response, EXTR_OVERWRITE);
        }

        return ['url' => $url, 'status' => $status, 'exit' => $exit];
    }

/**
 * Saves a variable for use inside a view template.
 * This method is the same as in \Nata\View\View::set(),
 * In the future this must be in some shared trait.
 *
 * @param string|array $one A string or an array of data to set.
 * @param string|array $two Value in case $one is a string (which then works as the key).
 *   Unused if $one is an associative array, otherwise serves as the values to $one's keys.
 * @return $this
 */
    public function set($one, $two = null) {
        if (is_array($one)) {
            if (is_array($two)) {
                $data = array_combine($one, $two);
            } else {
                $data = $one;
            }
        } else {
            if (strpos($one, '.') !== false) {
                $this->_viewVars = Hash::insert($this->_viewVars, $one, $two);
                return $this;
            }
            $data = [$one => $two];
        }

        $this->_viewVars = $data + $this->_viewVars;

        return $this;
    }

/**
 * Gets a variable for use inside a view template.
 * This method is the same as in \Nata\View\View::get(),
 * In the future this must be in some shared trait.
 *
 * @param string $key A string as key's variable.
 * @return mixed Value from view vars
 */
    public function get($key) {
        return Hash::get($this->_viewVars, $key);
    }

/**
 * Set/Get page title.
 *
 * @param string|array ...$title A string or an array with the title.
 * @return string|$this
 */
    public function title() {
        $args = func_get_args();
        if (func_num_args() === 0) {
            return $this->title;
        }
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        $this->title = $args;
        return $this;
    }

/**
 * Add to page title.
 *
 * @param string|array $title A string or an array with the title.
 * @return string|$this
 */
    public function addTitle() {
        $args = func_get_args();
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        $this->title = array_merge($args, $this->title);
        return $this;
    }

/**
 * Create page title.
 *
 * @return string Page title
 */
    private function _title() {
        return implode(
            Configure::read('Config.pageTitleSeparator'),
            $this->title
        );
    }

/**
 * Set page description.
 *
 * @param string $description A string or an array with the title
 * @return string|$this
 */
    public function description(?string $description) {
        if (func_num_args() === 0) {
            return $this->description;
        }

        if ($description) {
            $description = Text::truncate(strip_tags($description), 155);
        }

        $this->description = $description;

        return $this;
    }

/**
 * Instantiates the correct view class, hands it its data, and uses it to render the view output.
 *
 * @param string $template Template to use for rendering
 * @param string $layout Layout to use
 * @return \Nata\Http\Response A response object containing the rendered view.
 */
    public function render($template = null, $layout = null) {
        if (!$this->autoRender && $template === null) {
            return $this->response;
        }

        $event = $this->dispatchEvent('Controller.beforeRender');

        if ($event->isStopped()) {
            $this->autoRender = false;
            return $this->response;
        }

        $view = $this->_prepareView($template, $layout);
        $this->response->body($view->render());

        // In Controller::render(), after $this->response->body($view->render())
        if ($this->request->header('HX-Request') === 'true' && $title = $this->_title()) {
            // Add title to the response body
            $body = $this->response->body();
            // Workaround because HTTP does not handle very well UTF-8 characters
            $body .= '<title hx-swap-oob="true">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
            $this->response->body($body);
        }

        $event = $this->dispatchEvent('Controller.afterRender');
        $this->autoRender = false;

        return $this->response;
    }

/**
 * Prepare template configuration.
 *
 * @param string $template Template to use for rendering
 * @param string $layout Layout to use
 * @return \Nata\View\View
 */
    private function _prepareView($template, $layout) {
        $view = $this->viewBuilder();

        if ($template !== null) {
            $view->template($template);
        }

        if ($layout !== null) {
            $view->layout($layout);
        }

        if ($title = $this->_title()) {
            $view->set('title', $title);
        }
        if ($this->description !== false && $this->description !== null) {
            $view->set('description', $this->description);
        }

        return $view->set($this->_viewVars);
    }

/**
 * Called before the controller action. You can use this method to configure and customize components
 * or perform logic that needs to happen before each controller action.
 *
 * @return void
 */
    public function beforeShutdown() {}
    public function afterShutdown() {}

/**
 * Called after the controller action is run, but before the view is rendered. You can use this method
 * to perform logic or set view variables that are required on every request.
 *
 * @return void
 * @link http://book.cakephp.org/2.0/en/controllers.html#request-life-cycle-callbacks
 */
    public function beforeRender() {}
    public function afterRender() {}

/**
 * Called before the controller action. You can use this method to configure and customize components
 * or perform logic that needs to happen before each controller action.
 *
 * @return void
 * @link http://book.cakephp.org/2.0/en/controllers.html#request-life-cycle-callbacks
 */
    public function beforeFilter() {}

/**
 * Called before the controller action. You can use this method to configure and customize components
 * or perform logic that needs to happen before each controller action.
 *
 * @return void
 */
    public function startup() {}

/**
 * Called before the controller startup process.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param \Nata\Http\Request $request Request instance
 * @param \Nata\Http\Response $response Response instance
 */
    public function beforeStartup($event, $request, $response) {}

/**
 * The beforeRedirect method is invoked when the controller's redirect method is called but before any
 * further action. If this method returns false the controller will not continue on to redirect the request.
 * The $url, $status and $exit variables have same meaning as for the controller's method. You can also
 * return a string which will be interpreted as the url to redirect to or return associative array with
 * key 'url' and optionally 'status' and 'exit'.
 *
 * @param \Nata\Event\Event $event Event instance
 * @param string|array $url A string or array-based URL pointing to another location within the app,
 *     or an absolute URL
 * @param integer $status Optional HTTP status code (eg: 404)
 * @param boolean $exit If true, exit() will be called after the redirect
 * @return mixed
 *   false to stop redirection event,
 *   string controllers a new redirection url or
 *   array with the keys url, status and exit to be used by the redirect method.
 * @link http://book.cakephp.org/2.0/en/controllers.html#request-life-cycle-callbacks
 */
    public function beforeRedirect($event, $url, $status = null, $exit = true) {}

}
