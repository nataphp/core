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
use Nata\Http\Request;
use Nata\FilesystemManager\File;
use Nata\Filesystem\RemoteFile;
use Nata\I18n\Time;
use DateTime;
use DateTimeZone;
use Nata\Filesystem\Mimetype;
use Nata\FilesystemManager\FileFactory;
use NataException;
use NotFoundException;

/**
 * \Nata\Http\Response is responsible for managing the response text, status and headers of a HTTP response.
 *
 * By default controllers will use this class to render their response. If you are going to use
 * a custom response class it should subclass this object in order to ensure compatibility.
 */
class Response {

/**
 * Holds HTTP response statuses.
 *
 * @var array
 */
    protected $_statusCodes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Unused',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

/**
 * Holds known mime type mappings.
 *
 * @var array
 */
    protected $_mimeTypes = [];

/**
 * Protocol header to send to the client.
 *
 * @var string
 */
    protected $_protocol = 'HTTP/1.1';

/**
 * Status code to send to the client.
 *
 * @var integer
 */
    protected $_status = 200;

/**
 * Content type to send. This can be an 'extension' that
 * will be transformed using the $_mimetypes array or a complete mime-type.
 *
 * @var integer
 */
    protected $_contentType = 'text/html';

/**
 * Buffer list of headers.
 *
 * @var array
 */
    protected $_headers = [];

/**
 * Buffer string for response message.
 *
 * @var string
 */
    protected $_body = '';

/**
 * File object for file to be read out as response.
 *
 * @var \Nata\Filesystem\File
 */
    protected $_file;

/**
 * The charset the response body is encoded with.
 *
 * @var string
 */
    protected $_charset = 'UTF-8';

/**
 * Holds all the cache directives that will be converted
 * into headers when sending the request.
 *
 * @var array
 */
    protected $_cacheDirectives = [];

/**
 * Holds cookies to be sent to the client.
 *
 * @var array
 */
    protected $_cookies = [];


/**
 * Class constructor
 *
 * @param array $options list of parameters to setup the response. Possible values are:
 *  - body: the response text that should be sent to the client
 *  - status: the HTTP status code to respond with
 *  - type: a complete mime-type string or an extension mapped in this class
 *  - charset: the charset for the response body
 */
    public function __construct(array $options = []) {
        $this->_mimeTypes = Mimetype::get();

        if (isset($options['body'])) {
            $this->body($options['body']);
        }
        if (isset($options['statusCodes'])) {
            $this->httpCodes($options['statusCodes']);
        }
        if (isset($options['status'])) {
            $this->statusCode($options['status']);
        }
        if (isset($options['type'])) {
            $this->type($options['type']);
        }
        if (!isset($options['charset'])) {
            $options['charset'] = Configure::read('App.encoding');
        }
        $this->charset($options['charset']);
    }

/**
 * Sends the complete response to the client including headers and message body.
 * Will echo out the content in the response body.
 *
 * @return void
 */
    public function send() {
        if (isset($this->_headers['Location']) && $this->_status === 200) {
            $this->statusCode(302);
        }

        if (Configure::read('Response.compress') && $this->outputCompressed()) {
            $this->compress();
        }

        $codeMessage = $this->_statusCodes[$this->_status];

        $this->_setCookies();
        $this->_sendHeader("{$this->_protocol} {$this->_status} {$codeMessage}");
        $this->_setNataHeaders();
        $this->_setContent();
        $this->_setContentLength();
        $this->_setContentType();

        foreach ($this->_headers as $header => $value) {
            $this->_sendHeader($header, $value);
        }

        if ($this->_file) {
            $this->_sendFile($this->_file);
            $this->_file = null;
        }
        $this->_sendContent($this->_body);
    }

/**
 * Set response headers with app runtime.
 *
 * @return void
 */
    protected function _setNataHeaders() {
        if ($this->header('x-nataphp-generated')) {
            return;
        }
        $this->header('x-nataphp-generated', gentime('App', false));
    }

/**
 * Sets the cookies that have been added via static method Nata\Http\Response::cookie()
 * before any other output is sent to the client.
 * Will set the cookies in the order they have been set.
 *
 * @return void
 */
    protected function _setCookies() {
        foreach ($this->_cookies as $name => $c) {
            // Expire
            if ($c['expire'] instanceof Time) {
                $c['expire'] = (new Time($c['expire']))->timestamp();
            }

            // Workaround for 'SameSite' on PHP < 7.3
            // This makes use of a bug on setcookie present on versions < 7.3
            if (PHP_VERSION_ID < 70300) {
                $path = $c['path'];
                // Same site
                if ($c['sameSite']) {
                    $path .= '; SameSite=' . $c['sameSite'];
                }

                setcookie(
                    $name, $c['value'], $c['expire'], $path,
                    $c['domain'], $c['secure'], $c['httpOnly']
                );

                continue;
            }

            setcookie($name, $c['value'], [
                'expires' => $c['expire'],
                'path' => $c['path'],
                'domain' => $c['domain'],
                'samesite' => $c['sameSite'],
                'secure' => $c['secure'],
                'httponly' => $c['httpOnly']
            ]);
        }

    }

/**
 * Formats the Content-Type header based on the configured contentType and charset
 * the charset will only be set in the header if the response is of type text/*.
 *
 * @return void
 */
    protected function _setContentType() {
        if ($this->_status === 304 || $this->_status === 204) {
            return;
        }

        $whitelist = [
            'application/javascript', 'application/json', 'application/xml', 'application/rss+xml'
        ];

        $charset = false;
        if (
            $this->_charset &&
            (strpos($this->_contentType, 'text/') === 0 || in_array($this->_contentType, $whitelist))
        ) {
            $charset = true;
        }

        if ($charset) {
            $this->header('Content-Type', "{$this->_contentType}; charset={$this->_charset}");
        } else {
            $this->header('Content-Type', "{$this->_contentType}");
        }
    }

/**
 * Sets the response body to an empty text if the status code is 204 or 304.
 *
 * @return void
 */
    protected function _setContent() {
        if ($this->_status === 304 || $this->_status === 204) {
            $this->body('');
        }
    }

/**
 * Calculates the correct Content-Length and sets it as a header in the response.
 * Will not set the value if already set or if the output is compressed.
 *
 * @return void
 */
    protected function _setContentLength() {
        $shouldSetLength = !isset($this->_headers['Content-Length']) && !in_array($this->_status, range(301, 307));
        if (isset($this->_headers['Content-Length']) && $this->_headers['Content-Length'] === false) {
            unset($this->_headers['Content-Length']);
            return;
        }
        if ($shouldSetLength && !$this->outputCompressed()) {
            $offset = ob_get_level() ? ob_get_length() : 0;
            if (ini_get('mbstring.func_overload') & 2 && function_exists('mb_strlen')) {
                $this->length($offset + mb_strlen($this->_body, '8bit'));
            } else {
                $this->length($this->_headers['Content-Length'] = $offset + strlen($this->_body));
            }
        }
    }

/**
 * Sends a header to the client.
 *
 * @param string $name the header name
 * @param string $value the header value
 * @return void
 */
    protected function _sendHeader($name, $value = null) {
        if (headers_sent()) {
            return;
        }

        if ($value === null) {
            return header($name);
        }

        return header("{$name}: {$value}");
    }

/**
 * Sends a content string to the client.
 *
 * @param string $content string to send as response body
 * @return void
 */
    protected function _sendContent($content) {
        echo $content;
    }

/**
 * Buffers a header string to be sent.
 * Returns the complete list of buffered headers.
 *
 * ### Single header
 * e.g `header('Location', 'http://example.com');`
 *
 * ### Multiple headers
 * e.g `header(array('Location' => 'http://example.com', 'X-Extra' => 'My header'));`
 *
 * ### String header
 * e.g `header('WWW-Authenticate: Negotiate');`
 *
 * ### Array of string headers
 * e.g `header(array('WWW-Authenticate: Negotiate', 'Content-type: application/pdf'));`
 *
 * Multiple calls for setting the same header name will have the same effect as setting the header once
 * with the last value sent for it
 *  e.g `header('WWW-Authenticate: Negotiate'); header('WWW-Authenticate: Not-Negotiate');`
 * will have the same effect as only doing `header('WWW-Authenticate: Not-Negotiate');`
 *
 * @param string|array $header. An array of header strings or a single header string
 *  - an associative array of "header name" => "header value" is also accepted
 *  - an array of string headers is also accepted
 * @param string $value. The header value.
 * @return $this|array list of headers to be sent
 */
    public function header($header = null, $value = null) {
        if ($header === null) {
            return $this->_headers;
        }

        if (is_string($header) && $value === null) {
            if (!str_contains($header, ':')) {
                return $this->_headers[$header] ?? null;
            }

            [$header, $value] = explode(':', $header, 2);
        }

        if ($value !== null) {
            $header = [$header => $value];
        }

        foreach ($header as $h => $v) {
            if (is_numeric($h)) {
                $this->header($v);
                continue;
            }

            if ($v === null) {
                unset($this->_headers[$h]);
                continue;
            }

            $this->_headers[$h] = trim($v);
        }

        return $this;
    }

/**
 * Buffers the response message to be sent
 * if $content is null the current buffer is returned.
 *
 * @param string $content the string message to be sent
 * @return $this|string Current message buffer if $content param is passed as null
 */
    public function body($content = null) {
        if ($content === null) {
            return $this->_body;
        }
        $this->_body = $content;
        return $this;
    }

/**
 * Sets the HTTP status code to be sent
 * if $code is null the current code is returned.
 *
 * @param integer $code
 * @return $this|integer Current status code
 * @throws \NataException When an unknown status code is reached.
 */
    public function statusCode($code = null) {
        if ($code === null) {
            return $this->_status;
        }
        if (!isset($this->_statusCodes[$code])) {
            throw new NataException(sprintf('Unknown status code (%s)', $code));
        }
        $this->_status = $code;
        return $this;
    }

/**
 * Queries & sets valid HTTP response codes & messages.
 *
 * @param integer|array $code If $code is an integer, then the corresponding code/message is
 *        returned if it exists, null if it does not exist. If $code is an array,
 *        then the 'code' and 'message' keys of each nested array are added to the default
 *        HTTP codes. Example:
 *
 *        httpCodes(404); // returns array(404 => 'Not Found')
 *
 *        httpCodes(array(
 *            701 => 'Unicorn Moved',
 *            800 => 'Unexpected Minotaur'
 *        )); // sets these new values, and returns true
 *
 * @return mixed associative array of the HTTP codes as keys, and the message
 *    strings as values, or null of the given $code does not exist.
 */
    public function httpCodes($code = null) {
        if ($code === null) {
            return $this->_statusCodes;
        }

        if (is_array($code)) {
            $this->_statusCodes = $code + $this->_statusCodes;
            return true;
        }

        if (!isset($this->_statusCodes[$code])) {
            return null;
        }
        return array($code => $this->_statusCodes[$code]);
    }

/**
 * Sets the response content type. It can be either a file extension
 * which will be mapped internally to a mime-type or a string representing a mime-type
 * if $contentType is null the current content type is returned
 * if $contentType is an associative array, content type definitions will be stored/replaced
 *
 * ### Setting the content type
 *
 * e.g `type('jpg');`
 *
 * ### Returning the current content type
 *
 * e.g `type();`
 *
 * ### Storing content type definitions
 *
 * e.g `type(['keynote' => 'application/keynote', 'bat' => 'application/bat']);`
 *
 * ### Replacing a content type definition
 *
 * e.g `type(['jpg' => 'text/plain']);`
 *
 * @param string $contentType
 * @return mixed|$this current content type or false if supplied an invalid content type
 */
    public function type($contentType = null) {
        if ($contentType === null) {
            return $this->_contentType;
        }

        if (is_array($contentType)) {
            foreach ($contentType as $type => $definition) {
                $this->_mimeTypes[$type] = $definition;
            }
            return $this->_contentType;
        }

        if (isset($this->_mimeTypes[$contentType])) {
            $contentType = $this->_mimeTypes[$contentType];
            $contentType = is_array($contentType) ? current($contentType) : $contentType;
        }

        if (strpos($contentType, '/') === false) {
            return false;
        }

        $this->_contentType = $contentType;

        return $this;
    }

/**
 * Returns the mime type definition for an alias
 *
 * e.g `getMimeType('pdf'); // returns 'application/pdf'`
 *
 * @param string $alias the content type alias to map
 * @return mixed string mapped mime type or null if $alias is not mapped
 */
    public function getMimeType($alias) {
        $mimetype = Mimetype::get($alias);
        return $mimetype ? $mimetype[0] : null;
    }

/**
 * Maps a content-type back to an alias
 *
 * e.g `mapType('application/pdf'); // returns 'pdf'`
 *
 * @param string|array $ctype Either a string content type to map, or an array of types.
 * @return mixed Aliases for the types provided.
 */
    public function mapType($ctype) {
        if (is_array($ctype)) {
            return array_map([$this, 'mapType'], $ctype);
        }

        foreach ($this->_mimeTypes as $alias => $types) {
            if (is_array($types) && in_array($ctype, $types)) {
                return $alias;
            } elseif (is_string($types) && $types == $ctype) {
                return $alias;
            }
        }

        return null;
    }

/**
 * Sets/gets the response charset
 * if $charset is null the current charset is returned.
 *
 * @param string $charset Charset
 * @return $this|string Current charset
 */
    public function charset($charset = null) {
        if ($charset === null) {
            return $this->_charset;
        }
        $this->_charset = $charset;
        return $this;
    }

/**
 * Sets the correct headers to instruct the client to not cache the response
 *
 * @return void
 */
    public function disableCache() {
        $this->header([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate("D, d M Y H:i:s") . " GMT",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0'
        ]);
    }

/**
 * Sets the correct headers to instruct the client to cache the response.
 *
 * @param string $since a valid time since the response text has not been modified
 * @param string $time a valid time for cache expiry
 * @return void
 */
    public function cache($since, $time = '+1 day') {
        if (!is_int($time)) {
            $time = strtotime($time);
        }

        $this->header([
            'Date' => gmdate("D, j M Y G:i:s ", time()) . 'GMT'
        ]);

        $this->modified($since);
        $this->expires($time);
        $this->sharable(true);
        $this->maxAge($time - time());
    }

/**
 * Sets whether a response is eligible to be cached by intermediate proxies
 * This method controls the `public` or `private` directive in the Cache-Control
 * header
 *
 * @param boolean $public  if set to true, the Cache-Control header will be set as public
 * if set to false, the response will be set to private
 * if no value is provided, it will return whether the response is sharable or not
 * @param integer $time time in seconds after which the response should no longer be considered fresh
 * @return boolean
 */
    public function sharable($public = null, $time = null) {
        if ($public === null) {
            $public = array_key_exists('public', $this->_cacheDirectives);
            $private = array_key_exists('private', $this->_cacheDirectives);
            $noCache = array_key_exists('no-cache', $this->_cacheDirectives);
            if (!$public && !$private && !$noCache) {
                return null;
            }
            $sharable = $public || ! ($private || $noCache);
            return $sharable;
        }

        if ($public) {
            $this->_cacheDirectives['public'] = true;
            unset($this->_cacheDirectives['private']);
            $this->sharedMaxAge($time);
        } else {
            $this->_cacheDirectives['private'] = true;
            unset($this->_cacheDirectives['public']);
            $this->maxAge($time);
        }

        if (!$time) {
            $this->_setCacheControl();
        }

        return (bool)$public;
    }

/**
 * Sets the Cache-Control s-maxage directive.
 * The max-age is the number of seconds after which the response should no longer be considered
 * a good candidate to be fetched from a shared cache (like in a proxy server).
 * If called with no parameters, this function will return the current max-age value if any.
 *
 * @param integer $seconds if null, the method will return the current s-maxage value
 * @return $this|int
 */
    public function sharedMaxAge($seconds = null) {
        if ($seconds === null) {
            return (isset($this->_cacheDirectives['s-maxage']) ? $this->_cacheDirectives['s-maxage'] : null);
        }

        $this->_cacheDirectives['s-maxage'] = $seconds;
        $this->_setCacheControl();

        return $this;
    }

/**
 * Sets the Cache-Control max-age directive.
 * The max-age is the number of seconds after which the response should no longer be considered
 * a good candidate to be fetched from the local (client) cache.
 * If called with no parameters, this function will return the current max-age value if any
 *
 * @param integer $seconds if null, the method will return the current max-age value
 * @return $this|int
 */
    public function maxAge($seconds = null) {
        if ($seconds === null) {
            return (isset($this->_cacheDirectives['max-age']) ? $this->_cacheDirectives['max-age'] : null);
        }

        $this->_cacheDirectives['max-age'] = $seconds;
        $this->_setCacheControl();

        return $this;
    }

/**
 * Sets the Cache-Control must-revalidate directive.
 * must-revalidate indicates that the response should not be served
 * stale by a cache under any cirumstance without first revalidating
 * with the origin.
 * If called with no parameters, this function will return wheter must-revalidate is present.
 *
 * @param integer $seconds if null, the method will return the current
 * must-revalidate value
 * @return boolean
 */
    public function mustRevalidate($enable = null) {
        if ($enable !== null) {
            if ($enable) {
                $this->_cacheDirectives['must-revalidate'] = true;
            } else {
                unset($this->_cacheDirectives['must-revalidate']);
            }
            $this->_setCacheControl();
        }
        return array_key_exists('must-revalidate', $this->_cacheDirectives);
    }

/**
 * Helper method to generate a valid Cache-Control header from the options set
 * in other methods
 *
 * @return void
 */
    protected function _setCacheControl() {
        $control = '';
        foreach ($this->_cacheDirectives as $key => $val) {
            $control .= $val === true ? $key : sprintf('%s=%s', $key, $val);
            $control .= ', ';
        }
        $control = rtrim($control, ', ');
        $this->header('Cache-Control', $control);
    }

/**
 * Sets/gets the Expires header for the response by taking an expiration time.
 * If called with no parameters it will return the current Expires value.
 *
 * ## Examples:
 *
 * `$response->expires('now')` Will Expire the response cache now
 * `$response->expires(new DateTime('+1 day'))` Will set the expiration in next 24 hours
 * `$response->expires()` Will return the current expiration header value
 *
 * @param string|\DateTime $time
 * @return $this|string
 */
    public function expires($time = null) {
        if ($time === null) {
            return (isset($this->_headers['Expires']) ? $this->_headers['Expires'] : null);
        }
        $date = $this->_getUTCDate($time);
        $this->_headers['Expires'] = $date->format('D, j M Y H:i:s') . ' GMT';
        return $this;
    }

/**
 * Sets the Last-Modified header for the response by taking an modification time
 * If called with no parameters it will return the current Last-Modified value
 *
 * ## Examples:
 *
 * `$response->modified('now')` Will set the Last-Modified to the current time
 * `$response->modified(new DateTime('+1 day'))` Will set the modification date in the past 24 hours
 * `$response->modified()` Will return the current Last-Modified header value
 *
 * @param string|DateTime $time
 * @return $this|string
 */
    public function modified($time = null) {
        if ($time === null) {
            return (isset($this->_headers['Last-Modified']) ? $this->_headers['Last-Modified'] : null);
        }

        $date = $this->_getUTCDate($time);
        $this->_headers['Last-Modified'] = $date->format('D, j M Y H:i:s') . ' GMT';

        return $this;
    }

/**
 * Sets the response as Not Modified by removing any body contents
 * setting the status code to "304 Not Modified" and removing all
 * conflicting headers
 *
 * @return void
 */
    public function notModified() {
        $this->statusCode(304);
        $this->body('');
        $remove = [
            'Allow',
            'Content-Encoding',
            'Content-Language',
            'Content-Length',
            'Content-MD5',
            'Content-Type',
            'Last-Modified'
        ];
        foreach ($remove as $header) {
            unset($this->_headers[$header]);
        }
    }

/**
 * Sets the Vary header for the response, if an array is passed,
 * values will be imploded into a comma separated string. If no
 * parameters are passed, then an array with the current Vary header
 * value is returned
 *
 * @param string|array $cacheVariances a single Vary string or a array
 * containig the list for variances.
 * @return $this|array
 */
    public function vary($cacheVariances = null) {
        if ($cacheVariances === null) {
            return (isset($this->_headers['Vary']) ? explode(', ', $this->_headers['Vary']) : array());
        }
        $cacheVariances = (array)$cacheVariances;
        $this->_headers['Vary'] = implode(', ', $cacheVariances);
        return $this;
    }

/**
 * Sets the response Etag, Etags are a strong indicative that a response
 * can be cached by a HTTP client. A bad way of generaing Etags is
 * creating a hash of the response output, instead generate a unique
 * hash of the unique components that identifies a request, such as a
 * modification time, a resource Id, and anything else you consider it
 * makes it unique.
 *
 * Second parameter is used to instuct clients that the content has
 * changed, but sematicallly, it can be used as the same thing. Think
 * for instance of a page with a hit counter, two different page views
 * are equivalent, but they differ by a few bytes. This leaves off to
 * the Client the decision of using or not the cached page.
 *
 * If no parameters are passed, current Etag header is returned.
 *
 * @param string $hash the unique has that identifies this resposnse
 * @param boolean $weak whether the response is semantically the same as
 * other with th same hash or not
 * @return $this|string
 */
    public function etag($tag = null, $weak = false) {
        if ($tag === null) {
            return (isset($this->_headers['Etag']) ? $this->_headers['Etag'] : array());
        }

        $this->_headers['Etag'] = sprintf('%s"%s"', ($weak) ? 'W/' : null, $tag);

        return $this;
    }

/**
 * Returns a DateTime object initialized at the $time param and using UTC
 * as timezone.
 *
 * @param string|integer|DateTime $time
 * @return \DateTime
 */
    protected function _getUTCDate($time = null) {
        if ($time instanceof DateTime) {
            $result = clone $time;
        } elseif (is_int($time)) {
            $result = new DateTime(date('Y-m-d H:i:s', $time));
        } else {
            $result = new DateTime($time);
        }
        $result->setTimeZone(new DateTimeZone('UTC'));
        return $result;
    }

/**
 * Sets the correct output buffering handler to send a compressed response. Responses will
 * be compressed with zlib, if the extension is available.
 *
 * @return boolean false if client does not accept compressed responses or no handler is available, true otherwise
 */
    public function compress() {
        $compressionEnabled = ini_get("zlib.output_compression") !== '1' &&
            extension_loaded("zlib") &&
            (strpos(env('HTTP_ACCEPT_ENCODING'), 'gzip') !== false);
        return $compressionEnabled && ob_start('ob_gzhandler');
    }

/**
 * Returns whether the resulting output will be compressed by PHP
 *
 * @return boolean
 */
    public function outputCompressed() {
        return strpos(env('HTTP_ACCEPT_ENCODING'), 'gzip') !== false
            && (ini_get("zlib.output_compression") === '1' || in_array('ob_gzhandler', ob_list_handlers()));
    }

/**
 * Sets the correct headers to instruct the browser to download the response as a file.
 *
 * @param string $filename the name of the file as the browser will download the response
 * @return $this
 */
    public function download($filename) {
        $this->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $this;
    }

/**
 * Sets the protocol to be used when sending the response. Defaults to HTTP/1.1
 * If called with no arguments, it will return the current configured protocol
 *
 * @param string protocol to be used for sending response
 * @return $this|string protocol currently set
 */
    public function protocol($protocol = null) {
        if ($protocol === null) {
            return $this->_protocol;
        }
        $this->_protocol = $protocol;
        return $this;
    }

/**
 * Sets the Content-Length header for the response.
 * If called with no arguments returns the last Content-Length set.
 *
 * @param integer $bytes Number of bytes
 * @return integer|$this
 */
    public function length($bytes = null) {
        if ($bytes === null) {
            return ($this->_headers['Content-Length'] ? $this->_headers['Content-Length'] : null);
        }
        $this->_headers['Content-Length'] = $bytes;
        return $this;
    }

/**
 * Checks whether a response has not been modified according to the 'If-None-Match'
 * (Etags) and 'If-Modified-Since' (last modification date) request
 * headers headers. If the response is detected to be not modified, it
 * is marked as so accordingly so the client can be informed of that.
 *
 * In order to mark a response as not modified, you need to set at least
 * the Last-Modified etag response header before calling this method. Otherwise
 * a comparison will not be possible.
 *
 * @param \Nata\Http\Request $request Request object
 * @return boolean whether the response was marked as not modified or not.
 */
    public function checkNotModified(Request $request) {
        $etags = preg_split('/\s*,\s*/', $request->header('If-None-Match'), -1, PREG_SPLIT_NO_EMPTY);
        $modifiedSince = $request->header('If-Modified-Since');
        if ($responseTag = $this->etag()) {
            $etagMatches = in_array('*', $etags) || in_array($responseTag, $etags);
        }
        if ($modifiedSince) {
            $timeMatches = strtotime($this->modified()) == strtotime($modifiedSince);
        }
        $checks = compact('etagMatches', 'timeMatches');
        if (empty($checks)) {
            return false;
        }
        $notModified = !in_array(false, $checks, true);
        if ($notModified) {
            $this->notModified();
        }
        return $notModified;
    }

/**
 * String conversion. Fetches the response body as a string.
 * Does *not* send headers.
 *
 * @return string
 */
    public function __toString() {
        return (string)$this->_body;
    }

/**
 * Getter/Setter for cookie configs
 *
 * This method acts as a setter/getter depending on the type of the argument.
 * If the method is called with no arguments, it returns all configurations.
 *
 * If the method is called with a string as argument, it returns either the
 * given configuration if it is set, or null, if it's not set.
 *
 * If the method is called with an array as argument, it will set the cookie
 * configuration to the cookie container.
 *
 * @param array $options Either null to get all cookies, string for a specific cookie
 *  or array to set cookie.
 *
 * ### Options (when setting a configuration)
 *  - name: The Cookie name
 *  - value: Value of the cookie
 *  - expire: Time the cookie expires in
 *  - path: Path the cookie applies to
 *  - domain: Domain the cookie is for.
 *  - secure: Is the cookie https?
 *  - sameSite: SameSite policy
 *  - httpOnly: Is the cookie available in the client?
 *
 * ## Examples
 *
 * ### Getting all cookies
 *
 * `$this->cookie()`
 *
 * ### Getting a certain cookie configuration
 *
 * `$this->cookie('MyCookie')`
 *
 * ### Setting a cookie configuration
 *
 * `$this->cookie((array) $options)`
 *
 * @return mixed
 */
    public function cookie($options = null) {
        if ($options === null) {
            return $this->_cookies;
        }

        if (is_string($options)) {
            if (!isset($this->_cookies[$options])) {
                return null;
            }
            return $this->_cookies[$options];
        }

        $defaults = [
            'name' => 'NataCookie[default]',
            'value' => '',
            'expire' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'sameSite' => 'strict',
            'httpOnly' => false
        ];

        $options += $defaults;
        if (!is_numeric($options['expire'])) {
            $options['expire'] = new Time($options['expire']);
        }

        $this->_cookies[$options['name']] = $options;

        return $this;
    }

/**
 * Setup for display or download the given file.
 *
 * @param string|File $file File or path to file
 * @param array $options Options
 *  ### Options keys
 *  - name: Alternate download name
 *  - download: If `true` sets download header and forces file to be downloaded rather than displayed in browser
 * @return $this
 * @throws \NotFoundException
 */
    public function file($file, $options = []) {
        $options += [
            'name' => null,
            'download' => null
        ];

        if (!($file instanceof File)) {
            if (strpos($file, '..') !== false) {
                throw new NotFoundException('The requested file contains `..` and will not be read.');
            }
            $file = FileFactory::build($file);
        }

        if (!$file->exists() || !$file->isReadable()) {
            throw new NotFoundException(sprintf('The requested file %s was not found or not readable.', $file->url()));
        }

        // Auto-set content type if based on file's mimetype (won't change if default has been changed)
        if ($this->_contentType === 'text/html' && $file->mime() !== $this->_contentType) {
            $mime = $file->mime();
            if (Mimetype::isKnown($file->extension()) && !Mimetype::isValid($file->extension(), $file->mime())) {
                [$mime] = Mimetype::get($file->extension());
            }
            $this->type($mime);
        }

        $filename = $options['name'] ?? $file->basename();
        $fileSize = $file->size();

        $download = $options['download'];
        if ($download) {
            $agent = env('HTTP_USER_AGENT');
            $contentType = null;
            if (preg_match('%Opera(/| )([0-9].[0-9]{1,2})%', $agent)) {
                $contentType = 'application/octetstream';
            } elseif (preg_match('/MSIE ([0-9].[0-9]{1,2})/', $agent)) {
                $contentType = 'application/force-download';
            }

            if ($contentType) {
                $this->type($contentType);
            }

            $this->download($filename);

            $this->header('Accept-Ranges', 'bytes');

            $httpRange = env('HTTP_RANGE');
            if (isset($httpRange)) {
                [$d, $range] = explode('=', $httpRange);
                [$start, $end] = explode('-', $range);

                // Handle case where end is empty (requesting to the end of file)
                if ($end === '') {
                    $end = $fileSize - 1;
                }

                $length = $end - $start + 1;
                $this->header([
                    'Content-Length' => $length,
                    'Content-Range' => "bytes {$start}-{$end}/{$fileSize}"
                ]);

                $this->statusCode(206);
                $file->open('rb', true);
                $file->offset($start);
            } else {
                $this->header('Content-Length', $fileSize);
            }
        } else {
            $this->header('Content-Disposition', 'inline; filename="' . $filename . '"');
            $this->header('Content-Length', $fileSize);
        }

        $this->_clearBuffer();

        $this->_file = $file;

        return $this;
    }

/**
 * Set the response body to a JSON string.
 *
 * ### Examples
 *
 * ```php
 * `$this->json(['success' => true, 'data' => $data]);`
 * `$this->json($data);`
 * `$this->statusCode(422)->json(['success' => false, 'error' => 'An error occurred']);`
 * ```
 *
 * @param string|array $data JSON string or array to encode to JSON
 * @return $this
 */
    public function json(string|array $data = []) {
        $this->type('json');
        if (is_array($data)) {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        }
        $this->body($data);
        return $this;
    }

/**
 * Reads out a file, and echos the content to the client.
 *
 * @param File $file File object
 * @return boolean True is whole file is echoed successfully or false if client connection is lost in between
 */
    protected function _sendFile($file) {
        if (!$this->_isActive()) {
            $file->close();
            return false;
        }

        $this->_body = $file->read();

        $compress = $this->outputCompressed();
        if (!$compress) {
            $this->_flushBuffer();
        }

        return true;
    }

/**
 * Open given file.
 *
 * @param File $file File object
 * @param File $file File object
 * @param File $file File object
 * @return void
 */
    protected function _openFile($file, $mode, $force) {
        if ($file instanceof File) {
            $file->open($mode, $force);
        } elseif ($file instanceof RemoteFile) {
            $file->open($force);
        }
    }

/**
 * Returns true if connection is still active.
 *
 * @return boolean
 */
    protected function _isActive() {
        return connection_status() === CONNECTION_NORMAL && !connection_aborted();
    }

/**
 * Clears the contents of the topmost output buffer and discards them.
 *
 * @return boolean
 */
    protected function _clearBuffer() {
        //@codingStandardsIgnoreStart
        return @ob_end_clean();
        //@codingStandardsIgnoreEnd
    }

/**
 * Flushes the contents of the output buffer.
 *
 * @return void
 */
    protected function _flushBuffer() {
        //@codingStandardsIgnoreStart
        if (ob_get_level() > 0) {
            @flush();
            @ob_flush();
        }
        //@codingStandardsIgnoreEnd
    }

}
