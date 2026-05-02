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

use Nata\Console\Arguments;
use Nata\Console\Command;
use Nata\Console\Io;
use Nata\Console\OptionParser;
use Nata\Core\App;
use Nata\I18n\I18n;

/**
 * Command to generate JavaScript dictionary files for all available locales.
 */
class GenerateI18nJs extends Command {

/**
 * Command description.
 *
 * @var string
 */
    protected $_description = 'Generate JavaScript dictionary files for all available locales.';

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser(OptionParser $optionParser): OptionParser {
        $optionParser->setDescription('Generate JavaScript dictionary files for all available locales.')
            ->addOption('locale', [
                'short' => 'l',
                'help' => 'Generate dictionary for a specific locale only.',
                'default' => null
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force regeneration even if file exists.',
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
        $io->out('<info>Generating JavaScript dictionary files...</info>');
        $io->out('');

        $force = $args->getOption('force') === true;
        $specificLocale = $args->getOption('locale');

        // Get all available locales
        $availableLocales = I18n::available();
        if (empty($availableLocales)) {
            $io->error('No locales found. Add locale folders under resources/locales/ or src/Locale/.');
            return Command::CODE_ERROR;
        }

        // Filter by specific locale if provided
        if ($specificLocale !== null) {
            if (!isset($availableLocales[$specificLocale])) {
                $io->error(sprintf('Locale "%s" not found.', $specificLocale));
                $io->info('Available locales: ' . implode(', ', array_keys($availableLocales)));
                return Command::CODE_ERROR;
            }
            $availableLocales = [$specificLocale => $availableLocales[$specificLocale]];
        }

        $generated = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($availableLocales as $locale => $l10n) {
            $result = $this->_generateLocaleFile($locale, $force, $io);
            if ($result === true) {
                $generated++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
        }

        $io->out('');
        $io->out('<info>Summary:</info>');
        $io->out(sprintf('  Generated: %d', $generated));
        if ($skipped > 0) {
            $io->warning(sprintf('  Skipped: %d (already exists, use --force to regenerate)', $skipped));
        }
        if ($errors > 0) {
            $io->error(sprintf('  Errors: %d', $errors));
        }

        return $errors > 0 ? Command::CODE_ERROR : Command::CODE_SUCCESS;
    }

/**
 * Generate JavaScript dictionary file for a specific locale.
 *
 * @param string $locale Locale code
 * @param bool $force Force regeneration
 * @param Io $io Console Input/output
 * @return bool|string True on success, 'skipped' if skipped, false on error
 */
    protected function _generateLocaleFile(string $locale, bool $force, Io $io) {
        // Set locale temporarily to get domains for this locale
        $originalLocale = I18n::locale();
        I18n::cacheEnabled(false);
        I18n::locale($locale);
        I18n::translate('test', 'tests', null, 'default', I18n::LC_MESSAGES, 1, $locale);

        $_domains = I18n::domains();
        if (empty($_domains)) {
            $io->warning(sprintf('  [%s] No domains found, skipping...', $locale));
            I18n::locale($originalLocale);
            return 'skipped';
        }

        $basename = $locale . '.js';
        $path = App::path('public/i18n/') . $basename;

        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $io->error(sprintf('  [%s] Failed to create directory: %s', $locale, $directory));
                I18n::locale($originalLocale);
                return false;
            }
        }

        // Build domains array
        $modified = 0;
        if (file_exists($path)) {
            $modified = filemtime($path);
        }
        $domains = [];
        $domainModified = '';
        foreach ($_domains as $domain => $locales) {
            if (!isset($locales[$locale])) {
                continue;
            }

            $category = $locales[$locale];
            unset($category['LC_MESSAGES']['_reversei18n']);
            $domains[$domain] = $category;
            if (isset($category['LC_MESSAGES']['__lmf__'])) {
                $domainModified = $category['LC_MESSAGES']['__lmf__'];
            }

            if ($domainModified > $modified) {
                $io->out('');
                $io->info(sprintf('  [%s] Domain %s has been modified, forcing regeneration', $locale, $domain));
                $force = true;
            }
        }

        // Check if file exists and force flag
        if (!$force && file_exists($path)) {
            $io->info(sprintf('  [%s] File already exists, skipping... (use --force to regenerate)', $locale));
            I18n::locale($originalLocale);
            return 'skipped';
        }

        if (empty($domains)) {
            $io->warning(sprintf('  [%s] No translations found for this locale, skipping...', $locale));
            I18n::locale($originalLocale);
            return 'skipped';
        }

        unset($domains['LC_MESSAGES']['__lmf__']);

        // Generate file contents
        // Namespace by language code to allow multiple languages to coexist
        $contents = "if(typeof i18n==='undefined')i18n={};i18n['" . $locale . "']=" . json_encode($domains) . ";";

        // Write file
        if (file_put_contents($path, $contents) === false) {
            $io->error(sprintf('  [%s] Failed to write file: %s', $locale, $path));
            I18n::locale($originalLocale);
            return false;
        }

        $fileSize = filesize($path);
        $io->success(sprintf('  [%s] Generated: %s (%s)', $locale, $basename, $this->_formatBytes($fileSize)));

        I18n::locale($originalLocale);
        return true;
    }

/**
 * Format bytes to human readable format.
 *
 * @param int $bytes Bytes
 * @param int $precision Precision
 * @return string Formatted string
 */
    protected function _formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

}
