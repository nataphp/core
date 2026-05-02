<?php
/**
 * Base class for Jobs
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
 * @since         CakePHP(tm) v 1.2.0.5012
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Console;

use Nata\Core\App;
use Nata\Utility\Inflector;
use RecursiveDirectoryIterator;
use RegexIterator;

/**
 * Base class for command-line utilities for automating programmer chores.
 */
class CommandCollection {

/**
 * Commands
 *
 * @var array
 */
    protected $_commands = [];

/**
 * Commands found.
 *
 * @var array
 */
    protected $_commandsFound = [];


/**
 * Constructor.
 *
 * @param array<int, Command> $commands Commands
 * @return void
 */
    public function __construct(array $commands = []) {
        $this->_commands = $commands;
    }

/**
 * Get commands.
 *
 * @param string|null $name Name
 * @param string|null $parentCommand Parent command
 * @return Command|array<string, Command> Commands
 */
    public function get(string $name = null, ?string $parentCommand = null): Command|array {
        if ($name === null) {
            return $this->_commands;
        }

        if ($this->exists($name)) {
            return $this->_commands[$name];
        }

        return $this->_loadCommand($name, $parentCommand);
    }

/**
 * Load command.
 *
 * @param string $name Name
 * @param string|null $parentCommand Parent command
 * @return Command|null Command
 */
    protected function _loadCommand(string $name, ?string $parentCommand = null): ?Command {
        [$plugin, $command] = pluginSplit($name, true);
        $plugin = $plugin ? Inflector::camelize($plugin) : null;
        $command = Inflector::camelize($command);

        $namespace = 'Command';
        if ($parentCommand) {
            $namespace .= '\\' . Inflector::camelize($parentCommand);
        }

        $className = App::className($plugin . $command, $namespace);
        if (!$className) {
            return null;
        }
        return $this->_commands[strtolower($plugin . $command)] = new $className;
    }

/**
 * Add command.
 *
 * @param string $name Name
 * @param Command $command Command
 * @return void
 */
    public function add(string $name, Command $command): void {
        $alias = $this->_normalizeName($name);
        $this->_commands[$alias] = $command;
    }

/**
 * Add command.
 *
 * @param string $name Name
 * @return bool True if command exists
 */
    public function exists(string $name): bool {
        $name = $this->_normalizeName($name);
        return isset($this->_commands[$name]);
    }

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Command parameters
 * @return OptionParser
 */
    public function remove(string $name): void {
        $name = $this->_normalizeName($name);
        unset($this->_commands[$name]);
    }

/**
 * Auto-discover commands.
 *
 * @return $this
 */
    public function autoDiscover(): self {
        if ($this->_commandsFound) {
            return $this;
        }

        $folders = [
            App::path('Command'),
            App::core('Command'),
        ];

        foreach ($folders as $folder) {
            $this->_find($folder);
        }

        return $this;
    }

/**
 * Find commands in folder.
 *
 * @param string $folder Folder
 * @return void
 */
    protected function _find($folder) {
        $recursiveDirectory = new RecursiveDirectoryIterator($folder);
        $files = new RegexIterator($recursiveDirectory, '/\.php$/');
        foreach ($files as $file) {
            $fileName = basename($file);
            if (substr($fileName, 0, 1) === '.') {
                continue;
            }

            $className = $this->_extractClassName($file);

            $command = new $className;
            [$root, $name] = explode(' ', $command->getName(), 2);
            if ($this->exists($name)) {
                continue;
            }

            $this->_commands[$name] = $command;
        }
    }

/**
 * Extract class name from file.
 *
 * @param string $file File
 * @return string Class name
 */
    protected function _extractClassName($file) {
        $lines = file($file);

        $className = '';
        foreach ($lines as $line) {
            if (preg_match("/namespace\s(.+);/i", $line, $matches)) {
                $className .= $matches[1];
            }

            if (preg_match("/class\s(.*)\s?\{/i", $line, $matches)) {
                [$class] = explode(' ', trim($matches[1]));
                $className .= '\\' . trim($class);
                break;
            }
        }

        return $className;
    }

/**
 * Normalize name.
 *
 * @param string $name Name
 * @return string Normalized name
 */
    protected function _normalizeName(string $name): string {
        return strtolower(Inflector::camelize($name));
    }

}
