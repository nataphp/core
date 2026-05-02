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

namespace Nata\Http\Client;

use Nata\Cache\Cache;
use Nata\Core\App;
use Nata\Utility\Inflector;
use Nata\Filesystem\File;
use Nata\Filesystem\Mimetype;
use Nata\Http\Client\Response;
use InvalidArgumentException;
use Nata\Http\Client;
use Nata\I18n\Time;
use CURLFile;
use Nata\Http\Exception\HttpRequestException;

/**
 * HTTP request.
 */
class Request {

/**
 * HTTP version.
 *
 * @var string
 */
    protected $_httpVersion = '1.1';

/**
 * Method.
 *
 * @var string
 */
    protected $_method = 'GET';

/**
 * Request header.
 *
 * @var array
 */
    protected $_header = [];

/**
 * Cookies.
 *
 * @var array
 */
    protected $_cookies = [];

/**
 * Cookie jar file.
 *
 * @var \Nata\Filesystem\File|bool|string
 */
    protected $_cookieJar;

/**
 * Clear Cookie jar file.
 *
 * @var bool
 */
    protected $_clearCookieJar = false;

/**
 * Query.
 *
 * @var string
 */
    protected $_query;

/**
 * Body/data to sent.
 *
 * @var array
 */
    protected $_body;

/**
 * URL.
 *
 * @var string
 */
    protected $_url;

/**
 * Verifying peer's certificate.
 *
 * @var bool|string
 */
    protected $_verify = true;

/**
 * CA Certificates file.
 *
 * @var File
 */
    protected $_caCertificate;

/**
 * cURL options.
 *
 * @var array
 */
    private $_options = [];

/**
 * Cache config.
 *
 * @var string
 */
    protected $_cache;

/**
 * Methods in which the cache can be used.
 *
 * @var array
 */
    protected $_cacheMethods = ['GET'];

/**
 * Files to send.
 *
 * @var array
 */
    protected $_files;

/**
 * Files list with respective paths to prevent duplicates.
 *
 * @var array
 */
    protected $_filesUnique = [];

/**
 * Default properties values.
 *
 * @var array
 */
    protected $_defaultProperties = [];

/**
 * Header sent out by cURL.
 * This is only set after request is made.
 *
 * @var string
 */
    protected $_headerOut;

/**
 * Runtime cache.
 * This only works if cache is enabled.
 * Improves performance by avoiding using cache lib
 *
 * @var array
 */
    protected static $_runtimeCache = [];


/**
 * Constructor.
 *
 * @param string $url Request URL
 * @param array $config Request configuration
 * @return void
 */
    public function __construct($url = null, array $config = []) {
        // For BC accept the config on first parameter
        if (is_array($url)) {
            $config = $url;
            $url = null;
        } elseif (is_string($url)) {
            $config['url'] = $url;
            $url = null;
        }

        $this->_options = [
            CURLOPT_CUSTOMREQUEST => $this->_method,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 150,
            CURLOPT_VERBOSE => 0,
            CURLOPT_ENCODING => '', // This is set for the 'Accept-Encoding' header value (Defaults to 'deflate, gzip')
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];

        if (!is_array($config)) {
            $config = ['url' => $config];
        }

        $this->_defaultProperties = get_object_vars($this);

        if (!empty($config)) {
            $this->config($config);
        }

    }

/**
 * Get/Set request method.
 *
 * @param string $method Request type/method
 * @return $this|mixed
 */
    public function method($method = null) {
        if ($method === null) {
            return $this->options(CURLOPT_CUSTOMREQUEST);
        }

        $this->_method = strtoupper($method);
        $this->options(CURLOPT_CUSTOMREQUEST, $this->_method);

        return $this;
    }

/**
 * Get/Set server's response timeout.
 *
 * @param int $timeout timeout
 * @return $this|int
 */
    public function connectionTimeout($connectionTimeout = null) {
        if ($connectionTimeout === null) {
            return $this->options(CURLOPT_CONNECTTIMEOUT);
        }
        $this->options(CURLOPT_CONNECTTIMEOUT, $connectionTimeout);
        return $this;
    }

/**
 * Get/Set request execution timeout.
 *
 * @param int $execTimeout Execution timeout
 * @return $this|int
 */
    public function timeout($timeout = null) {
        if ($timeout === null) {
            return $this->options(CURLOPT_TIMEOUT);
        }
        $this->options(CURLOPT_TIMEOUT, $timeout);
        return $this;
    }

/**
 * Get/Set request execution timeout.
 *
 * @param int $execTimeout Execution timeout
 * @return $this|int
 */
    public function maxRedirections($maxRedirection = null) {
        if ($maxRedirection === null) {
            return $this->options(CURLOPT_MAXREDIRS);
        }
        $this->options(CURLOPT_MAXREDIRS, $maxRedirection);
        return $this;
    }

/**
 * Get/Set fail on error. Defaults to 'false'.
 *
 * @param bool $execTimeout Execution timeout
 * @return $this|int
 */
    public function failOnError($failOnError = null) {
        if ($failOnError === null) {
            return $this->options(CURLOPT_FAILONERROR);
        }
        $this->options(CURLOPT_FAILONERROR, $failOnError);
        return $this;
    }

/**
 * Get/Set HTTP version.
 *
 * @param int $httpVersion Execution timeout
 * @return $this|int
 */
    public function httpVersion($httpVersion = null) {
        if ($httpVersion === null) {
            return $this->_httpVersion;
        }
        $this->_httpVersion = $httpVersion;
        return $this;
    }

/**
 * Get/Set request execution timeout.
 *
 * @param int $execTimeout Execution timeout
 * @return $this|int
 */
    public function userAgent($userAgent = null) {
        return $this->header('User-Agent', $userAgent);
    }

/**
 * Get/Set Content type.
 *
 * @param string $contentType Content type
 * @return $this|string
 */
    public function contentType($contentType = null) {
        if (strpos($contentType, '/') === false) {
            if ($type = Mimetype::get($contentType)) {
                [$contentType] = $type;
            }
        }
        return $this->header('Content-Type', $contentType);
    }

/**
 * Get/Set SSL certificate verification behavior of a request.
 *
 * - Set to true to enable SSL certificate verification and use the default CA bundle provided by operating system.
 * - Set to false to disable certificate verification (this is insecure!).
 * - Set to a string to provide the path to a CA bundle to enable verification using a custom certificate.
 *
 * @param bool|string $verify
 * @param string $value Header value
 * @return $this|array|string
 */
    public function verify($verify = null) {
        if ($verify === null) {
            if ($this->_verify === null) {
                $this->_verify = true;
            }
            return $this->_verify;
        }
        $this->_verify = $verify;
        return $this;
    }

/**
 * Get/Set request header fields.
 *
 * @param array|string $field Array to replace header string to get/set one particular header
 * @param string $value Header value
 * @return $this|array|string
 */
    public function header($field = null, $value = null) {
        return $this->_keyValue('_header', $field, $value);
    }

/**
 * Add request header field.
 *
 * @param string $field Header field
 * @param string $value Header field value
 * @return $this
 */
    public function addHeader($field, $value = null) {
        if (!is_array($field)) {
            $field = [$field => $value];
        }
        $this->_header = array_merge($this->_header, $field);
        return $this;
    }

/**
 * Remove request header field.
 *
 * @param string $field Header field to remove
 * @return $this
 */
    public function removeHeader($field) {
        unset($this->_header[$field]);
        return $this;
    }

/**
 * Get/Set cookies.
 *
 * @param array|string $key Array to replace cookies,
 *  string to get/set one particular cookie value
 * @param string $value Cookie value
 * @return $this|array|string
 */
    public function cookies($key = null, $value = null) {
        return $this->_keyValue('_cookies', $key, $value);
    }

/**
 * Add request header.
 *
 * @param array|string $key Array to add cookies,
 *  string to add one particular cookie value
 * @param string $value Cookie value
 * @return $this
 */
    public function addCookie($key, $value) {
        $this->_cookies = array($key => $value) + $this->_cookies;
        return $this;
    }

/**
 * Get/Set Cookie Jar file.
 * Shorthand for CURLOPT_COOKIEFILE, CURLOPT_COOKIEJAR options.
 *
 * If true, the cookie jar file will be set automatically.
 * If filename, if will be used as the cookie jar file.
 *
 * @param \Nata\Filesystem\File|bool|string $cookieJar Cookie jar
 * @return \Nata\Filesystem\File|bool|string|$this
 */
    public function cookieJar($cookieJar = null) {
        if (func_num_args() === 0) {
            return $this->_cookieJar;
        }

        $this->_cookieJar = $cookieJar;

        return $this;
    }

/**
 * Get/Set Cookie Jar file.
 * Shorthand for CURLOPT_COOKIEFILE, CURLOPT_COOKIEJAR options.
 *
 * If true, the cookie jar file will be set automatically.
 * If filename, if will be used as the cookie jar file.
 *
 * @param string $clearCookieJar Clear Cookie jar
 * @return string|$this
 */
    public function clearCookieJar($clearCookieJar = null) {
        if ($clearCookieJar === null) {
            return $this->_clearCookieJar;
        }

        $this->_clearCookieJar = $clearCookieJar;

        return $this;
    }

/**
 * Get/Set cURL options.
 *
 * @param array|string $option Array to replace options,
 *  string to get/set one particular option value
 * @param string $value Cookie value
 * @return $this|array|string
 */
    public function options($option = null, $value = null) {
        return $this->_keyValue('_options', $option, $value);
    }

/**
 * Add cURL option.
 *
 * @param string $option Array to add cookies,
 *  string to add one particular cookie value
 * @param string $value Cookie value
 * @return $this
 */
    public function addOption($option, $value) {
        $this->_options = array($option => $value) + $this->_options;
        return $this;
    }

/**
 * Get/Set key/value values.
 *
 * @param array|string $key Array to replace values,
 *  string to get/set one particular value
 * @param string $value Value
 * @return $this|array|string
 */
    protected function _keyValue($property, $key = null, $value = null) {
        if ($key === null) {
            return $this->{$property};
        }

        if (!is_array($key)) {
            if ($value === null) {
                return isset($this->{$property}[$key]) ? $this->{$property}[$key] : null;
            }
            $this->{$property}[$key] = $value;
        } else {
            $this->{$property} = $key;
        }

        return $this;
    }

/**
 * Get/Set if is a simulated XMLHttpRequest.
 *
 * @param bool $ajax AJAX request
 * @return $this|bool
 */
    public function ajax($ajax = null) {
        if ($ajax === null) {
            return $this->header('X-Requested-With') === 'XMLHttpRequest';
        }

        if ($ajax === true) {
            $this->_header['X-Requested-With'] = 'XMLHttpRequest';
        } elseif ($this->header('X-Requested-With')) {
            unset($this->_header['X-Requested-With']);
        }

        return $this;
    }

/**
 * Get/Set URL.
 *
 * @param string $url URL
 * @return $this|string
 */
    public function url($url = null) {
        if ($url === null) {
            return $this->_url;
        }
        $this->_url = $url;
        return $this;
    }

/**
 * Get/Set query parameters.
 *
 * @param string|array $query Query parameters
 * @return $this|mixed
 */
    public function query($query = null) {
        if ($query === null) {
            // usage of data() as the source for query parameters is for BC
            if ($this->_query === null && $this->_method === 'GET' && $data = $this->data()) {
                $this->_query = http_build_query($data);
            }
            return $this->_query;
        }

        $this->_query = http_build_query($query);

        return $this;
    }

/**
 * Get/Set body data to be sent.
 *
 * @param mixed $body Data to be sent
 * @return $this|mixed
 */
    public function body($body = null) {
        if ($body === null) {
            return $this->_body;
        }

        $this->_body = $body;

        return $this;
    }

/**
 * Convenience method for setting the body of the request.
 *
 * @param mixed $data Data to be sent
 * @return $this|mixed
 */
    public function data($data = null) {
        return $this->body($data);
    }

/**
 * Get/Set cache config.
 *
 * @param string $cache Cache config name
 * @param string|array $methods HTTP methods in which
 * @return $this|string
 */
    public function cache($cache = null, $methods = []) {
        if (func_num_args() === 0) {
            return $this->_cache;
        }
        $this->_cache = $cache === true ? 'default' : $cache;
        $this->_cacheMethods = (array)$methods;
        return $this;
    }

/**
 * Set/Get files.
 *
 * Add a file and specify alternative filename:
 *
 * ```
 * $request->files('/path/to/file.png', 'custom_name.png');
 * ```
 *
 * Add an array of files and specify alternative filenames:
 *
 * ```
 * $request->addFiles([
 *     'path/to/file' => 'custom_name.png',
 *     'path/to/file2' => 'another_custom_name.jpg',
 * ]);
 * ```
 *
 * Alternatively you can control the each file's field name individually:
 *
 * ```
 * $request->addFiles([
 *      [
 *          'file' => '/path/to/photo.jpg',
 *          'field' => 'image'
 *      ],
 *      [
 *          'file' => '/path/to/another-file.docx',
 *          'field' => 'document'
 *      ]
 * ]);
 * ```
 *
 * To enable multiple, just add [] to the fieldname:
 *
 * ```
 * $request->addFiles([
 *      [
 *          'file' => '/path/to/photo.jpg',
 *          'field' => 'image[]'
 *      ]
 * ]);
 * ```
 *
 * @param File|string|array $files Null to get, String with path,
 *   Array with file path as key, alternative file name as value or file path as value (without alternative name)
 * @param string $name Alternative file name
 * @param string $field Field name
 * @return array|$this
 */
    public function files($files = null, $name = null, $field = 'file') {
        if ($files === null) {
            return $this->_files;
        }

        $this->_files = [];
        $this->_addFiles($files, $name, $field);

        return $this;
    }

/**
 * Add files.
 *
 * @param File|string|array $files Null to get, String with path,
 *   Array with file path as key, alternative file name as value or file path as value (without alternative name)
 * @param string $name Alternative file name
 * @param string $field Field name
 * @return $this
 */
    public function addFiles($files, $name = null, $field = 'file') {
        $this->_addFiles($files, $name, $field);
        return $this;
    }

/**
 * Prepare files.
 *
 * @param string|array $files Files to prepare
 * @param string|null $name Name
 * @param string|null $field Field
 * @return void
 * @see Request::files
 * @see Request::addFiles
 */
    protected function _addFiles($files, $name, $field) {
        $default = [
            'field' => $field,
            'file' => null,
            'name' => null
        ];

        if ($files instanceof File) {
            $files = [['file' => $files, 'name' => $name]];
        } elseif (is_string($files)) {
            $files = [['file' => $files, 'name' => $name]];
        } elseif (isset($files['file'])) {
            $files = [$files];
        }

        foreach ($files as $path => $file) {
            if (is_int($path) && is_string($file)) {
                $path = $file;
                $file = $default;
            } elseif (is_string($path) && is_string($file)) {
                $file = [
                    'file' => $path,
                    'name' => $file
                ];
            } elseif ($file instanceof File) {
                $file = ['file' => $file];
            }

            $file += $default;
            if (empty($file['file'])) {
                throw new HttpRequestException(sprintf('File path is empty (%s).', gettype($file['file'])));
            } elseif (is_string($file['file'])) {
                $file['file'] = new File($file['file']);
            }

            if (!($file['file'] instanceof File)) {
                $type = gettype($file['file']);
                if ($type === 'object') {
                    $type = get_class($file['file']);
                }

                throw new HttpRequestException(sprintf(
                    'File is invalid. It needs to be a valid path or File instance, given "%s".',
                    $type
                ));
            }

            $path = $file['file']->pwd();
            if (!$file['file']->exists()) {
                throw new InvalidArgumentException(sprintf('File not found: "%s"', $path));
            }

            // Already set? continue...
            if (isset($this->_filesUnique[$file['file']->pwd()])) {
                continue;
            }
            // Make sure there's no duplicate files
            $this->_filesUnique[$file['file']->pwd()] = 1;

            if (!$file['name']) {
                $file['name'] = $file['file']->basename();
            }

            $cUrlFile = $this->_fileCreate(
                $file['file']->pwd(),
                $file['file']->mime(),
                $file['name']
            );

            $fieldName = $file['field'];
            $fieldName = str_replace('[]', '', $fieldName);

            $isMultiple = $this->_isPostMultiple($fieldName, $file['field']);
            if (!$isMultiple) {
                $this->_files[$fieldName] = $cUrlFile;
                continue;
            }

            if (isset($this->_files[$fieldName])) {
                $this->_files[$fieldName] = [$this->_files[$fieldName]];
            }

            $this->_files[$fieldName][] = $cUrlFile;
        }

        // If theres files, it's a multipart/form-data content type
        if (!$this->header('Content-Type') && $this->_files) {
            $this->header('Content-Type', 'multipart/form-data');
        }

    }

/**
 * Check if files POST fields should be multiple or not.
 *
 * @param string $fieldName Field name (without [])
 * @param string $fieldOption Field name in file options
 * @return bool
 */
    protected function _isPostMultiple($fieldName, $fieldOption): bool {
        if (str_contains($fieldOption, '[]')) {
            return true;
        }

        if (isset($this->_files[$fieldName])) {
            return true;
        }

        return false;
    }

/**
 * Send HTTP request.
 *
 * @param array $options One-time use options
 * @return \Nata\Http\Client\Response Response instance
 */
    public function send(array $options = []) {
        $options += $this->_options;
        $url = $this->url();

        // Set data
        if (!in_array($this->_method, ['HEAD', 'OPTIONS', 'TRACE'])) {
            $data = $this->body();
            $query = $this->query();
            // GET
            if ($this->_method === 'GET') {
                $url .= $query ? '?' . $query : '';
            // POST/PUT/DELETE/CONNECT...
            } elseif ($data || $this->_files) {
                $options[CURLOPT_POSTFIELDS] = $this->_getPreparedPostData($data);
            }
        }

        $options[CURLOPT_URL] = $url;

        // SSL Setup
        $certificate = $this->_loadRootCertificates()->pwd();
        if ($certificate && $this->verify()) {
            $options[CURLOPT_SSL_VERIFYPEER] = $certificate;
        }
        $options[CURLOPT_CAINFO] = $certificate;

        // Cookie Jar
        $cookieJarFile = $this->_getCookieJarFile($url);
        if ($cookieJarFile) {
            if ($this->_clearCookieJar === true) {
                file_put_contents($cookieJarFile, '');
            }
            $options[CURLOPT_COOKIEFILE] = $cookieJarFile;
            $options[CURLOPT_COOKIEJAR] = $cookieJarFile;
        }

        if ($this->_cookies) {
            $options[CURLOPT_COOKIE] = $this->_normalizeCookie($this->_cookies);
        }

        $options[CURLOPT_HTTPHEADER] = $this->_normalizeHeader($this->_header);

        if ($this->_httpVersion == 2) {
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        }

        $response = $this->_cache($options);
        if (!$response) {
            $ch = curl_init();
            curl_setopt_array($ch, $options);

            $rawResponse = '';
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$rawResponse) {
                $rawResponse .= $data;
                return strlen($data);
            });

            curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $error = curl_error($ch);
            $this->_headerOut = curl_getinfo($ch, CURLINFO_HEADER_OUT);
            $body = $this->_createHandle(substr($rawResponse, $headerSize));

            curl_close($ch);

            $response = null;
            $responses = $this->_parseResponses(substr($rawResponse, 0, $headerSize));
            foreach ($responses as $index => $responseData) {
                $_rawResponse = implode(PHP_EOL, $responseData);

                // Load response instance
                $response = new Response([
                    'request' => $this,
                    'url' => $this->url(),
                    'body' => ($index === (count($responses) - 1) ? $body : null),
                    'raw' => $_rawResponse,
                    'error' => $error,
                    'headerSize' => $headerSize,
                    'headerOut' => $this->_headerOut
                ], $response);

                $_rawResponse = null;
            }

            $body = $headerSize = $rawResponse = $headerOut = null;
            unset($rawResponses, $rawResponse, $body, $headerSize, $headerOut);

            $this->_cache($options, $response);
        }

        return $response;
    }

/**
 * Create handle.
 *
 * @param string $body Body
 * @return resource Handle
 */
    protected function _createHandle(string $body) {
        if (empty($body)) {
            return;
        }
        $handle = fopen('php://memory', 'w+');
        fwrite($handle, $body);
        rewind($handle);
        return $handle;
    }

/**
 * Parse response header.
 *
 * @param string $raw Raw HTTP header
 * @return array Array of headers
 */
    protected function _parseResponses($raw) {
        $lines = explode(PHP_EOL, $raw);

        $rawResponses = [];
        $index = -1;
        foreach ($lines as $line) {
            if (strpos($line, 'HTTP/') !== false) {
                $index++;
            }

            if (!isset($rawResponses[$index])) {
                $rawResponses[$index] = [];
            }

            $rawResponses[$index][] = $line;
        }

        return $rawResponses;
    }

/**
 * Prepare data for sending.
 *
 * @param string|array $data Data to send
 * @return string Data to send
 */
    protected function _getPreparedPostData($data) {
        $this->addOption(CURLOPT_POST, true);

        $contentType = $this->header('Content-Type');
        $method = '_get' . Inflector::camelize(Inflector::slug($contentType));
        if (!$contentType || !method_exists($this, $method)) {
            return is_array($data) ? http_build_query($data) : $data;
        }

        return $this->{$method}($data);
    }

/**
 * JSON data.
 *
 * @param array $data Data to send
 * @return string JSON encoded data
 */
    protected function _getApplicationJson($data): string {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        // Check if data is really valid JSON
        if (strpos($data, '{') === false && strpos($data, '[{') === false) {
            throw new InvalidArgumentException(sprintf('Invalid JSON string for content-type %s.', $this->contentType()));
        }

        return $data;
    }

/**
 * Multipart form data.
 *
 * @param array $data Data to send
 * @return array Multipart form data
 */
    protected function _getMultipartFormData($data) {
        $preparedData = [];
        $this->_flattenMultipartFormDataArray($preparedData, $data);
        $this->_flattenMultipartFormDataArray($preparedData, $this->_files);
        return $preparedData;
    }

/**
 * Flattening form data post data array.
 *
 * @param array &$postData Data to send
 * @param array $data Data to send
 * @param string $prefix Prefix
 * @return void
 */
    protected function _flattenMultipartFormDataArray(&$postData, $data, $prefix = null) {
        foreach ((array)$data as $field => $value) {
            $key = $prefix ? $prefix . '[' . $field . ']' : $field;
            if (is_array($value)) {
                $this->_flattenMultipartFormDataArray($postData, $value, $key);
                continue;
            }
            $postData[$key] = $value;
        }
    }

/**
 * Get cookie jar filename path.
 *
 * @param string $url Request URL
 * @return string Cookie jar file
 */
    protected function _getCookieJarFile($url) {
        if (!$this->_cookieJar || is_string($this->_cookieJar)) {
            return $this->_cookieJar;
        }

        if ($this->_cookieJar === true) {
            $host = str_ireplace('www.', '', parse_url($url, PHP_URL_HOST));
            $slug = Inflector::slug($host);
            $this->_cookieJar = TMP . 'cache' . DS . sprintf('%s.cookies', $slug);
        } elseif ($this->_cookieJar instanceof File) {
            $this->_cookieJar = $this->_cookieJar->pwd();
        }

        if (!file_exists($this->_cookieJar)) {
            file_put_contents($this->_cookieJar, '');
        }

        return $this->_cookieJar;
    }

/**
 * Normalize cookies for cURL.
 *
 * @param array $cookies Cookies data
 * @return string Cookies
 */
    private function _normalizeCookie($cookies) {
        if (empty($cookies)) {
            return null;
        }

        $_cookies = '';
        foreach ($cookies as $key => $value) {
            if (!empty($_cookies)) {
                $_cookies .= '; ';
            }

            if ($value instanceof Cookie) {
                $_cookies .= (string)$value;
                continue;
            }

            $_cookies .= $key . '=' . $value;
        }

        return $_cookies;
    }

/**
 * Normalize header for cURL.
 *
 * @param array $header Header fields
 * @return array Headers
 */
    private function _normalizeHeader($header) {
        $_header = array();

        foreach ($header as $field => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if (!is_numeric($field)) {
                $value = $field . ': ' . $value;
            }

            $_header[] = $value;
        }

        return $_header;
    }

/**
 * Add field name to data if needed.
 * If class \CURLFile doesn't exist (present only on PHP >= 5.5.0), fallsback
 * to previous method.
 *
 * @param string $filename Filename
 * @param string $mimetype file MIME type
 * @param string $postname File postname
 * @return \CURLFile|string CURLFile instance
 */
    private function _fileCreate($filename, $mimetype, $postname) {
        if (class_exists('\CURLFile')) {
            $this->addOption(CURLOPT_SSL_VERIFYPEER, false);

            return new CURLFile(
                $filename,
                $mimetype,
                $postname
            );

        }

        return "@" . $filename . ";filename="
            . ($postname ?: basename($filename))
            . ($mimetype ? ";type=" . $mimetype : '');
    }

/**
 * Get the CA certificates file.
 *
 * It will check if the file is more than 6 months old, if so,
 * will attempt to update it.
 *
 * @return File CA certificates file
 */
    private function _loadRootCertificates() {
        if ($this->_caCertificate === null) {
            $certFilePath = dirname(__FILE__) . DS . 'cacert.pem';
            $rootCertFile = new File($certFilePath);

            // If we're not requesting the CA certificate, make the necessary checks
            if (!str_contains($this->url(), 'cacert.pem')) {
                // Check if root certificate needs updating
                $modifiedTime = $rootCertFile->lastChange();
                if ((new Time($modifiedTime))->modify('6 months')->isPast()) {
                    $this->_updateRootCertificates($rootCertFile);
                }
            }

            $this->_caCertificate = $rootCertFile;
        }
        return $this->_caCertificate;
    }

/**
 * Update certificate file with most recent hash.
 *
 * @param File $rootCertFile CA Certificates file
 * @return bool
 */
    private function _updateRootCertificates(File $rootCertFile): bool {
        $request = Client::get('https://curl.se/ca/cacert.pem');
        $response = $request->send();
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contents = $response->getBody();
        if (empty($contents)) {
            return false;
        }

        $rootCertFile->write($response->getBody());
        return $rootCertFile->close();
    }

/**
 * Response's cache.
 *
 * @param array $options Options
 * @param \Nata\Http\Client\Response $response Request's response
 * @return \Nata\Http\Client\Response If cached, returns the request's response
 */
    protected function _cache($options, $response = null) {
        if (!$this->_cache || !in_array($this->_method, $this->_cacheMethods)) {
            return null;
        }

        $cacheKey = str_ireplace(['www.', '.'], '', parse_url($options[CURLOPT_URL], PHP_URL_HOST));
        $cacheKey .= '_' . substr(md5(json_encode($options)), 0, 5);

        // Read cache
        if ($response === null) {
            // Check runtime cache
            if (isset(static::$_runtimeCache[$cacheKey])) {
                return static::$_runtimeCache[$cacheKey];
            }

            $response = Cache::read($cacheKey, $this->_cache);
            if (!$response) {
                return null;
            }
            return static::$_runtimeCache[$cacheKey] = $response;
        }

        static::$_runtimeCache[$cacheKey] = $response;
        return Cache::write($cacheKey, $response, $this->_cache);
    }

/**
 * Get/Set request configuration.
 *
 * @param array|string $config request configuration.
 * @return string
 */
    public function config($config = null) {
        if ($config === null) {
            $vars = array();

            foreach (get_object_vars($this) as $varName => $value) {
                $varName = substr($varName, 0, 1) === '_' ? str_replace('_', '', $varName) : $varName;
                $vars[$varName] = $value;
            }

            return $vars;
        }

        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        foreach ($config as $varName => $value) {
            $this->{$varName}($value);
            //$this->{'_' . $varName} = $value;
        }

        return $this;
    }

/**
 * Reset properties to default values.
 *
 * @return $this
 */
    public function reset() {
        foreach ($this->_defaultProperties as $varName => $value) {
            $this->{$varName} = $value;
        }
        return $this;
    }

/**
 * __toString.
 *
 * @return string Request header and body
 */
    public function __toString() {
        $headerOut = $this->_headerOut;

        if ($headerOut === null) {
            $headerOut = $this->_normalizeHeader($this->_header);
        }

        return $headerOut . $this->data();
    }

}