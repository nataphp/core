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

use Nata\Core\App;
use Nata\Routing\Router;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;
use Nata\Controller\Controller;
use Nata\Controller\Component;
use Exception;
use ForbiddenException;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\ORM\Entity;

/**
 * User/session authentication component.
 */
class Auth extends Component {

/**
 * Shared settings between authentication adapters.
 *
 * @const string
 */
    const ALL = 'all';

/**
 * Default component configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'authenticate' => ['Form'],
        'loginAction' => [
            'plugin' => null,
            'controller' => 'users',
            'action' => 'login'
        ],
        'logoutAction' => [
            'controller' => 'users',
            'action' => 'logout'
        ],
        'loginRedirect' => null,
        'logoutRedirect' => null,
        'authorize' => false,
        'authError' => '',
        'sessionKey' => 'Auth.User',
        'unauthorizedRedirect' => true
    ];

/**
 * The session key name where the record of the current user is stored. Default
 * key is "Auth.User". If you are using only stateless authenticators set this
 * to false to ensure session is not started.
 *
 * @var string
 */
    protected $_sessionKey = 'Auth.User';

/**
 * Controller actions for which user validation is not required.
 *
 * @var array
 */
    protected $_allowedActions = [];

/**
 * The current user, used for stateless authentication when
 * sessions are not available.
 *
 * @var array
 */
    private $_user = [];

/**
 * The instance of the Authenticate provider that was used for
 * successfully logging in the current user after calling `login()`
 * in the same request
 *
 * @var \Nata\Auth\Base
 */
    protected $_authenticationProvider;

/**
 * The instance of the Authenticate provider that was used for
 * successfully logging in the current user after calling `login()`
 * in the same request
 *
 * @var \Nata\Auth\Base
 */
    protected $_authorizationProvider;

/**
 * Objects that will be used for authentication checks.
 *
 * @var array
 */
    protected $_authenticateObjects = [];

/**
 * Objects that will be used for authorization checks.
 *
 * @var array
 */
    protected $_authorizeObjects = [];


/**
 * Authentication configuration
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config = []) {
        $this->config($config);
    }

/**
 * Authentication configuration
 *
 * @param array $config Configuration array
 * @return void
 */
    public function startup($event) {
        $controller = $event->getSubject();
        $action = $controller->request->params['action'];


        if (!$controller->isAction($action)) {
            return;
        }


        if ($this->isAllowed($controller)) {
            return;
        }

        if (!$this->_getUser()) {
            $result = $this->_unauthenticated($controller);
            if ($result instanceof Response) {
                $event->stopPropagation();
            }
            return $result;
        }

        if ($this->_isLoginAction($controller) || empty($this->_config['authorize']) || $this->isAuthorized($this->user())) {
            return;
        }

        $event->stopPropagation();

        return $this->_unauthorized($controller);
    }

/**
 * Checks whether current action is accessible without authentication.
 *
 * @param Controller $controller A reference to the instantiating
 *   controller object
 * @param string $action Check if action is allowed (fallsback to action in parameter)
 * @return bool True if action is accessible without authentication else false
 */
    public function isAllowed(Controller $controller, string $action = null) {
        $action = $this->_normalizeAction($action ?? $controller->request->params['action']);
        return in_array($action, $this->_allowedActions);
    }

/**
 * Get the current user.
 *
 * Will prefer the user cache over sessions. The user cache is primarily used for
 * stateless authentication. For stateful authentication,
 * cookies + sessions will be used.
 *
 * @param string $key field to retrieve. Leave null to get entire User record
 * @return array|null Either User record or null if no user is logged in.
 * @link http://book.cakephp.org/3.0/en/controllers/components/authentication.html#accessing-the-logged-in-user
 */
    public function user($key = null) {
        $sessionKey = $this->config('sessionKey');
        $session = $this->_request->getSession();
        if (!empty($this->_user)) {
            $user = $this->_user;
        } elseif ($sessionKey && $session->check($sessionKey)) {
            $user = $session->read($sessionKey);
        } else {
            return null;
        }

        if ($key === null) {
            return $user;
        }

        return Hash::get($user, $key);
    }

/**
 * Similar to AuthComponent::user() except if the session user cannot be found, connected authentication
 * objects will have their getUser() methods called. This lets stateless authentication methods function correctly.
 *
 * @return bool true if a user can be found, false if one cannot.
 */
    protected function _getUser() {
        $user = $this->user();

        if ($user) {
            $this->_request->getSession()->delete('Auth.redirect');
            return true;
        }

        if (empty($this->_authenticateObjects)) {
            $this->constructAuthenticate();
        }

        foreach ($this->_authenticateObjects as $auth) {
            $result = $auth->getUser($this->_request);
            if (!empty($result) && is_array($result)) {
                $this->_user = $result;
                return true;
            }
        }

        return false;
    }

/**
 * Get list of allowed actions.
 *
 * @return array List of allowed actions
 */
    public function allowedActions() {
        return $this->_allowedActions;
    }

/**
 * Takes a list of actions in the current controller for which authentication is not required, or
 * no parameters to allow all actions.
 *
 * You can use allow with either an array or a simple string.
 *
 * ```
 * $this->Auth->allow('*');
 * $this->Auth->allow('view');
 * $this->Auth->allow(['edit', 'add']);
 * ```
 * or to allow all actions
 * ```
 * $this->Auth->allow();
 * ```
 *
 * @param string|array $actions Controller action name or array of actions
 * @return $this
 * @link http://book.cakephp.org/3.0/en/controllers/components/authentication.html#making-actions-public
 */
    public function allow($actions = null) {
        if ($actions === null || $actions === '*') {
            $this->_allowedActions = get_class_methods($this->_controller);
        } else {
            $actions = $this->_normalizeAction($actions);
            $this->_allowedActions = array_merge($this->_allowedActions, (array)$actions);
        }
        return $this;
    }

/**
 * Removes items from the list of allowed/no authentication required actions.
 *
 * You can use deny with either an array or a simple string.
 *
 * ```
 * $this->Auth->deny('view');
 * $this->Auth->deny(['edit', 'add']);
 * ```
 * or
 * ```
 * $this->Auth->deny();
 * ```
 * to remove all items from the allowed list
 *
 * @param string|array $actions Controller action name or array of actions
 * @return $this
 * @see Auth::allow()
 * @link http://book.cakephp.org/3.0/en/controllers/components/authentication.html#making-actions-require-authorization
 */
    public function deny($actions = null) {
        if ($actions === null) {
            $this->_allowedActions = array();
        } else {
            $actions = $this->_normalizeAction($actions);

            foreach ((array)$actions as $action) {
                $i = array_search($action, $this->_allowedActions);
                if (is_int($i)) {
                    unset($this->_allowedActions[$i]);
                }
            }

            $this->_allowedActions = array_values($this->_allowedActions);
        }

        return $this;
    }

/**
 * Normalize given actions.
 *
 * @return bool true if a user can be found, false if one cannot.
 */
    protected function _normalizeAction($actions) {
        if (!is_array($actions)) {
            return Inflector::variable($actions);
        }

        foreach ($actions as $index => $action) {
            $actions[$index] = $this->_normalizeAction($action);
        }

        return $actions;
    }

/**
 * Get the URL a user should be redirected to upon login.
 *
 * Pass a URL in to set the destination a user should be redirected to upon
 * logging in.
 *
 * If no parameter is passed, gets the authentication redirect URL. The URL
 * returned is as per following rules:
 *
 *  - Returns the normalized URL from session Auth.redirect value if it is
 *    present and for the same domain the current app is running on.
 *  - If there is no session value and there is a config `loginRedirect`, the
 *    `loginRedirect` value is returned.
 *  - If there is no session and no `loginRedirect`, / is returned.
 *
 * @param string|array $url Optional URL to write as the login redirect URL.
 * @return string Redirect URL
 */
    public function redirectUrl($url = null) {
        $session = $this->_request->getSession();

        if ($url !== null) {
            if (Router::normalize($url) === Router::normalize($this->_config['logoutAction'])) {
                return;
            }

            $redir = $url;
            $session->write('Auth.redirect', $redir);
        } elseif ($session->check('Auth.redirect')) {
            $redir = $session->read('Auth.redirect');
            $session->delete('Auth.redirect');
            if (Router::normalize($redir) === Router::normalize($this->_config['loginAction'])) {
                $redir = $this->_config['loginRedirect'];
            }
        } elseif ($this->_config['loginRedirect']) {
            $redir = $this->_config['loginRedirect'];
        } else {
            $redir = '/';
        }

        if (is_array($redir)) {
            return Router::url($redir + array('_base' => false));
        }

        return $redir;
    }

/**
 * Use the configured authentication adapters, and attempt to identify the user
 * by credentials contained in $request.
 *
 * Triggers `Auth.afterIdentify` event which the authenticate classes can listen
 * to.
 *
 * @return array User record data, or false, if the user could not be identified.
 */
    public function identify() {
        if (empty($this->_authenticateObjects)) {
            $this->constructAuthenticate();
        }

        foreach ($this->_authenticateObjects as $auth) {
            $result = $auth->authenticate($this->_request, $this->_response);

            if (!empty($result) && is_array($result)) {
                $this->_authenticationProvider = $auth;
                // TODO
                // $this->dispatchEvent('Auth.afterIdentify', [$result]);
                $this->setUser($result);
                return $result;
            }
        }

        return false;
    }

/**
 * Set provided user info to session as logged in user.
 *
 * The user record is written to the session key specified in AuthComponent::$sessionKey.
 * The session id will also be changed in order to help mitigate session replays.
 *
 * @param array|Entity $user Array of user data.
 * @return $this
 */
    public function setUser(array|Entity $user) {
        if ($user instanceof Entity) {
            $entity = $user;
            $user = [
                'id' => $entity->id,
                'model' => $entity->source(),
            ];
            $entity = null;
        }
        $sessionKey = $this->config('sessionKey');
        $session = $this->_request->getSession();
        $session->write($sessionKey, $user);
        return $this;
    }

/**
 * Log a user out.
 *
 * Returns the logout action to redirect to. Triggers the `Auth.logout` event
 * which the authenticate classes can listen for and perform custom logout logic.
 * AuthComponent will remove the session data, so there is no need to do that
 * in an authentication object. Logging out will also renew the session id.
 * This helps mitigate issues with session replays.
 *
 * @return string Normalized config `logoutRedirect`
 * @link http://book.cakephp.org/3.0/en/controllers/components/authentication.html#logging-users-out
 */
    public function logout() {
        if (empty($this->_authenticateObjects)) {
            $this->constructAuthenticate();
        }

        $sessionKey = $this->config('sessionKey');
        $session = $this->_request->getSession();

        // $this->dispatchEvent('Auth.logout', [$user]);
        $session->delete($sessionKey);
        $session->delete('Auth.redirect');
        $session->renew();

        return Router::normalize($this->_config['logoutRedirect']);
    }

/**
 * Handles unauthenticated access attempt. First the `unauthenticated()` method
 * of the last authenticator in the chain will be called. The authenticator can
 * handle sending response or redirection as appropriate and return `true` to
 * indicate no further action is necessary. If authenticator returns null this
 * method redirects user to login action. If it's an AJAX request and config
 * `ajaxLogin` is specified that element is rendered else a 403 HTTP status code
 * is returned.
 *
 * @param \Nata\Controller\Controller $controller A reference to the controller object.
 * @return void|\Nata\Http\Response Null if current action is login action
 *   else response object returned by authenticate object or Controller::redirect().
 */
    protected function _unauthenticated(Controller $controller) {
        if (empty($this->_authenticateObjects)) {
            $this->constructAuthenticate();
        }

        $auth = end($this->_authenticateObjects);
        $result = $auth->unauthenticated($this->_request, $this->_response);
        if ($result !== null) {
            return $result;
        }

        if ($this->_isLoginAction($controller)) {
            if (empty($controller->request->data)
                && !$this->_request->getSession()->check('Auth.redirect')
                && env('HTTP_REFERER')
            ) {
                $this->redirectUrl($controller->referer(null, true));
            }
            return;
        }

        if (!$controller->request->is('ajax')) {
            $this->redirectUrl($controller->request->here(false));
        }

        return $controller->redirect($this->_config['loginAction']);
    }

/**
 * Loads the configured authentication objects.
 *
 * @return mixed either null on empty authenticate value, or an array of loaded objects.
 * @throws \Exception
 */
    public function constructAuthenticate() {
        if (empty($this->_config['authenticate'])) {
            return;
        }

        $this->_authenticateObjects = [];
        $authenticate = Hash::normalize((array)$this->_config['authenticate']);
        $global = array();

        if (isset($authenticate[Auth::ALL])) {
            $global = $authenticate[Auth::ALL];
            unset($authenticate[Auth::ALL]);
        }

        foreach ($authenticate as $alias => $config) {
            if (!empty($config['className'])) {
                $class = $config['className'];
                unset($config['className']);
            } else {
                $class = $alias;
            }

            $className = App::className($class, 'Auth/Authenticate');

            if (!class_exists($className)) {
                throw new Exception(sprintf('Authentication adapter "%s" was not found.', $class));
            }

            if (!method_exists($className, 'authenticate')) {
                throw new Exception('Authentication objects must implement an authenticate() method.');
            }

            $config = array_merge($global, (array)$config);
            $this->_authenticateObjects[$alias] = new $className($config);
            // $this->_controller->eventManager()->on($this->_authenticateObjects[$alias]);
        }

        return $this->_authenticateObjects;
    }

/**
 * Getter for authenticate objects. Will return a particular authenticate object.
 *
 * @param string $alias Alias for the authenticate object
 *
 * @return \Nata\Auth\Base|null
 */
    public function getAuthenticate($alias) {
        if (empty($this->_authenticateObjects)) {
            $this->constructAuthenticate();
        }

        return isset($this->_authenticateObjects[$alias]) ? $this->_authenticateObjects[$alias] : null;
    }

/**
 * Normalizes config `loginAction` and checks if current request URL is same as login action.
 *
 * @param \Nata\Controller\Controller $controller A reference to the controller object.
 * @return bool True if current action is login action else false.
 */
    protected function _isLoginAction(Controller $controller) {
        $url = '';

        if (isset($controller->request->url)) {
            $url = $controller->request->url;
        }

        $url = Router::normalize($url);
        $loginAction = Router::normalize($this->_config['loginAction']);
        return $loginAction === $url;
    }

/**
 * Handle unauthorized access attempt.
 *
 * @param \Nata\Controller\Controller $controller A reference to the controller object
 * @return \Nata\Http\Response
 * @throws \ForbiddenException
 */
    protected function _unauthorized(Controller $controller) {
        if ($this->_config['unauthorizedRedirect'] === false) {
            throw new ForbiddenException($this->_config['authError']);
        }

        if ($this->_config['unauthorizedRedirect'] === true) {
            $default = '/';

            if (!empty($this->_config['loginRedirect'])) {
                $default = $this->_config['loginRedirect'];
            }

            $url = $controller->referer($default, true);
        } else {
            $url = $this->_config['unauthorizedRedirect'];
        }

        return $controller->redirect($url);
    }

/**
 * Check if the provided user is authorized for the request.
 *
 * Uses the configured Authorization adapters to check whether or not a user is authorized.
 * Each adapter will be checked in sequence, if any of them return true, then the user will
 * be authorized for the request.
 *
 * @param array|null $user The user to check the authorization of. If empty the user in the session will be used.
 * @param \Nata\Http\Request|null $request The request to authenticate for. If empty, the current request will be used.
 * @return bool True if $user is authorized, otherwise false
 */
    public function isAuthorized($user = null, Request $request = null) {
        if (empty($user) && !$this->user()) {
            return false;
        }

        if (empty($user)) {
            $user = $this->user();
        }

        if (empty($request)) {
            $request = $this->_request;
        }

        if (empty($this->_authorizeObjects)) {
            $this->constructAuthorize();
        }

        if (empty($this->_authorizeObjects) && $user) {
            return true;
        }

        foreach ($this->_authorizeObjects as $authorizer) {
            if ($authorizer->authorize($user, $request) === true) {
                $this->_authorizationProvider = $authorizer;
                return true;
            }
        }

        return false;
    }

/**
 * Loads the authorization objects configured.
 *
 * @return mixed Either null when authorize is empty, or the loaded authorization objects.
 * @throws \Exception
 */
    public function constructAuthorize() {
        if (empty($this->_config['authorize'])) {
            return;
        }

        $this->_authorizeObjects = array();
        $authorize = Hash::normalize((array)$this->_config['authorize']);
        $global = array();

        if (isset($authorize[Auth::ALL])) {
            $global = $authorize[Auth::ALL];
            unset($authorize[Auth::ALL]);
        }

        foreach ($authorize as $alias => $config) {
            if (!empty($config['className'])) {
                $class = $config['className'];
                unset($config['className']);
            } else {
                $class = $alias;
            }

            $className = App::className($class, 'Auth/Authorize');

            if (!class_exists($className)) {
                throw new Exception(sprintf('Authorization adapter "%s" was not found.', $class));
            }

            if (!method_exists($className, 'authorize')) {
                throw new Exception('Authorization objects must implement an authorize() method.');
            }

            $config = (array)$config + $global;
            $this->_authorizeObjects[$alias] = new $className($this, $config);
        }

        return $this->_authorizeObjects;
    }

/**
 * Getter for authorize objects. Will return a particular authorize object.
 *
 * @param string $alias Alias for the authorize object
 * @return \Nata\Auth\BaseAuthorize|null
 */
    public function getAuthorize($alias) {
        if (empty($this->_authorizeObjects)) {
            $this->constructAuthorize();
        }
        return isset($this->_authorizeObjects[$alias]) ? $this->_authorizeObjects[$alias] : null;
    }

/**
 * If login was called during this request and the user was successfully
 * authenticated, this function will return the instance of the authentication
 * object that was used for logging the user in.
 *
 * @return \Nata\Auth\Authenticate\Base|null
 */
    public function authenticationProvider() {
        return $this->_authenticationProvider;
    }

/**
 * If there was any authorization processing for the current request, this function
 * will return the instance of the Authorization object that granted access to the
 * user to the current address.
 *
 * @return \Nata\Auth\Authorize\Base|null
 */
    public function authorizationProvider() {
        return $this->_authorizationProvider;
    }

}
