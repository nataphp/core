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
use Nata\Utility\Inflector;

/**
 * Plugin install subcommand: copy public files and run schema.
 * Also handles composer require for Composer-distributed plugins.
 */
class Install extends Command {

    use PluginCommandTrait;

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Install a plugin: copy public files and run schema.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Install a plugin: copy public files to public/plugin/ and run schema SQL.')
            ->addArgument('plugin', [
                'required' => true,
                'help' => 'Plugin name (e.g. Notify) or package (e.g. acme/payments).'
            ])
            ->addOption('no-composer', [
                'help' => 'Skip running "composer require" (use when package is already downloaded).',
                'boolean' => true,
                'default' => false
            ])
            ->addOption('composer-path', [
                'short' => 'c',
                'help' => 'Path override for Composer-installed plugin (e.g. vendor/nataphp/notify).',
                'default' => null
            ])
            ->addOption('skip-schema', [
                'help' => 'Skip running schema SQL.',
                'boolean' => true,
                'default' => false
            ])
            ->addOption('skip-assets', [
                'help' => 'Skip copying public files.',
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
        $noComposer = $args->getOption('no-composer') === true;
        $composerPath = $args->getOption('composer-path');
        $skipSchema = $args->getOption('skip-schema') === true;
        $skipAssets = $args->getOption('skip-assets') === true;

        // Determine whether this is a local or Composer plugin
        $localPlugin = Inflector::camelize($input);
        $isLocal = strpos($input, '/') === false
            && is_dir(ROOT . 'plugins' . DS . $localPlugin . DS);

        if ($isLocal) {
            $plugin = $localPlugin;
        } else {
            // Composer-based install
            $packageName = $this->_resolvePackageName($input);

            if (!$noComposer && !$this->_isComposerInstalled($packageName)) {
                $io->info(sprintf('Package "%s" not installed. Running composer require...', $packageName), 2);
                if (!$this->_runComposer('require', $packageName, $io)) {
                    $io->error('composer require failed. Aborting.');
                    return Command::CODE_ERROR;
                }
            }

            $plugin = ComposerPlugins::derivePluginName($packageName);

            if ($composerPath === null) {
                $composerPath = $this->_findComposerPath($packageName);
                if ($composerPath === null) {
                    $io->error(sprintf('Could not locate installed package "%s" in vendor/.', $packageName));
                    return Command::CODE_ERROR;
                }
            }
        }

        $sourcePath = Plugin::getPluginSourcePath($plugin, $composerPath);
        if (!is_dir($sourcePath)) {
            $io->error(sprintf('Plugin "%s" not found at: %s', $plugin, $sourcePath));
            return Command::CODE_ERROR;
        }

        $manifest = Plugin::getManifest($plugin, $composerPath);
        $io->info(sprintf('Installing plugin: %s', $plugin), 2);

        // Local plugins are not separate Composer packages, so their namespace
        // is only autoloadable if the app's composer.json maps it. Register it
        // and regenerate the autoloader. Composer-distributed plugins already
        // carry their own psr-4 map via "composer require", so this is skipped.
        if ($isLocal && $this->_registerLocalAutoload($plugin, $io)) {
            if (!$this->_runComposerDumpAutoload($io)) {
                $io->error('  composer dump-autoload failed. Run it manually before using the plugin.');
                return Command::CODE_ERROR;
            }
            $io->success('  Autoloader regenerated.');
        }

        if (!$skipAssets) {
            $hasPublic = is_dir($sourcePath . 'public' . DS);
            if ($manifest !== null && isset($manifest['public']) && !$manifest['public']) {
                $hasPublic = false;
            }
            if ($hasPublic) {
                if (Plugin::copyPublicFiles($plugin, $composerPath)) {
                    $io->success('  Public files copied to public/plugin/' . Inflector::dasherize($plugin) . '/');
                } else {
                    $io->error('  Failed to copy public files.');
                    return Command::CODE_ERROR;
                }
            } else {
                $io->comment('  No public files to copy.');
            }
        }

        if (!$skipSchema) {
            $schemaFiles = $this->_getSchemaFiles($plugin, $composerPath, $manifest);
            if (!empty($schemaFiles)) {
                foreach ($schemaFiles as $sqlFile) {
                    try {
                        if (Plugin::runSchema($plugin, $sqlFile, $composerPath)) {
                            $io->success('  Schema executed: ' . $sqlFile);
                        } else {
                            $io->warning('  Schema file not found: ' . $sqlFile);
                        }
                    } catch (\Throwable $e) {
                        $io->error('  Schema error: ' . $e->getMessage());
                        return Command::CODE_ERROR;
                    }
                }
            } else {
                $io->comment('  No schema files to run.');
            }
        }

        $io->out('');
        $io->success(sprintf('Plugin "%s" installed successfully.', $plugin));

        return Command::CODE_SUCCESS;
    }

/**
 * Ensure the application's composer.json maps a local plugin's PSR-4 namespace.
 *
 * Local plugins (under plugins/<Name>/) are not standalone Composer packages,
 * so their classes only autoload when the app's composer.json registers
 * "<Plugin>\\" => "plugins/<Plugin>/src/". Adds the entry when missing; leaves
 * an existing mapping untouched.
 *
 * @param string $plugin Plugin name in CamelCase.
 * @param Io $io Console I/O.
 * @return bool True when an entry was added (autoloader must be regenerated), false otherwise.
 */
    protected function _registerLocalAutoload(string $plugin, Io $io): bool {
        $composerFile = ROOT . 'composer.json';
        if (!is_file($composerFile)) {
            $io->warning('  composer.json not found; skipping autoload registration.');
            return false;
        }

        if (!is_dir(ROOT . 'plugins' . DS . $plugin . DS . 'src')) {
            $io->comment('  No src/ directory; skipping autoload registration.');
            return false;
        }

        $json = json_decode((string)@file_get_contents($composerFile), true);
        if (!is_array($json)) {
            $io->warning('  Could not parse composer.json; skipping autoload registration.');
            return false;
        }

        $namespace = $plugin . '\\';
        $relativePath = 'plugins/' . $plugin . '/src/';

        $existing = $json['autoload']['psr-4'][$namespace] ?? null;
        if ($existing !== null) {
            $io->comment(sprintf('  Autoload already registered: %s => %s', $namespace, $existing));
            return false;
        }

        $json['autoload']['psr-4'][$namespace] = $relativePath;

        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $io->error('  Failed to encode composer.json.');
            return false;
        }

        if (file_put_contents($composerFile, $encoded . "\n") === false) {
            $io->error('  Failed to write composer.json.');
            return false;
        }

        $io->success(sprintf('  Registered autoload: %s => %s', $namespace, $relativePath));
        return true;
    }

/**
 * Get schema files from manifest or auto-detect config/schema/*.sql
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
