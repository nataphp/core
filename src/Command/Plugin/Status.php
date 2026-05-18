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
 * Plugin status subcommand: show install status for a plugin.
 */
class Status extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Show install status for a plugin.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Show install status: public files, schema, enabled.')
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
        $pluginPath = ROOT . 'public' . DS . 'plugin' . DS;
        $pluginWebroot = $pluginPath . Inflector::dasherize($plugin);
        $hasPublicCopied = is_dir($pluginWebroot);
        $hasPublicSource = is_dir($sourcePath . 'public' . DS);
        $schemaDir = $sourcePath . 'config' . DS . 'schema' . DS;
        $schemaFiles = is_dir($schemaDir) ? glob($schemaDir . '*.sql') : [];
        $schemaCount = count($schemaFiles);

        $plugins = Plugin::list();
        $isDisabled = in_array($plugin . '.disabled', $plugins);
        $isAvailable = in_array($plugin, $plugins) || $isDisabled;

        $io->info(sprintf('Plugin: %s', $plugin), 2);
        $io->out('  Source path:    ' . $sourcePath);
        $io->out('  Available:     ' . ($isAvailable ? '<success>yes</success>' : '<comment>no</comment>'));
        $io->out('  Disabled:      ' . ($isDisabled ? '<warning>yes</warning>' : '<success>no</success>'));
        $io->out('  Public source: ' . ($hasPublicSource ? '<success>yes</success>' : '<comment>no</comment>'));
        $io->out('  Public copied: ' . ($hasPublicCopied ? '<success>yes</success>' : '<comment>no</comment>'));
        $io->out('  Schema files:  ' . $schemaCount);
        if ($manifest !== null) {
            $io->out('  Manifest:      <success>yes</success> (version: ' . ($manifest['version'] ?? '?') . ')');
        } else {
            $io->out('  Manifest:      <comment>no</comment> (plugin.json)');
        }
        $io->out('');

        return Command::CODE_SUCCESS;
    }

}
