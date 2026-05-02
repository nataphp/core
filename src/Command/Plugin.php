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

namespace Nata\Command;

use Nata\Command\Plugin\Install;
use Nata\Command\Plugin\Uninstall;
use Nata\Command\Plugin\ListPlugins;
use Nata\Command\Plugin\Assets;
use Nata\Command\Plugin\Schema;
use Nata\Command\Plugin\Status;
use Nata\Console\Arguments;
use Nata\Console\Command;
use Nata\Console\Io;
use Nata\Console\OptionParser;
use Nata\Core\Plugin as CorePlugin;

/**
 * Plugin management command: install, uninstall, list, assets, schema, status.
 */
class Plugin extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Manage plugins: install, uninstall, list, assets, schema, status.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Manage plugins: install, uninstall, copy assets, run schema, and more.')
            ->addSubcommand('install', [
                'help' => 'Install a plugin: copy public files and run schema.',
                'parser' => (new Install())->getOptionParser(),
            ])
            ->addSubcommand('uninstall', [
                'help' => 'Uninstall a plugin: remove public files and optionally disable.',
                'parser' => (new Uninstall())->getOptionParser(),
            ])
            ->addSubcommand('list_plugins', [
                'help' => 'List available and installed plugins.',
                'parser' => (new ListPlugins())->getOptionParser(),
            ])
            ->addSubcommand('assets', [
                'help' => 'Copy plugin public files only (re-run for updates).',
                'parser' => (new Assets())->getOptionParser(),
            ])
            ->addSubcommand('schema', [
                'help' => 'Run plugin schema SQL only.',
                'parser' => (new Schema())->getOptionParser(),
            ])
            ->addSubcommand('status', [
                'help' => 'Show install status for a plugin.',
                'parser' => (new Status())->getOptionParser(),
            ]);

        return $optionParser;
    }

/**
 * Execute command (default: list plugins when no subcommand).
 *
 * @param Arguments $args Arguments
 * @param Io $io Console Input/output
 * @return int|null Exit code
 */
    public function execute(Arguments $args, Io $io): ?int {
        $plugins = CorePlugin::list();
        $io->info('Available plugins:', 2);
        foreach ($plugins as $plugin) {
            $disabled = strpos($plugin, '.disabled') !== false;
            $name = $disabled ? preg_replace('/\.disabled$/', '', $plugin) : $plugin;
            $io->out('  ' . $name . ($disabled ? ' <comment>(disabled)</comment>' : ''));
        }
        $io->out('');
        $io->hr(1);
        $io->comment('Subcommands: install, uninstall, list_plugins, assets, schema, status');
        $io->comment('Example: nata plugin install Menus');
        $io->comment('Run "nata plugin help <subcommand>" for subcommand help.');

        return Command::CODE_SUCCESS;
    }

}
