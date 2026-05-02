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

use Nata\Core\ConfigAwareTrait;
use Nata\Utility\Inflector;
use RuntimeException;

/**
 * Base class for command-line utilities for automating programmer chores.
 */
abstract class Command {

    use ConfigAwareTrait;

/**
 * Default error code
 *
 * @var int
 */
    public const CODE_ERROR = 1;

/**
 * Default success code
 *
 * @var int
 */
    public const CODE_SUCCESS = 0;

/**
 * Command name.
 *
 * @var string
 */
    protected $_name;

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = '';

/**
 * Constructor.
 *
 * @param array $options Command options
 * @return void
 */
    public function __construct(array $options = []) {
        $this->config($options);
    }

/**
 * Command name.
 *
 * @return string
 */
    public function getName(): string {
        if (!$this->_name) {
            $this->_name = 'nata ' . static::defaultName();
        }
        return $this->_name;
    }

/**
 * Command description.
 *
 * @return string
 */
    public function getDescription(): string {
        return $this->_description;
    }

/**
 * Get the command name.
 *
 * Returns the command name based on class name.
 * For e.g. for a command with class name `UpdateTableCommand` the default
 * name returned would be `'update_table'`.
 *
 * @return string
 */
    public static function defaultName(): string {
        $pos = strrpos(static::class, '\\');
        $name = substr(static::class, $pos + 1);
        return Inflector::underscore($name);
    }

/**
 * Get the option parser.
 *
 * You can override buildOptionParser() to define your options & arguments.
 *
 * @return OptionParser
 * @throws RuntimeException When the parser is invalid
 */
    public function getOptionParser(): OptionParser {
        [$root, $name] = explode(' ', $this->getName(), 2);
        $parser = new OptionParser($name);
        $parser->setRootName($root);
        $parser->setDescription(static::getDescription());

        return $this->_buildOptionParser($parser);
    }

/**
 * Execute command.
 *
 * @param Arguments $args Arguments
 * @param Io $io Console Input/output
 * @return int
 */
    abstract public function execute(Arguments $args, Io $io): ?int;

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Command parameters
 * @return OptionParser
 */
    abstract protected function _buildOptionParser(OptionParser $optionParser): OptionParser;

}
