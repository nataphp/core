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
use Nata\Http\Runner;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Http\Server\MiddlewareInterface;
use Nata\I18n\I18n;
use Nata\Routing\Exception\MissingRouteException;
use Nata\Routing\Router;

/**
 * Middleware to parse request URL based on defined routes.
 */
class Routing implements MiddlewareInterface {

/**
 * Config directory.
 *
 * @var string
 */
    protected $_configDir;

/**
 * Routes loaded.
 *
 * @var bool
 */
    protected static $_routesLoaded = false;


/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function __construct(array $options = []) {
        $options += [
            'configDir' => App::path('Config/')
        ];
        $this->_configDir = $options['configDir'];
    }

/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function process(Request $request, Response $response, Runner $next) {
        $this->_parseParams($request);
        return $next->process($request, $response, $next);
    }

/**
 * Applies Routing to the request to be dispatched.
 * If Routes have not been loaded they will be loaded, and app/Config/routes.php will be run.
 *
 * @param Nata\Http\Request $request Request
 */
    protected function _parseParams($request): void {
        Router::setRequest($request);

        if (static::$_routesLoaded !== true) {
            include_once $this->_configDir . 'routes.inc.php';
            static::$_routesLoaded = true;
        }

        $params = Router::parse('/' . $request->url);
        if (!$params['controller']) {
            throw new MissingRouteException([
                'url' => $request->here(),
            ]);
        }

        $request->addParams($params);

        if (isset($params['language']) && I18n::isAvailable($params['language'])) {
            I18n::locale($params['language']);
        }
    }

}
