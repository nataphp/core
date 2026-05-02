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

namespace Nata\Console;

use Exception;
use Nata\Collection\Collection;
use Nata\Console\ProcessManager\Process;
use Nata\Core\App;

/**
 * A wrapper around the various IO operations shell tasks need to do.
 *
 * Packages up the stdout, stderr, and stdin streams providing a simple
 * consistent interface for shells to use. This class also makes mocking streams
 * easy to do in unit tests.
 */
class ProcessManager {

/**
 * User.
 *
 * @var string
 */
    protected $_user;


/**
 * Constructor
 *
 * @param string $user OS User
 * @return void
 */
    public function __construct(string $user = null) {
        $this->_user = $user;
    }

/**
 * Get/Set user.
 *
 * @param string $user User
 * @return $this|string
 */
    public function user(string $user = null) {
        if (func_num_args() === 0) {
            return $this->_user;
        }

        $this->_user = $user;

        return $this;
    }

/**
 * Get process by it's ID.
 *
 * @param int $pid Process ID
 * @return Process
 */
    public function get(int $pid): Process {
        $system = $this->_loadSystem();
        return $system->get($pid);
    }

/**
 * Get list of currently executing processes.
 *
 * @return Collection
 */
    public function list(): Collection {
        $system = $this->_loadSystem();
        return new Collection($system->list());
    }

/**
 * Find processes by given PID, PPID or command.
 *
 * @param string|array $keyword(s)
 * @return Collection
 */
    public function find($keyword): Collection {
        $system = $this->_loadSystem();
        return new Collection($system->find($keyword));
    }

/**
 * Get process ID by giving the command.
 *
 * @param string $command Command to get PID of
 * @return int
 */
    public function getId(string $command): ?int {
        $system = $this->_loadSystem();
        $process = $system->find($command)->first();
        if (!$process) {
            return null;
        }
        return $process->getId();
    }

/**
 * Check if process ir running by providing PID or Process instance.
 *
 * @param int|Process
 * @return bool
 */
    public function isRunning($process): bool {
        $system = $this->_loadSystem();
        return $system->find($process);
    }

/**
 * Kill given process instance or PID.
 *
 * @param int|Process $process Process
 * @return bool
 */
    public function kill($process): bool {
        $system = $this->_loadSystem();
        return $system->kill($process);
    }

/**
 * Load system adapter class.
 *
 * @return System
 */
    protected function _loadSystem() {
        $sysName = $this->_getOsName();
        $className = App::className($sysName, 'Console/ProcessManager/System');
        if (!$className) {
            throw new Exception(sprintf("System '%s' it's not supported.", $sysName));
        }
        return new $className($this->_user);
    }

/**
 * Get OS name.
 *
 * @return string
 */
    protected function _getOsName() {
        switch (PHP_OS) {
            case 'WINNT':
                $env = 'Windows';
                break;
            default:
                $env = PHP_OS;
        }
        return $env;
    }

}
