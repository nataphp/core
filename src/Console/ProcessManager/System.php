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

use Nata\Collection\Collection;

abstract class System {

/**
 * User.
 *
 * @var string
 */
    protected $_user;

/**
 * Constructor.
 *
 * @param string $user
 * @return void
 */
    public function __construct($user) {
        $this->_user = $user;
    }

/**
 * Get process by it's ID.
 *
 * @param int $pid Process ID
 * @return Process
 */
    abstract public function get(int $pid): ?Process;

/**
 * Get list of currently executing processes.
 *
 * @return Collection
 */
    abstract public function list(): Collection;

/**
 * Find processes by given PID, PPID or command.
 *
 * @param string|array $keyword(s)
 * @return Collection
 */
    abstract public function find(string|array $keyword): Collection;

/**
 * Check if process ir running by providing PID or Process instance.
 *
 * @param int|Process
 * @return bool
 */
    abstract public function isRunning(int|Process $process): bool;

/**
 * Kill given process instance or PID.
 *
 * @param int|Process $process Process
 * @return bool
 */
    abstract public function kill(int|Process $process): bool;

}