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
 * Plugin uninstall subcommand: remove public files and optionally disable.
 */
class Uninstall extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Uninstall a plugin: remove public files.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Uninstall a plugin: remove public files from public/plugin/.')
            ->addArgument('plugin', [
                'required' => true,
                'help' => 'Plugin name (e.g. Menus, Acl)'
            ])
            ->addOption('keep-files', [
                'help' => 'Keep public files (do not remove)',
                'boolean' => true,
                'default' => false
            ]);

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
        $plugin = Inflector::camelize($args->getArgument('plugin'));
        $keepFiles = $args->getOption('keep-files') === true;

        $pluginPath = App::path('public/plugin/');
        $pluginWebroot = $pluginPath . Inflector::dasherize($plugin);

        $io->info(sprintf('Uninstalling plugin: %s', $plugin), 2);

        if (!$keepFiles && is_dir($pluginWebroot)) {
            $folder = new \Nata\Filesystem\Folder($pluginWebroot);
            if ($folder->delete()) {
                $io->success('  Public files removed from public/plugin/' . Inflector::dasherize($plugin) . '/');
            } else {
                $io->error('  Failed to remove public files.');
                return Command::CODE_ERROR;
            }
        } elseif ($keepFiles) {
            $io->comment('  Public files kept (--keep-files).');
        } else {
            $io->comment('  No public files to remove.');
        }

        $io->out('');
        $io->success(sprintf('Plugin "%s" uninstalled.', $plugin));
        $io->comment('To disable the plugin at runtime, rename its folder to {Name}.disabled');

        return Command::CODE_SUCCESS;
    }

}
