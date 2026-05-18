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
 * Plugin schema subcommand: run schema SQL only.
 */
class Schema extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Run plugin schema SQL only.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Run plugin schema SQL files.')
            ->addArgument('plugin', [
                'required' => true,
                'help' => 'Plugin name (e.g. Menus, Acl)'
            ])
            ->addOption('composer-path', [
                'short' => 'c',
                'help' => 'Path when installed via Composer (e.g. vendor/maismls/menus)',
                'default' => null
            ])
            ->addOption('connection', [
                'short' => 'd',
                'help' => 'Database connection name',
                'default' => 'default'
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
        $connection = $args->getOption('connection');

        $sourcePath = Plugin::getPluginSourcePath($plugin, $composerPath);
        if (!is_dir($sourcePath)) {
            $io->error(sprintf('Plugin "%s" not found at: %s', $plugin, $sourcePath));
            return Command::CODE_ERROR;
        }

        $manifest = Plugin::getManifest($plugin, $composerPath);
        $schemaFiles = $this->_getSchemaFiles($plugin, $composerPath, $manifest);

        if (empty($schemaFiles)) {
            $io->comment(sprintf('Plugin "%s" has no schema files.', $plugin));
            return Command::CODE_SUCCESS;
        }

        $io->info(sprintf('Running schema for plugin: %s', $plugin), 2);

        foreach ($schemaFiles as $sqlFile) {
            try {
                if (Plugin::runSchema($plugin, $sqlFile, $composerPath, $connection)) {
                    $io->success('  Executed: ' . $sqlFile);
                } else {
                    $io->warning('  File not found: ' . $sqlFile);
                }
            } catch (\Throwable $e) {
                $io->error('  Error: ' . $e->getMessage());
                return Command::CODE_ERROR;
            }
        }

        $io->out('');
        $io->success('Schema executed successfully.');

        return Command::CODE_SUCCESS;
    }

/**
 * Get schema files from manifest or auto-detect.
 *
 * @param string $plugin Plugin name
 * @param string|null $composerPath Composer path
 * @param array|null $manifest Manifest data
 * @return array List of schema file paths
 */
    protected function _getSchemaFiles(string $plugin, ?string $composerPath, ?array $manifest): array {
        if ($manifest !== null && !empty($manifest['schema']) && is_array($manifest['schema'])) {
            return $manifest['schema'];
        }
        $path = Plugin::getPluginSourcePath($plugin, $composerPath);
        $schemaDir = $path . 'config' . DS . 'schema' . DS;
        if (!is_dir($schemaDir)) {
            return [];
        }
        $files = glob($schemaDir . '*.sql');
        $result = [];
        foreach ($files as $file) {
            $result[] = 'config/schema/' . basename($file);
        }
        sort($result);
        return $result;
    }

}
