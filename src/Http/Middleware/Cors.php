<?php
/**
 * NataPHP Framework.
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

use Nata\Core\ConfigAwareTrait;
use Nata\Http\Runner;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Core\Configure;
use Nata\Http\Server\MiddlewareInterface;
use Nata\I18n\Time;

/**
 * Check request if application is under maintenance mode.
 */
class Cors implements MiddlewareInterface {

    use ConfigAwareTrait;

/**
 * Config directory.
 *
 * @var string
 */
    protected $_defaultConfig = [];

/**
 * Constructor.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 *
 * @param array $options Options
 */
    public function __construct(array $options = []) {
        $this->config($options);
    }

/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function process(Request $request, Response $response, Runner $next) {
        if (str_contains($request->host(), 'api.maismls')) {
            if (strtoupper($request->method()) === 'OPTIONS') {
                $allowHeaders = $request->header('Access-Control-Request-Headers');
                $response->header('Access-Control-Allow-Origin', $request->header('origin'));
                $response->header('Access-Control-Allow-Methods', 'GET');
                // $response->header('Access-Control-Allow-Credentials', 'true');
                $response->header('Access-Control-Allow-Headers', 'authorization' . ($allowHeaders ? ',' . $allowHeaders : ''));
                return $response;
            } elseif ($request->header('origin')) {
                $response->header('Access-Control-Allow-Origin', $request->header('origin'));
                $response->header('Access-Control-Allow-Method', 'GET');
                $response->statusCode(200);
            }
        }
        return $next->process($request, $response, $next);
    }

}
