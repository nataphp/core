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

use Nata\Core\Configure;
use Nata\Utility\Hash;
use Nata\Http\Session;
use ArrayAccess;
use Nata\Routing\Router;

/**
 * A class that helps wrap Request information and particulars about a single request.
 * Provides methods commonly used to introspect on the request headers and request body.
 *
 * Has both an Array and Object interface. You can access framework parameters using indexes:
 *
 * `$request['controller']` or `$request->controller`.
 */
class Request implements ArrayAccess {

/**
 * Array of parameters parsed from the url.
 *
 * @var array
 */
    public $params = [
        'plugin' => null,
        'controller' => null,
        'action' => null,
        'named' => [],
        'pass' => [],
    ];

/**
 * Array of POST data. Will contain form data as well as uploaded files.
 * Inputs prefixed with 'data' will have the data prefix removed. If there is
 * overlap between an input prefixed with data and one without, the 'data' prefixed
 * value will take precedence.
 *
 * @var array
 */
    protected $_data = [];

/**
 * Array of querystring arguments.
 *
 * @var array
 */
    protected $_query = [];

/**
 * Array of cookies.
 *
 * @var array
 */
    protected $_cookies = [];

/**
 * The url string used for the request.
 *
 * @var string
 */
    public $url;

/**
 * Base url path.
 *
 * @var string
 */
    public $base = false;

/**
 * Webroot path segment for the request.
 *
 * @var string
 */
    public $webroot = '/';

/**
 * The full address to the current request
 *
 * @var string
 */
    public $here;

/**
 * Instance of a Session object relative to this request.
 *
 * @var \Nata\Http\Session
 */
    protected $_session;

/**
 * Header lines list.
 *
 * @var array
 */
    protected $_header;

/**
 * The built in detectors used with `is()` can be modified with `addDetector()`.
 *
 * There are several ways to specify a detector, see Nata\Http\Request::addDetector() for the
 * various formats and ways to define detectors.
 *
 * @var array
 */
    protected $_detectors = [
        'get' => ['env' => 'REQUEST_METHOD', 'value' => 'GET'],
        'post' => ['env' => 'REQUEST_METHOD', 'value' => 'POST'],
        'put' => ['env' => 'REQUEST_METHOD', 'value' => 'PUT'],
        'delete' => ['env' => 'REQUEST_METHOD', 'value' => 'DELETE'],
        'head' => ['env' => 'REQUEST_METHOD', 'value' => 'HEAD'],
        'options' => ['env' => 'REQUEST_METHOD', 'value' => 'OPTIONS'],
        'ssl' => ['method' => '_isSsl'],
        'https' => ['method' => '_isSsl'],
        'ajax' => ['env' => 'HTTP_X_REQUESTED_WITH', 'value' => 'XMLHttpRequest'],
        'htmx' => ['env' => 'HTTP_HX_REQUEST', 'value' => 'true'],
        'form' => ['env' => 'CONTENT_TYPE', 'options' => ['form-data', 'x-www-form']],
        'flash' => ['env' => 'HTTP_USER_AGENT', 'pattern' => '/^(Shockwave|Adobe) Flash/'],
        'mobile' => ['env' => 'HTTP_USER_AGENT', 'options' => [
            'Android', 'AvantGo', 'BB10', 'BlackBerry', 'DoCoMo', 'Fennec', 'iPod', 'iPhone', 'iPad',
            'J2ME', 'MIDP', 'NetFront', 'Nokia', 'Opera Mini', 'Opera Mobi', 'PalmOS', 'PalmSource',
            'portalmmm', 'Plucker', 'ReqwirelessWeb', 'SonyEricsson', 'Symbian', 'UP\\.Browser',
            'webOS', 'Windows CE', 'Windows Phone OS', 'Xiino'
        ]],
        'bot' => ['env' => 'HTTP_USER_AGENT', 'pattern' => '/bot|crawl|slurp|spider|mediapartners/i'],
        'badbot' => ['env' => 'HTTP_USER_AGENT', 'pattern' => '/spbot|OpenLinkProfiler|MJ12bot|MegaIndex|AhrefsBot/i'],
        'google' => ['env' => 'HTTP_USER_AGENT', 'pattern' => '/googlebot/i'],
        'bing' => ['env' => 'HTTP_USER_AGENT', 'pattern' => '/bingbot/i'],
        'requested' => ['param' => 'requested', 'value' => 1]
    ];

/**
 * Copy of php://input.  Since this stream can only be read once in most SAPI's
 * keep a copy of it so users don't need to know about that detail.
 *
 * @var string
 */
    protected $_input = '';


/**
 * Constructor.
 *
 * @param string $url Trimmed url string to use.  Should not contain the application base path.
 * @param boolean $parseEnvironment Set to false to not auto parse the environment. ie. GET, POST and FILES.
 * @return void
 */
    public function __construct($url = null, $parseEnvironment = true) {
        $this->_base();

        if (empty($url)) {
            $url = $this->_url();
        }

        if ($url[0] == '/') {
            $url = substr($url, 1);
        }

        $this->url = $url;

        if ($parseEnvironment) {
            $this->_processPost();
            $this->_processPatch();
            $this->_processGet();
            $this->_processCookies();
            $this->_processFiles();
        }

        $this->here = $this->base . '/' . $this->url;

        $this->_session = Session::create();
    }

/**
 * Process the post data and set what is there into the object.
 * Processed data is available at `$this->_data`.
 *
 * @return void
 */
    protected function _processPost() {
        if ($_POST) {
            $this->_data = $_POST;
        } elseif (
            ($this->is('put') || $this->is('patch') || $this->is('delete')) &&
            strpos(env('CONTENT_TYPE'), 'application/x-www-form-urlencoded') === 0
        ) {
            $data = $this->_readInput();
            parse_str($data, $this->_data);
        } elseif (
            ($this->is('post') || $this->is('put') || $this->is('patch') || $this->is('delete')) &&
            strpos(env('CONTENT_TYPE'), 'application/json') !== false
        ) {
            $data = $this->_readInput();
            $this->_data = json_decode($data, true);
        }

        if (!is_array($this->_data)) {
            return;
        }

        if (env('HTTP_X_HTTP_METHOD_OVERRIDE')) {
            $this->_data['_method'] = env('HTTP_X_HTTP_METHOD_OVERRIDE');
        }

        if (isset($this->_data['_method'])) {
            $_SERVER['REQUEST_METHOD'] = $this->_data['_method'];
            unset($this->_data['_method']);
        }

        $this->_trim($this->_data);
    }

/**
 * Process the GET parameters and move things into the object.
 *
 * @return void
 */
    protected function _processGet() {
        $this->_query = $_GET;
        if (Router::isSignedQuery($this->_query) && !Router::isSignedQueryValid($this->_query)) {
            $this->_query = [];
        }

        // If query string is formated incorrectly with &amp;
        if (isset($_SERVER['QUERY_STRING']) && str_contains($_SERVER['QUERY_STRING'], '&amp;')) {
            $query = [];
            foreach ($this->_query as $key => $val) {
                $key = str_replace('amp;', '', $key);
                $query[$key] = $val;
            }
            $this->_query = $query;
            $query = null;
        }

        unset($this->_query['/' . str_replace('.', '_', urldecode($this->url))]);

        if (strpos($this->url, '?') !== false) {
            [, $querystr] = explode('?', $this->url);
            parse_str($querystr, $queryArgs);
            $this->_query += $queryArgs;
        }

        if (isset($this->params['url'])) {
            $this->_query = array_merge($this->params['url'], $this->_query);
        }

        $this->_trim($this->_query);
    }

/**
 * Process request cookies data.
 *
 * @return void
 */
    protected function _processCookies() {
        $this->_cookies = $_COOKIE;
    }

/**
 * Process $_FILES and move things into the object.
 *
 * @return void
 */
    protected function _processFiles() {
        if (isset($_FILES) && is_array($_FILES)) {
            $this->_restructureFileData($_FILES);
        }
    }

/**
 * Recursively walks the FILES array restructuring the data
 * into something sane and usable.
 *
 * @param array $data The data to traverse/insert.
 * @return void
 */
    protected function _restructureFileData(array $data) {
        foreach ($data as $key => $fileParams) {
            if (!is_array($fileParams)) {
                continue;
            }
            $fileParams = $this->_normalizePhpSingleFileUploadFields($fileParams);
            $_data = [];
            foreach ($fileParams as $name => $files) {
                $_data = Hash::merge($_data, $this->_fileData($files, $name));
            }
            $this->_data[$key] = $_data;
        }
    }

/**
 * PHP single-file uploads expose scalar strings for name/type/tmp_name/error/size.
 * _fileData() expects list-shaped values (like multi-file uploads). Wrap scalars.
 *
 * @param array<string, mixed> $fileParams One $_FILES bucket (e.g. $_FILES['file']).
 * @return array<string, mixed>
 */
    protected function _normalizePhpSingleFileUploadFields(array $fileParams): array
    {
        if (!isset($fileParams['name'], $fileParams['tmp_name'])) {
            return $fileParams;
        }
        if (is_array($fileParams['name'])) {
            return $fileParams;
        }

        $attrs = ['name', 'type', 'tmp_name', 'error', 'size'];
        if (\PHP_VERSION_ID >= 80100) {
            $attrs[] = 'full_path';
        }
        foreach ($attrs as $attr) {
            if (!\array_key_exists($attr, $fileParams)) {
                continue;
            }
            if (!\is_array($fileParams[$attr])) {
                $fileParams[$attr] = [$fileParams[$attr]];
            }
        }

        return $fileParams;
    }

/**
 * Search recursively for value.
 *
 * @param array $fields The data to traverse/insert.
 * @param string $name The terminal field name, which is the top level key in $_FILES.
 * @return array New array
 */
    protected function _fileData(array $fields, string $name) {
        $_files = [];

        foreach ($fields as $index => $files) {
            if (is_array($files)) {
                $_files[$index] = $this->_fileData($files, $name);
            } else {
                $_files[$index][$name] = $files;
            }
        }

        return $_files;
    }

/**
 * Trim request data.
 *
 * @param array $data Data to trim
 * @return array Trimmed data
 */
    private function _trim(array &$data) {
        foreach($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            } elseif (is_array($value)) {
                $this->_trim($value);
            }
            $data[$key] = $value;
        }
    }

/**
 * Get the request uri. Looks in PATH_INFO first, as this is the exact value we need prepared
 * by PHP. Following that, REQUEST_URI, PHP_SELF, HTTP_X_REWRITE_URL and argv are checked in that order.
 * Each of these server variables have the base path, and query strings stripped off.
 *
 * @return string URI The NataPHP request path that is being accessed.
 */
    protected function _url() {
        if (!empty($_SERVER['PATH_INFO'])) {
            return $_SERVER['PATH_INFO'];
        } elseif (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '://') === false) {
            $uri = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $fullUrl = env('HTTPS') ? 'https' : 'http';
            $fullUrl .= '://' . env('HTTP_HOST');
            $uri = substr($_SERVER['REQUEST_URI'], strlen( $fullUrl ));
        } elseif (isset($_SERVER['PHP_SELF']) && isset($_SERVER['SCRIPT_NAME'])) {
            $uri = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['PHP_SELF']);
        } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            $uri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif ($var = env('argv')) {
            $uri = $var[0];
        }

        $base = $this->base;
        if (strlen($base) > 0 && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        if (strpos($uri, '?') !== false) {
            [$uri] = explode('?', $uri, 2);
        }

        if (empty($uri) || $uri == '/' || $uri == '//') {
            return '/';
        }

        return $uri;
    }

/**
 * Returns a base URL and sets the proper webroot.
 *
 * @return string Base URL
 */
    protected function _base() {
        $dir = $webroot = null;
        $config = Configure::read('App');
        extract($config);

        // workaround to prevent having the internal public root exposed to the world
        // when apache rewrites are not properly configured
        $removeInternalPath = function ($base) {
            $base = str_replace('App' . DS . 'public' . DS . 'index.php', '', $base);
            $base = str_replace('App' . DS . 'public', '', $base);
            $base = str_replace('public' . DS . 'index.php', '', $base);
            $base = str_replace('public/index.php', '', $base);
            $base = str_replace('App/public/index.php', '', $base);
            $base = str_replace('App/public', '', $base);
            $base = str_replace('index.php', '', $base);
            return $base;
        };

        if (!isset($base)) {
            $base = $this->base;
        }
        if ($base !== false) {
            $this->webroot = $base . '/';
            return $this->base = $removeInternalPath($base);
        }

        if (!$baseUrl) {
            $base = dirname(env('PHP_SELF'));

            if ($webroot === 'public' && $webroot === basename($base)) {
                $base = dirname($base);
            }
            if (!empty($dir) && $dir === basename($base)) {
                $base = dirname($base);
            }

            if ($base === DS || $base === '.') {
                $base = '';
            }

            $base = $removeInternalPath($base);
            $this->webroot = $base . '/';
            return $this->base = $base;
        }

        $file = '/' . basename($baseUrl);
        $base = dirname($baseUrl);

        if ($base === DS || $base === '.') {
            $base = '';
        }
        $this->webroot = $base . '/';

        $docRoot = env('DOCUMENT_ROOT');
        $docRootContainsWebroot = strpos($docRoot, $dir . '/' . $webroot);

        if (!empty($base) || !$docRootContainsWebroot) {
            if (strpos($this->webroot, '/' . $dir . '/') === false) {
                $this->webroot .= $dir . '/';
            }
            if (strpos($this->webroot, '/' . $webroot . '/') === false) {
                $this->webroot .= $webroot . '/';
            }
        }

        return $this->base = $removeInternalPath($base) . $file;
    }

/**
 * Get the IP the client is using, or says they are using.
 *
 * @param boolean $safe Use safe = false when you think the user might manipulate their HTTP_CLIENT_IP
 *   header. Setting $safe = false will also look at HTTP_X_FORWARDED_FOR
 * @return string The client IP.
 */
    public function clientIp($safe = true) {
        if (!$safe && env('HTTP_X_FORWARDED_FOR') != null) {
            $ipaddr = preg_replace('/(?:,.*)/', '', env('HTTP_X_FORWARDED_FOR'));
        } else {
            if (env('HTTP_CLIENT_IP') != null) {
                $ipaddr = env('HTTP_CLIENT_IP');
            } else {
                $ipaddr = env('REMOTE_ADDR');
            }
        }

        if (env('HTTP_CLIENTADDRESS') != null) {
            $tmpipaddr = env('HTTP_CLIENTADDRESS');

            if (!empty($tmpipaddr)) {
                $ipaddr = preg_replace('/(?:,.*)/', '', $tmpipaddr);
            }
        }

        return trim($ipaddr);
    }

/**
 * Returns the referer that referred this request.
 *
 * @param boolean $local Attempt to return a local address. Local addresses do not contain hostnames.
 * @return string The referring address for this request.
 */
    public function referer($local = false) {
        $ref = env('HTTP_REFERER');
        $forwarded = env('HTTP_X_FORWARDED_HOST');
        if ($forwarded) {
            $ref = $forwarded;
        }

        $base = '';
        if (defined('FULL_BASE_URL')) {
            $base = FULL_BASE_URL . $this->webroot;
        }

        if (!empty($ref) && !empty($base)) {
            if ($local && strpos($ref, $base) === 0) {
                $ref = substr($ref, strlen($base));
                if ($ref[0] != '/') {
                    $ref = '/' . $ref;
                }
                return $ref;
            } elseif (!$local) {
                return $ref;
            }
        }

        return '/';
    }

/**
 * Magic get method allows access to parsed routing parameters directly on the object.
 *
 * Allows access to `$this->params['controller']` via `$this->controller`
 *
 * @param string $name The property being accessed.
 * @return mixed Either the value of the parameter or null.
 */
    public function __get($name) {
        if ($name === 'data') {
            return $this->_data;
        } elseif ($name === 'query') {
            return $this->_query;
        } else if (isset($this->params[$name])) {
            return $this->params[$name];
        }
    }

/**
 * Magic isset method allows isset/empty checks
 * on routing parameters.
 *
 * @param string $name The property being accessed.
 * @return bool Existence
 */
    public function __isset($name) {
        return isset($this->params[$name]);
    }

/**
 * Magic call method allows dynamicall
 * on routing parameters.
 *
 * @param string $name The pseudo-method being called.
 * @param array $arguments The arguments passed to pseudo-method.
 * @return mixed The called method return
 */
    public function __call($name, array $arguments) {
        if (substr($name, 0, 2) === 'is') {
            $type = strtolower(substr($name, 2));
            return $this->is($type);
        }
    }

/**
 * Returns the instance of the Session object for this request.
 *
 * @return \Nata\Http\Session
 */
    public function getSession() {
        return $this->_session;
    }

/**
 * Check whether or not a Request is a certain type.
 * Uses the built in detection rules as well as additional rules
 * defined with Nata\Http\Request::addDetector(). Any detector can be called as `is($type)` or `is$Type()`.
 *
 * @param string|array $types The type of request you want to check.
 * @return boolean Whether or not the request is the type you are checking.
 */
    public function is($types) {
        foreach ((array)$types as $type) {
            $type = strtolower($type);
            if (!isset($this->_detectors[$type])) {
                return false;
            }

            if ('htmx' === $type) {
                return $this->header('HX-Request') === 'true';
            }

            $detect = $this->_detectors[$type];

            if (isset($detect['method']) && method_exists($this, $detect['method'])) {
                return call_user_func([$this, $detect['method']]);
            }

            if (isset($detect['callback']) && is_callable($detect['callback'])) {
                return call_user_func($detect['callback'], $this);
            }

            if (isset($detect['env'])) {
                if (isset($detect['value']) && ((is_array($detect['value']) && in_array(env($detect['env']), $detect['value']))
                      || (env($detect['env']) == $detect['value']))) {
                    return true;
                }
                if (isset($detect['pattern']) && (bool)preg_match($detect['pattern'], env($detect['env']))) {
                    return true;
                }
                if (isset($detect['options'])) {
                    $pattern = '/' . implode('|', $detect['options']) . '/i';
                    if ((bool)preg_match($pattern, env($detect['env']))) {
                        return true;
                    }
                }
            }

            if (isset($detect['param'])) {
                $key = $detect['param'];
                $value = $detect['value'];
                if (isset($this->params[$key]) && $this->params[$key] == $value) {
                    return true;
                }
            }
        }

        return false;
    }

/**
 * Check if the request is using SSL.
 *
 * @return boolean Whether or not the request is using SSL.
 */
    private function _isSsl() {
        $forwardedProto = $this->header('X-Forwarded-Proto');
        if ($forwardedProto !== null) {
            return strtolower($forwardedProto) === 'https';
        }
        // Fall back to HTTPS environment variable
        return env('HTTPS') == 1;
    }

/**
 * Add a new detector to the list of detectors that a request can use.
 * There are several different formats and types of detectors that can be set.
 *
 * ### Environment value comparison
 *
 * An environment value comparison, compares a value fetched from `env()` to a known value
 * the environment value is equality checked against the provided value.
 *
 * e.g `addDetector('post', array('env' => 'REQUEST_METHOD', 'value' => 'POST'))`
 *
 * ### Pattern value comparison
 *
 * Pattern value comparison allows you to compare a value fetched from `env()` to a regular expression.
 *
 * e.g `addDetector('iphone', array('env' => 'HTTP_USER_AGENT', 'pattern' => '/iPhone/i'));`
 *
 * ### Option based comparison
 *
 * Option based comparisons use a list of options to create a regular expression.  Subsequent calls
 * to add an already defined options detector will merge the options.
 *
 * e.g `addDetector('mobile', array('env' => 'HTTP_USER_AGENT', 'options' => array('Fennec')));`
 *
 * ### Method detectors
 *
 * Method detectors allow you to specify a method name on the Request object to handle the check.
 * The method will be called without parameters.
 *
 * e.g `addDetector('ssl', array('method' => '_isSsl'));`
 *
 * ### Callback detectors
 *
 * Callback detectors allow you to provide a 'callback' type to handle the check.  The callback will
 * receive the request object as its only parameter.
 *
 * e.g `addDetector('custom', array('callback' => array('SomeClass', 'somemethod')));`
 *
 * ### Request parameter detectors
 *
 * Allows for custom detectors on the request parameters.
 *
 * e.g `addDetector('post', array('param' => 'requested', 'value' => 1)`
 *
 * @param string $name The name of the detector.
 * @param array $options  The options for the detector definition.  See above.
 * @return void
 */
    public function addDetector($name, $options) {
        $name = strtolower($name);
        if (isset($this->_detectors[$name]) && isset($options['options'])) {
            $options = Hash::merge($this->_detectors[$name], $options);
        }
        $this->_detectors[$name] = $options;
    }

/**
 * Add parameters to the request's parsed parameter set. This will overwrite any existing parameters.
 * This modifies the parameters available through `$request->params`.
 *
 * @param array $params Array of parameters to merge in
 * @return The current object, you can chain this method.
 */
    public function addParams($params) {
        $this->params = array_merge($this->params, (array)$params);
        return $this;
    }

/**
 * Add paths to the requests' paths vars.
 * This will overwrite any existing paths.
 * Provides an easy way to modify, here, webroot and base.
 *
 * @param array $paths Array of paths to merge in
 * @return NataRequest the current object, you can chain this method.
 */
    public function addPaths($paths) {
        foreach (array('webroot', 'here', 'base') as $element) {
            if (isset($paths[$element])) {
                $this->{$element} = $paths[$element];
            }
        }
        return $this;
    }

/**
 * Get the value of the current requests url.
 * Will include named parameters and querystring arguments.
 *
 * @param boolean $base Include the base path, set to false to trim the base path off.
 * @return string the current request url including query string args.
 */
    public function here($base = true) {
        $url = $this->here;

        if (!empty($this->_query)) {
            $url .= Router::queryString($this->_query);
        }

        if (!$base) {
            $url = preg_replace('/^' . preg_quote($this->base, '/') . '/', '', $url, 1);
        }

        return $url;
    }

/**
 * Read an HTTP header from the Request information.
 *
 * @param string $name Name of the header you want.
 * @return mixed Either false on no header being set or the value of the header.
 */
    public function getHeaderLine($name) {
        return $this->header($name);
    }

/**
 * Read an HTTP header from the Request information.
 *
 * @param string $name Name of the header you want.
 * @return mixed Either false on no header being set or the value of the header.
 */
    public function header($name = null) {
        $header = $this->_getHeader();
        if ($name === null) {
            return $header;
        }

        $name = strtolower($name);
        return $header[$name] ?? null;
    }

/**
 * Read an HTTP header from the Request information.
 *
 * @param string $name Name of the header you want.
 * @return mixed Either false on no header being set or the value of the header.
 */
    private function _getHeader() {
        if ($this->_header === null) {
            $this->_header = [];
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                $name = strtolower($name);
                $this->_header[$name] = $value;
            }

            $keys = array_keys($this->_header);
            foreach ($_SERVER as $name => $value) {
                if (strpos($name, 'HTTP_') === false) {
                    continue;
                }

                $name = strtolower(str_replace('_', '-', str_replace('HTTP_', '', $name)));
                if (in_array($name, $keys)) {
                    continue;
                }

                $this->_header[$name] = $value;
            }
        }
        return $this->_header;
    }

/**
 * Get the HTTP scheme used for this request.
 *
 * Checks X-Forwarded-Proto header first (for requests behind proxies/load balancers),
 * then falls back to the HTTPS environment variable.
 *
 * @return string The HTTP scheme used.
 */
    public function scheme() {
        // Check X-Forwarded-Proto header first (for requests behind proxies/load balancers)
        $forwardedProto = $this->header('X-Forwarded-Proto');
        if ($forwardedProto !== null) {
            return strtolower($forwardedProto) === 'https' ? 'https' : 'http';
        }

        // Fall back to HTTPS environment variable
        return env('HTTPS') ? 'https' : 'http';
    }

/**
 * Get the HTTP method used for this request.
 * There are a few ways to specify a method.
 *
 * - If your client supports it you can use native HTTP methods.
 * - You can set the HTTP-X-Method-Override header.
 * - You can submit an input with the name `_method`
 *
 * Any of these 3 approaches can be used to set the HTTP method used
 * by NataPHP internally, and will effect the result of this method.
 *
 * @return string The name of the HTTP method used.
 */
    public function getMethod() {
        return env('REQUEST_METHOD');
    }

/**
 * @deprecated Use getMethod() instead
 * @return string The name of the HTTP method used.
 */
    public function method() {
        return $this->getMethod();
    }

/**
 * Get the host that the request was handled on.
 *
 * @return string
 */
    public function getHost() {
        return env('HTTP_HOST') ?? parse_url($this->url, PHP_URL_HOST);
    }

/**
 * @deprecated Use getHost() instead
 */
    public function host() {
        return $this->getHost();
    }

/**
 * Get the domain name and include $tldLength segments of the tld.
 *
 * @param integer $tldLength Number of segments your tld contains. For example: `example.com` contains 1 tld.
 *   While `example.co.uk` contains 2.
 * @return string Domain name without subdomains.
 */
    public function getDomain($tldLength = 1) {
        $segments = explode('.', $this->getHost());
        $domain = array_slice($segments, -1 * ($tldLength + 1));
        return implode('.', $domain);
    }

/**
 * Get the domain name and include $tldLength segments of the tld.
 *
 * @deprecated Use getDomain() instead
 */
    public function domain($tldLength = 1) {
        return $this->getDomain($tldLength);
    }

/**
 * Get the subdomains for a host.
 *
 * @param integer $tldLength Number of segments your tld contains. For example: `example.com` contains 1 tld.
 *   While `example.co.uk` contains 2.
 * @return array of subdomains.
 */
    public function getSubdomains($tldLength = 1) {
        $segments = explode('.', $this->getHost());
        return array_slice($segments, 0, -1 * ($tldLength + 1));
    }

/**
 * Get the subdomains for a host.
 *
 * @deprecated Use getSubdomains() instead
 */
    public function subdomains($tldLength = 1) {
        return $this->getSubdomains($tldLength);
    }

/**
 * Find out which content types the client accepts or check if they accept a
 * particular type of content.
 *
 * #### Get all types:
 *
 * `$this->request->accepts();`
 *
 * #### Check for a single type:
 *
 * `$this->request->accepts('application/json');`
 * `$this->request->accepts('json');`
 *
 * This method will order the returned content types by the preference values indicated
 * by the client.
 *
 * @param string $type The content type to check for. Leave null to get all types a client accepts.
 * @return array|bool Either an array of all the types the client accepts or a boolean if they accept the
 *   provided type.
 */
    public function accepts($type = null) {
        $raw = $this->parseAccept();
        $accept = [];

        foreach ($raw as $types) {
            $accept = array_merge($accept, $types);
        }

        if ($type === null) {
            return $accept;
        }

        foreach ($accept as $mimetype) {
            if ($mimetype === $type) {
                return true;
            }

            [$s, $subtype] = explode('/', $mimetype);

            if ($subtype === $type) {
                return true;
            }
        }

        return false;
    }

/**
 * Parse the HTTP_ACCEPT header and return a sorted array with content types
 * as the keys, and pref values as the values.
 *
 * Generally you want to use \Nata\Http\Request::accept() to get a simple list
 * of the accepted content types.
 *
 * @return array An array of prefValue => array(content/types)
 */
    public function parseAccept() {
        $accept = [];
        $header = explode(',', $this->header('accept'));
        foreach (array_filter($header) as $value) {
            $prefPos = strpos($value, ';');

            if ($prefPos !== false) {
                $prefValue = substr($value, strpos($value, '=') + 1);
                $value = trim(substr($value, 0, $prefPos));
            } else {
                $prefValue = '1.0';
                $value = trim($value);
            }

            if (!isset($accept[$prefValue])) {
                $accept[$prefValue] = [];
            }

            if ($prefValue) {
                $accept[$prefValue][] = $value;
            }
        }

        krsort($accept);

        return $accept;
    }

/**
 * Get the languages accepted by the client, or check if a specific language is accepted.
 *
 * Get the list of accepted languages:
 *
 * {{{ Request->acceptLanguage(); }}}
 *
 * Check if a specific language is accepted:
 *
 * {{{ Request->acceptLanguage('es-es'); }}}
 *
 * @param string $language The language to test.
 * @return array|bool If a $language is provided, a boolean. Otherwise the array of accepted languages.
 */
    public function acceptLanguage($language = null) {
        $accepts = preg_split('/[;,]/', $this->header('Accept-Language'));

        foreach ($accepts as &$accept) {
            $accept = strtolower($accept);
            if (strpos($accept, '_') !== false) {
                $accept = str_replace('_', '-', $accept);
            }
        }

        if ($language === null) {
            return $accepts;
        }

        return in_array($language, $accepts);
    }

/**
* Provides a read accessor for `$this->params`.
*
* @param string $name Dot separated name of the value to read
* @return mixed The value being read
*/
    public function getParams($name = null) {
        if (!empty($name)) {
            return Hash::get($this->params, $name);
        }
        return $this->params;
    }

/**
 * Provides a read/write accessor for `$this->_data`.
 * Allows you to use a syntax similar to `\Nata\Http\Session` for reading post data.
 *
 * ## Reading values.
 *
 * `$request->data('Post.title');`
 *
 * When reading values you will get `null` for keys/values that do not exist.
 *
 * ## Writing values
 *
 * `$request->data('Post.title', 'New post!');`
 *
 * You can write to any value, even paths/keys that do not exist, and the arrays
 * will be created for you.
 *
 * @param string $name Dot separated name of the value to read/write
 * @param string $value Value to write
 * @return mixed Either the value being read, or this so you can chain consecutive writes.
 */
    public function data($name = null, $value = null) {
        if ($name === null) {
            return $this->_data;
        } elseif (!is_array($name)) {
            if ($value === null) {
                if (func_num_args() === 1) {
                    return Hash::get($this->_data, $name);
                }
                unset($this->_data[$name]);
            } else {
                $this->_data = Hash::insert($this->_data, $name, $value);
            }
        } else {
            $this->_data = array_merge($this->_data, $name);
        }
        return $this;
    }

/**
* Provides a read accessor for `$this->_query`.
*
* @param string $name Dot separated name of the value to read
* @return mixed The value being read
*/
    public function query(string $name = null) {
        if ($name === null) {
            return $this->_query;
        }
        return Hash::get($this->_query, $name);
    }

/**
* Same as `$this->query()` but only returns values if it's a valid signed query.
*
* @param string $name Dot separated name of the value to read
* @return mixed The value being read
*/
    public function signedQuery(string $name = null) {
        if (!Router::isSignedQueryValid($this->_query)) {
            return $name === null ? [] : null;
        }
        return $this->query($name);
    }

/**
* Provides a read accessor for `$this->_cookies`.
*
* @param string $name Name of the cookie to read
* @return mixed The cookie value being read
*/
    public function cookies(string $name = null) {
        if ($name === null) {
            return $this->_cookies;
        }
        return isset($this->_cookies[$name]) ? $this->_cookies[$name] : null;
    }

/**
 * Read data from `php://input`. Useful when interacting with XML or JSON
 * request body content.
 *
 * Getting input with a decoding function:
 *
 * `$this->request->input('json_decode');`
 *
 * Getting input using a decoding function, and additional params:
 *
 * `$this->request->input('Xml::build', array('return' => 'DOMDocument'));`
 *
 * Any additional parameters are applied to the callback in the order they are given.
 *
 * @param string $callback A decoding callback that will convert the string data to another
 *     representation. Leave empty to access the raw input data. You can also
 *     supply additional parameters for the decoding callback using var args, see above.
 * @return The decoded/processed request data.
 */
    public function input($callback = null) {
        $input = $this->_readInput();
        $args = func_get_args();
        if (!empty($args)) {
            $callback = array_shift($args);
            array_unshift($args, $input);
            return call_user_func_array($callback, $args);
        }
        return $input;
    }

/**
 * Read data from php://input, mocked in tests.
 *
 * @return string contents of php://input
 */
    protected function _readInput() {
        if (empty($this->_input)) {
            $fh = fopen('php://input', 'r');
            $content = stream_get_contents($fh);
            fclose($fh);
            $this->_input = $content;
        }
        return $this->_input;
    }

/**
 * Array access read implementation.
 *
 * @param string $name Name of the key being accessed.
 * @return mixed
 */
    public function offsetGet($name): mixed {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }
        if ($name == 'url') {
            return $this->_query;
        }
        if ($name == 'data') {
            return $this->_data;
        }
        return null;
    }

/**
 * Array access write implementation.
 *
 * @param string $name Name of the key being written
 * @param mixed $value The value being written.
 * @return void
 */
    public function offsetSet($name, $value): void {
        $this->params[$name] = $value;
    }

/**
 * Array access isset() implementation.
 *
 * @param string $name thing to check.
 * @return boolean
 */
    public function offsetExists($name): bool {
        return isset($this->params[$name]);
    }

/**
 * Array access unset() implementation.
 *
 * @param string $name Name to unset.
 * @return void
 */
    public function offsetUnset($name): void {
        unset($this->params[$name]);
    }

}