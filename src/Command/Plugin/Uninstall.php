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
use Nata\Core\ComposerPlugins;
use Nata\Core\Plugin;
use Nata\Filesystem\Folder;
use Nata\Utility\Inflector;

/**
 * Plugin uninstall subcommand: remove public files and composer remove if applicable.
 */
class Uninstall extends Command {

    use PluginCommandTrait;

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Uninstall a plugin: remove public files and optionally run composer remove.';

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
                'help' => 'Plugin name (e.g. Notify) or package (e.g. acme/payments).'
            ])
            ->addOption('keep-files', [
                'help' => 'Keep public files (do not remove).',
                'boolean' => true,
                'default' => false
            ])
            ->addOption('no-composer', [
                'help' => 'Skip running "composer remove" for Composer-installed plugins.',
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
        $input = $args->getArgument('plugin');
        $keepFiles = $args->getOption('keep-files') === true;
        $noComposer = $args->getOption('no-composer') === true;

        $packageName = $this->_resolvePackageName($input);
        $isComposerPlugin = $this->_isComposerInstalled($packageName);

        $plugin = $isComposerPlugin
            ? ComposerPlugins::derivePluginName($packageName)
            : Inflector::camelize($input);

        $pluginWebroot = ROOT . 'public' . DS . 'plugin' . DS . Inflector::dasherize($plugin);

        $io->info(sprintf('Uninstalling plugin: %s', $plugin), 2);

        // Remove assets first — vendor dir will be gone after composer remove
        if (!$keepFiles && is_dir($pluginWebroot)) {
            $folder = new Folder($pluginWebroot);
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

        if ($isComposerPlugin && !$noComposer) {
            $io->info(sprintf('Removing package "%s" via Composer...', $packageName), 2);
            if (!$this->_runComposer('remove', $packageName, $io)) {
                $io->error('composer remove failed.');
                return Command::CODE_ERROR;
            }
            $io->success(sprintf('Package "%s" removed.', $packageName));
        } elseif (!$isComposerPlugin) {
            $io->comment('  Local plugin: no composer remove needed.');
            $io->comment('  To disable at runtime, rename its folder to ' . $plugin . '.disabled');
        }

        $io->out('');
        $io->success(sprintf('Plugin "%s" uninstalled.', $plugin));

        return Command::CODE_SUCCESS;
    }

}
