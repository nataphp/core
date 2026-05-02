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

use Nata\Async\Task;
use Fiber;
use Exception;

class TaskQueue {
    private array $_tasks = [];
    private array $_pendingTasks = [];

    public function addTask(Task $task): void {
        $this->_tasks[] = $task;
    }

    public function processQueue(): void {
        // Create fibers for each task
        $fibers = array_map(function (Task $task) {
            return new Fiber(function () use ($task) {
                while (!$task->completed && $task->retryCount <= $task->maxRetries) {
                    if ($task->run()) {
                        break;
                    }

                    // Optional: Add delay between retries
                    if ($task->retryCount < $task->maxRetries) {
                        Fiber::suspend(pow(2, $task->retryCount)); // Exponential backoff
                    }
                }
                return $task;
            });
        }, $this->_tasks);

        // Start all fibers
        array_walk($fibers, fn(Fiber $fiber) => $fiber->start());

        // Process fibers
        while (!empty($fibers)) {
            foreach ($fibers as $key => $fiber) {
                if (!$fiber->isTerminated()) {
                    try {
                        $sleepTime = $fiber->resume();
                        if (is_numeric($sleepTime)) {
                            // Simulate retry delay
                            usleep($sleepTime * 1_000_000);
                        }
                    } catch (Exception $e) {
                        echo "Fiber error: " . $e->getMessage() . "\n";
                    }
                }

                if ($fiber->isTerminated()) {
                    $task = $fiber->getReturn();

                    if (!$task->completed) {
                        echo "Task {$task->id} failed after {$task->maxRetries} retries. Last error: "
                             . ($task->lastError ? $task->lastError->getMessage() : 'Unknown') . "\n";
                    } else {
                        echo "Task {$task->id} completed successfully. Result: "
                             . (is_scalar($task->result) ? $task->result : 'Complex result') . "\n";
                    }

                    unset($fibers[$key]);
                }
            }
        }
    }
}
