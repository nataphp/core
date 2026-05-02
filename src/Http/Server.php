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

use Nata\Http\BaseApplication;
use Nata\Core\NataObject;
use Nata\Event\Listener;
use Nata\Event\Manager;
use Nata\Http\Request;
use Nata\Http\Response;
use RuntimeException;

/**
 * Runs an application invoking all the PSR7 middleware and the registered application.
 */
class Server extends NataObject implements Listener  {

/**
 * Event manager, used to handle dispatcher filters.
 *
 * @var Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Application.
 *
 * @var \App\Application
 */
    protected $_app;

/**
 * Runner.
 *
 * @var Runner
 */
    protected $_runner;


/**
 * Constructor.
 *
 * @param \App\Application $app Application instance
 */
    public function __construct(BaseApplication $app) {
        $this->_app = $app;
        $this->_runner = new Runner;
    }

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @param \Nata\Event\Manager $eventManager Event manager instance
 * @return \Nata\Event\Manager
 */
    public function eventManager($eventManager = null) {
        if ($eventManager === null) {
            if ($this->_eventManager === null) {
                $this->_eventManager = new Manager();
                $this->_eventManager->on($this);
            }
            return $this->_eventManager;
        }
        $this->_eventManager = $eventManager;
        return $this;
    }

/**
 * Returns the list of events this object listents to.
 *
 * @return array
 */
    public function implementedEvents() {
        return ['Server.buildMiddleware'];
    }

/**
 * Run the request/response through the Application and its middleware.
 *
 * This will invoke the following methods:
 *
 * - App->bootstrap() - Perform any bootstrapping logic for your application here.
 * - App->middleware() - Attach any application middleware here.
 * - Trigger the 'Server.buildMiddleware' event. You can use this to modify the
 *   from event listeners.
 * - Run the middleware queue including the application.
 *
 * @param \Nata\Http\Request|null $request The request to use or null.
 * @param \Nata\Http\Response|null $response The response to use or null.
 * @return void
 * @throws \RuntimeException When the application does not make a response.
 */
    public function run(Request $request = null, Response $response = null) {
        $this->_app->bootstrap();

        $request = $request ?: new Request;
        $response = $response ?: new Response;

        $middleware = $this->_app->middleware(new MiddlewareQueue);
        if (!($middleware instanceof MiddlewareQueue)) {
            throw new RuntimeException('The application `middleware` method did not return a middleware queue.');
        }

        $this->dispatchEvent('Server.buildMiddleware', ['middleware' => $middleware]);

        $middleware->add($this->_app);
        $response = $this->_runner->run($middleware, $request, $response);
        if (!($response instanceof Response)) {
            throw new RuntimeException(sprintf(
                'Application did not create a response. Got "%s" instead.',
                is_object($response) ? get_class($response) : $response
            ));
        }

        $response->send();
    }

}
