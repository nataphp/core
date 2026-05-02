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

use Nata\Utility\Inflector;

/**
 * HTTP Response.
 */
class Response {

/**
 * Request instance.
 *
 * @var \Nata\Http\Client\Request
 */
    protected $_request;

/**
 * Raw response.
 *
 * @var string
 */
    protected $_raw;

/**
 * Header size.
 *
 * @var int
 */
    protected $_headerSize;

/**
 * Request header that was sent.
 *
 * @var string
 */
    protected $_headerOut;

/**
 * Error.
 *
 * @var string
 */
    protected $_error;

/**
 * Body.
 *
 * @var mixed
 */
    protected $_body;

/**
 * Response header.
 *
 * @var array
 */
    protected $_header;

/**
 * Response header with lowercase header lines to map it.
 *
 * @var array
 */
    protected $_headerMap;

/**
 * Response cookies.
 *
 * @var \Nata\Http\Client\CookieCollection
 */
    protected $_cookies;

/**
 * Status.
 *
 * @var string
 */
    protected $_status;

/**
 * Status code.
 *
 * @var int
 */
    protected $_statusCode;

/**
 * Status message.
 *
 * @var string
 */
    protected $_statusMessage;

/**
 * MIME type.
 *
 * @var string
 */
    protected $_mimeType;

/**
 * Character set.
 *
 * @var string
 */
    protected $_charset;

/**
 * Image size.
 *
 * @var array
 */
    protected $_imageSize;

/**
 * Previous response.
 *
 * Usually this is set when a redirect is followed,
 * gathering all chained responses.
 *
 * @var \Nata\Http\Client\Response
 */
    protected $_previous;


/**
 * Constructor.
 *
 * @param array $data Response data
 * @param \Nata\Http\Client\Response $previous Previous response instance
 *  This is useful for redirects, that you can access previous response data
 */
    public function __construct(array $data, Response $previous = null) {
        $this->_previous = $previous;
        $this->_request = $data['request'];
        $this->_body = &$data['body'];
        $this->_error = $data['error'];
        $this->_headerSize = $data['headerSize'];
        $this->_headerOut = $data['headerOut'];
        $this->_raw = $data['raw'];
    }

/**
 * Get previous response.
 *
 * @return \Nata\Http\Client\Response Response
 */
    public function getPrevious() {
        return $this->_previous;
    }

/**
 * Get raw response.
 *
 * @return string Raw HTTP Response
 */
    public function getRaw() {
        return $this->_raw;
    }

/**
 * Get location/URL.
 *
 * @return string Response location/URL
 */
    public function getUrl() {
        if ($this->_previous) {
            return $this->_previous->getHeader('Location');
        }
        return $this->_request->url();
    }

/**
 * Get request instance.
 *
 * @return \Nata\Http\Client\Request Request
 */
    public function getRequest() {
        return $this->_request;
    }

/**
 * Get request headers.
 *
 * @return array Response parsed headers
 */
    public function getHeaderOut() {
        return $this->_headerOut;
    }

/**
 * Get HTTP response header.
 *
 * @param string $field Header field name
 * @return array|string Response parsed headers
 */
    public function getHeader($field = null) {
        if ($this->_header === null) {
            $this->_header = $this->_parseHeader(substr($this->_raw, 0, $this->_headerSize));
        }

        if ($field === null) {
            return $this->_header;
        }

        if (isset($this->_headerMap[$field])) {
            $field = $this->_headerMap[$field];
        }

        return isset($this->_header[$field]) ? $this->_header[$field] : null;
    }

/**
 * Get response cookies.
 * If cookie name is given, respective value will be returned.
 *
 * @param string $cookie Cookie name
 * @return array Cookie(s)
 */
    public function getCookies($cookie = null) {
        if ($this->_cookies === null) {
            $this->_cookies = $this->_parseCookies($this->getHeader('Set-Cookie'));
        }
        return $this->_cookies->get($cookie);
    }

/**
 * Get response cookies.
 * If cookie name is given, respective value will be returned.
 *
 * @param string $cookie Cookie name
 * @return CookieCollection Cookie(s)
 */
    protected function _parseCookies($cookies) {
        $collection = new CookieCollection;

        foreach ((array)$cookies as $cookie) {
            $config = array();
            $attributes = explode(';', $cookie);

            foreach ($attributes as $index => $attribute) {
                $params = explode('=', trim($attribute));

                if ($index === 0) {
                    list($config['name'], $config['value']) = $params;
                    continue;
                }

                $paramValue = isset($params[1]) ? $params[1] : null;
                $paramName = Inflector::variable($params[0]);

                switch ($paramName) {
                    case 'maxAge':
                        $config['maxAge'] = $paramValue;
                        break;
                    case 'httpOnly':
                        $config['httpOnly'] = true;
                        break;
                    default:
                        $config[$paramName] = $paramValue;
                        break;
                }

            }

            $collection->add($config);
        }
        return $collection;
    }

/**
 * Get body from response.
 *
 * @return string Body
 */
    public function getBody() {
        $data = '';
        if (is_resource($this->_body)) {
            $chunkSize = $this->_getOptimalChunkSize();
            while (!feof($this->_body)) {
                $data .= fgets($this->_body, $chunkSize);
            }
            rewind($this->_body);
        }
        return $data;
    }

/**
 * Determine the optimal chunk size based on the file size.
 *
 * @param int $fileSize The size of the file in bytes.
 * @return int The optimal chunk size in bytes.
 */
    private function _getOptimalChunkSize(): int {
        $size = $this->getLength();
        if ($size < (1 * 1024 * 1024)) { // Less than 1 MB
            return 4096;
        } elseif ($size < (100 * 1024 * 1024)) { // 1 MB to 100 MB
            return 16384;
        }
        // Greater than 100 MB
        return 65536; // 64 KB
    }

/**
 * Get decoded JSON body.
 *
 * @param bool $assoc When TRUE, returned objects will be converted into associative arrays.
 * @param int $depth User specified recursion depth.
 * @param int $options Bitmask of JSON decode options.
 *  Currently there are two supported options:
 *   - The first is JSON_BIGINT_AS_STRING that allows casting big integers to string instead of
 *  floats which is the default.
 *   - The second option is JSON_OBJECT_AS_ARRAY that has the same effect as setting assoc to TRUE.
 * @return array|object JSON array/object
 * @see http://php.net/json-decode
 */
    public function getJsonBody($assoc = false, $depth = 512, $options = 0) {
        if ($this->is('json')) {
            return json_decode($this->getBody(), $assoc, $depth, $options);
        }
    }

/**
 * Get header field 'Status'.
 *
 * @return string Response body
 */
    public function getStatus() {
        if ($this->_status === null) {
            if ($this->_error) {
                [$msg, $status] = splitter($this->_error, ':');
                $this->_status = $status ? trim($status) : null;
            } else {
                [$this->_status] = explode(PHP_EOL, $this->_raw);
            }
        }
        return $this->_status;
    }

/**
 * Get response status code from header field 'Status'.
 *
 * @return int Status code
 */
    public function getStatusCode() {
        if ($this->_statusCode === null) {
            $status = $this->getStatus();
            $this->_statusCode = (int)$this->_getStatusParts($status, 1, 0);
        }
        return $this->_statusCode;
    }

/**
 * Get response status message from header field 'Status'.
 *
 * @return string Status message
 */
    public function getStatusMessage() {
        if ($this->_statusMessage === null) {
            $status = $this->getStatus();
            $this->_statusMessage = $this->_getStatusParts($status, 2);
        }
        return $this->_statusMessage;
    }

/**
 * Get and set.
 *
 * @return string 'Content-Type' value
 */
    protected function _getStatusParts($status, $part, $default = null) {
        if ($status === null) {
            return $default;
        }
        $parts = splitter($status, ' ', false, 3);
        return $parts[$part] ? $parts[$part] : $default;
    }

/**
 * Get header field 'Content-Type'.
 *
 * @return string 'Content-Type' value
 */
    public function getType() {
        return $this->getHeader('Content-Type');
    }

/**
 * Get MIME Type.
 *
 * @return string 'Content-Type' value
 */
    public function getMimeType() {
        if ($this->_mimeType === null) {
            $type = $this->getType();
            list($mime) = array_map('trim', explode(';', $type));
            $this->_mimeType = $mime;
        }
        return $this->_mimeType;
    }

/**
 * Get content character set.
 *
 * @return string Character set
 */
    public function getCharset() {
        if ($this->_charset === null) {
            $type = $this->getType();
            $this->_charset = false;
            if (strpos($type, ';') !== false) {
                [$mime, $charset] = array_map('trim', explode(';', $this->getType()));
                $this->_charset = trim(str_replace('charset=', '', $charset));
            }
        }
        return $this->_charset;
    }

/**
 * Get content encoding.
 *
 * @return string Encoding
 */
    public function getEncoding() {
        return $this->getHeader('Content-Encoding');
    }

/**
 * Get content length.
 *
 * @return string Content length
 */
    public function getLength() {
        return $this->getHeader('Content-Length');
    }

/**
 * Get image dimensions.
 *
 * @return array Image's width and height
 */
    public function getImageSize() {
        if ($this->_imageSize === null) {
            $this->_imageSize = array();
            if ($this->is('image')) {
                $img = imagecreatefromstring($this->getBody());
                $this->_imageSize = array(imagesx($img), imagesy($img));
            }
        }
        return $this->_imageSize;
    }

/**
 * Check if content type is the one passed in argument.
 *
 * @return bool True if matches given type name
 */
    public function is($type) {
        $mime = explode('/', $this->getMimeType());
        return $mime[0] === $type || (isset($mime[1]) && $mime[1] === $type);
    }

/**
 * Get Error.
 *
 * @return string Error message
 */
    public function getError() {
        return $this->_error;
    }

/**
 * Check if request returned an error.
 *
 * @return bool
 */
    public function hasError() {
        return !empty($this->_error);
    }

/**
 * Check if response was successfull.
 *
 * @return bool
 */
    public function isOk() {
        return empty($this->_error) && $this->getStatusCode() == 200;
    }

/**
 * Parse response header.
 *
 * @param string $raw Raw HTTP header
 * @return array Array of headers
 */
    protected function _parseHeader($raw) {
        $retVal = [];
        $fields = explode(PHP_EOL, $raw);
        foreach ($fields as $field) {
            if (!preg_match('/([^:]+): (.+)/m', $field, $match)) {
                continue;
            }

            $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($match) {
                return strtoupper($match[0]);
            }, strtolower(trim($match[1])));

            $this->_headerMap[strtolower($match[1])] = $match[1];

            if (isset($retVal[$match[1]])) {
                $retVal[$match[1]] = [$retVal[$match[1]], $match[2]];
            } else {
                $retVal[$match[1]] = trim($match[2]);
            }
        }
        return $retVal;
    }

/**
 * Check if request returned an error.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'headerOut' => $this->getHeaderOut(),
            'status' => $this->getStatus(),
            'header' => $this->getHeader(),
            'body' => $this->getBody()
        ];
    }

/**
 * Check if request returned an error.
 *
 * @return bool
 */
    public function __toString() {
        return $this->_raw;
    }

/**
 * __destruct
 *
 * @return void
 */
    public function __destruct() {
        if (is_resource($this->_body)) {
            fclose($this->_body);
        }
        $this->_header = null;
        $this->_request = null;
        $this->_previous = null;
        $this->_raw = null;
        unset($this->_request, $this->_previous);
    }

}