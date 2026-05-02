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
use Nata\Core\Plugin;
use Nata\Utility\Inflector;

/**
 * Plugin assets subcommand: copy public files only.
 */
class Assets extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Copy plugin public files only (re-run for updates).';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Copy plugin public files to public/plugin/. Re-run after updating plugin assets.')
            ->addArgument('plugin', [
                'required' => true,
                'help' => 'Plugin name (e.g. Menus, Acl)'
            ])
            ->addOption('composer-path', [
                'short' => 'c',
                'help' => 'Path when installed via Composer (e.g. vendor/maismls/menus)',
                'default' => null
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
        $composerPath = $args->getOption('composer-path');

        $sourcePath = Plugin::getPluginSourcePath($plugin, $composerPath);
        if (!is_dir($sourcePath)) {
            $io->error(sprintf('Plugin "%s" not found at: %s', $plugin, $sourcePath));
            return Command::CODE_ERROR;
        }

        $manifest = Plugin::getManifest($plugin, $composerPath);
        if ($manifest !== null && isset($manifest['public']) && !$manifest['public']) {
            $io->comment(sprintf('Plugin "%s" has no public files to copy.', $plugin));
            return Command::CODE_SUCCESS;
        }

        if (!is_dir($sourcePath . 'public' . DS)) {
            $io->comment(sprintf('Plugin "%s" has no public folder.', $plugin));
            return Command::CODE_SUCCESS;
        }

        if (Plugin::copyPublicFiles($plugin, $composerPath)) {
            $io->success(sprintf('Public files copied to public/plugin/%s/', Inflector::dasherize($plugin)));
            return Command::CODE_SUCCESS;
        }

        $io->error('Failed to copy public files.');
        return Command::CODE_ERROR;
    }

}
