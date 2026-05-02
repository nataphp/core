<?php
/**
 * NataPHP Framework
 *
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

namespace Nata\Http\Middleware;

use Closure;
use Nata\Core\App;
use Nata\Cache\Cache as CacheLib;
use Nata\Http\Runner;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Http\Server\MiddlewareInterface;

/**
 * Middleware to parse request URL based on defined routes.
 */
class Cache implements MiddlewareInterface {

/**
 * Cache enabled.
 *
 * @var bool
 */
    protected $_enabled;

/**
 * Cache key.
 *
 * @var Closure
 */
    protected $_key;

/**
 * HTTP method(s).
 *
 * @var string|array
 */
    protected $_methods;

/**
 * Cache config name.
 *
 * @var string
 */
    protected $_configName;


/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function __construct(array $options = []) {
        $options += [
            'enabled' => true,
            'key' => function ($request) {
                return md5($request->getHost() . $request->here() . $request->header('Accept') . json_encode($request->query()));
            },
            'methods' => ['GET', 'POST'],
            'config' => null
        ];
        $this->_key = $options['key'];
        $this->_configName = $options['config'];
        $this->_enabled = $options['enabled'];
        $this->_methods = $options['methods'];
    }

/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function process(Request $request, Response $response, Runner $next) {
        $enabled = $this->_isEnabled($request);
        if (!$enabled || !$this->_configName) {
            return $next->process($request, $response, $next);
        }

        if (!$this->_isMethodAllowed($request)) {
            return $next->process($request, $response, $next);
        }

        $cacheKey = $this->_getKey($request);
        $cachedResponse = CacheLib::read($cacheKey, $this->_configName);
        if ($cachedResponse) {
            $cachedResponse->header([
                'x-nataphp-cached' => 1,
                'x-nataphp-cache-info' => json_encode(CacheLib::info($cacheKey, $this->_configName)),
                'x-nataphp-prerendering-generated' => null
            ]);
            return $cachedResponse;
        }

        $response = $next->process($request, $response, $next);

        CacheLib::write($cacheKey, $response, $this->_configName);

        return $response;
    }

/**
 * Is HTTP method allowed.
 *
 * @param Request $request Request
 * @return bool True if enabled, false otherwise
 */
    protected function _isMethodAllowed(Request $request): bool {
        $methods = $this->_methods;
        if ($methods instanceof Closure) {
            $methods = $methods($request);
        }
        return in_array($request->getMethod(), (array)$methods);
    }

/**
 * Is enabled.
 *
 * @param Request $request Request
 * @return bool True if enabled, false otherwise
 */
    protected function _isEnabled(Request $request): bool {
        $enabled = $this->_enabled;
        if ($enabled instanceof Closure) {
            $enabled = $enabled($request);
        }
        return $enabled;
    }

/**
 * Get cache key.
 *
 * @param Request $request Request
 * @return string Cache key
 */
    protected function _getKey(Request $request): string {
        $key = $this->_key;
        if ($key instanceof Closure) {
            $key = $key($request);
        }
        return $key;
    }

}
