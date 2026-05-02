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

use Nata\Collection\Collection;
use Nata\Console\ProcessManager\Process;
use Nata\Console\ProcessManager\System;

class Linux extends System {


/**
 * Get process by it's ID.
 *
 * @param int $pid Process ID
 * @return Process
 */
    public function get(int $pid): ?Process {
        $cmd = $this->_getPsCmd();
        $cmd .= ' -p ' . escapeshellarg($pid);
        if ($this->_user) {
            $cmd .= ' -u ' . escapeshellarg($this->_user);
        }
        return $this->_exec($cmd)->first();
    }

/**
 * Get list of currently executing processes.
 *
 * @return Collection
 */
    public function list(): Collection {
        $cmd = $this->_getPsCmd();
        return $this->_exec($cmd);
    }

/**
 * Find processes by given PID, PPID or command.
 *
 * @param string|array $keyword(s)
 * @return Collection
 */
    public function find(string|array $keyword): Collection {
        $cmd = $this->_getPsCmd();
        foreach ((array)$keyword as $key) {
            $cmd .= ' | grep -i ' . escapeshellarg($key);
        }
        $cmd .= ' | grep -v grep';
        return $this->_exec($cmd);
    }

/**
 * Check if process ir running by providing PID or Process instance.
 *
 * @param int|Process
 * @return bool
 */
    public function isRunning(int|Process $process): bool {
        if ($process instanceof Process) {
            $process = $process->getId();
        }

        if (!($process > 0)) {
            return false;
        }

        return $this->get($process) instanceof Process;
    }

/**
 * Kill given process instance or PID.
 *
 * @param int|Process $process Process
 * @return bool
 */
    public function kill(int|Process $process): bool {
        if ($process instanceof Process) {
            $process = $process->getId();
        }

        if (!($process > 0)) {
            return false;
        }

        return exec(sprintf('kill %d', (int)$process)) !== false;
    }

/**
 * Execute ps command.
 *
 * @param string $cmd ps cmd
 * @return Collection
 */
    protected function _exec($cmd): Collection {
        exec($cmd, $output);

        $processes = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (str_contains($line, 'UID')) {
                continue;
            }

            // Extract the process information
            $parts = [$uid, $pid, $ppid, $stime, $tty, $cpu, $mem, $time] = preg_split('/\s+/', $line);
            $pcmd = $this->_extractCmd($parts);

            $processes[] = new Process([
                'command' => $pcmd,
                'uid' => $uid,
                'time' => $time,
                'tty' => $tty,
                'pid' => $pid,
                'ppid' => $ppid,
                'startTime' => $stime,
                'cpu' => $cpu,
                'memory' => $mem
            ]);

        }

        return new Collection($processes);
    }

/**
 * Get ps command.
 *
 * @return string
 */
    protected function _getPsCmd(): string {
        $cmd = 'ps -o uid,pid,ppid,stime,tty,%cpu,%mem,time,cmd';
        if ($this->_user) {
            $cmd .= ' -u ' . escapeshellarg($this->_user);
        } else {
            $cmd .= ' -e';
        }
        return $cmd;
    }

/**
 * Extract process command from the given parts.
 *
 * @param array $parts
 * @return string
 */
    protected function _extractCmd($parts): string {
        krsort($parts);

        $cmd = '';
        foreach ($parts as $part) {
            if (preg_match("/[0-9]{2}:[0-9]{2}:[0-9]{2}/", $part)) {
                break;
            }
            $cmd = $part . ' ' . $cmd;
        }
        return trim($cmd);
    }


}