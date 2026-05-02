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

namespace Nata\Console\ProcessManager;

use Nata\Core\PropertiesSetterTrait;
use Nata\I18n\Time;

/**
 * A wrapper around the various IO operations shell tasks need to do.
 *
 * Packages up the stdout, stderr, and stdin streams providing a simple
 * consistent interface for shells to use. This class also makes mocking streams
 * easy to do in unit tests.
 */
class Process {

    use PropertiesSetterTrait;

/**
 * User ID.
 *
 * @var string
 */
    protected $_uid;

/**
 * Process ID.
 *
 * @var int
 */
    protected $_pid;

/**
 * Process's parent ID.
 *
 * @var int
 */
    protected $_ppid;

/**
 * CPU (in percentage).
 *
 * @var string
 */
    protected $_cpu;

/**
 * Memory usage in percentage.
 *
 * @var string
 */
    protected $_memory;

/**
 * Process's start time.
 *
 * @var string
 */
    protected $_startTime;

/**
 * The type of terminal the process is running on.
 *
 * @var string
 */
    protected $_tty;

/**
 * Total amount of CPU usage.
 *
 * @var string
 */
    protected $_time;

/**
 * The name of the command that started the process.
 *
 * @var string
 */
    protected $_command;


/**
 * Constructor
 *
 * @param array $info Process information
 * @return void
 */
    public function __construct(array $info) {
        $this->_set($info);
    }

/**
 * Get UID.
 *
 * @return string
 */
    public function getUid(): string {
        return $this->_uid;
    }

/**
 * Get process ID.
 *
 * @return int
 */
    public function getId(): int {
        return $this->_pid;
    }

/**
 * Get process's parent ID.
 *
 * @return int
 */
    public function getParentId(): int {
        return $this->_ppid;
    }

/**
 * @alias
 */
    public function getPpid(): int {
        return $this->getParentId();
    }

/**
 * Get command that started process.
 *
 * @return string
 */
    public function getCommand(): string {
        return $this->_command;
    }

/**
 * @alias
 */
    public function getCmd(): string {
        return $this->getCommand();
    }

/**
 * Get process start time.
 *
 * @return string
 */
    public function getStartTime(): string {
        return $this->_startTime;
    }

/**
 * Get CPU time.
 *
 * @return string
 */
    public function getTime(): string {
        return $this->_time;
    }

/**
 * Get CPU time in seconds.
 *
 * @return int
 */
    public function getTimeInSeconds(): int {
        return (new Time(0))->modify($this->_time)->timestamp();
    }

/**
 * Get CPU percentage.
 *
 * @return string
 */
    public function getCpu() {
        return $this->_cpu;
    }

/**
 * Get Memory percentage.
 *
 * @return string
 */
    public function getMemory() {
        return $this->_memory;
    }

/**
 * Get type of terminal the process is running on.
 *
 * @return string
 */
    public function getTty() {
        return $this->_tty;
    }

/**
 * toArray.
 *
 * @return array
 */
    public function toArray() {
        return [
            'uid' => $this->_uid,
            'pid' => $this->_pid,
            'ppid' => $this->_ppid,
            'cmd' => $this->_command,
            'startTime' => $this->_startTime,
        ];
    }

/**
 * __toString.
 *
 * @return string
 */
    public function __toString() {
        return implode('  ', array_values($this->toArray()));
    }

}
