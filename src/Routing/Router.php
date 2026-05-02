<?php
/**
 * Parses the request URL into controller, action, and parameters.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Routing
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Routing;

use Nata\Cache\Cache;
use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Utility\Inflector;
use Nata\Utility\Hash;
use Nata\Http\Request;
use Nata\I18n\I18n;
use RouterException;

/**
 * Parses the request URL into controller, action, and parameters.  Uses the connected routes
 * to match the incoming url string to parameters that will allow the request to be dispatched.  Also
 * handles converting parameter lists into url strings, using the connected routes.  Routing allows you to decouple
 * the way the world interacts with your application (urls) and the implementation (controllers and actions).
 *
 * ### Connecting routes
 *
 * Connecting routes is done using Router::connect().  When parsing incoming requests or reverse matching
 * parameters, routes are enumerated in the order they were connected.  You can modify the order of connected
 * routes using Router::promote().  For more information on routes and how to connect them see Router::connect().
 *
 * ### Named parameters
 *
 * Named parameters allow you to embed key:value pairs into path segments.  This allows you create hash
 * structures using urls.  You can define how named parameters work in your application using Router::connectNamed()
 */

class Router {

/**
 * Array of routes connected with Router::connect()
 *
 * @var array
 */
    public static $routes = [];

/**
 * Array of named routes for quick lookup
 *
 * @var array
 */
    protected static $_namedRoutes = [];

/**
 * List of action prefixes used in connected routes.
 * Includes admin prefix
 *
 * @var array
 */
    protected static $_prefixes = [];

/**
 * Cache configuration.
 *
 * @var string
 */
    protected static $_cache;

/**
 * Directive for Router to parse out file extensions for mapping to Content-types.
 *
 * @var boolean
 */
    protected static $_parseExtensions = false;

/**
 * List of valid extensions to parse from a URL.  If null, any extension is allowed.
 *
 * @var array
 */
    protected static $_validExtensions = [];

/**
 * 'Constant' regular expression definitions for named route elements
 */
    const ACTION = 'index|show|add|create|edit|update|remove|del|delete|view|item';
    const YEAR = '[12][0-9]{3}';
    const MONTH = '0[1-9]|1[012]';
    const DAY = '0[1-9]|[12][0-9]|3[01]';
    const ID = '[0-9]+';
    const UUID = '[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}';

/**
 * Named expressions
 *
 * @var array
 */
    protected static $_namedExpressions = [
        'Action' => Router::ACTION,
        'Year' => Router::YEAR,
        'Month' => Router::MONTH,
        'Day' => Router::DAY,
        'ID' => Router::ID,
        'UUID' => Router::UUID
    ];

/**
 * Stores all information necessary to decide what named arguments are parsed under what conditions.
 *
 * @var array
 */
    protected static $_namedConfig = [
        'default' => ['page', 'fields', 'order', 'limit', 'recursive', 'sort', 'direction', 'step'],
        'greedyNamed' => false,
        'separator' => ':',
        'rules' => false,
    ];

/**
 * The route matching the URL of the current request
 *
 * @var array
 */
    protected static $_currentRoute = [];

/**
 * Default HTTP request method => controller action map.
 *
 * @var array
 */
    protected static $_resourceMap = [
        ['action' => 'index', 'method' => 'GET', 'id' => false],
        ['action' => 'view', 'method' => 'GET', 'id' => true],
        ['action' => 'add', 'method' => 'POST', 'id' => false],
        ['action' => 'edit', 'method' => 'PUT', 'id' => true],
        ['action' => 'delete', 'method' => 'DELETE', 'id' => true],
        ['action' => 'edit', 'method' => 'POST', 'id' => true]
    ];

/**
 * List of resource-mapped controllers
 *
 * @var array
 */
    protected static $_resourceMapped = [];

/**
 * Maintains the request object stack for the current request.
 * This will contain more than one request object when requestAction is used.
 *
 * @var array
 */
    protected static $_requests = [];

/**
 * Initial state is populated the first time reload() is called which is at the bottom
 * of this file.  This is a cheat as get_class_vars() returns the value of static vars even if they
 * have changed.
 *
 * @var array
 */
    protected static $_initialState = [];

/**
 * Default route class to use
 *
 * @var string
 */
    protected static $_routeClass = 'Nata\Routing\Route\Route';

/**
 * Stack of active route scopes for grouping routes
 *
 * @var array
 */
    protected static $_scopeStack = [];

/**
 * Route groups organized by scope for performance optimization
 * Structure: ['scope_id' => ['conditions' => [...], 'routes' => [...]]]
 *
 * @var array
 */
    protected static $_routeScopes = [];

/**
 * Counter for generating unique scope IDs
 *
 * @var int
 */
    protected static $_scopeIdCounter = 0;

/**
 * Ordered list of language codes used in routed URLs (e.g. website).
 * Set via Router::languages($list); read via Router::languages().
 *
 * @var array
 */
    protected static $_routedLanguages = [];


/**
 * Gets or sets the ordered list of routed language codes for the application.
 *
 * This list is used when building routes or constructing `:language` constraints to ensure
 * the routing convention is defined in a single location.
 *
 * If called with an argument, sets the routed languages to the specified list.
 * If called without arguments, returns the current routed languages list.
 * If the list is not explicitly set, it defaults to all available languages returned by I18n::available().
 *
 * @param array|null $list Optional. The ordered list of locale codes (e.g. ['en', 'pt', 'pt-br', 'fr', 'es']).
 *                         Omit to retrieve the current list.
 * @return array The routed language codes.
 */
    public static function languages(array $list = null): array {
        if ($list === null) {
            return static::$_routedLanguages;
        }
        static::$_routedLanguages = $list;
        return $list;
    }

/**
 * Set the default route class to use or return the current one
 *
 * @param string $routeClass to set as default
 * @return mixed void|string
 * @throws RouterException
 */
    public static function defaultRouteClass($routeClass = null) {
        if (is_null($routeClass)) {
            return static::$_routeClass;
        }
        static::$_routeClass = static::_validateRouteClass($routeClass);
    }

/**
 * Validates that the passed route class exists and is a subclass of Route
 *
 * @param $routeClass
 * @return string
 * @throws RouterException
 */
    protected static function _validateRouteClass($routeClass) {
        $routeClass = App::className($routeClass, 'Routing/Route');
        if (
            $routeClass != 'Nata\Routing\Route\Route' &&
            (!$routeClass || !is_subclass_of($routeClass, 'Nata\Routing\Route\Route'))
        ) {
            throw new RouterException('Route classes must extend \Nata\Routing\Route\Route');
        }
        return $routeClass;
    }

/**
 * Sets the Routing prefixes.
 *
 * @return void
 */
    protected static function _setPrefixes() {
        $routing = Configure::read('Routing');
        if (!empty($routing['prefixes'])) {
            static::$_prefixes = array_merge(static::$_prefixes, (array)$routing['prefixes']);
        }
    }

/**
 * Gets the named route elements for use in app/Config/routes.php
 *
 * @return array Named route elements
 * @see Router::$_namedExpressions
 */
    public static function getNamedExpressions() {
        return static::$_namedExpressions;
    }

/**
 * Resource map getter & setter.
 *
 * @param array $resourceMap Resource map
 * @return mixed
 * @see Router::$_resourceMap
 */
    public static function resourceMap($resourceMap = null) {
        if ($resourceMap === null) {
            return static::$_resourceMap;
        }
        static::$_resourceMap = $resourceMap;
    }

/**
 * Cache configuration.
 *
 * @param string $cache Cache
 * @return string
 */
    public static function cache($cache = null) {
        if (func_num_args() === 0) {
            return static::$_cache;
        }
        return static::$_cache = $cache;
    }

/**
 * Connects a new Route in the router.
 *
 * Routes are a way of connecting request urls to objects in your application.  At their core routes
 * are a set or regular expressions that are used to match requests to destinations.
 *
 * Examples:
 *
 * `Router::connect('/:controller/:action/*');`
 *
 * The first parameter will be used as a controller name while the second is used as the action name.
 * the '/*' syntax makes this route greedy in that it will match requests like `/posts/index` as well as requests
 * like `/posts/edit/1/foo/bar`.
 *
 * `Router::connect('/home-page', array('controller' => 'pages', 'action' => 'display', 'home'));`
 *
 * The above shows the use of route parameter defaults. And providing routing parameters for a static route.
 *
 * {{{
 * Router::connect(
 *   '/:lang/:controller/:action/:id',
 *   array(),
 *   array('id' => '[0-9]+', 'lang' => '[a-z]{3}')
 * );
 * }}}
 *
 * Shows connecting a route with custom route parameters as well as providing patterns for those parameters.
 * Patterns for routing parameters do not need capturing groups, as one will be added for each route params.
 *
 * $options offers four 'special' keys. `pass`, `named`, `persist` and `routeClass`
 * have special meaning in the $options array.
 *
 * - `pass` is used to define which of the routed parameters should be shifted into the pass array.  Adding a
 *   parameter to pass will remove it from the regular route array. Ex. `'pass' => array('slug')`
 * - `persist` is used to define which route parameters should be automatically included when generating
 *   new urls. You can override persistent parameters by redefining them in a url or remove them by
 *   setting the parameter to `false`.  Ex. `'persist' => array('lang')`
 * - `routeClass` is used to extend and change how individual routes parse requests and handle reverse routing,
 *   via a custom routing class. Ex. `'routeClass' => 'SlugRoute'`
 * - `named` is used to configure named parameters at the route level. This key uses the same options
 *   as Router::connectNamed()
 *
 * You can also add additional conditions for matching routes to the $defaults array.
 * The following conditions can be used:
 *
 * - `[type]` Only match requests for specific content types.
 * - `[method]` Only match requests with specific HTTP verbs.
 * - `[server]` Only match when $_SERVER['SERVER_NAME'] matches the given value.
 *
 * Example of using the `[method]` condition:
 *
 * `Router::connect('/tasks', ['controller' => 'tasks', 'action' => 'index', '[method]' => 'GET']);`
 *
 * Example of using the `[server]` condition (all hosts except 'not-this-one.com'):
 *
 * `Router::connect('/tasks', ['controller' => 'tasks', 'action' => 'index', '[server]' => ['*', '!not-this-one.com']]);`
 *
 * The above route will only be matched for GET requests. POST requests will fail to match this route.
 *
 * @param string $route A string describing the template of the route
 * @param array $defaults An array describing the default route parameters. These parameters will be used by default
 *   and can supply routing parameters that are not dynamic. See above.
 * @param array $options An array matching the named elements in the route to regular expressions which that
 *   element should match.  Also contains additional parameters such as which routed parameters should be
 *   shifted into the passed arguments, supplying patterns for routing parameters and supplying the name of a
 *   custom routing class.
 * @see routes
 * @return array Array of routes
 * @throws RouterException
 */
    public static function connect(string $route, array $defaults = [], array $options = []): array {
        // Track which scope this route belongs to (for performance optimization)
        $routeScopeId = null;
        $explicitPrefixFalse = (array_key_exists('prefix', $defaults) && $defaults['prefix'] === false);

        // Merge scope parameters from the scope stack
        if (!empty(static::$_scopeStack)) {
            $scopesForPath = [];
            foreach (static::$_scopeStack as $scope) {
                if (!empty($scope['path'])) {
                    $scopesForPath[] = $scope;
                }
                // Merge scope params into defaults (scope params win; inner scope processed after outer)
                if (!empty($scope['params'])) {
                    $defaults = array_merge($scope['params'], $defaults);
                }
                // Track scope for route grouping: use outermost scope with a literal path_prefix
                // so nested scopes (e.g. /:plugin under /pro) have their routes tried when the outer scope matches
                if (isset($scope['id']) && isset(static::$_routeScopes[$scope['id']]) && !empty($scope['path'])) {
                    $path = $scope['path'];
                    $isLiteral = (strpos($path, ':') === false && strpos($path, '*') === false);
                    if ($isLiteral) {
                        $routeScopeId = $scope['id'];
                    } elseif ($routeScopeId === null) {
                        $routeScopeId = $scope['id'];
                    }
                }
            }
            // Prepend scope paths innermost-first so URL order is outer/inner (e.g. /pro/:plugin/:controller)
            foreach (array_reverse($scopesForPath) as $scope) {
                $inner = ltrim($route, '/');
                $path = rtrim($scope['path'], '/');
                $path = (strpos($path, '/') !== 0) ? '/' . $path : $path;
                $route = ($inner !== '') ? $path . '/' . $inner : $path;
            }
            // Allow connect() or inner scope to disable prefix (e.g. plugin routes under /pro using plugin's Controller, not Pro sub-namespace)
            if ($explicitPrefixFalse) {
                $defaults['prefix'] = false;
            }
        }

        //var_dump($route);
        //var_dump($defaults);

        // Check for prefix keys (e.g., 'admin' => true) only if 'prefix' is not already explicitly set
        // This prevents scope prefixes from being overridden by prefix keys
        // IMPORTANT: Check this BEFORE any prefix key processing to preserve scope prefixes
        $hasExplicitPrefix = isset($defaults['prefix']);

        if (!$hasExplicitPrefix) {
            foreach (static::$_prefixes as $prefix) {
                if (isset($defaults[$prefix])) {
                    if ($defaults[$prefix]) {
                        $defaults['prefix'] = $prefix;
                    } else {
                        unset($defaults[$prefix]);
                    }
                    break;
                }
            }
        } else {
            // If prefix is explicitly set (e.g., from scope), remove any conflicting prefix keys
            // to prevent ambiguity
            foreach (static::$_prefixes as $prefix) {
                if (isset($defaults[$prefix]) && $prefix !== $defaults['prefix']) {
                    unset($defaults[$prefix]);
                }
            }
        }

        if (isset($defaults['prefix']) && $defaults['prefix'] !== false && $defaults['prefix'] !== null) {
            static::$_prefixes[] = $defaults['prefix'];
            static::$_prefixes = array_keys(array_flip(static::$_prefixes));
        }

        $defaults += ['plugin' => null];

        if (empty($options['action'])) {
            $defaults += ['action' => 'index'];
        }

        // Extract route name if provided
        $routeName = null;
        if (isset($options['name'])) {
            $routeName = $options['name'];
            unset($options['name']);
        }

        $routeClass = static::$_routeClass;
        if (isset($options['routeClass'])) {
            $routeClass = static::_validateRouteClass($options['routeClass']);
            unset($options['routeClass']);
        }

        if ($routeClass == 'Nata\Routing\Route\Redirect' && isset($defaults['redirect'])) {
            $defaults = $defaults['redirect'];
        }

        // Language is persistent by default for any route that has :language or a language default
        if (strpos($route, ':language') !== false || array_key_exists('language', $defaults)) {
            $options['persist'] = array_merge($options['persist'] ?? [], ['language']);
        }

        $routeObject = new $routeClass($route, $defaults, $options);
        static::$routes[] = $routeObject;

        // Add route to scope group for performance optimization
        if ($routeScopeId !== null && isset(static::$_routeScopes[$routeScopeId])) {
            static::$_routeScopes[$routeScopeId]['routes'][] = $routeObject;
        }

        // Store named route if name was provided
        if ($routeName !== null) {
            static::$_namedRoutes[$routeName] = $routeObject;
        }

        return static::$routes;
    }

/**
 * Connects a new redirection Route in the router.
 *
 * Redirection routes are different from normal routes as they perform an actual
 * header redirection if a match is found. The redirection can occur within your
 * application or redirect to an outside location.
 *
 * Examples:
 *
 * `Router::redirect('/home/*', array('controller' => 'posts', 'action' => 'view', array('persist' => true)));`
 *
 * Redirects /home/* to /posts/view and passes the parameters to /posts/view.  Using an array as the
 * redirect destination allows you to use other routes to define where a url string should be redirected to.
 *
 * `Router::redirect('/posts/*', 'http://google.com', array('status' => 302));`
 *
 * Redirects /posts/* to http://google.com with a HTTP status of 302
 *
 * ### Options:
 *
 * - `status` Sets the HTTP status (default 301)
 * - `persist` Passes the params to the redirected route, if it can.  This is useful with greedy routes,
 *   routes that end in `*` are greedy.  As you can remap urls and not loose any passed/named args.
 *
 * @param string $route A string describing the template of the route
 * @param array $url A url to redirect to. Can be a string or a Cake array-based url
 * @param array $options An array matching the named elements in the route to regular expressions which that
 *   element should match. Also contains additional parameters such as which routed parameters should be
 *   shifted into the passed arguments. As well as supplying patterns for routing parameters.
 * @see routes
 * @return array Array of routes
 */
    public static function redirect($route, $url, array $options = []) {
        $options['routeClass'] = 'Nata\Routing\Route\Redirect';
        if (is_string($url)) {
            $url = ['redirect' => $url];
        }
        return static::connect($route, $url, $options);
    }

/**
 * Creates a routing scope for grouping routes with common attributes.
 *
 * Scopes allow you to group routes under a common path prefix, domain, or with shared parameters.
 * The path can include a domain which will be automatically parsed and applied to all routes in the scope.
 *
 * Examples:
 *
 * Basic path scope:
 * `Router::scope('/admin', ['prefix' => 'admin'], function() { ... });`
 *
 * Domain scope:
 * `Router::scope('api.example.com', function() { ... });`
 *
 * Domain with path:
 * `Router::scope('api.example.com/v2', ['version' => 2], function() { ... });`
 *
 * Nested scopes:
 * ```
 * Router::scope('api.example.com', function() {
 *     Router::scope('/v2', ['version' => 2], function() {
 *         Router::connect('/users', ['controller' => 'Users']);
 *     });
 * });
 * ```
 *
 * @param string $path Path or domain+path for the scope (e.g., '/admin' or 'api.example.com/v1')
 * @param array|callable $params Parameters to apply to all routes in scope, or callback if second param
 * @param callable|null $callback Callback function containing route definitions
 * @return void
 */
    public static function scope($path, $params = [], $callback = null) {
        // Handle different argument patterns - scope(path, callback)
        if (is_callable($params)) {
            $callback = $params;
            $params = [];
        }

        $scopePath = '';
        $scopeHost = null;

        // Parse domain from path if present
        // Pattern 1: domain.com/path or subdomain.domain.com/path
        if (preg_match('#^([a-z0-9*][-a-z0-9.]*\.[a-z0-9][-a-z0-9.]+)(/.*)$#i', $path, $matches)) {
            $scopeHost = $matches[1];
            $scopePath = rtrim($matches[2], '/');
        }
        // Pattern 2: domain.com or subdomain.domain.com (no trailing path)
        elseif (preg_match('#^([a-z0-9*][-a-z0-9.]*\.[a-z0-9][-a-z0-9.]+)$#i', $path, $matches)) {
            $scopeHost = $matches[1];
            $scopePath = '';
        }
        // Pattern 3: localhost or IP addresses with optional path
        elseif (preg_match('#^(localhost|127\.0\.0\.1|\[::1\]|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(:[0-9]+)?(/.*)?$#i', $path, $matches)) {
            $scopeHost = $matches[1] . ($matches[2] ?? '');
            $scopePath = isset($matches[3]) ? rtrim($matches[3], '/') : '';
        }
        // Pattern 3b: host-like with wildcard (e.g. maismls.*) - treat as host only so no path prefix is added
        elseif (strpos($path, '/') === false && preg_match('#^[a-z0-9*][-a-z0-9.]*\.[a-z0-9*][-a-z0-9.]*$#i', $path)) {
            $scopeHost = $path;
            $scopePath = '';
        }
        // Pattern 4: No domain, just path
        else {
            $scopePath = rtrim($path, '/');
        }

        // Build scope configuration
        $scope = [
            'path' => $scopePath,
            'params' => $params,
            'id' => ++static::$_scopeIdCounter // Unique scope ID for grouping
        ];

        // Add host to params if detected
        if ($scopeHost !== null) {
            // Check if params already has [host] defined
            if (!isset($scope['params']['[host]'])) {
                $scope['params']['[host]'] = $scopeHost;
            }
        }

        // Extract conditions for early filtering
        $conditions = [];
        if (isset($scope['params']['[host]'])) {
            $conditions['host'] = $scope['params']['[host]'];
        }
        if (!empty($scopePath)) {
            $conditions['path_prefix'] = $scopePath;
        }

        // Initialize scope group if it has conditions (for performance optimization)
        if (!empty($conditions)) {
            static::$_routeScopes[$scope['id']] = [
                'conditions' => $conditions,
                'routes' => []
            ];
        }

        // Push onto stack
        static::$_scopeStack[] = $scope;

        // Execute callback with routes
        if (is_callable($callback)) {
            $callback();
        }

        // Pop from stack
        array_pop(static::$_scopeStack);
    }

/**
 * Specifies what named parameters CakePHP should be parsing out of incoming urls. By default
 * CakePHP will parse every named parameter out of incoming URLs.  However, if you want to take more
 * control over how named parameters are parsed you can use one of the following setups:
 *
 * Do not parse any named parameters:
 *
 * {{{ Router::connectNamed(false); }}}
 *
 * Parse only default parameters used for CakePHP's pagination:
 *
 * {{{ Router::connectNamed(false, array('default' => true)); }}}
 *
 * Parse only the page parameter if its value is a number:
 *
 * {{{ Router::connectNamed(array('page' => '[\d]+'), array('default' => false, 'greedy' => false)); }}}
 *
 * Parse only the page parameter no matter what.
 *
 * {{{ Router::connectNamed(array('page'), array('default' => false, 'greedy' => false)); }}}
 *
 * Parse only the page parameter if the current action is 'index'.
 *
 * {{{
 * Router::connectNamed(
 *    array('page' => array('action' => 'index')),
 *    array('default' => false, 'greedy' => false)
 * );
 * }}}
 *
 * Parse only the page parameter if the current action is 'index' and the controller is 'pages'.
 *
 * {{{
 * Router::connectNamed(
 *    array('page' => array('action' => 'index', 'controller' => 'pages')),
 *    array('default' => false, 'greedy' => false)
 * );
 * }}}
 *
 * ### Options
 *
 * - `greedy` Setting this to true will make Router parse all named params.  Setting it to false will
 *    parse only the connected named params.
 * - `default` Set this to true to merge in the default set of named parameters.
 * - `reset` Set to true to clear existing rules and start fresh.
 * - `separator` Change the string used to separate the key & value in a named parameter.  Defaults to `:`
 *
 * @deprecated This is dumb, should be removed
 * @param array $named A list of named parameters. Key value pairs are accepted where values are
 *    either regex strings to match, or arrays as seen above.
 * @param array $options Allows to control all settings: separator, greedy, reset, default
 * @return array
 */
    public static function connectNamed($named, $options = []) {
        if (isset($options['separator'])) {
            static::$_namedConfig['separator'] = $options['separator'];
            unset($options['separator']);
        }

        if ($named === true || $named === false) {
            $options = array_merge(['default' => $named, 'reset' => true, 'greedy' => $named], $options);
            $named = array();
        } else {
            $options = array_merge(['default' => false, 'reset' => false, 'greedy' => true], $options);
        }

        if ($options['reset'] == true || static::$_namedConfig['rules'] === false) {
            static::$_namedConfig['rules'] = [];
        }

        if ($options['default']) {
            $named = array_merge($named, static::$_namedConfig['default']);
        }

        foreach ($named as $key => $val) {
            if (is_numeric($key)) {
                static::$_namedConfig['rules'][$val] = true;
            } else {
                static::$_namedConfig['rules'][$key] = $val;
            }
        }

        static::$_namedConfig['greedyNamed'] = $options['greedy'];
        return static::$_namedConfig;
    }

/**
 * Gets the current named parameter configuration values.
 *
 * @return array
 * @see Router::$_namedConfig
 */
    public static function namedConfig() {
        return static::$_namedConfig;
    }

/**
 * Creates REST resource routes for the given controller(s). When creating resource routes
 * for a plugin, by default the prefix will be changed to the lower_underscore version of the plugin
 * name. By providing a prefix you can override this behavior.
 *
 * ### Options:
 *
 * - 'id' - The regular expression fragment to use when matching IDs.  By default, matches
 *    integer values and UUIDs.
 * - 'prefix' - URL prefix to use for the generated routes.  Defaults to '/'.
 *
 * @param string|array $controller A controller name or array of controller names (i.e. "Posts" or "ListItems")
 * @param array $options Options to use when generating REST routes
 * @return array Array of mapped resources
 */
    public static function mapResources(string|array $controller, array $options = []): array {
        $hasPrefix = isset($options['prefix']);
        $options = array_merge([
            'prefix' => '/',
            'id' => static::ID . '|' . static::UUID
        ], $options);

        $host = $options['host'] ?? null;
        unset($options['host']);
        $prefix = $options['prefix'];
        foreach ((array)$controller as $name) {
            [$plugin, $name] = pluginSplit($name);
            $urlName = Inflector::dasherize($name);
            $plugin = Inflector::dasherize($plugin);
            if ($plugin && !$hasPrefix) {
                $prefix = '/' . $plugin . '/';
            }

            foreach (static::$_resourceMap as $params) {
                $url = $prefix . $urlName . (($params['id']) ? '/:id' : '');
                Router::connect($url,
                    [
                        'plugin' => $plugin,
                        'controller' => $urlName, // Use dasherized name (ActionDispatcher will camelize it)
                        'action' => $params['action'],
                        '[method]' => $params['method'],
                        '[host]' => $host
                    ],
                    ['id' => $options['id'], 'pass' => ['id']]
                );
            }
            static::$_resourceMapped[] = $urlName;
        }
        return static::$_resourceMapped;
    }

/**
 * Returns the list of prefixes used in connected routes.
 *
 * @return array A list of prefixes used in connected routes
 */
    public static function prefixes() {
        return static::$_prefixes;
    }

/**
 * Get a named route by its name.
 *
 * @param string $name The name of the route
 * @return \Nata\Routing\Route\Route|null Route object or null if not found
 */
    public static function getNamedRoute($name) {
        return static::$_namedRoutes[$name] ?? null;
    }

/**
 * Check if a named route exists.
 *
 * @param string $name The name of the route
 * @return bool True if the named route exists, false otherwise
 */
    public static function hasNamedRoute($name) {
        return isset(static::$_namedRoutes[$name]);
    }

/**
 * Get all named routes.
 *
 * @return array Array of named routes [name => RouteObject]
 */
    public static function getNamedRoutes() {
        return static::$_namedRoutes;
    }

/**
 * Get the current scope stack (useful for debugging).
 *
 * @return array Current scope stack
 */
    public static function getScopeStack() {
        return static::$_scopeStack;
    }

/**
 * Check if current host matches a host pattern (supports wildcards and negations).
 *
 * @param string $currentHost The current request host
 * @param string|array $hostPattern Host pattern(s) to match against
 * @return bool True if host matches the pattern
 */
    protected static function _matchesHost($currentHost, $hostPattern) {
        if (is_array($hostPattern)) {
            $hasWildcard = false;
            $excluded = [];
            $included = [];

            foreach ($hostPattern as $pattern) {
                // Handle negations (exclude patterns)
                if (strpos($pattern, '!') === 0) {
                    $excluded[] = substr($pattern, 1);
                } elseif ($pattern === '*') {
                    $hasWildcard = true;
                } else {
                    $included[] = $pattern;
                }
            }

            // Check exclusions first
            foreach ($excluded as $excludePattern) {
                if (static::_hostPatternMatch($currentHost, $excludePattern)) {
                    return false; // Explicitly excluded
                }
            }

            // If we have specific includes, check them
            if (!empty($included)) {
                foreach ($included as $includePattern) {
                    if (static::_hostPatternMatch($currentHost, $includePattern)) {
                        return true;
                    }
                }
                return false; // Didn't match any includes
            }

            // If we have wildcard and no exclusions matched, accept
            if ($hasWildcard) {
                return true;
            }

            return false;
        }

        return static::_hostPatternMatch($currentHost, $hostPattern);
    }

/**
 * Match a host against a single pattern (supports wildcards).
 *
 * @param string $host The host to match
 * @param string $pattern The pattern to match against
 * @return bool True if host matches pattern
 */
    protected static function _hostPatternMatch($host, $pattern) {
        if ($pattern === '*') {
            return true;
        }

        if ($host === null) {
            return false;
        }

        if ($pattern === $host) {
            return true;
        }

        // Wildcard subdomain: *.example.com
        if (strpos($pattern, '*.') === 0) {
            $domain = substr($pattern, 2);
            // Match subdomain.example.com or example.com
            return substr($host, -strlen('.' . $domain)) === '.' . $domain || $host === $domain;
        }

        return false;
    }

/**
 * Check if URL starts with given path prefix.
 *
 * @param string $url The URL to check
 * @param string $prefix The path prefix
 * @return bool True if URL starts with prefix
 */
    protected static function _matchesPathPrefix($url, $prefix) {
        if (empty($prefix) || $prefix === '/') {
            return true;
        }

        // Ensure prefix starts with /
        if (strpos($prefix, '/') !== 0) {
            $prefix = '/' . $prefix;
        }

        // Check if URL starts with prefix (str_starts_with is PHP 8+ and avoids extra comparison)
        return str_starts_with($url, $prefix);
    }

/**
 * Detect path traversal in URL (e.g. ..\, ../, %2e%2e, encoded slashes).
 * Returns true if the path should be rejected (route not found).
 *
 * @param string $url URL path to check
 * @return bool True if path traversal detected
 */
    protected static function _isPathTraversal(string $url): bool {
        // Backslash (Windows) or literal ".."
        if (strpos($url, '\\') !== false || strpos($url, '..') !== false) {
            return true;
        }

        // Only decode when URL might contain encoded traversal (%2e, %5c, etc.)
        if (strpos($url, '%') === false) {
            return false;
        }

        // URL-encoded parent: %2e%2e or %252e%252e etc
        $decoded = rawurldecode($url);
        if (strpos($decoded, '..') !== false || strpos($decoded, '\\') !== false) {
            return true;
        }
        return false;
    }

/**
 * Generate URL from a named route.
 *
 * @param string $name The name of the route
 * @param array $params Parameters to pass to the route
 * @param bool|array $full If (bool) true, the full base URL will be prepended to the result
 * @return string|null Generated URL or null if route not found
 */
    public static function namedUrl($name, array $params = [], $full = false) {
        if (!isset(static::$_namedRoutes[$name])) {
            return null;
        }

        $route = static::$_namedRoutes[$name];
        $url = array_merge($route->defaults, $params);

        return static::url($url, $full);
    }

/**
 * Parses given URL string. Returns 'routing' parameters for that url.
 *
 * @param string $url URL to be parsed
 * @return array Parsed elements from URL
 */
    public static function parse(string $url) {
        $host = env('SERVER_HOST');

        $cacheConfig = static::$_cache;
        if ($cacheConfig) {
            $cacheKey = 'routing_parse_url_' . Inflector::slug($url) . '_' . md5($host . $url);
            $result = Cache::read($cacheKey, $cacheConfig);
            if ($result) {
                return $result;
            }
        }

        $ext = null;
        $out = [
            'controller' => null,
            'action' => null,
            'ext' => null
        ];

        // Reject path traversal attempts (e.g. ..\..\..\var\log or ..%2f..%2fetc)
        if (static::_isPathTraversal($url)) {
            Cache::write($cacheKey, $out, $cacheConfig);
            return $out;
        }

        if (strlen($url) && strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        if (strpos($url, '?') !== false) {
            $url = substr($url, 0, strpos($url, '?'));
        }

        extract(static::_parseExtension($url));

        // Performance optimization: Build list of candidate routes using scope filtering
        $candidateRoutes = [];
        $scopedRouteIds = []; // Track which routes we've already added from scopes

        if (!empty(static::$_routeScopes)) {
            // First, prioritize routes from matching scopes (more specific routes first)
            $matchingScopes = [];
            foreach (static::$_routeScopes as $scopeId => $scope) {
                $scopeMatches = true;

                // Check host condition
                if (isset($scope['conditions']['host'])) {
                    if (!static::_matchesHost($host, $scope['conditions']['host'])) {
                        $scopeMatches = false;
                    }
                }

                // Check path prefix condition
                if ($scopeMatches && isset($scope['conditions']['path_prefix'])) {
                    if (!static::_matchesPathPrefix($url, $scope['conditions']['path_prefix'])) {
                        $scopeMatches = false;
                    }
                }

                // If scope matches, add to matching scopes (prioritize by path length - more specific first)
                if ($scopeMatches && !empty($scope['routes'])) {
                    $pathLength = isset($scope['conditions']['path_prefix']) ? strlen($scope['conditions']['path_prefix']) : 0;
                    $matchingScopes[] = ['scope' => $scope, 'pathLength' => $pathLength];
                }
            }

            // Sort only when needed (2+ scopes) so most specific path is tried first
            if (count($matchingScopes) > 1) {
                usort($matchingScopes, function($a, $b) {
                    return $b['pathLength'] - $a['pathLength'];
                });
            }

            // Add routes from matching scopes first (most specific first)
            foreach ($matchingScopes as $match) {
                $scope = $match['scope'];
                foreach ($scope['routes'] as $route) {
                    $routeId = spl_object_id($route);
                    if (!isset($scopedRouteIds[$routeId])) {
                        $candidateRoutes[] = $route;
                        $scopedRouteIds[$routeId] = true;
                    }
                }
            }

            // Add routes that are not in any scope (less specific, catch-all routes)
            foreach (static::$routes as $route) {
                $routeId = spl_object_id($route);
                if (!isset($scopedRouteIds[$routeId])) {
                    $candidateRoutes[] = $route;
                }
            }
        } else {
            // No scopes defined, use all routes
            $candidateRoutes = static::$routes;
        }

        // Iterate through candidate routes only
        for ($i = 0, $len = count($candidateRoutes); $i < $len; $i++) {
            $route = $candidateRoutes[$i];
            if (($r = $route->parse($url)) !== false) {
                static::$_currentRoute[] = $route;
                $out = $r;

                // Ensure prefix from route defaults is preserved
                // If route has explicit prefix, don't let it be overridden
                if (isset($out['prefix'])) {
                    // Remove any conflicting prefix keys to prevent override
                    foreach (static::$_prefixes as $prefix) {
                        if (isset($out[$prefix]) && $prefix !== $out['prefix']) {
                            unset($out[$prefix]);
                        }
                    }
                }
                break;
            }
        }

        if (isset($out['action'])) {
            $out['action'] = Inflector::dasherize($out['action']);
        }

        if (!empty($ext) && !isset($out['ext'])) {
            $out['ext'] = $ext;
        }

        if ($cacheConfig) {
            Cache::write($cacheKey, $out, $cacheConfig);
        }

        return $out;
    }

/**
 * Parses a file extension out of a URL, if Router::parseExtensions() is enabled.
 *
 * @param string $url
 * @return array Returns an array containing the altered URL and the parsed extension.
 */
    protected static function _parseExtension($url) {
        $ext = null;
        if (static::$_parseExtensions) {
            if (preg_match('/\.[0-9a-zA-Z]*$/', $url, $match) === 1) {
                $match = substr($match[0], 1);
                if (empty(static::$_validExtensions)) {
                    $url = substr($url, 0, strpos($url, '.' . $match));
                    $ext = $match;
                } else {
                    foreach (static::$_validExtensions as $name) {
                        if (strcasecmp($name, $match) === 0) {
                            $url = substr($url, 0, strpos($url, '.' . $name));
                            $ext = $match;
                            break;
                        }
                    }
                }
            }
        }
        return compact('ext', 'url');
    }

/**
 * Takes parameter and path information back from the Dispatcher, sets these
 * parameters as the current request parameters that are merged with url arrays
 * created later in the request.
 *
 * Nested requests will create a stack of requests.  You can remove requests using
 * Router::popRequest().  This is done automatically when using Object::requestAction().
 *
 * Will accept either a NataRequest object or an array of arrays. Support for
 * accepting arrays may be removed in the future.
 *
 * @param NataRequest|array $request Parameters and path information or a NataRequest object.
 * @return void
 */
    public static function setRequest($request) {
        if (!($request instanceof Request)) {
            $request += [[], []];
            $request[0] += ['controller' => false, 'action' => false, 'plugin' => null];

            $requestObj = new Request();
            $requestObj->addParams($request[0])->addPaths($request[1]);

            $request = $requestObj;

            unset($requestObj);
        }

        static::$_requests[] = $request;
    }

/**
 * Pops a request off of the request stack.  Used when doing requestAction
 *
 * @return NataRequest The request removed from the stack.
 * @see Router::setRequest()
 * @see Object::requestAction()
 */
    public static function popRequest() {
        return array_pop(static::$_requests);
    }

/**
 * Get the either the current request object, or the first one.
 *
 * @param boolean $current Whether you want the request from the top of the stack or the first one.
 * @return \Nata\Http\Request Request
 */
    public static function getRequest($current = false) {
        if ($current) {
            $i = count(static::$_requests) - 1;
            return isset(static::$_requests[$i]) ? static::$_requests[$i] : null;
        }

        return isset(static::$_requests[0]) ? static::$_requests[0] : null;
    }

/**
 * Gets parameter information
 *
 * @param boolean $current Get current request parameter, useful when using requestAction
 * @return array Parameter information
 */
    public static function getParams($current = false) {
        if ($current && static::$_requests) {
            return static::$_requests[count(static::$_requests) - 1]->params;
        }
        if (isset(static::$_requests[0])) {
            return static::$_requests[0]->params;
        }
        return [];
    }

/**
 * Gets URL parameter by name
 *
 * @param string $name Parameter name
 * @param boolean $current Current parameter, useful when using requestAction
 * @return string Parameter value
 */
    public static function getParam($name = 'controller', $current = false) {
        $params = Router::getParams($current);
        if (isset($params[$name])) {
            return $params[$name];
        }
        return null;
    }

/**
 * Gets path information
 *
 * @param boolean $current Current parameter, useful when using requestAction
 * @return array
 */
    public static function getPaths($current = false) {
        if ($current) {
            return static::$_requests[count(static::$_requests) - 1];
        }
        if (!isset(static::$_requests[0])) {
            return ['base' => null];
        }
        return ['base' => static::$_requests[0]->base];
    }

/**
 * Reloads default Router settings.  Resets all class variables and
 * removes all connected routes.
 *
 * @return void
 */
    public static function reload() {
        if (empty(static::$_initialState)) {
            static::$_initialState = get_class_vars(__NAMESPACE__ . '\Router');
            static::_setPrefixes();
            return;
        }

        foreach (static::$_initialState as $key => $val) {
            if ($key != '_initialState') {
                static::${$key} = $val;
            }
        }

        // Reset named routes
        static::$_namedRoutes = [];

        // Reset scope stack and scope groups
        static::$_scopeStack = [];
        static::$_routeScopes = [];
        static::$_scopeIdCounter = 0;

        static::_setPrefixes();
    }

/**
 * Promote a route (by default, the last one added) to the beginning of the list
 *
 * @param integer $which A zero-based array index representing the route to move. For example,
 *    if 3 routes have been added, the last route would be 2.
 * @return boolean Returns false if no route exists at the position specified by $which.
 */
    public static function promote($which = null) {
        if ($which === null) {
            $which = count(static::$routes) - 1;
        }
        if (!isset(static::$routes[$which])) {
            return false;
        }
        $route =& static::$routes[$which];
        unset(static::$routes[$which]);
        array_unshift(static::$routes, $route);
        return true;
    }

/**
 * Finds URL for specified action.
 *
 * Returns an URL pointing to a combination of controller and action. Param
 * $url can be:
 *
 * - Empty - the method will find address to actual controller/action.
 * - '/' - the method will find base URL of application.
 * - A combination of controller/action - the method will find url for it.
 *
 * There are a few 'special' parameters that can change the final URL string that is generated
 *
 * - `base` - Set to false to remove the base path from the generated url. If your application
 *   is not in the root directory, this can be used to generate urls that are 'cake relative'.
 *   cake relative urls are required when using requestAction.
 * - `?` - Takes an array of query string parameters
 * - `#` - Allows you to set url hash fragments.
 * - `full_base` - If true the `FULL_BASE_URL` constant will be prepended to generated urls.
 *
 * @param string|array $url Nata-relative URL, like "/products/edit/92" or "/presidents/elect/4"
 *   or an array specifying any of the following: 'controller', 'action',
 *   and/or 'plugin', in addition to named arguments (keyed array elements),
 *   and standard URL arguments (indexed array elements)
 * @param bool|array $full If (bool) true, the full base URL will be prepended to the result.
 *   If an array accepts the following keys
 *    - escape - used when making urls embedded in html escapes query string '&'
 *    - full - if true the full base URL will be prepended.
 * @return string Full translated URL with base path.
 */
    public static function url($url = null, array|bool $full = false) {
        $params = ['plugin' => null, 'controller' => null, 'action' => 'index'];

        if (is_bool($full)) {
            $escape = false;
        } else {
            extract($full + ['escape' => false, 'full' => false]);
        }

        $path = ['base' => null];
        if (!empty(static::$_requests)) {
            $request = static::$_requests[count(static::$_requests) - 1];
            $params = $request->params;
            $path = ['base' => $request->base, 'here' => $request->here];
        }

        if (empty($url)) {
            $output = isset($path['here']) ? $path['here'] : '/';
            if ($full && defined('FULL_BASE_URL')) {
                $output = FULL_BASE_URL . $output;
            }
            return $output;
        }

        // Handle named routes
        if (is_array($url) && isset($url['name'])) {
            $routeName = $url['name'];
            unset($url['name']);

            if (!isset(static::$_namedRoutes[$routeName])) {
                throw new RouterException("Named route '{$routeName}' not found");
            }

            $route = static::$_namedRoutes[$routeName];
            $url = array_merge($route->defaults, $url);
        }

        $cacheConfig = null; // static::$_cache;
        if ($cacheConfig) {
            $cacheKey = 'routing_url_' . md5(serialize([$url, $path, $params, $full]));
            $result = Cache::read($cacheKey, $cacheConfig);
            if ($result) {
                return $result;
            }
        }

        $base = $path['base'];
        $extension = $output = $q = $frag = null;
        if (is_array($url)) {
            if (isset($url['base']) && $url['base'] === false) {
                $base = null;
                unset($url['base']);
            }
            if (isset($url['full_base']) && $url['full_base'] === true) {
                $full = true;
                unset($url['full_base']);
            }
            if (isset($url['?'])) {
                $q = $url['?'];
                unset($url['?']);
            }
            if (isset($url['#'])) {
                $frag = '#' . $url['#'];
                unset($url['#']);
            }
            if (isset($url['ext'])) {
                $extension = '.' . $url['ext'];
                unset($url['ext']);
            }

            if (empty($url['action'])) {
                if (empty($url['controller']) || $params['controller'] === $url['controller']) {
                    $url['action'] = $params['action'];
                } else {
                    $url['action'] = 'index';
                }
            }

            $prefixExists = (array_intersect_key($url, array_flip(static::$_prefixes)));

            foreach (static::$_prefixes as $prefix) {
                if (!empty($params[$prefix]) && empty($prefixExists)) {
                    $url[$prefix] = true;
                    $url['prefix'] = $prefix;
                } elseif (isset($url[$prefix]) && !$url[$prefix]) {
                    unset($url[$prefix]);
                }
                if (isset($url[$prefix]) && strpos($url['action'], $prefix . '_') === 0) {
                    $url['action'] = substr($url['action'], strlen($prefix) + 1);
                }
            }

            // When no prefix was requested, lock to current request prefix so prefixed routes don't match from non-prefixed context
            if (empty($prefixExists) && !array_key_exists('prefix', $url)) {
                $url['prefix'] = $params['prefix'] ?? false;
            }

            $url += ['controller' => $params['controller'], 'plugin' => $params['plugin']];

            $match = false;
            for ($i = 0, $len = count(static::$routes); $i < $len; $i++) {
                $originalUrl = $url;

                if (isset(static::$routes[$i]->options['persist'], $params)) {
                    $url = static::$routes[$i]->persistParams($url, $params);
                }

                if ($match = static::$routes[$i]->match($url)) {
                    $output = trim($match, '/');
                    break;
                }

                $url = $originalUrl;
            }

            if ($match === false) {
                $output = static::_handleNoRoute($url);
            }
        } else {
            if (
                (strpos($url, '://') !== false ||
                (strpos($url, 'javascript:') === 0) ||
                (strpos($url, 'mailto:') === 0)) ||
                (!strncmp($url, '#', 1))
            ) {
                return $url;
            }

            if (substr($url, 0, 1) === '/') {
                $output = substr($url, 1);
            } else {
                foreach (static::$_prefixes as $prefix) {
                    if (isset($params[$prefix])) {
                        $output .= $prefix . '/';
                        break;
                    }
                }
                if (!empty($params['plugin']) && $params['plugin'] !== $params['controller']) {
                    $output .= Inflector::dasherize($params['plugin']) . '/';
                }
                $output .= Inflector::dasherize($params['controller']) . '/' . $url;
            }

        }

        $protocol = preg_match('#^[a-z][a-z0-9+-.]*\://#i', $output);
        if ($protocol === 0) {
            $output = str_replace('//', '/', $base . '/' . $output);

            if ($full && defined('FULL_BASE_URL')) {
                $output = FULL_BASE_URL . $output;
            }
            if (!empty($extension)) {
                $output = rtrim($output, '/');
            }
        }

        $out = $output . $extension . static::queryString($q, [], $escape) . $frag;

        if ($cacheConfig) {
            Cache::write($cacheKey, $out, $cacheConfig);
        }

        return $out;
    }

/**
 * A special fallback method that handles url arrays that cannot match
 * any defined routes.
 *
 * @param array $url A url that didn't match any routes
 * @return string A generated url for the array
 * @see Router::url()
 */
    protected static function _handleNoRoute($url) {
        $named = $args = [];
        $skip = array_merge(
            ['bare', 'action', 'controller', 'plugin', 'prefix'],
            static::$_prefixes
        );

        $keys = array_values(array_diff(array_keys($url), $skip));
        $count = count($keys);

        // Remove this once parsed URL parameters can be inserted into 'pass'
        for ($i = 0; $i < $count; $i++) {
            $key = $keys[$i];
            if (is_numeric($keys[$i])) {
                $args[] = $url[$key];
            } else {
                $named[$key] = $url[$key];
            }
        }

        [$args, $named] = [Hash::filter($args), Hash::filter($named)];
        foreach (static::$_prefixes as $prefix) {
            $prefixed = $prefix . '_';
            if (!empty($url[$prefix]) && strpos($url['action'], $prefixed) === 0) {
                $url['action'] = substr($url['action'], strlen($prefixed) * -1);
                break;
            }
        }

        if (empty($named) && empty($args) && (!isset($url['action']) || $url['action'] === 'index')) {
            $url['action'] = null;
        }

        $urlOut = array_filter([$url['controller'], $url['action']]);

        if (isset($url['plugin'])) {
            array_unshift($urlOut, $url['plugin']);
        }

        foreach (static::$_prefixes as $prefix) {
            if (isset($url[$prefix])) {
                array_unshift($urlOut, $prefix);
                break;
            }
        }

        $output = implode('/', $urlOut);
        if (!empty($args)) {
            $output .= '/' . implode('/', array_map('rawurlencode', $args));
        }

        if (!empty($named)) {
            $output .= static::queryString($named);
        }
        return $output;
    }

/**
 * Generates a well-formed querystring from $q.
 *
 * @param string|array $q Query string Either a string of already compiled query string arguments or
 *    an array of arguments to convert into a query string.
 * @param array $extra Extra querystring parameters.
 * @param boolean $escape Whether or not to use escaped &
 * @return string
 */
    public static function queryString($q, array $extra = [], bool $escape = false): string {
        if (empty($q) && empty($extra)) {
            return '';
        }

        $out = '';
        if (is_array($q)) {
            $q = array_merge($q, $extra);
        } else {
            $out = $q;
            $q = $extra;
        }

        if (isset($q['_qk']) && $q['_qk'] === true) {
            unset($q['_qk']);
            $q['_qk'] = static::_getQueryHashKey($q);
        }

        $join = $escape === true ? '&' : '&amp;';
        $addition = http_build_query($q, '', $join);
        if ($out && $addition && substr($out, strlen($join) * -1, strlen($join)) != $join) {
            $out .= $join;
        }

        $out .= $addition;
        if (isset($out[0]) && $out[0] != '?') {
            $out = '?' . $out;
        }

        return $out;
    }

/**
 * Check if 'signed' query is valid, based on given query key/hash.
 *
 * @param string|array $q Query string Either a string of already compiled query string arguments or
 *    an array of arguments to convert into a query string.
 * @return bool
 */
    public static function isSignedQueryValid($q = []): bool {
        if (!$q && $request = static::getRequest()) {
            $q = $request->query();
        }

        $q = static::queryAsArray($q);
        unset($q['page'], $q['limit'], $q['sort'], $q['direction'], $q['paginator']);

        if (!static::isSignedQuery($q)) {
            return false;
        }

        $checkQueryKey = $q['_qk'];
        unset($q['_qk']);

        $queryKey = static::_getQueryHashKey($q);

        return $checkQueryKey === $queryKey;
    }

/**
 * Check if query is "signed" with a respective hash in $q.
 *
 * @param string|array $q Query string Either a string of already compiled query string arguments or
 *    an array of arguments to convert into a query string.
 * @return bool
 */
    public static function isSignedQuery($q = []): bool {
        if (!$q && $request = static::getRequest()) {
            $q = $request->query();
        }

        $q = static::queryAsArray($q);

        return isset($q['_qk']);
    }

/**
 * Generate a hash key from given $q.
 *
 * @param array $q Query array
 * @return string Query key
 */
    protected static function _getQueryHashKey(array $q): string {
        return substr(hash('sha256', json_encode(static::_normalizeAsString($q)) . Configure::read('Security.salt')), 0, 10);
    }

/**
 * Normalized parameters as string.
 *
 * @param array $q Normalize parameters
 * @return array Normalize parameters
 */
    protected static function _normalizeAsString(array $q): array {
        foreach ($q as $k => $v) {
            if ($v === null) {
                unset($q[$k]);
                continue;
            }

            if (is_array($v)) {
                $v = static::_normalizeAsString($v);
            }

            if (is_bool($v) || is_numeric($v)) {
                settype($v, 'string');
            }

            $q[$k] = $v;
        }

        return $q;
    }

/**
 * Converts given query string to an array.
 *
 * @param string|array $q Query string Either a string of already compiled query string arguments or
 *    an array of arguments to convert into a query array.
 * @return array Query parsed as an array
 */
    public static function queryAsArray($q): array {
        if (empty($q)) {
            return [];
        }

        if (is_string($q)) {
            $q = str_replace('&amp;', '&', ltrim($q, '?'));
            parse_str($q, $result);
            $q = $result;
        }

        return $q;
    }

/**
 * Reverses a parsed parameter array into a string. Works similarly to Router::url(), but
 * Since parsed URL's contain additional 'pass' and 'named' as well as 'url.url' keys.
 * Those keys need to be specially handled in order to reverse a params array into a string url.
 *
 * This will strip out 'autoRender', 'bare', 'requested', and 'return' param names as those
 * are used for NataPHP internals and should not normally be part of an output url.
 *
 * @param Request|array $params The params array or Request object that needs to be reversed.
 * @param boolean $full Set to true to include the full url including the protocol when reversing
 *     the url.
 * @return string The string that is the reversed result of the array
 */
    public static function reverse($params, $full = false) {
        $url = [];
        if ($params instanceof Request) {
            $url = $params->query();
            $params = $params->params;
        } elseif (isset($params['?'])) {
            $url = $params['?'];
        }
        $pass = isset($params['pass']) ? $params['pass'] : [];
        $named = isset($params['named']) ? $params['named'] : [];

        unset(
            $params['pass'], $params['named'], $params['paging'], $params['models'], $params['url'], $url['url'],
            $params['autoRender'], $params['bare'], $params['requested'], $params['return'],
            $params['_Token'], $params['_qk'], $params['[host]'], $params['_matchedRoute'], $params['_route'], $params['_args_'], $params['_trailing_']
        );
        $params = array_merge($params, $pass, $named);
        if (!empty($url)) {
            $params['?'] = $url;
        }
        return Router::url($params, $full);
    }

/**
 * Normalizes a URL for purposes of comparison.  Will strip the base path off
 * and replace any double /'s.  It will not unify the casing and underscoring
 * of the input value.
 *
 * @param array|string $url URL to normalize Either an array or a string url.
 * @return string Normalized URL
 */
    public static function normalize($url = '/') {
        if (is_array($url)) {
            $url = Router::url($url);
        }

        if (preg_match('/^[a-z\-]+:\/\//', $url)) {
            return $url;
        }

        $request = Router::getRequest();
        if (!empty($request->base) && stristr($url, $request->base)) {
            $url = preg_replace('/^' . preg_quote($request->base, '/') . '/', '', $url, 1);
        }

        $url = '/' . $url;

        while (strpos($url, '//') !== false) {
            $url = str_replace('//', '/', $url);
        }
        $url = preg_replace('/(?:(\/$))/', '', $url);

        if (empty($url)) {
            return '/';
        }
        return $url;
    }

/**
 * Returns the route matching the current request URL.
 *
 * @return NataRoute Matching route object.
 */
    public static function &requestRoute() {
        return static::$_currentRoute[0];
    }

/**
 * Returns the route matching the current request (useful for requestAction traces)
 *
 * @return NataRoute Matching route object.
 */
    public static function &currentRoute() {
        return static::$_currentRoute[count(static::$_currentRoute) - 1];
    }

/**
 * Removes the plugin name from the base URL.
 *
 * @param string $base Base URL
 * @param string $plugin Plugin name
 * @return string base url with plugin name removed if present
 */
    public static function stripPlugin($base, $plugin = null) {
        if ($plugin != null) {
            $base = preg_replace('/(?:' . $plugin . ')/', '', $base);
            $base = str_replace('//', '', $base);
            $pos1 = strrpos($base, '/');
            $char = strlen($base) - 1;

            if ($pos1 === $char) {
                $base = substr($base, 0, $char);
            }
        }
        return $base;
    }

/**
 * Instructs the router to parse out file extensions from the URL. For example,
 * http://example.com/posts.rss would yield an file extension of "rss".
 * The file extension itself is made available in the controller as
 * `$this->params['ext']`, and is used by the RequestHandler component to
 * automatically switch to alternate layouts and templates, and load helpers
 * corresponding to the given content, i.e. RssHelper. Switching layouts and helpers
 * requires that the chosen extension has a defined mime type in `NataResponse`
 *
 * A list of valid extension can be passed to this method, i.e. Router::parseExtensions('rss', 'xml');
 * If no parameters are given, anything after the first . (dot) after the last / in the URL will be
 * parsed, excluding querystring parameters (i.e. ?q=...).
 *
 * @return void
 * @see RequestHandler::startup()
 */
    public static function parseExtensions() {
        static::$_parseExtensions = true;
        if (func_num_args() > 0) {
            static::setExtensions(func_get_args(), false);
        }
    }

/**
 * Get the list of extensions that can be parsed by Router.
 * To initially set extensions use `Router::parseExtensions()`
 * To add more see `setExtensions()`
 *
 * @return array Array of extensions Router is configured to parse.
 */
    public static function extensions() {
        return static::$_validExtensions;
    }

/**
 * Set/add valid extensions.
 * To have the extensions parsed you still need to call `Router::parseExtensions()`
 *
 * @param array $extensions List of extensions to be added as valid extension
 * @param boolean $merge Default true will merge extensions. Set to false to override current extensions
 * @return array
 */
    public static function setExtensions($extensions, $merge = true) {
        if (!is_array($extensions)) {
            return static::$_validExtensions;
        }

        if (!$merge) {
            return static::$_validExtensions = $extensions;
        }

        return static::$_validExtensions = array_merge(static::$_validExtensions, $extensions);
    }
}

// Save the initial state
Router::reload();
