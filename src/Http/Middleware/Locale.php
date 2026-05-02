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

use Nata\Core\App;
use Nata\Cache\Cache as CacheLib;
use Nata\Http\Runner;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Http\Server\MiddlewareInterface;
use Nata\Routing\Exception\MissingRouteException;
use Nata\Routing\Router;

/**
 * Middleware to parse request URL based on defined routes.
 */
class Locale implements MiddlewareInterface {

/**
 * Cache enabled.
 *
 * @var bool
 */
    protected $_enabled;

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
            'config' => null
        ];
        $this->_configName = $options['config'];
        $this->_enabled = $options['enabled'];
    }

/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function process(Request $request, Response $response, Runner $next) {
        return $next->process($request, $response, $next);
    }

}
