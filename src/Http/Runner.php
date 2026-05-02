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

use Nata\Http\Request;
use Nata\Http\Response;

/**
 * Executes the middleware queue and provides the `next` callable
 * that allows the queue to be iterated.
 */
class Runner {

/**
 * The current index in the middleware queue.
 *
 * @var int
 */
    protected $_index;

/**
 * The middleware queue being run.
 *
 * @var \Nata\Http\MiddlewareQueue
 */
    protected $_middleware;


/**
 * @param MiddlewareQueue $middleware The middleware queue
 * @param Request $request The Server Request
 * @param Response $response The response
 * @return Response A response object
 */
    public function run(MiddlewareQueue $middleware, Request $request, Response $response) {
        $this->_middleware = $middleware;
        $this->_index = 0;

        return $this->process($request, $response);
    }

/**
 * @param Request $request The server request
 * @param Response $response The response object
 * @return Response An updated response
 */
    public function process(Request $request, Response $response) {
        $next = $this->_middleware->get($this->_index);

        if ($next) {
            $this->_index++;
            return $next->process($request, $response, $this);
        }

        // End of the queue
        return $response;
    }

}
