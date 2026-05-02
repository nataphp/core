<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

use Nata\Console\Exception\StopException;
use Nata\Log\Log;
use RuntimeException;
use SplFileObject;

/**
 * A wrapper around the various IO operations shell tasks need to do.
 *
 * Packages up the stdout, stderr, and stdin streams providing a simple
 * consistent interface for shells to use. This class also makes mocking streams
 * easy to do in unit tests.
 */
class Io {

/**
 * Output constant making verbose shells.
 *
 * @var int
 */
    public const VERBOSE = 2;

/**
 * Output constant for making normal shells.
 *
 * @var int
 */
    public const NORMAL = 1;

/**
 * Output constants for making quiet shells.
 *
 * @var int
 */
    public const QUIET = 0;

/**
 * The output stream
 *
 * @var \Nata\Console\Output
 */
    protected $_out;

/**
 * The error stream
 *
 * @var \Nata\Console\Output
 */
    protected $_err;

/**
 * The input stream
 *
 * @var \Nata\Console\Input
 */
    protected $_in;

/**
 * The helper registry.
 *
 * @var \Nata\Console\HelperRegistry
 */
    protected $_helpers;

/**
 * The current output level.
 *
 * @var int
 */
    protected $_level = self::NORMAL;

/**
 * The number of bytes last written to the output stream
 * used when overwriting the previous message.
 *
 * @var int
 */
    protected $_lastWritten = 0;

/**
 * Whether files should be overwritten
 *
 * @var bool
 */
    protected $_forceOverwrite = false;

/**
 * @var bool
 */
    protected $_interactive = true;

/**
 * Constructor
 *
 * @param \Nata\Console\Output|null $out A Output object for stdout.
 * @param \Nata\Console\Output|null $err A Output object for stderr.
 * @param \Nata\Console\Input|null $in A Input object for stdin.
 * @todo @param \Nata\Console\HelperRegistry|null $helpers A HelperRegistry instance
 */
    public function __construct(
        ?Output $out = null,
        ?Output $err = null,
        ?Input $in = null
        // ?HelperRegistry $helpers = null
    ) {
        $this->_out = $out ?: new Output('php://stdout');
        $this->_err = $err ?: new Output('php://stderr');
        $this->_in = $in ?: new Input('php://stdin');
        // $this->_helpers = $helpers ?: new HelperRegistry();
        // $this->_helpers->setIo($this);
    }

/**
 * @param bool $value Value
 * @return void
 */
    public function setInteractive(bool $value): void {
        $this->_interactive = $value;
    }

/**
 * Get/set the current output level.
 *
 * @param int|null $level The current output level.
 * @return int The current output level.
 */
    public function level(?int $level = null): int {
        if ($level !== null) {
            $this->_level = $level;
        }

        return $this->_level;
    }

/**
 * Output at the verbose level.
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if current level is less than ConsoleIo::VERBOSE
 */
    public function verbose($message, int $newlines = 1): ?int {
        return $this->out($message, $newlines, self::VERBOSE);
    }

/**
 * Output at all levels.
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if current level is less than ConsoleIo::QUIET
 */
    public function quiet($message, int $newlines = 1): ?int {
        return $this->out($message, $newlines, self::QUIET);
    }

/**
 * Outputs a single or multiple messages to stdout. If no parameters
 * are passed outputs just a newline.
 *
 * ### Output levels
 *
 * There are 3 built-in output level. ConsoleIo::QUIET, ConsoleIo::NORMAL, ConsoleIo::VERBOSE.
 * The verbose and quiet output levels, map to the `verbose` and `quiet` output switches
 * present in most shells. Using ConsoleIo::QUIET for a message means it will always display.
 * While using ConsoleIo::VERBOSE means it will only display when verbose output is toggled.
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @param int $level The message's output level, see above.
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if provided $level is greater than current level.
 */
    public function out($message = '', int $newlines = 1, int $level = self::NORMAL): ?int {
        if ($level <= $this->_level) {
            $this->_lastWritten = $this->_out->write($message, $newlines);

            return $this->_lastWritten;
        }

        return null;
    }

/**
 * Convenience method for out() that wraps message between <info /> tag
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @param int $level The message's output level, see above.
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if provided $level is greater than current level.
 * @see https://book.cakephp.org/4/en/console-and-shells.html#ConsoleIo::out
 */
    public function info($message, int $newlines = 1, int $level = self::NORMAL): ?int {
        $messageType = 'info';
        $message = $this->wrapMessageWithType($messageType, $message);

        return $this->out($message, $newlines, $level);
    }

/**
 * Convenience method for out() that wraps message between <comment /> tag
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @param int $level The message's output level, see above.
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if provided $level is greater than current level.
 * @see https://book.cakephp.org/4/en/console-and-shells.html#ConsoleIo::out
 */
    public function comment($message, int $newlines = 1, int $level = self::NORMAL): ?int {
        $messageType = 'comment';
        $message = $this->wrapMessageWithType($messageType, $message);

        return $this->out($message, $newlines, $level);
    }

/**
 * Convenience method for err() that wraps message between <warning /> tag
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @return int The number of bytes returned from writing to stderr.
 * @see https://book.cakephp.org/4/en/console-and-shells.html#ConsoleIo::err
 */
    public function warning($message, int $newlines = 1): int {
        $messageType = 'warning';
        $message = $this->wrapMessageWithType($messageType, $message);

        return $this->err($message, $newlines);
    }

/**
 * Convenience method for err() that wraps message between <error /> tag
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @return int The number of bytes returned from writing to stderr.
 * @see https://book.cakephp.org/4/en/console-and-shells.html#ConsoleIo::err
 */
    public function error($message, int $newlines = 1): int {
        $messageType = 'error';
        $message = $this->wrapMessageWithType($messageType, $message);

        return $this->err($message, $newlines);
    }

/**
 * Convenience method for out() that wraps message between <success /> tag
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @param int $level The message's output level, see above.
 * @return int|null The number of bytes returned from writing to stdout
 *   or null if provided $level is greater than current level.
 * @see https://book.cakephp.org/4/en/console-and-shells.html#ConsoleIo::out
 */
    public function success($message, int $newlines = 1, int $level = self::NORMAL): ?int {
        $messageType = 'success';
        $message = $this->wrapMessageWithType($messageType, $message);

        return $this->out($message, $newlines, $level);
    }

/**
 * Halts the the current process with a StopException.
 *
 * @param string $message Error message.
 * @param int $code Error code.
 * @return void
 * @throws \Nata\Console\Exception\StopException
 */
    public function abort($message, $code = Command::CODE_ERROR): void {
        $this->error($message);

        throw new StopException($message, $code);
    }

/**
 * Wraps a message with a given message type, e.g. <warning>
 *
 * @param string $messageType The message type, e.g. "warning".
 * @param array<string>|string $message The message to wrap.
 * @return array<string>|string The message wrapped with the given message type.
 */
    protected function wrapMessageWithType(string $messageType, $message) {
        if (is_array($message)) {
            foreach ($message as $k => $v) {
                $message[$k] = "<{$messageType}>{$v}</{$messageType}>";
            }
        } else {
            $message = "<{$messageType}>{$message}</{$messageType}>";
        }

        return $message;
    }

/**
 * Overwrite some already output text.
 *
 * Useful for building progress bars, or when you want to replace
 * text already output to the screen with new text.
 *
 * **Warning** You cannot overwrite text that contains newlines.
 *
 * @param array<string>|string $message The message to output.
 * @param int $newlines Number of newlines to append.
 * @param int|null $size The number of bytes to overwrite. Defaults to the
 *    length of the last message output.
 * @return void
 */
    public function overwrite($message, int $newlines = 1, ?int $size = null): void {
        $size = $size ?: $this->_lastWritten;

        // Output backspaces.
        $this->out(str_repeat("\x08", $size), 0);

        $newBytes = (int)$this->out($message, 0);

        // Fill any remaining bytes with spaces.
        $fill = $size - $newBytes;
        if ($fill > 0) {
            $this->out(str_repeat(' ', $fill), 0);
        }
        if ($newlines) {
            $this->out($this->nl($newlines), 0);
        }

        // Store length of content + fill so if the new content
        // is shorter than the old content the next overwrite
        // will work.
        if ($fill > 0) {
            $this->_lastWritten = $newBytes + $fill;
        }
    }

/**
 * Outputs a single or multiple error messages to stderr. If no parameters
 * are passed outputs just a newline.
 *
 * @param array<string>|string $message A string or an array of strings to output
 * @param int $newlines Number of newlines to append
 * @return int The number of bytes returned from writing to stderr.
 */
    public function err($message = '', int $newlines = 1): int {
        return $this->_err->write($message, $newlines);
    }

/**
 * Returns a single or multiple linefeeds sequences.
 *
 * @param int $multiplier Number of times the linefeed sequence should be repeated
 * @return string
 */
    public function nl(int $multiplier = 1): string {
        return str_repeat(Output::LF, $multiplier);
    }

    /**
     * Outputs a series of minus characters to the standard output, acts as a visual separator.
     *
     * @param int $newlines Number of newlines to pre- and append
     * @param int $width Width of the line, defaults to 79
     * @return void
     */
    public function hr(int $newlines = 0, int $width = 79): void {
        $this->out('', $newlines);
        $this->out(str_repeat('-', $width));
        $this->out('', $newlines);
    }

/**
 * Prompts the user for input, and returns it.
 *
 * @param string $prompt Prompt text.
 * @param string|null $default Default input value.
 * @return string Either the default value, or the user-provided input.
 */
    public function ask(string $prompt, ?string $default = null): string {
        return $this->_getInput($prompt, null, $default);
    }

/**
 * Change the output mode of the stdout stream
 *
 * @param int $mode The output mode.
 * @return void
 * @see \Nata\Console\Output::setOutputAs()
 */
    public function setOutputAs(int $mode): void {
        $this->_out->outputAs($mode);
    }

/**
 * Gets defined styles.
 *
 * @return array
 * @see \Nata\Console\Output::styles()
 */
    public function styles(): array {
        return $this->_out->styles();
    }

/**
 * Get defined style.
 *
 * @param string $style The style to get.
 * @return array
 * @see \Nata\Console\Output::getStyle()
 */
    public function getStyle(string $style): array {
        return $this->_out->getStyle($style);
    }

/**
 * Adds a new output style.
 *
 * @param string $style The style to set.
 * @param array $definition The array definition of the style to change or create.
 * @return void
 * @see \Nata\Console\Output::setStyle()
 */
    public function setStyle(string $style, array $definition): void {
        $this->_out->setStyle($style, $definition);
    }

/**
 * Prompts the user for input based on a list of options, and returns it.
 *
 * @param string $prompt Prompt text.
 * @param array<string>|string $options Array or string of options.
 * @param string|null $default Default input value.
 * @return string Either the default value, or the user-provided input.
 */
    public function askChoice(string $prompt, $options, ?string $default = null): string {
        if (is_string($options)) {
            if (strpos($options, ',')) {
                $options = explode(',', $options);
            } elseif (strpos($options, '/')) {
                $options = explode('/', $options);
            } else {
                $options = [$options];
            }
        }

        $printOptions = '(' . implode('/', $options) . ')';
        $options = array_merge(
            array_map('strtolower', $options),
            array_map('strtoupper', $options),
            $options
        );
        $in = '';
        while ($in === '' || !in_array($in, $options, true)) {
            $in = $this->_getInput($prompt, $printOptions, $default);
        }

        return $in;
    }

/**
 * Prompts the user for input, and returns it.
 *
 * @param string $prompt Prompt text.
 * @param string|null $options String of options. Pass null to omit.
 * @param string|null $default Default input value. Pass null to omit.
 * @return string Either the default value, or the user-provided input.
 */
    protected function _getInput(string $prompt, ?string $options, ?string $default): string {
        if (!$this->_interactive) {
            return (string)$default;
        }

        $optionsText = '';
        if (isset($options)) {
            $optionsText = " $options ";
        }

        $defaultText = '';
        if ($default !== null) {
            $defaultText = "[$default] ";
        }
        $this->_out->write('<question>' . $prompt . "</question>$optionsText\n$defaultText> ", 0);
        $result = $this->_in->read();

        $result = $result === null ? '' : trim($result);
        if ($default !== null && $result === '') {
            return $default;
        }

        return $result;
    }

/**
 * Connects or disconnects the loggers to the console output.
 *
 * Used to enable or disable logging stream output to stdout and stderr
 * If you don't wish all log output in stdout or stderr
 * through Cake's Log class, call this function with `$enable=false`.
 *
 * @param int|bool $enable Use a boolean to enable/toggle all logging. Use
 *   one of the verbosity constants (self::VERBOSE, self::QUIET, self::NORMAL)
 *   to control logging levels. VERBOSE enables debug logs, NORMAL does not include debug logs,
 *   QUIET disables notice, info and debug logs.
 * @return void
 */
    public function setLoggers($enable): void {
        Log::drop('stdout');
        Log::drop('stderr');
        if ($enable === false) {
            return;
        }
        $outLevels = ['notice', 'info'];
        if ($enable === static::VERBOSE || $enable === true) {
            $outLevels[] = 'debug';
        }
        if ($enable !== static::QUIET) {
            Log::config('stdout', [
                'engine' => 'ConsoleLog',
                'levels' => $outLevels,
                'stream' => $this->_out,
            ]);
        }
        Log::config('stderr', [
            'engine' => 'ConsoleLog',
            'levels' => ['emergency', 'alert', 'critical', 'error', 'warning'],
            'stream' => $this->_err,
        ]);
    }

/**
 * Render a Console Helper
 *
 * Create and render the output for a helper object. If the helper
 * object has not already been loaded, it will be loaded and constructed.
 *
 * @param string $name The name of the helper to render
 * @param array<string, mixed> $config Configuration data for the helper.
 * @return \Nata\Console\Helper The created helper instance.
 */
    public function helper(string $name, array $config = []): Helper {
        $name = ucfirst($name);
        return $this->_helpers->load($name, $config);
    }

/**
 * Create a file at the given path.
 *
 * This method will prompt the user if a file will be overwritten.
 * Setting `forceOverwrite` to true will suppress this behavior
 * and always overwrite the file.
 *
 * If the user replies `a` subsequent `forceOverwrite` parameters will
 * be coerced to true and all files will be overwritten.
 *
 * @param string $path The path to create the file at.
 * @param string $contents The contents to put into the file.
 * @param bool $forceOverwrite Whether the file should be overwritten.
 *   If true, no question will be asked about whether to overwrite existing files.
 * @return bool Success.
 * @throws \Nata\Console\Exception\StopException When `q` is given as an answer
 *   to whether a file should be overwritten.
 */
    public function createFile(string $path, string $contents, bool $forceOverwrite = false): bool {
        $this->out();
        $forceOverwrite = $forceOverwrite || $this->_forceOverwrite;

        if (file_exists($path) && $forceOverwrite === false) {
            $this->warning("File `{$path}` exists");
            $key = $this->askChoice('Do you want to overwrite?', ['y', 'n', 'a', 'q'], 'n');
            $key = strtolower($key);

            if ($key === 'q') {
                $this->error('Quitting.', 2);
                throw new StopException('Not creating file. Quitting.');
            }
            if ($key === 'a') {
                $this->_forceOverwrite = true;
                $key = 'y';
            }
            if ($key !== 'y') {
                $this->out("Skip `{$path}`", 2);

                return false;
            }
        } else {
            $this->out("Creating file {$path}");
        }

        try {
            // Create the directory using the current user permissions.
            $directory = dirname($path);
            if (!file_exists($directory)) {
                mkdir($directory, 0777 ^ umask(), true);
            }

            $file = new SplFileObject($path, 'w');
        } catch (RuntimeException $e) {
            $this->error("Could not write to `{$path}`. Permission denied.", 2);

            return false;
        }

        $file->rewind();
        $file->fwrite($contents);
        if (file_exists($path)) {
            $this->out("<success>Wrote</success> `{$path}`");

            return true;
        }
        $this->error("Could not write to `{$path}`.", 2);

        return false;
    }

}
