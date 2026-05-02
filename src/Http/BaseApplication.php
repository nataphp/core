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

use Nata\Console\CommandCollection;
use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Core\NataObject;
use Nata\Event\Manager;
use Nata\Http\ActionDispatcher;
use Nata\Http\Server\MiddlewareInterface;

/**
 * Dispatcher takes the URL information, parses it for parameters and
 * tells the involved controllers what to do.
 *
 * This is the heart of Nata's operation.
 *
 * Dispatcher converts Requests into controller actions. It uses the dispatched \Nata\Http\Request
 * to locate and load the correct controller. If found, the requested action is called on
 * the controller.
 */
class BaseApplication extends NataObject implements MiddlewareInterface {

/**
 * Event manager.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Application configuration directory.
 *
 * @var string
 */
    protected $_configDir;


/**
 * Constructor.
 *
 * @param string $configDir Application configuration directory
 * @param \Nata\Event\Manager $eventManager Event Manager instance
 */
    public function __construct($configDir = null, Manager $eventManager = null) {
        $this->_configDir = $configDir ?: (is_dir(ROOT . 'config') ? ROOT . 'config' . DS : App::path('Config/'));
        $this->_eventManager = $eventManager ?: Manager::instance();
    }

/**
 * Application bootstrap.
 *
 * @return void
 */
    public function bootstrap() {
        Configure::write('App', [
            'base' => false,
            'baseUrl' => false,
            'dir' => APP_DIR,
            'webroot' => 'public',
            'themed' => false,
            'encoding' => 'UTF-8',
            'timezone' => 'UTC'
        ]);

        include $this->_configDir . 'core.inc.php';

        if (file_exists($this->_configDir . 'database.inc.php')) {
            include $this->_configDir . 'database.inc.php';
        }

        include $this->_configDir . 'bootstrap.inc.php';

        // Improve PHP configuration to prevent issues
        ini_set('upload_max_filesize', '100M');
        date_default_timezone_set(Configure::read('App.timezone'));
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding(Configure::read('App.encoding'));
        }

        register_shutdown_function('\Nata\Core\App::shutdown');

        Configure::setErrorHandlers();
    }

/**
 * Load middleware into queue.
 *
 * @param \Nata\Http\MiddlewareQueue $middlewareQueue Queue
 * @return \Nata\Http\MiddlewareQueue
 */
    public function middleware(MiddlewareQueue $middlewareQueue) {
        return $middlewareQueue;
    }

/**
 * Application middleware.
 *
 * @param \Nata\Http\Request $request Request
 * @param \Nata\Http\Response $response Response
 * @param \Nata\Http\Runner $next Runner
 * @return \Nata\Http\Response
 */
    public function process(Request $request, Response $response, Runner $next) {
        $dispatcher = new ActionDispatcher($this->_eventManager);
        $dispatcher->dispatch($request, $response);
        return $next->process($request, $response);
    }

/**
 * Console commands managment.
 *
 * ```
 * // Add commands with nested naming
 * $commands->add('user dump', UserDumpCommand::class);
 * $commands->add('user:show', UserShowCommand::class);
 *
 * // Rename a command entirely
 * $commands->add('lazer', UserDeleteCommand::class);
 *
 * ```
 *
 * @param CommandCollection $commands CommandCollection
 * @return CommandCollection
 */
    public function console(CommandCollection $commands): CommandCollection {
        return $commands;
    }

}
