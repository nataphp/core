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
        $out[] = '<info>Usage:</info>';
        $out[] = sprintf('%s [options] command [args...]', 'nata');
        $out[] = '';

        $commands = (new CommandCollection())->autoDiscover()->get();
        if (!empty($commands)) {
            $out[] = '<info>Available commands:</info>';
            $out[] = '';
            $max = $this->_getMaxLength($commands) + 2;
            foreach ($commands as $name => $command) {
                $cmdline = Text::wrapBlock($name, [
                    'width' => 72,
                    'indent' => str_repeat(' ', $max),
                    'indentAt' => 1,
                ]);

                if ($desc = $command->getDescription()) {
                    $cmdline .= str_pad('', strlen($name), ' ');
                    $cmdline .= $desc;
                }

                $out[] = $cmdline;
            }
            $out[] = '';
        }

        $io->out($out);

        return Command::CODE_SUCCESS;
    }

/**
 * Iterate over a collection and find the longest named thing.
 *
 * @param array<Command> $collection Collection
 * @return int Max length
 */
    protected function _getMaxLength(array $collection): int {
        $max = 0;
        foreach ($collection as $item) {
            $max = strlen($item->getName()) > $max ? strlen($item->getName()) : $max;
        }
        return $max;
    }

}
