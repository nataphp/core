<?php
/**
 * ErrorHandler for Console Shells
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
 * @since         CakePHP(tm) v 2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Console;

use Nata\Console\Output;
use Nata\Error\Handler;
use Nata\Core\Configure;
use Nata\Log\Log;

/**
 * Error Handler for Cake console. Does simple printing of the
 * exception that occurred and the stack trace of the error.
 */
class ErrorHandler {


/**
 * Handle a exception in the console environment. Prints a message to stderr.
 *
 * @param Exception $exception The exception to handle
 * @return void
 */
    public function handleException($exception) {
        $io = new Io();
        $message = $exception->getMessage();
        if (method_exists($exception, 'getFullMessage')) {
            $message = $exception->getFullMessage();
        }
        $io->error($message);

        if (!method_exists($exception, 'getFullMessage')) {
            $io->comment($exception->getTraceAsString());
        }

    }

/**
 * Handle errors in the console environment. Writes errors to stderr,
 * and logs messages if Configure::read('debug') is 0.
 *
 * @param integer $code Error code
 * @param string $description Description of the error.
 * @param string $file The file the error occurred in.
 * @param integer $line The line the error occurred on.
 * @param array $context The backtrace of the error.
 * @return void
 */
    public function handleError($code, $description, $file = null, $line = null, $context = null) {
        if (error_reporting() === 0) {
            return;
        }

        [$name, $log] = Handler::mapErrorCode($code);
        $message = sprintf('%s in [%s, line %s]', $description, $file, $line);

        if (!Configure::read('debug')) {
            Log::write($log, $message);
        }

        $io = new Io();
        $io->error(sprintf("<error>%s Error:</error> %s\n", $name, $message));
    }

}
