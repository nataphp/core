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

namespace Nata\Cron;

use Nata\Cron\Output;
use Nata\Error\Handler;
use Nata\Core\Configure;
use Nata\Log\Log;
use Throwable;

/**
 * Error Handler for Cake console. Does simple printing of the
 * exception that occurred and the stack trace of the error.
 */
class ErrorHandler {

/**
 * Standard error stream.
 *
 * @var CronOutput
 */
    public static $stderr;


/**
 * Get the stderr object for the console error handling.
 *
 * @return Ou
 */
    public static function getStderr() {
        if (empty(static::$stderr)) {
            static::$stderr = new Output('php://stderr');
        }
        return static::$stderr;
    }

/**
 * Handle a exception in the console environment. Prints a message to stderr.
 *
 * @param Throwable $exception The exception to handle
 * @return void
 */
    public static function handleException(Throwable $exception) {
        $stderr = static::getStderr();

        $stderr->outputAs(Output::COLOR);

        $stderr->write(sprintf("<error>Error: </error> %s (%s (%s))\n\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));

        if (!Configure::read('debug')) {
            Log::write(LOG_ERR, sprintf(
                "%s (%s (%s))\n%s\n# %s\n",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString(),
                static::_getCommand()
            ), ['scope' => ['cron']]);
        }

        static::_stop($exception->getCode() ?? 1);
    }

/**
 * Handle errors in the console environment. Writes errors to stderr,
 * and logs messages if Configure::read('debug') is 0.
 *
 * @param integer|Throwable $code Error code
 * @param string $description Description of the error.
 * @param string $file The file the error occurred in.
 * @param integer $line The line the error occurred on.
 * @param array $context The backtrace of the error.
 * @return void
 */
    public static function handleError($code, $description = null, $file = null, $line = null, $context = null) {
        if (error_reporting() === 0) {
            return;
        }

        // Suppress the libpng warning about incorrect sRGB profile
        if ($description && str_contains($description, 'libpng warning: iCCP: known incorrect sRGB profile')) {
            return null;
        }

        if ($code instanceof Throwable) {
            return static::handleException($code);
        }

        $stderr = static::getStderr();
        [$name, $log] = Handler::mapErrorCode($code);
        $message = sprintf('%s in [%s, line %s]', $description, $file, $line);
        $stderr->write(sprintf("<error>%s Error:</error> %s\n", $name, $message));

        if (!Configure::read('debug')) {
            Log::write($log, $message, ['scope' => ['cron']]);
        }

        if ($log === LOG_ERR) {
            static::_stop(1);
        }
    }

/**
 * Get the command that was executed.
 *
 * @return string
 */
    protected static function _getCommand() {
        $argv = (array)$_SERVER['argv'];
        array_shift($argv);
        foreach ($argv as &$arg) {
            if (strpos($arg, ' ') !== false) {
                $arg = '"' . $arg . '"';
            }
        }
        return implode(' ', $argv);
    }

/**
 * Wrapper for exit(), used for testing.
 *
 * @param $code int The exit code.
 */
    protected static function _stop($code = 0) {
        exit($code);
    }

}
