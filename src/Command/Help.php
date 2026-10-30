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

namespace Nata\Command;

use Nata\Console\Arguments;
use Nata\Console\Command;
use Nata\Console\CommandCollection;
use Nata\Console\Io;
use Nata\Console\OptionParser;
use Nata\Utility\Text;

/**
 * Help command for NataPHP.
 */
class Help extends Command {

/**
 * Default terminal width, when it cannot be detected.
 *
 * @var int
 */
    public const DEFAULT_WIDTH = 120;

/**
 * Narrowest width used to wrap descriptions.
 *
 * @var int
 */
    public const MIN_WIDTH = 60;

/**
 * Commands registered by the application (with their aliases).
 *
 * @var CommandCollection|null
 */
    protected $_commands;

/**
 * Set the commands registered by the application, so the help lists them
 * under their registered names (e.g. "calendar:generate").
 *
 * @param CommandCollection $commands Commands
 * @return void
 */
    public function setCommands(CommandCollection $commands): void {
        $this->_commands = $commands;
    }

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser($optionParser): OptionParser {
        $optionParser->addArgument('command');
        return $optionParser;
    }

/**
 * Execute command.
 *
 * @param Arguments $args Arguments
 * @param Io $io Console Input/output
 * @return int|null Exit code
 */
    public function execute(Arguments $args, Io $io): ?int {
        $out = [];
        $out[] = '';
        $out[] = '<warning>Usage:</warning>';
        $out[] = '  nata <success>command</success> [options] [args...]';
        $out[] = '';

        $collection = $this->_commands ?? new CommandCollection();
        $commands = $collection->autoDiscover()->get();
        ksort($commands);

        if (!empty($commands)) {
            $out[] = '<warning>Available commands:</warning>';
            $nameWidth = $this->_getMaxLength(array_keys($commands)) + 4;
            $descriptionWidth = max($this->_terminalWidth() - $nameWidth - 1, self::MIN_WIDTH);
            $indent = str_repeat(' ', $nameWidth);

            foreach ($commands as $name => $command) {
                $line = '  <success>' . $name . '</success>';
                $description = trim($command->getDescription());
                if ($description !== '') {
                    $line .= str_repeat(' ', $nameWidth - strlen($name) - 2);
                    $line .= Text::wrap($description, [
                        'width' => $descriptionWidth,
                        'indent' => $indent,
                        'indentAt' => 1,
                    ]);
                }
                $out[] = $line;
            }
            $out[] = '';
            $out[] = 'Run <info>nata command --help</info> for the options of a command.';
            $out[] = '';
        }

        $io->out($out);

        return Command::CODE_SUCCESS;
    }

/**
 * Find the longest name.
 *
 * @param array<string> $names Names
 * @return int Max length
 */
    protected function _getMaxLength(array $names): int {
        $max = 0;
        foreach ($names as $name) {
            $max = max($max, strlen($name));
        }
        return $max;
    }

/**
 * Detect the terminal width (COLUMNS, "mode con" on Windows, "stty size"
 * elsewhere), falling back to DEFAULT_WIDTH.
 *
 * @return int Width in columns
 */
    protected function _terminalWidth(): int {
        $columns = (int)getenv('COLUMNS');
        if ($columns > 0) {
            return $columns;
        }

        $output = [];
        if (DIRECTORY_SEPARATOR === '\\') {
            // The second value of "mode con" is the number of columns (labels are localized).
            @exec('mode con', $output);
            preg_match_all('/:\s*(\d+)/', implode("\n", $output), $matches);
            $columns = (int)($matches[1][1] ?? 0);
        } else {
            @exec('stty size 2>/dev/null', $output);
            $parts = explode(' ', trim($output[0] ?? ''));
            $columns = (int)($parts[1] ?? 0);
        }

        return $columns > 0 ? $columns : self::DEFAULT_WIDTH;
    }

}
