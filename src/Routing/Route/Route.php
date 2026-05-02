<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 1.3
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Routing\Route;

use Nata\Core\Plugin;
use Nata\I18n\I18n;
use Nata\Routing\Router;
use Nata\Utility\Inflector;
use Nata\Utility\Validation;

/**
 * A single Route used by the Router to connect requests to
 * parameter maps.
 *
 * Not normally created as a standalone.  Use Router::connect() to create
 * Routes for your application.
 *
 * @package Cake.Routing.Route
 */
class Route {

/**
 * An array of named segments in a Route.
 * `/:controller/:action/:id` has 3 key elements
 *
 * @var array
 */
    public $keys = [];

/**
 * An array of additional parameters for the Route.
 *
 * @var array
 */
    public $options = [];

/**
 * Default parameters for a Route
 *
 * @var array
 */
    public $defaults = [];

/**
 * The routes template string.
 *
 * @var string
 */
    public $template = null;

/**
 * Is this route a greedy route?  Greedy routes have a `/*` in their
 * template
 *
 * @var string
 */
    protected $_greedy = false;

/**
 * The compiled route regular expression
 *
 * @var string
 */
    protected $_compiledRoute = null;

/**
 * Cached regex pattern for :plugin (loaded plugin names only). Built once per process.
 *
 * @var string|null
 */
    protected static $_pluginPattern = null;

/**
 * Regex fragment for greedy path capture (/* and /**).
 * Uses segment-based matching instead of .* to avoid catastrophic backtracking on long URLs.
 * Matches the same as .* for normal paths (zero or more path segments).
 *
 * @var string
 */
    protected static $_greedyPathPattern = '(?:[^/]+/)*[^/]*';

/**
 * Default values for optional route keys (e.g. :language). Populated in _writeRoute().
 * Used in parse() to fill missing/empty captures and in _writeUrl() to omit segment when value equals default.
 *
 * @var array
 */
    protected $_optionalDefaults = [];

/**
 * HTTP header shortcut map.
 * Used for evaluating header-based route expressions.
 *
 * @var array
 */
    protected $_headerMap = [
        'type' => 'content_type',
        'method' => 'request_method',
        'host' => 'server_name'
    ];

/**
 * Constructor for a Route
 *
 * @param string $template Template string with parameter placeholders
 * @param array $defaults Array of defaults for the route.
 * @param array $options Array of additional options for the Route
 */
    public function __construct($template, array $defaults = [], array $options = []) {
        $this->template = $template;
        $this->defaults = (array)$defaults;
        $this->options = (array)$options;
    }

/**
 * Check if a Route has been compiled into a regular expression.
 *
 * @return boolean
 */
    public function compiled() {
        return !empty($this->_compiledRoute);
    }

/**
 * Compiles the route's regular expression.  Modifies defaults property so all necessary keys are set
 * and populates $this->names with the named routing elements.
 *
 * @return array Returns a string regular expression of the compiled route.
 */
    public function compile() {
        if ($this->compiled()) {
            return $this->_compiledRoute;
        }
        $this->_writeRoute();
        return $this->_compiledRoute;
    }

/**
 * Builds a route regular expression. Uses the template, defaults and options
 * properties to compile a regular expression that can be used to parse request strings.
 *
 * @return void
 */
    protected function _writeRoute() {
        if (empty($this->template) || ($this->template === '/')) {
            $this->_compiledRoute = '#^/*$#';
            $this->keys = [];
            return;
        }

        $route = $this->template;
        $names = $routeParams = [];
        $parsed = preg_quote($this->template, '#');

        preg_match_all('#:([A-Za-z0-9_-]+[A-Z0-9a-z])#', $route, $namedElements);
        foreach ($namedElements[1] as $i => $name) {
            $search = '\\' . $namedElements[0][$i];
            $pattern = null;
            if (isset($this->options[$name])) {
                $pattern = $this->options[$name];
            } elseif ($name === 'plugin') {
                $pattern = static::_getPluginPattern();
            }
            if ($pattern !== null) {
                $option = null;
                if ($name !== 'plugin' && array_key_exists($name, $this->defaults)) {
                    $option = '?';
                }
                $slashParam = '/\\' . $namedElements[0][$i];
                if (strpos($parsed, $slashParam) !== false) {
                    // Path segments (e.g. /:language) must not be optional: / must not match /:language
                    $routeParams[$slashParam] = '(?:/(?P<' . $name . '>' . $pattern . '))';
                } else {
                    $routeParams[$search] = '(?:(?P<' . $name . '>' . $pattern . ')' . $option . ')' . $option;
                }
            } else {
                $routeParams[$search] = '(?:(?P<' . $name . '>[^/]+))';
            }
            $names[] = $name;
        }

        if (preg_match('#\/\*\*$#', $route)) {
            $greedy = '(?:/(?P<_trailing_>' . static::$_greedyPathPattern . '))?';
            $parsed = preg_replace('#/\\\\\*\\\\\*$#', $greedy, $parsed);
            $this->_greedy = true;
        } elseif (preg_match('#\/\*$#', $route)) {
            $greedy = '(?:/(?P<_args_>' . static::$_greedyPathPattern . '))?';
            $parsed = preg_replace('#/\\\\\*$#', $greedy, $parsed);
            $this->_greedy = true;
        }

        krsort($routeParams);

        $parsed = str_replace(array_keys($routeParams), array_values($routeParams), $parsed);
        $this->_compiledRoute = '#^' . $parsed . '[/]*$#';
        $this->keys = $names;

        // Save optional key defaults before removing (for parse and _writeUrl)
        $this->_optionalDefaults = [];
        foreach ($this->keys as $key) {
            if (array_key_exists($key, $this->defaults)) {
                $this->_optionalDefaults[$key] = $this->defaults[$key];
            }
            unset($this->defaults[$key]);
        }
    }

/**
 * Returns a regex pattern that matches only loaded plugin names (hyphenated).
 * Used when the route template contains :plugin and no explicit option was passed,
 * so that :plugin only matches actual plugins and does not capture e.g. "profile" or "users".
 * Result is cached so the pattern is prepared once per process.
 *
 * @return string|null Pattern for the plugin segment, or null to match any segment
 */
    protected static function _getPluginPattern() {
        if (static::$_pluginPattern !== null) {
            return static::$_pluginPattern;
        }
        $plugins = Plugin::loaded();
        if (empty($plugins)) {
            return null;
        }
        $hyphenated = array_map([Inflector::class, 'hyphen'], $plugins);
        $safe = array_map(function ($name) {
            return preg_quote($name, '#');
        }, $hyphenated);
        static::$_pluginPattern = implode('|', $safe);
        return static::$_pluginPattern;
    }

/**
 * Checks to see if the given URL can be parsed by this route.
 * If the route can be parsed an array of parameters will be returned; if not
 * false will be returned. String urls are parsed if they match a routes regular expression.
 *
 * @param string $url The url to attempt to parse.
 * @return mixed Boolean false on failure, otherwise an array or parameters
 */
    public function parse(string $url) {
        if (!$this->compiled()) {
            $this->compile();
        }

        if (!preg_match($this->_compiledRoute, $url, $route)) {
            return false;
        }

        array_shift($route);

        $count = count($this->keys) + 1;
        for ($i = 0; $i <= $count; $i++) {
            unset($route[$i]);
        }

        foreach ($this->keys as $key) {
            if (!isset($route[$key]) || $route[$key] === '') {
                if (isset($this->_optionalDefaults[$key])) {
                    $route[$key] = $this->_optionalDefaults[$key];
                } else {
                    return false;
                }
            } else {
                $route[$key] = rawurldecode($route[$key]);
            }
        }

        // Assign defaults, set passed args to pass
        $route['pass'] = [];
        foreach ($this->defaults as $key => $value) {
            if (!$this->_matchHeaderExpression($key, $value)) {
                return false;
            }

            if (isset($route[$key])) {
                continue;
            }

            if (is_integer($key)) {
                $route['pass'][] = $value;
                continue;
            }

            $route[$key] = $value;
        }

        if (isset($route['_args_'])) {
            $pass = $this->_parseArgs($route['_args_'], $route);
            $route['pass'] = array_merge($route['pass'], $pass);
            unset($route['_args_']);
        }

        if (isset($route['_trailing_'])) {
            $route['pass'][] = rawurldecode($route['_trailing_']);
            unset($route['_trailing_']);
        }

        // Restructure 'pass' key route params
        if (isset($this->options['pass'])) {
            $j = count($this->options['pass']);
            while ($j--) {
                if (!isset($route[$this->options['pass'][$j]])) {
                    continue;
                }

                array_unshift($route['pass'], $route[$this->options['pass'][$j]]);
            }
        }

        if (isset($route['lang']) || isset($route['language'])) {
            $this->_reverseLocaleParams($route);
        }

        $route['_route'] = $this;
        $route['_matchedRoute'] = $this->template;

        return $route;
    }

/**
 * Checks to see if the given header parameters match.
 *
 * @param string $key Header name to check.
 * @param string $val Header value to check.
 * @return bool True if not set or set and it matches, false otherwise
 */
    protected function _matchHeaderExpression($key, $val): bool {
        $key = (string)$key;
        if ($key[0] !== '[' || $val === null) {
            return true;
        }

        $header = str_replace(['[', ']'], '', $key);
        if (isset($this->_headerMap[$header])) {
            $header = $this->_headerMap[$header];
        } else {
            $header = 'http_' . $header;
        }
        $header = strtoupper($header);

        $val = (array)$val;
        $h = false;
        $hv = env($header);
        foreach ($val as $v) {
            $not = strpos($v, '!') === 0;
            $v = ltrim($v, '!');
            if (!Validation::rangePattern($hv, $v)) {
                continue;
            } elseif ($not) {
                return false;
            }

            $h = true;
        }
        return $h;
    }

/**
 * Apply persistent parameters to a url array. Persistent parameters are a special
 * key used during route creation to force route parameters to persist when omitted from
 * a url array.
 *
 * @param array $url The array to apply persistent parameters to.
 * @param array $params An array of persistent values to replace persistent ones.
 * @return array An array with persistent parameters applied.
 */
    protected function _reverseLocaleParams(&$route) {
        return;

        $language = $route['lang'] ?? $route['language'];
        if (!$language) {
            $language = I18n::locale();
        }
        $route['controller'] = I18n::reverse($route['controller'], 'route', null, I18n::LC_MESSAGES, $language);
        $route['action'] = I18n::reverse($route['action'], 'route', null, I18n::LC_MESSAGES, $language);
    }

/**
 * Parse passed and Named parameters into a list of passed args, and a hash of named parameters.
 * The local and global configuration for named parameters will be used.
 *
 * @param string $args A string with the passed & named params.  eg. /1/page:2
 * @param string $context The current route context, which should contain controller/action keys.
 * @return array Array of ($pass, $named)
 */
    protected function _parseArgs($args, $context) {
        $pass = [];
        $args = explode('/', $args);
        foreach ($args as $param) {
            if (empty($param) && $param !== '0' && $param !== 0) {
                continue;
            }
            $pass[] = rawurldecode($param);
        }
        return $pass;
    }

/**
 * Return true if a given named $param's $val matches a given $rule depending on $context. Currently implemented
 * rule types are controller, action and match that can be combined with each other.
 *
 * @param string $val The value of the named parameter
 * @param array $rule The rule(s) to apply, can also be a match string
 * @param string $context An array with additional context information (controller / action)
 * @return boolean
 */
    protected function _matchNamed($val, $rule, $context) {
        if ($rule === true || $rule === false) {
            return $rule;
        }
        if (is_string($rule)) {
            $rule = ['match' => $rule];
        }
        if (!is_array($rule)) {
            return false;
        }

        $controllerMatches = (
            !isset($rule['controller'], $context['controller']) ||
            in_array($context['controller'], (array)$rule['controller'])
        );
        if (!$controllerMatches) {
            return false;
        }
        $actionMatches = (
            !isset($rule['action'], $context['action']) ||
            in_array($context['action'], (array)$rule['action'])
        );

        if (!$actionMatches) {
            return false;
        }

        return (!isset($rule['match']) || preg_match('/' . $rule['match'] . '/', $val));
    }

/**
 * Apply persistent parameters to a url array. Persistent parameters are a special
 * key used during route creation to force route parameters to persist when omitted from
 * a url array.
 *
 * @param array $url The array to apply persistent parameters to.
 * @param array $params An array of persistent values to replace persistent ones.
 * @return array An array with persistent parameters applied.
 */
    public function persistParams($url, $params) {
        foreach ($this->options['persist'] as $persistKey) {
            if (array_key_exists($persistKey, $params) && !isset($url[$persistKey])) {
                $url[$persistKey] = $params[$persistKey];
            }
        }
        return $url;
    }

/**
 * Attempt to match a url array.  If the url matches the route parameters and settings, then
 * return a generated string url.  If the url doesn't match the route parameters, false will be returned.
 * This method handles the reverse routing or conversion of url arrays into string urls.
 *
 * @param array $url An array of parameters to check matching with.
 * @return mixed Either a string url for the parameters if they match or false.
 */
    public function match($url) {
        if (!$this->compiled()) {
            $this->compile();
        }

        $defaults = $this->defaults;
        if (isset($defaults['prefix'])) {
            // If caller explicitly asked for a different prefix, this route does not match
            if (isset($url['prefix']) && $url['prefix'] !== $defaults['prefix']) {
                return false;
            }
            $url['prefix'] = $defaults['prefix'];
        }
        if (isset($defaults['[method]'])) {
            $url['[method]'] = $defaults['[method]'];
        }
        if (isset($defaults['[host]'])) {
            $url['[host]'] = $defaults['[host]'];
        }

        // Check that all the key names are in the url
        $keyNames = array_flip($this->keys);
        if (array_intersect_key($keyNames, $url) !== $keyNames) {
            return false;
        }

        // Missing defaults is a fail.
        if (array_diff_key($defaults, $url) !== []) {
            return false;
        }

        $pass = [];
        foreach ($url as $key => $value) {
            if (!$this->_matchHeaderExpression($key, $value)) {
                return false;
            }

            // keys that exist in the defaults and have different values is a match failure.
            $defaultExists = array_key_exists($key, $defaults);
            if ($defaultExists && $defaults[$key] != $value) {
                // Allow false to mean "omit" for optional keys (e.g. language => false to clear persisted param)
                if (!($value === false && isset($this->_optionalDefaults[$key]))) {
                    return false;
                }
            } elseif ($defaultExists) {
                continue;
            }

            // If the key is a routed key, its not different yet.
            if (array_key_exists($key, $keyNames)) {
                continue;
            }

            // pull out passed args
            $numeric = is_numeric($key);
            if ($numeric && isset($defaults[$key]) && $defaults[$key] == $value) {
                continue;
            } elseif ($numeric) {
                $pass[] = $value;
                unset($url[$key]);
                continue;
            }

            // keys that don't exist are different.
            if (!$defaultExists && !empty($value)) {
                return false;
            }
        }

        // if a not a greedy route, no extra params are allowed.
        if (!$this->_greedy && !empty($pass)) {
            return false;
        }

        // check patterns for routed params
        if (!empty($this->options)) {
            foreach ($this->options as $key => $pattern) {
                if (!array_key_exists($key, $url)) {
                    continue;
                }
                if ($url[$key] === false && isset($this->_optionalDefaults[$key])) {
                    continue; // allow "omit" for optional keys
                }
                if (!preg_match('#^' . $pattern . '$#', $url[$key])) {
                    return false;
                }
            }
        }

        return $this->_writeUrl(array_merge($url, ['pass' => $pass]));
    }

/**
 * Converts a matching route array into a url string. Composes the string url using the template
 * used to create the route.
 *
 * @param array $params The params to convert to a string url.
 * @return string Composed route string.
 */
    protected function _writeUrl($params) {
        if (isset($params['prefix'])) {
            $prefixed = $params['prefix'] . '_';
        }

        if (isset($prefixed, $params['action']) && strpos($params['action'], $prefixed) === 0) {
            $params['action'] = substr($params['action'], strlen($prefixed) * -1);
            unset($params['prefix']);
        }

        if (is_array($params['pass'])) {
            $params['pass'] = implode('/', array_map('rawurlencode', $params['pass']));
        }

        if (!empty($params['named']) && is_array($params['named'])) {
            $params['pass'] = $params['pass'] . '/' . Router::queryString($params['named'], [], true);
        }
        $out = $this->template;

        $search = $replace = [];
        foreach ($this->keys as $key) {
            $string = null;
            if (isset($params[$key])) {
                $string = $params[$key];
                if (isset($this->_optionalDefaults[$key])) {
                    // Omit segment only when explicitly cleared (false) or empty; do not omit when
                    // value equals the route default so that e.g. /language-code/ stays in the URL.
                    if ($string === false || $string === '') {
                        $string = '';
                    } elseif ($key !== 'language' && $string === $this->_optionalDefaults[$key]) {
                        $string = '';
                    }
                }
            }
            $searchKey = $key;
            if (!isset($params[$key]) && strpos($out, $key) != strlen($out) - strlen($key)) {
                $searchKey = $key . '/';
            }
            $search[] = ':' . $searchKey;
            $replace[] = $string;
        }
        $out = str_replace($search, $replace, $out);

        if (strpos($this->template, '*')) {
            $out = str_replace('*', $params['pass'], $out);
        }

        $out = str_replace('//', '/', $out);
        return $out;
    }

}
