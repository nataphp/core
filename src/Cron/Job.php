<?php
/**
 * Base class for Jobs
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 1.2.0.5012
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Cron;

use Closure;
use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\ORM\TableRegistry;

/**
 * Base class for command-line utilities for automating programmer chores.
 */
class Job extends NataObject {

/**
 * ANSI color codes.
 *
 * @var array
 */
    const ANSI_COLORS = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'purple' => "\033[0;35m",
        'magenta' => "\033[0;35m",
        'pink' => "\033[0;35m",
        'brown' => "\033[0;33m",
        'gray' => "\033[0;30m",
        'dark_gray' => "\033[1;30m",
        'light_gray' => "\033[0;37m",
        'light_blue' => "\033[1;34m",
        'light_green' => "\033[1;32m",
        'light_cyan' => "\033[1;36m",
        'light_red' => "\033[1;31m",
        'light_purple' => "\033[1;35m",
        'cyan' => "\033[0;36m",
        'white' => "\033[0;37m",
        'end' => "\033[0m"
    ];

/**
 * Output constants for making verbose and quiet jobs.
 */
    const VERBOSE = 2;
    const NORMAL = 1;
    const QUIET = 0;

/**
 * An instance of ConsoleOptionParser that has been configured for this class.
 *
 * @var ConsoleOptionParser
 */
    public $OptionParser;

/**
 * If true, the script will ask for permission to perform actions.
 *
 * @var boolean
 */
    public $interactive = true;

/**
 * Contains command switches parsed from the command line.
 *
 * @var array
 */
    public $params = array();

/**
 * The command (method/task) that is being run.
 *
 * @var string
 */
    public $command;

/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 */
    public $args = array();

/**
 * The name of the job in camelized.
 *
 * @var string
 */
    public $name;

/**
 * The name of the plugin the job belongs to.
 * Is automatically set by JobDispatcher when a job is constructed.
 *
 * @var string
 */
    public $plugin;

/**
 * Contains task registry instance.
 *
 * @var array
 */
    protected $_tasks;

/**
 * Contains tasks to load and instantiate.
 *
 * @var array
 */
    public $tasks = array();

/**
 * stdout object.
 *
 * @var CronOutput
 */
    public $stdout;

/**
 * stderr object.
 *
 * @var CronOutput
 */
    public $stderr;

/**
 * stdin object
 *
 * @var ConsoleInput
 */
    public $stdin;


/**
 *  Constructs this Job instance.
 *
 * @param Cron\Output $stdout A Cron\Output object for stdout.
 * @param Cron\Output $stderr A Cron\Output object for stderr.
 * @param ConsoleInput $stdin A ConsoleInput object for stdin.
 * @param array $config Array of config.
 * @link http://book.cakephp.org/2.0/en/console-and-jobs.html#Job
 */
    public function __construct($stdout = null, $stderr = null, $stdin = null, array $config = []) {
        if ($this->name === null) {
            $this->name = App::classShortName(get_class($this));
        }

        $this->config($config);

        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->stdin = $stdin;

        $parent = get_parent_class($this);

        if ($this->tasks !== null && $this->tasks !== false) {
            $this->_mergeVars(array('tasks'), $parent, true);
        }

        $this->_loadTasks();
        $this->initialize();
    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @return void
 */
    public function initialize() {}

/**
 * Get the component registry for this controller.
 *
 * @return \Nata\Controller\ComponentRegistry
 */
    public function tasks() {
        if ($this->_tasks === null) {
            $this->_tasks = new TaskRegistry($this);
        }
        return $this->_tasks;
    }

/**
 * Loads the defined components using the Component factory.
 *
 * @return void
 */
    protected function _loadTasks() {
        if (empty($this->tasks)) {
            return;
        }

        $registry = $this->tasks();
        $tasks = $registry->normalizeArray($this->tasks);

        foreach ($tasks as $properties) {
            $this->loadTask($properties['class'], $properties['config']);
        }
    }

/**
 * Add a task to the job's registry.
 *
 * This method will also set the task to a property.
 * For example:
 *
 * ```
 * $this->loadTask('QMail.Email');
 * ```
 *
 * Will result in a `Toolbar` property being set.
 *
 * @param string $name The name of the task to load.
 * @param array $config The config for the task.
 * @return \Nata\Cron\Task
 */
    public function loadTask($name, array $config = []) {
        [, $prop] = pluginSplit($name);
        $this->{$prop} = $this->tasks()->load($name, $config);
        return $this->{$prop};
    }

/**
 * Provides backwards compatibility access to the request object properties.
 * Also provides the params alias.
 *
 * @param string $name
 * @return void
 */
    public function __get($name) {
        if ($this->tasks()->exists($name)) {
            return $this->{$name} = $this->tasks()->load($name);
        }

        if ($this->plugin) {
            $this->plugin .= '.';
        }

        return TableRegistry::get($this->plugin . $name);
    }

/**
 * Dispatch a command to another Job. Similar to Object::requestAction()
 * but intended for running jobs from other jobs.
 *
 * ### Usage:
 *
 * With a string command:
 *
 *    `return $this->dispatchJob('schema create DbAcl');`
 *
 * Avoid using this form if you have string arguments, with spaces in them.
 * The dispatched will be invoked incorrectly. Only use this form for simple
 * command dispatching.
 *
 * With an array command:
 *
 * `return $this->dispatchJob('schema', 'create', 'i18n', '--dry');`
 *
 * @return mixed The return of the other job.
 * @link http://book.cakephp.org/2.0/en/console-and-jobs.html#Job::dispatchJob
 */
    public function dispatchJob() {
        $args = func_get_args();
        if (is_string($args[0]) && count($args) == 1) {
            $args = explode(' ', $args[0]);
        }

        $dispatcher = new Dispatcher($args, false);
        return $dispatcher->dispatch();
    }

/**
 * Display the help in the correct format
 *
 * @param string $command
 * @return void
 */
    protected function _displayHelp($command) {
        $format = 'text';
        if (!empty($this->args[0]) && $this->args[0] == 'xml') {
            $format = 'xml';
            $this->stdout->outputAs(Output::RAW);
        } else {
            $this->_welcome();
        }
        return $this->out($this->OptionParser->help($command, $format));
    }

/**
 * Execute a system command. This function is a wrapper for the PHP `exec` function.
 *
 * @param string $command The command to run
 * @param array $output If provided, the output of the command will be written into this variable
 * @param integer $return_var If provided, this variable will be filled with the return code from the command
 * @return string|boolean The last line from the result of the command
 */
    protected function exec($command, Closure $customOutput = null) {
        $return = shell_exec($command);
        if ($customOutput) {
            $return = $customOutput(trim($return));
        }
        if ($return) {
            $this->out($return);
        }
    }

/**
 * Output a message to the console. If the message is an array, each value will be output on a new line.
 *
 * @param string|array $msg Message to output
 * @param integer $newlines Number of newlines to append
 * @param string $color Color of the text
 * @return void
 */
    protected function out(string|array $msg, int $newlines = 1, string $color = null) {
        if (is_array($msg)) {
            foreach ($msg as $line) {
                $this->out($line, $newlines, $color);
            }
            return;
        }
        if ($color) {
            $msg = $this->wrapColor($msg, $color);
        }
        echo $msg . str_repeat(PHP_EOL, $newlines);
    }

/**
 * Output a error message to the console.
 *
 * @param string|array $msg Message to output
 * @param integer $newlines Number of newlines to append
 * @return void
 */
    protected function error(string|array $msg, int $newlines = 1) {
        $msg = (array)$msg;
        foreach ($msg as $index => $line) {
            $msg[$index] = $this->wrapColor($line, 'red');
        }
        $this->out($msg, $newlines);
    }

/**
 * Output a success message to the console.
 *
 * @param string|array $msg Message to output
 * @param integer $newlines Number of newlines to append
 * @return void
 */
    protected function success(string|array $msg, int $newlines = 1) {
        $msg = (array)$msg;
        foreach ($msg as $index => $line) {
            $msg[$index] = $this->wrapColor($line, 'green');
        }
        $this->out($msg, $newlines);
    }

/**
 * Output a info message to the console.
 *
 * @param string|array $msg Message to output
 * @param integer $newlines Number of newlines to append
 * @return void
 */
    protected function info(string|array $msg, int $newlines = 1) {
        $msg = (array)$msg;
        foreach ($msg as $index => $line) {
            $msg[$index] = $this->wrapColor($line, 'cyan');
        }
        $this->out($msg, $newlines);
    }

/**
 * Output a warning message to the console.
 *
 * @param string|array $msg Message to output
 * @param integer $newlines Number of newlines to append
 * @return void
 */
    public function warn(string|array $msg, int $newlines = 1) {
        $msg = (array)$msg;
        foreach ($msg as $index => $line) {
            $msg[$index] = $this->wrapColor($line, 'yellow');
        }
        $this->out($msg, $newlines);
    }

/**
 * Wrap a string with ansi color codes.
 *
 * @param string $string
 * @param string $color
 * @return string
 */
    protected function wrapColor(string $string, string $color): string {
        $color = static::ANSI_COLORS[$color] ?? static::ANSI_COLORS['white'];
        return $color . $string . static::ANSI_COLORS['end'];
    }

}
