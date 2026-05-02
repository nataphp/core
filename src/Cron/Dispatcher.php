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

namespace Nata\Cron;

use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Utility\Inflector;
use Nata\Console\ProcessManager;
use Nata\Console\ProcessManager\Process;
use Nata\Cron\ErrorHandler;
use Nata\Log\Log;
use NataException;
use MissingJobException;
use MissingJobMethodException;

/**
 * Job dispatcher handles dispatching cli commands.
 */
class Dispatcher {


/**
 * Contains command switches parsed from the command line.
 *
 * @var array
 */
    public $params = [];

/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 */
    public $args = [];

/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 */
    public $paths = [];

/**
 * Constructor
 *
 * The execution of the script is stopped after dispatching the request with
 * a status code of either 0 or 1 according to the result of the dispatch.
 *
 * @param array $args the argv from PHP
 * @param boolean $bootstrap Should the environment be bootstrapped.
 */
    public function __construct($args = [], $bootstrap = true) {
        set_time_limit(0);

        Log::config('cron', [
            'engine' => 'File',
            'levels' => ['info', 'error', 'warning'],
            'scopes' => ['cron'],
            'file' => 'cron'
        ]);

        if ($bootstrap) {
            $this->_initConstants();
        }

        $this->parseParams($args);

        if ($bootstrap) {
            $this->_initEnvironment();
        }
    }

/**
 * Run the dispatcher.
 *
 * @param array $argv The argv from PHP
 * @return void
 */
    public static function run($argv) {
        $dispatcher = new Dispatcher($argv);
        $dispatcher->_stop($dispatcher->dispatch() === false ? 1 : 0);
    }

/**
 * Defines core configuration.
 *
 * @return void
 */
    protected function _initConstants() {
        if (function_exists('ini_set')) {
            ini_set('html_errors', false);
            ini_set('implicit_flush', true);
            ini_set('max_execution_time', 0);
        }

        if (!defined('ROOT')) {
            define('DS', DIRECTORY_SEPARATOR);
            // This file: ROOT/Nata/Cron/Dispatcher.php — two levels above __DIR__ is ROOT.
            define('ROOT', dirname(__DIR__, 2) . DS);
            define('NATA_DIR', 'Nata');
            define('APP_DIR', 'src');
            define('NATA', ROOT . NATA_DIR . DS);
            define('APP', ROOT . APP_DIR . DS);
            define('TMP', ROOT . 'var' . DS);
            define('LOGS', TMP . 'logs' . DS);
        }

    }

/**
 * Defines current working environment.
 *
 * @return void
 * @throws NataException
 */
    protected function _initEnvironment() {
        if (!$this->_bootstrap()) {
            $message = "Unable to load NataPHP core.\nMake sure " . DS . 'Nata' . DS . ' exists in ' . ROOT;
            throw new NataException($message);
        }

        if (!isset($this->args[0])) {
            $message = "This file has been loaded incorrectly and cannot continue.\n" .
                "Please make sure that " . DS . 'Nata' . DS . "Console is in your system path,\n" .
                "and check the cookbook for the correct usage of this command.\n";
            throw new NataException($message);
        }

    }

/**
 * Initializes the environment and loads the Nata core.
 *
 * @return boolean Success.
 */
    protected function _bootstrap() {
        $configDir = is_dir(ROOT . 'config') ? ROOT . 'config' . DS : App::path('Config/');

        Configure::write('App', [
            'base' => false,
            'baseUrl' => false,
            'dir' => APP_DIR,
            'webroot' => 'public',
            'themed' => false,
            'encoding' => 'UTF-8',
            'timezone' => 'UTC',
            'maintenance' => false
        ]);

        include $configDir . 'core.inc.php';

        if (file_exists($configDir . 'database.inc.php')) {
            include $configDir . 'database.inc.php';
        }

        include $configDir . 'bootstrap.inc.php';

        // Improve PHP configuration to prevent issues
        ini_set('upload_max_filesize', '100M');

        date_default_timezone_set(Configure::read('App.timezone'));

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding(Configure::read('App.encoding'));
        }

        $this->setErrorHandlers();

        if (!defined('FULL_BASE_URL')) {
            define('FULL_BASE_URL', 'http://localhost');
        }

        return true;
    }

/**
 * Set the error/exception handlers for the console
 * based on the `Error.cronHandler`, and `Exception.cronHandler` values
 * if they are set. If they are not set, the default CronErrorHandler will be
 * used.
 *
 * @return void
 */
    public function setErrorHandlers() {
        $error = Configure::read('Error');
        $exception = Configure::read('Exception');

        $errorHandler = new ErrorHandler();
        if (empty($error['cronHandler'])) {
            $error['cronHandler'] = [$errorHandler, 'handleError'];
            Configure::write('Error', $error);
        }

        if (empty($exception['cronHandler'])) {
            $exception['cronHandler'] = [$errorHandler, 'handleException'];
            Configure::write('Exception', $exception);
        }

        set_exception_handler($exception['cronHandler']);
        set_error_handler($error['cronHandler'], Configure::read('Error.level'));
    }

/**
 * Dispatches a CLI request.
 *
 * @return boolean
 * @throws MissingJobMethodException
 */
    public function dispatch() {
        $args = $this->args;
        array_shift($args);

        $job = $args[0] ?? null;
        if (!$job) {
            $this->help();
            return false;
        }

        $args = self::_normalizeArguments($args);
        if ($process = $this->_findProcessRunning($args)) {
            $this->args = ['cron', 'error', 'already-running', $process->getId()];
            $this->dispatch();
            return false;
        }

        $jobObject = $this->_loadJob($job, $args);
        $command = isset($args[1]) ? Inflector::variable($args[1]) : 'main';

        $method = method_exists($jobObject, $command);
        $private = strpos($command, '_') === 0 && $method;
        if ($private || !$method) {
            throw new MissingJobMethodException([
                'job' => $job,
                'method' => $command
            ]);
        }

        array_shift($args);
        array_shift($args);

        $jobObject->command = implode(' ', $this->args);
        return call_user_func_array([$jobObject, $command], $args);
    }

/**
 * Get job to use, either plugin job or application job
 *
 * All paths in the loaded job paths are searched.
 *
 * @param Job $job Optionally the name of a plugin
 * @return Job An object
 * @throws MissingJobException when errors are encountered.
 */
    protected function _loadJob($job, array $args) {
        [$plugin, $jobName] = pluginSplit($job, true);
        if ($plugin !== null) {
            $plugin = Inflector::camelize($plugin);
        }

        $jobName = Inflector::camelize($jobName);
        $jobClassName = App::className($plugin . $jobName, 'Cron');
        if (!$jobClassName) {
            throw new MissingJobException([
                'class' => $jobName
            ]);
        }

        // Parse out the config/options parameters
        $config = [];
        foreach ($args as $index => $arg) {
            if (strpos($arg, '--') === 0) {
                $key = Inflector::variable(substr($arg, 2));
                $value = true;
                if (strpos($key, '=') !== false) {
                    [$key, $value] = explode('=', $key);
                } elseif (isset($args[$index + 1]) && strpos($args[$index + 1], '--') !== 0) {
                    $value = $args[$index + 1];
                    $index++;
                }
                if ($value === 'true' || $value === 'false' || $value === '1' || $value === '0') {
                    $value = ($value === 'true');
                }
                $config[$key] = $value;
                unset($args[$index]);
            }
        }

        // initialize the job object
        $job = new $jobClassName(null, null, null, $config);

        if ($plugin !== null) {
            $job->plugin = rtrim($plugin, '.');
        }
        $job->params = $this->params;

        return $job;
    }

/**
 * Parses command line options and extracts the directory paths from $params
 *
 * @param array $args Parameters to parse
 * @return void
 */
    public function parseParams($args) {
        if (empty($args)) {
            return;
        }
        $this->_parsePaths($args);
        $this->params = str_replace('\\', '/', $this->params);
    }

/**
 * Normalize CLI arguments.
 * In some systems the first argument, the plugin dotted string
 * is replaced with a underscore.
 *
 * @param array $args The argv from PHP
 * @return array
 */
    protected static function _normalizeArguments(array $args) {
        if (isset($args[1]) && strpos($args[1], '_') !== false) {
            $args[1] = str_replace('_', '.', $args[1]);
        }

        foreach ($args as $key => $arg) {
            if (strpos($arg, '--') === 0) {
                continue;
            } elseif (str_contains($arg, ' ')) {
                $args[$key] = $arg;
                continue;
            }
            $args[$key] = Inflector::dasherize($arg);
        }

        return $args;
    }

/**
 * Parses out the paths from from the argv
 *
 * @param array $args
 * @return void
 */
    protected function _parsePaths($args) {
        foreach ($args as $key => $arg) {
            if ((strpos($arg, '\\') !== false || strpos($arg, '/') !== false)) {
                if (!file_exists($arg)) {
                    unset($args[$key]);
                }
            } elseif (stripos($arg, 'cron.php') === false) {
                $this->params[] = $arg;
            }
        }
        $this->args = $args;
    }

/**
 * Shows console help.
 * Performs an internal dispatch to the CommandList Job
 *
 * @return void
 */
    public function help() {
        $this->args = ['cron', 'command-list'];
        $this->dispatch();
    }

/**
 * Check if the process is already running.
 *
 * @param string|array $args
 * @return Process|null
 */
    private function _findProcessRunning(array $args): ?Process {
        $normalize = function ($value) {
            return strtolower(str_replace(['.', '_', '-'], '', $value));
        };

        $pid = getmypid();
        $jobName = $normalize(implode(' ', $args));
        $processManager = new ProcessManager();
        foreach ($processManager->find('cron.php') as $process) {
            if ($pid == $process->getId() || $pid == $process->getPpId()) {
                continue;
            }

            $command = $normalize($process->getCommand());
            if (str_contains($command, $jobName)) {
                return $process;
            }
        }
        return null;
    }

/**
 * Stop execution of the current script
 *
 * @param integer|string $status see http://php.net/exit for values
 * @return void
 */
    protected function _stop($status = 0) {
        exit($status);
    }

}
