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

namespace Nata\Command\Plugin;

use Nata\Console\Arguments;
use Nata\Console\Command;
use Nata\Console\Io;
use Nata\Console\OptionParser;
use Nata\Core\App;
use Nata\Core\Plugin;
use Nata\Utility\Inflector;

/**
 * Plugin list subcommand: list available and installed plugins.
 */
class ListPlugins extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'List available and installed plugins.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('List available plugins and their install status.');

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
        $plugins = Plugin::list();
        $pluginPath = ROOT . 'public' . DS . 'plugin' . DS;

        $io->info('Available plugins:', 2);

        foreach ($plugins as $plugin) {
            $disabled = strpos($plugin, '.disabled') !== false;
            $name = $disabled ? preg_replace('/\.disabled$/', '', $plugin) : $plugin;
            $publicDir = $pluginPath . Inflector::dasherize($name);
            $installed = is_dir($publicDir);
            $line = '  ' . $name;
            if ($disabled) {
                $line .= ' <comment>(disabled)</comment>';
            }
            if ($installed) {
                $line .= ' <success>[installed]</success>';
            }
            $io->out($line);
        }

        $io->out('');
        $io->hr(1);
        $io->comment('Use "nata plugin install <name>" to install a plugin.');

        return Command::CODE_SUCCESS;
    }

}
