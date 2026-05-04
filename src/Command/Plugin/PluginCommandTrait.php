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

use Nata\Console\Io;
use Nata\Utility\Inflector;

/**
 * Shared Composer-related helpers for plugin commands.
 */
trait PluginCommandTrait {

/**
 * Resolve a user-supplied plugin reference to a Composer package name.
 *
 * 'Notify'        → 'nataphp/notify'
 * 'MyMenus'       → 'nataphp/my-menus'
 * 'acme/payments' → 'acme/payments'  (slash = third-party, use as-is)
 *
 * @param string $input Plugin name or package name
 * @return string Composer package name
 */
    protected function _resolvePackageName(string $input): string {
        if (strpos($input, '/') !== false) {
            return strtolower($input);
        }
        return 'nataphp/' . strtolower(Inflector::dasherize($input));
    }

/**
 * Check whether a package is listed in vendor/composer/installed.json.
 *
 * @param string $packageName Composer package name
 * @return bool
 */
    protected function _isComposerInstalled(string $packageName): bool {
        $path = ROOT . 'vendor' . DS . 'composer' . DS . 'installed.json';
        if (!is_file($path)) {
            return false;
        }
        $data = json_decode(@file_get_contents($path), true);
        if (!is_array($data)) {
            return false;
        }
        $packages = $data['packages'] ?? $data;
        foreach ($packages as $package) {
            if (($package['name'] ?? '') === $packageName) {
                return true;
            }
        }
        return false;
    }

/**
 * Shell out to Composer to run require or remove.
 * Streams output line by line and returns whether it succeeded.
 *
 * @param string $subcommand 'require' or 'remove'
 * @param string $packageName Composer package name
 * @param Io     $io Console I/O
 * @return bool True on success
 */
    protected function _runComposer(string $subcommand, string $packageName, Io $io): bool {
        if (!function_exists('exec')) {
            $io->error('exec() is disabled. Run "composer ' . $subcommand . ' ' . $packageName . '" manually.');
            return false;
        }

        $cmd = sprintf('composer %s %s 2>&1', $subcommand, escapeshellarg($packageName));
        $io->comment('Running: ' . $cmd);

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        foreach ($output as $line) {
            $io->out('  ' . $line);
        }

        return $exitCode === 0;
    }

/**
 * Find the vendor-relative path for an installed Composer package.
 * Returns a root-relative path (e.g. 'vendor/nataphp/notify') or null.
 *
 * @param string $packageName Composer package name
 * @return string|null
 */
    protected function _findComposerPath(string $packageName): ?string {
        $installedJson = ROOT . 'vendor' . DS . 'composer' . DS . 'installed.json';
        if (!is_file($installedJson)) {
            return null;
        }
        $data = json_decode(@file_get_contents($installedJson), true);
        if (!is_array($data)) {
            return null;
        }
        $packages = $data['packages'] ?? $data;
        $composerDir = ROOT . 'vendor' . DS . 'composer' . DS;
        foreach ($packages as $package) {
            if (($package['name'] ?? '') !== $packageName) {
                continue;
            }
            $relPath = $package['install-path'] ?? null;
            if ($relPath === null) {
                return null;
            }
            $relPath = str_replace(['/', '\\'], DS, $relPath);
            $absPath = realpath($composerDir . $relPath);
            if ($absPath === false) {
                return null;
            }
            return str_replace(ROOT, '', $absPath);
        }
        return null;
    }

}
