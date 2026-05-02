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

use Nata\Core\ConfigAwareTrait;
use Nata\Http\Client\Request;
use Nata\Http\Client\Request\Get;
use Nata\Http\Client\Request\Post;
use Nata\Http\Client\Request\Put;
use Nata\Http\Client\Request\Head;
use Nata\Http\Client\Request\Delete;

/**
 * HTTP client.
 * Nata cURL wrapper.
 *
 * @todo Needs refactoring for better separation of concerns
 */
class Client {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * @TODO Complete the number of options that the client has (based on the ones on the constructor)
 *
 * - 'timeout': The maximum number of seconds to allow cURL functions to execute.
 * - 'maxRedirects'
 *
 * @var array
 */
    protected $__defaultConfig = [
        'timeout' => 150,
        'maxRedirects' => 5,
        ''
    ];

/**
 * Root certificates file.
 *
 * @var \Nata\Filesystem\File
 */
    protected $_cacert;


/**
 * TODO
 * __constructor.
 *
 * @param array $config HTTP Client configuration
 * @return void
 */
    public function __construct(array $config = []) {
        $config += [];
        $this->config($config);
    }

/**
 * Send HTTP GET request.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request\Get
 */
    public static function get($config = []) {
        return new Get($config);
    }

/**
 * Send HTTP POST request.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request\Post
 */
    public static function post($config = []) {
        return new Post($config);
    }

/**
 * Send HTTP PUT request.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request\Put
 */
    public static function put($config = []) {
        return new Put($config);
    }

/**
 * Send HTTP PUT request.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request\Put
 */
    public static function head($config = []) {
        return new Head($config);
    }

/**
 * Send HTTP DELETE request.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request\Delete
 */
    public static function delete($config = []) {
        return new Delete($config);
    }

/**
 * Get HTTP request method.
 *
 * @param array|string $config URL or array with configuration
 * @return \Nata\Http\Client\Request
 */
    public static function newRequest($config = []) {
        return new Request($config);
    }

/**
 * Get HTTP request method.
 *
 * @deprecated Use Client::newRequest() instead
 */
    public static function getRequest($config = []) {
        return static::newRequest($config);
    }

/**
 * Create HTTP custom request method.
 *
 * @deprecated Use Client::newRequest() instead
 */
    public static function request($config = []) {
        return static::newRequest($config);
    }

/**
 * Send HTTP request.
 *
 * @todo Place here the required logic to make the request (cURL)
 * @param \Nata\Http\Client\Request $request Request
 * @return \Nata\Http\Client\Response
 */
    public static function send(Request $request) {
        return $request->send();
    }

/**
 * Auto send request if option 'send' is set to true.
 *
 * @param \Nata\Network\Http\Request $request Request instance
 * @param array $config URL or array with configuration
 * @return \Nata\Network\Http\Request|\Nata\Network\Http\Response
 */
    protected function _send() {
        $config = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 150,
            CURLOPT_VERBOSE => 0,
            CURLOPT_ENCODING => '',
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];

        /****

        cURL logic

        ******/

    }

}