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

namespace Nata\Service;

use Exception;
use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Event\Listener;
use Nata\Filesystem\File;
use Nata\Utility\Inflector;

/**
 * Service base class for loading models, logging, configuration
 * handling, etc.
 */
class Service extends NataObject implements Listener {

/**
 * Service name.
 *
 * @var string
 */
    public $name;

/**
 * Service domain.
 *
 * @var string
 */
    public $domain;

/**
 * Daemon process ID file.
 *
 * @var \Nata\Filesystem\File
 */
    protected $_pidFile;


/**
 * Constructor.
 *
 * @param array $config Service options
 */
    public function __construct(array $config) {
        $this->config($config);

        $this->name = $config['name'];
        $this->domain = $config['domain'];

        $this->initialize($config);
    }

/**
 * Pseudo-constructor.
 *
 * @param array $config Config
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        $eventMap = [
            'Service.afterStartup' => 'afterStartup',
            'Service.beforeStart' => 'beforeStart',
            'Service.afterStart' => 'afterStart',
            'Service.beforeStop' => 'beforeStop',
            'Service.afterStop' => 'afterStop',
            'Service.shutdown' => 'beforeShutdown'
        ];

        $events = [];
        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }

            $events[$event] = $method;
        }

        return $events;
    }

/**
 * Start daemon.
 *
 * @param string $method Method name
 * @return void
 */
    public function __start() {
        if (!env('CLI_MODE')) {
            throw new Exception('Daemon mode can only be run on the Console.');
        }

        if ($this->isRunning()) {
            if (env('CLI_MODE')) {
                echo('Already running...');
            }
            return false;
        }

        $this->_pidState(getmypid());

        while (true) {
            $this->_execute();
        }

    }

/**
 * Stop process.
 *
 * @return bool True if started running, false otherwise
 */
    public function __stop() {
        $state = $this->_pidState();
        $pidFile = $this->_getPidFile();
        if ($state['pid']) {
            if (!$this->_processIsRunning($state['pid'])) {
                $pidFile->delete();
            }
            if ($this->_killProcess($state['pid'])) {
                $pidFile->delete();
            }
        }
    }

/**
 * Get daemon status.
 *
 * @param string $method Method name
 * @return bool True if started running, false otherwise
 */
    public function __status() {
        $state = $this->_pidState();
        return $state['pid'] > 0 ? $state : null;
    }

/**
 * Get daemon status.
 *
 * @param string $method Method name
 * @return bool True if started running, false otherwise
 */
    public function __isRunning() {
        return $this->_getPid() && $this->_processIsRunning($this->_getPid());
    }

/**
 * Get/Set daemon state.
 *
 * @param string $method Method name
 * @return array True if started running, false otherwise
 */
    protected function __getPid() {
        $state = $this->_pidState();
        return $state['pid'];
    }

/**
 * Get/Set process state.
 *
 * @param string $method Method name
 * @return array True if started running, false otherwise
 */
    protected function __pidState($pid = null) {
        $stateFile = $this->_getPidFile();
        $state = ['pid' => null, 'time' => null];
        if ($stateFile->exists()) {
            $state = unserialize($stateFile->read());
        }

        if ($pid === null) {
            return $state;
        }

        $sid = null;
        ignore_user_abort(true);
        if (!$this->_isWindows()) {
            $fpid = pcntl_fork();
            // On error
            if ($fpid < 0) {
                exit;
            // Parent...
            } elseif ($fpid) {
                exit;
            }

            $sid = posix_setsid();
            // On error
            if ($sid < 0) {
                exit;
            }
        }

        $state = ['time' => time(), 'pid' => getmypid(), 'sid' => $sid];
        $stateFile->write(serialize($state));
        $stateFile->close();

        return $state;
    }

/**
 * Get daemon file state.
 *
 * @param string $method Method name
 * @return File Daemon file with state
 */
    protected function __getPidFile() {
        if ($this->_pidFile === null) {
            $this->_pidFile = new File(TMP . 'cache' . DS . sprintf(
                '%s.pid',
                Inflector::slug($this->config('registryAlias'))
            ));
        }
        return $this->_pidFile;
    }

/**
 * Check if process with given ID is running.
 *
 * @param int $pid Process ID
 * @return bool True if running, false otherwise
 */
    protected function __processIsRunning($pid) {
        if (getmypid() === $pid) {
            return true;
        }

        $isRunning = false;
        if ($this->_isWindows()) {
            $out = [];

            exec("TASKLIST /FO LIST /FI \"PID eq $pid\"", $out);

            if (count($out) > 1) {
                $isRunning = true;
            }
        } elseif (posix_kill(intval($pid), 0)) {
            $isRunning = true;
        }

        return $isRunning;
    }

/**
 * Kill process ID.
 *
 * @param int $pid Process ID
 * @return bool True if killed, false otherwise
 */
    protected function __killProcess($pid) {
        return $this->_isWindows() ? exec("taskkill /F /PID $pid") : exec("kill -9 $pid");
    }

/**
 * Check if we're in a windows env.
 *
 * @param int $pid Process ID
 * @return bool True if killed, false otherwise
 */
    protected function _isWindows() {
        return strncasecmp(PHP_OS, 'win', 3) === 0;
    }

/**
 * Get daemon file state.
 *
 * @param string $method Method name
 * @return File Daemon file with state
 */
    protected function _getServiceName() {
        return $this->domain . $this->name;
    }

}
