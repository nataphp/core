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

namespace Nata\Console;

use Nata\Core\App;
use Nata\Core\Configure;
use MissingJobMethodException;
use Nata\Console\ErrorHandler;
use Nata\Core\Exception;
use Nata\Http\BaseApplication;
use Nata\Utility\Inflector;

/**
 * Job dispatcher handles dispatching cli commands.
 */
class Dispatcher {

/**
 * PHP argv.
 *
 * @var array
 */
    protected $_argv;

/**
 * Application.
 *
 * @var BaseApplication
 */
    protected $_application;

/**
 * Commands.
 *
 * @var CommandCollection
 */
    protected $_commands;


/**
 * Constructor.
 *
 * The execution of the script is stopped after dispatching the request with
 * a status code of either 0 or 1 according to the result of the dispatch.
 *
 * @param array $args the argv from PHP
 * @param BaseApplication $application The application
 * @param boolean $bootstrap Should the environment be bootstrapped.
 */
    public function __construct($argv = [], BaseApplication $application = null, $bootstrap = true) {
        set_time_limit(0);

        if ($bootstrap) {
            $this->_initEnvironment();
        }

        $this->_application = $application;
        $this->_argv = $this->_parseArgv($argv);
        $this->_commands = new CommandCollection();
    }

/**
 * Run the dispatcher.
 *
 * @param array $argv The argv from PHP
 * @return void
 */
    public static function run($argv, BaseApplication $application = null) {
        $dispatcher = new Dispatcher($argv, $application);
        $dispatcher->_stop($dispatcher->dispatch() === false ? 1 : 0);
    }

/**
 * Defines core configuration.
 *
 * @return bool
 * @throws Exception
 */
    protected function _initEnvironment() {
        if (function_exists('ini_set')) {
            ini_set('html_errors', false);
            ini_set('implicit_flush', true);
            ini_set('max_execution_time', 0);
        }

        if (!defined('ROOT')) {
            define('DS', DIRECTORY_SEPARATOR);
            // This file: ROOT/Nata/Console/Dispatcher.php — two levels above __DIR__ is ROOT.
            define('ROOT', dirname(__DIR__, 2) . DS);
            define('NATA_DIR', 'Nata');
            define('APP_DIR', 'src');
            define('NATA', ROOT . NATA_DIR . DS);
            define('APP', ROOT . APP_DIR . DS);
            define('TMP', ROOT . 'var' . DS);
            define('LOGS', TMP . 'logs' . DS);
        }

        if ($this->_bootstrap()) {
            return true;
        }

        throw new Exception(
            "Unable to load NataPHP core.\nMake sure " . DS . 'Nata' . DS . ' exists in ' . ROOT
        );
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
 * @return int Exit code
 */
    public function dispatch(): int {
        $argv = $this->_argv;
        $cmdName = array_shift($argv);
        $commands = $this->_application->console($this->_commands);

        // No command passed
        if (!$cmdName) {
            return $this->_availableCommandsOutput($commands);
        }

        $command = $commands->get($cmdName);
        if (!($command instanceof Command)) {
            $io = new Io();
            $io->error(sprintf(
                "Command '%s' doesn't exist in this application.",
                $cmdName
            ));
            $io->info("Run 'bin/nata help' for a list of available commands.");
            return Command::CODE_ERROR;
        }

        $optionParser = $command->getOptionParser();
        [$options, $args] = $optionParser->parse(array_values($argv));

        $io = new Io();

        // Check if is a subcommand
        $maybeSubcommand = array_shift($argv);
        $subCommandName = $maybeSubcommand ? Inflector::underscore($maybeSubcommand) : null;
        $subCommand = $subCommandName ? ($optionParser->subcommands()[$subCommandName] ?? null) : null;
        $argNames = $optionParser->argumentNames();
        if ($subCommand) {
            $subCommandInstance = $commands->get($subCommandName, $cmdName);
            if ($subCommandInstance instanceof Command) {
                $command = $subCommandInstance;
                $argNames = $command->getOptionParser()->argumentNames();
            }
        }
        $args = new Arguments($args, $options, $argNames);

        // Help
        if ($options['help'] !== false) {
            return $this->_helpOuput($optionParser, $subCommand ? $subCommandName : null);
        }

        $result = $command->execute($args, $io);
        if (is_int($result)) {
            return $result;
        }

        if ($result === null) {
            return Command::CODE_SUCCESS;
        }

        return Command::CODE_ERROR;
    }

/**
 * Parses command line options and extracts the directory paths from $params.
 *
 * @param array $args Parameters to parse
 * @see https://nullprogram.com/blog/2020/08/01/
 * @return array
 */
    protected function _parseArgv($argv) {
        foreach ($argv as $key => $arg) {
            if (stripos($arg, 'nata.php') !== false) {
                unset($argv[$key]);
            }
        }
        return array_values($argv);
    }

/**
 * Output help.
 *
 * @param OptionParser $optionParser Option parser
 * @param string|null $subCommand Subcommand
 * @return int Success code
 */
    protected function _helpOuput(OptionParser $optionParser, ?string $subCommand = null): int {
        (new Io())->out($optionParser->help($subCommand));
        return Command::CODE_SUCCESS;
    }

/**
 * Output available commands.
 *
 * @return int Success code
 */
    protected function _availableCommandsOutput(): int {
        $io = new Io();
        $io->info(sprintf('Welcome to NataPHP %s Console.', Configure::version()), 1);
        $io->info("Run 'bin/nata help' for a list of available commands.");
        return Command::CODE_SUCCESS;
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
