<?php
/**
 * NataPHP Framework
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

namespace Nata\Async;

use Exception;
use Closure;
use Throwable;

class Task {

/**
 * Unique identifier for the task.
 *
 * @var string
 */
    public string $id;

/**
 * The callable function to be executed by this task.
 *
 * @var Closure
 */
    private Closure $_callable;

/**
 * Maximum number of retry attempts allowed.
 *
 * @var int
 */
    public int $maxRetries;

/**
 * Current number of execution attempts
 *
 * @var int
 */
    private int $_attempts = 0;

/**
 * Delay in seconds between retry attempts
 *
 * @var int
 */
    public int $retryDelay;

/**
 * Number of times the task has been retried
 *
 * @var int
 */
    public int $retryCount = 0;

/**
 * Result of the task execution
 *
 * @var mixed
 */
    public mixed $result = null;

/**
 * Whether the task has completed successfully.
 *
 * @var bool
 */
    public bool $completed = false;

/**
 * Last error encountered during execution.
 *
 * @var Exception|null
 */
    public ?Exception $lastError = null;

/**
 * Priority level of the task.
 *
 * @var int
 */
    protected int $_priority = 0;

/**
 * Constructor.
 *
 * @param callable $callable
 * @param int $maxRetries
 */
    public function __construct(callable $callable, int $maxRetries = 3) {
        $this->id = uniqid();
        $this->_callable = $callable;
        $this->maxRetries = $maxRetries;
    }

/**
 * Get/set priority.
 *
 * @param int|null $priority
 * @return int
 */
    public function priority(int $priority = null): self|int {
        if ($priority === null) {
            return $this->_priority;
        }

        $this->_priority = $priority;
        return $this;
    }

/**
 * Run the task.
 *
 * @return bool
 */
    public function run(): bool {
        try {
            $this->_attempts++;
            $this->result = ($this->_callable)($this->retryCount, $this->_attempts);
            $this->completed = true;
            return true;
        } catch (Throwable $e) {
            $this->lastError = $e;
            $this->retryCount++;
            return false;
        }
    }

}
