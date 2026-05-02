<?php
/**
 * NataPHP Framework
 *
 * @author      Sergio Dinis Lopes <sergiodlopes at gmail dot com>
 * @copyright   Copyright (c) Sérgio Dinis Lopes
 * @file        Application's Cron Job class template
 */

namespace Nata\Cron;

use Nata\Console\ProcessManager\Process;

class Error extends Job {

/**
 * Cron job already running error.
 *
 * @param Process $process
 * @return void
 */
    public function alreadyRunning($processId) {
        $this->warn(sprintf('Cron job already running with PID %s', $processId));
    }

}
