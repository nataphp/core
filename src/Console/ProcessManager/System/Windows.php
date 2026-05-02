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

namespace Nata\Console\ProcessManager\System;

use DateTime;
use Nata\Collection\Collection;
use Nata\Console\ProcessManager\Process;
use Nata\Console\ProcessManager\System;
use Nata\I18n\Time;

class Windows extends System {

/**
 * Get process by it's ID.
 *
 * @param int $pid Process ID
 * @return Process
 */
    public function get(int $pid): ?Process {
        return $this->find($pid)->first();
    }

/**
 * List processes.
 *
 * @return Collection
 */
    public function list(): Collection {
        return $this->_getProcesses();
    }

/**
 * Find processes by given PID, PPID or command.
 *
 * @param string|array $keyword(s)
 * @return Collection
 */
    public function find(string|array $keywords): Collection {
        $runningProcesses = $this->_getProcesses();
        $found = [];
        foreach ((array)$keywords as $keyword) {
            foreach ($runningProcesses as $process) {
                if (is_numeric($keyword) && ($process->getId() != $keyword && $process->getPpid() != $keyword)) {
                    continue;
                } elseif (!str_contains($process->getCommand(), $keyword)) {
                    continue;
                }

                $found[] = $process;
            }
        }

        return new Collection($found);
    }

/**
 * Check if process ir running by providing PID or Process instance.
 *
 * @param int|Process
 * @return bool
 */
    public function isRunning($process): bool {
        if ($process instanceof Process) {
            $process = $process->getId();
        }

        if (!($process > 0)) {
            return false;
        }

        return $this->get($process) instanceof Process;
    }

/**
 * List processes.
 *
 * @return array
 */
    public function getId(): ?int {
        return null;
    }

/**
 * List processes.
 *
 * @return array
 */
    public function kill($process): bool {
        return false;
    }

/**
 * List processes.
 *
 * @return Collection
 */
    protected function _getProcesses(): Collection {
        exec('wmic process get commandline,creationdate,processid,parentprocessid,workingsetsize /format:csv', $output);

        $processes = [];
        foreach ($output as $line) {
            $line = trim($line);
            // Skip the header lines
            if (empty($line) || str_contains($line, '===') || str_contains($line, 'ParentProcessId')) {
                continue;
            }

            // Extract the process information
            [$_node, $_cmd, $_creationDate, $_pid, $_parentId, $_memory] = explode(',', $line);

            $columns = array_reverse(explode(',', $line));
            $memory = array_shift($columns);
            $parentId = array_shift($columns);
            $pid = array_shift($columns);
            $creationDate = array_shift($columns);
            $node = array_pop($columns);
            $cmd = implode(',', array_reverse($columns));

            $processes[] = new Process([
                'command' => $cmd,
                'pid' => $pid,
                'ppid' => $parentId,
                'memory' => $memory,
                'startTime' => $this->_parseStartTime($creationDate)
            ]);

        }

        return new Collection($processes);
    }

/**
 * Parse the start time of a process.
 *
 * @param string $startTime
 * @return Time
 */
    protected function _parseStartTime(string $startTime): ?Time {
        if (strpos($startTime, '.') === false) {
            return null;
        }

        // Extract date components
        $year = substr($startTime, 0, 4);
        $month = substr($startTime, 4, 2);
        $day = substr($startTime, 6, 2);
        $hour = substr($startTime, 8, 2);
        $minute = substr($startTime, 10, 2);
        $second = substr($startTime, 12, 2);
        $microsecond = substr($startTime, 15, 6);
        $offset = substr($startTime, 21);

        // Construct a DateTime object
        $date = DateTime::createFromFormat('YmdHis.uO', $year . $month . $day . $hour . $minute . $second . '.' . $microsecond . $offset);
        return new Time($date->format('Y-m-d H:i:s.uO'));
    }

}