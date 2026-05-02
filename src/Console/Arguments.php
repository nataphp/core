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

/**
 * Provides an interface for interacting with
 * a command's options and arguments.
 */
class Arguments {

/**
 * Positional argument name map.
 *
 * @var array<int, string>
 */
    protected $_argNames;

/**
 * Positional arguments.
 *
 * @var array<int, string>
 */
    protected $_args;

/**
 * Named options.
 *
 * @var array<string, string>
 */
    protected $_options;

/**
 * Constructor.
 *
 * @param array<int, string> $args Positional arguments
 * @param array<string, string|int|bool|null> $options Named arguments
 * @param array<int, string> $argNames List of argument names. Order is expected to be
 *  the same as $args.
 */
    public function __construct(array $args, array $options, array $argNames) {
        $this->_args = $args;
        $this->_options = $options;
        $this->_argNames = $argNames;
    }

/**
 * Get all positional arguments.
 *
 * @return array<int, string>
 */
    public function getArguments(): array {
        return $this->_args;
    }

/**
 * Get positional arguments by index.
 *
 * @param int $index The argument index to access.
 * @return string|null The argument value or null
 */
    public function getArgumentAt(int $index): ?string {
        if ($this->hasArgumentAt($index)) {
            return $this->_args[$index];
        }
        return null;
    }

/**
 * Check if a positional argument exists
 *
 * @param int $index The argument index to check.
 * @return bool
 */
    public function hasArgumentAt(int $index): bool {
        return isset($this->_args[$index]);
    }

/**
 * Check if a positional argument exists by name
 *
 * @param string $name The argument name to check.
 * @return bool
 */
    public function hasArgument(string $name): bool {
        $offset = array_search($name, $this->_argNames, true);
        if ($offset === false) {
            return false;
        }

        return isset($this->_args[$offset]);
    }

/**
 * Check if a positional argument exists by name
 *
 * @param string $name The argument name to check.
 * @return string|null
 */
    public function getArgument(string $name): ?string {
        $offset = array_search($name, $this->_argNames, true);
        if ($offset === false || !isset($this->_args[$offset])) {
            return null;
        }

        return $this->_args[$offset];
    }

/**
 * Get an array of all the options
 *
 * @return array
 */
    public function getOptions(): array {
        return $this->_options;
    }

/**
 * Get an option's value or null
 *
 * @param string $name The name of the option to check.
 * @return string|int|bool|null The option value or null.
 */
    public function getOption(string $name) {
        return $this->_options[$name] ?? null;
    }

/**
 * Check if an option is defined and not null.
 *
 * @param string $name The name of the option to check.
 * @return bool
 */
    public function hasOption(string $name): bool {
        return isset($this->_options[$name]);
    }

}
