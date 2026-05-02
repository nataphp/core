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

namespace Nata\View\Helper;

use Nata\View\Helper;
use Nata\Routing\Router;
use Nata\Utility\Text;

/**
 * Url Helper.
 */
class Url extends Helper {

/**
 * Router parameters.
 *
 * @var array
 */
    protected $_params = [];

/**
 * Request instance.
 *
 * @var \Nata\Http\Request
 */
    protected $_request;

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'secure' => null,
        'host' => null,
        'url' => null,
        'param' => null,
        'prefix' => null,
        'controller' => null,
        'action' => null,
        'query' => null,
        'append_query' => null,
        'method' => null,
        'true' => null,
        'false' => null,
        'full' => false
    ];


/**
 * Pseudo-constructor.
 *
 * @param array $config Helper configuration
 * @return void
 */
    public function initialize(array $config) {
        $this->_request = $this->_view->request;
        if ($this->_request) {
            $this->_params = $this->_request->params;
            unset($this->_params['_route']);
        }
    }

/**
 * Get base url.
 *
 * @param array $params Smarty params
 * @return string Base URL
 */
    public function base($params) {
        $params = $this->_normalizeParams($params);
        return Router::url('/', $params['full']);
    }

/**
 * Parse URL.
 *
 * @param array $params Smarty params
 * @return string Parameter value f found
 */
    public function get($params) {
        $params = $this->_normalizeParams($params);

        if ($this->_request === null) {
            return $this->_createUrl($params);
        }

        $_routeParams = $params['url'];
        if (!$_routeParams && ($params['prefix'] || $params['controller'] || $params['action'])) {
            $_routeParams = [
                'prefix' => $params['prefix'],
                'controller' => $params['controller'],
                'action' => $params['action'],
            ];
        }

        $appendQuery = $params['append_query'];

        // Get query/param
        if ($params['param'] || $params['query']) {
            if (isset($this->_params[$params['param']])) {
                return $this->_params[$params['param']];
            }
            return $this->_request->query($params['query']);
        // Url
        } elseif ($_routeParams) {
            if (is_string($_routeParams) && strpos($_routeParams, ':')) {
                $_routeParams = Text::insert($_routeParams, $this->_params);
            }
        }

        if (!empty($appendQuery)) {
            if ($appendQuery === true) {
                $appendQuery = $_routeParams;
            }
            $_routeParams['?'] = array_merge($this->_request->query(), $appendQuery);
        }

        return $this->_routeUrl($_routeParams, $params);
    }

/**
 * Check if given parameter matches matches route or request
 * Allows to return string if true or false
 *
 * @param array $params Smarty parameters
 * @return string|bool Return true/false or given string for boolean result
 */
    public function is($params) {
        $result = false;

        if (!empty($params) && $this->_request !== null) {
            $params = $this->_normalizeParams($params);

            foreach ($params as $name => $param) {
                switch ($name) {
                    case 'url':
                        $result = $this->_isCurrentUrl($param);
                        break;
                    case 'method':
                        $result = $this->_request->is($params['method']);
                        break;
                    case 'query':
                        $value = $this->_request->query($param);
                        $result = strlen($value) > 0;
                        break;
                    default:
                        if (isset($this->_params[$name])) {
                            $result = $param === $this->_params[$name];
                        } elseif ($query = $this->_request->query($name)) {
                            $result = $param === $query;
                        }
                        break 2;
                }
            }

            if (isset($params['controller']) && isset($params['action'])) {
                $result = $params['controller'] === $this->_params['controller']
                    && $params['action'] === $this->_params['action'];
            }

            if ($params['false'] && !$result) {
                return $params['false'];
            }

            if ($params['true'] && $result) {
                return $params['true'];
            }

        }

        return $result;
    }

/**
 * Check if given parameter has given parameter
 * Allows to return string if true or false
 *
 * @param array $params Smarty parameters
 * @return string|bool Return true/false or given string for boolean result
 */
    public function has($params) {
        return $this->is($params);
    }

/**
 * Check if current URL is given path.
 *
 * @param string|array $urls URL(s)
 * @return boolean true if given path is the current
 */
    private function _isCurrentUrl($urls) {
        if (!is_array($urls)) {
            $urls = array($urls);
        }

        $currentUrl = rtrim($this->_view->request->here(), '/');

        foreach ($urls as $url) {
            $normalizedUrl = $this->_normalizeUrl($url);

            if (strpos($url, '*') === false) {
                return $normalizedUrl === $currentUrl;
            }

            if (stripos($currentUrl, $normalizedUrl) === 0) {
                return true;
            }
        }

        return false;
    }

/**
 * Normalize route url forcing the end slash.
 *
 * @param string $url Router url string
 * @return string Route url with ending slash
 */
    private function _normalizeUrl($url) {
        $url = str_replace('*', '', $url);
        $url = Router::url($url);
        return rtrim($url, '/');
    }

/**
 * Create URL.
 *
 * @param array $params Smarty params
 * @return string Url
 */
    private function _createUrl($params) {
        extract($params);

        if (!$full) {
            return $url;
        }

        $_url = 'http';

        if ($secure) {
            $_url .= 's';
        }

        return $_url . '://' . $host . $url;
    }

/**
 * Check if current URL is given path.
 * Allows to return string if true or false.
 *
 * @param string $path Path to value
 * @param string $true If true, return given string
 * @param string $false If false, return given string
 * @return string
 */
    private function _active($path, $true = '', $false = '') {
        return $this->_isCurrentUrl($path) ? $true : $false;
    }

}
