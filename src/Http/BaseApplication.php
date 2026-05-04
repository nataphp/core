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

use Nata\Cache\Cache;
use Nata\Console\CommandCollection;
use Nata\Core\ComposerPlugins;
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
        // Load environment variables from the root .env file into Configure
        Configure::loadEnv(ROOT . '.env');

        // Core application paths and locale settings; base/baseUrl are false so
        // the framework auto-detects them from the request
        Configure::write('App', [
            'base' => false,
            'baseUrl' => false,
            'dir' => APP_DIR,
            'webroot' => 'public',
            'themed' => false,
            'encoding' => 'UTF-8',
            'timezone' => 'UTC'
        ]);

        // Safe production defaults — individual apps override these as needed
        Configure::write('debug', false);       // disable verbose error output
        Configure::write('development', false); // disable development-only features
        Configure::write('Cache', ['disabled' => false]); // ensure caching is active

        // Maintenance mode defaults; apps raise 'enabled' and set a message/until
        // date when they need to take the site offline temporarily
        Configure::write('maintenance', [
            'enabled' => false,
            'level' => 1,       // 1 = full lock-out, higher levels may allow partial access
            'description' => null,
            'until' => null,    // expected end time shown to visitors
            'allowedIp' => [],  // IPs that bypass the maintenance gate
        ]);

        // PHP error handling — routes errors to the Nata handler which formats
        // and logs them; cron jobs use a dedicated handler to avoid HTTP responses
        Configure::write('Error', [
            'handler' => '\Nata\Error\Handler::handleError',
            'cronHandler' => '\Nata\Cron\ErrorHandler::handleError',
            'level' => E_ALL & ~E_DEPRECATED, // capture everything except deprecation notices
            'trace' => true
        ]);

        // Uncaught exception handling — renders a clean error page and logs the
        // exception; expected user-facing exceptions are excluded from the log
        Configure::write('Exception', [
            'handler' => '\Nata\Error\Handler::handleException',
            'cronHandler' => '\Nata\Cron\ErrorHandler::handleError',
            'renderer' => '\Nata\Error\Renderer',
            'log' => true,
            'skipLog' => ['NotFoundException', 'UnderMaintenanceException']
        ]);

        // Internal framework cache used by the core itself (e.g. plugin discovery);
        // prefers APC for speed and falls back to file-based caching
        Cache::config('__nata_core__', [
            'engine' => ['Apc', 'File'],
            'duration' => '10 days'
        ]);

        if (is_file($this->_configDir . 'local.inc.php')) {
            include $this->_configDir . 'local.inc.php';
        }

        include $this->_configDir . 'bootstrap.inc.php';

        if (file_exists($this->_configDir . 'database.inc.php')) {
            include $this->_configDir . 'database.inc.php';
        }

        ComposerPlugins::load();

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
