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

namespace Nata\Core;

use Nata\Cache\Cache;
use Nata\Utility\Inflector;

/**
 * Discovers and loads NataPHP plugins installed via Composer.
 *
 * Reads vendor/composer/installed.json at runtime to find packages with
 * "type": "nataphp-plugin", then loads them via Plugin::load(). Results
 * are cached and invalidated automatically when installed.json changes.
 */
class ComposerPlugins {

    const CACHE_KEY = 'nata_composer_plugins';
    const CACHE_CONFIG = '__nata_core__';

/**
 * Load all Composer-installed nataphp plugins.
 * Skips plugins already loaded (local plugins take precedence).
 *
 * @param string|null $vendorDir Override vendor dir for testing.
 * @return void
 */
    public static function load(?string $vendorDir = null): void {
        foreach (static::getPlugins($vendorDir) as $descriptor) {
            if (Plugin::loaded($descriptor['name'])) {
                continue;
            }
            try {
                Plugin::load($descriptor['name'], [
                    'path'      => $descriptor['path'],
                    'bootstrap' => $descriptor['bootstrap'],
                    'routes'    => $descriptor['routes'],
                    'namespace' => $descriptor['name'],
                ]);
            } catch (\Throwable $e) {
                trigger_error(
                    sprintf('ComposerPlugins: failed to load "%s": %s', $descriptor['name'], $e->getMessage()),
                    E_USER_WARNING
                );
            }
        }
    }

/**
 * Return parsed list of Composer plugin descriptors, using mtime-based cache.
 *
 * Each descriptor: [name, path, package, bootstrap, routes, public]
 *
 * @param string|null $vendorDir Override vendor dir for testing.
 * @return array<int, array>
 */
    public static function getPlugins(?string $vendorDir = null): array {
        $vendorDir = static::_vendorDir($vendorDir);
        $composerDir = $vendorDir . 'composer' . DS;
        $installedJsonPath = $composerDir . 'installed.json';

        if (!is_file($installedJsonPath)) {
            return [];
        }

        $mtime = filemtime($installedJsonPath);

        $cached = Cache::read(static::CACHE_KEY, static::CACHE_CONFIG);
        if (is_array($cached) && isset($cached['mtime']) && $cached['mtime'] === $mtime) {
            return $cached['plugins'];
        }

        $packages = static::_parseInstalledJson($installedJsonPath);
        $plugins = [];

        foreach ($packages as $package) {
            if (($package['type'] ?? '') !== 'nataphp-plugin') {
                continue;
            }

            $pluginPath = static::_resolvePluginPath($package, $composerDir);
            if ($pluginPath === null) {
                trigger_error(
                    sprintf('ComposerPlugins: could not resolve path for package "%s".', $package['name'] ?? '?'),
                    E_USER_WARNING
                );
                continue;
            }

            $nataExtra = $package['extra']['nataphp'] ?? [];
            $pluginName = static::_derivePluginName($package);

            if (isset($plugins[$pluginName])) {
                trigger_error(
                    sprintf('ComposerPlugins: name collision for "%s" (packages "%s" and "%s"). Second package skipped.',
                        $pluginName, $plugins[$pluginName]['package'], $package['name']),
                    E_USER_WARNING
                );
                continue;
            }

            $plugins[$pluginName] = [
                'name'      => $pluginName,
                'path'      => $pluginPath,
                'package'   => $package['name'],
                'bootstrap' => (bool) ($nataExtra['bootstrap'] ?? false),
                'routes'    => (bool) ($nataExtra['routes']    ?? false),
                'public'    => (bool) ($nataExtra['public']    ?? false),
            ];
        }

        $result = array_values($plugins);
        Cache::write(static::CACHE_KEY, ['mtime' => $mtime, 'plugins' => $result], static::CACHE_CONFIG);

        return $result;
    }

/**
 * Derive a NataPHP plugin name from a Composer package name.
 *
 * 'nataphp/notify'   → 'Notify'
 * 'nataphp/my-menus' → 'MyMenus'
 * 'acme/plugin-pay'  → 'Pay'
 * extra.nataphp.name overrides all derivation.
 *
 * @param string $packageName Composer package name (e.g. 'nataphp/notify')
 * @return string PascalCase plugin name
 */
    public static function derivePluginName(string $packageName): string {
        return static::_derivePluginName(['name' => $packageName, 'extra' => []]);
    }

/**
 * Read and parse installed.json, returning the packages array.
 *
 * @param string $path Absolute path to installed.json
 * @return array
 */
    protected static function _parseInstalledJson(string $path): array {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return [];
        }
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return [];
        }
        return $data['packages'] ?? $data;
    }

/**
 * Derive plugin name from a raw package array.
 *
 * @param array $package Raw package entry from installed.json
 * @return string PascalCase plugin name
 */
    protected static function _derivePluginName(array $package): string {
        $override = $package['extra']['nataphp']['name'] ?? null;
        if ($override && is_string($override)) {
            return Inflector::camelize($override);
        }

        $parts = explode('/', $package['name'] ?? '');
        $last = end($parts);
        $last = preg_replace('/^plugin[-_]/i', '', $last);

        return Inflector::camelize($last);
    }

/**
 * Resolve absolute filesystem path for a package from its install-path.
 * install-path in installed.json is relative to vendor/composer/.
 *
 * @param array  $package     Raw package entry
 * @param string $composerDir Absolute path to vendor/composer/ with trailing DS
 * @return string|null Absolute path with trailing DS, or null if unresolvable
 */
    protected static function _resolvePluginPath(array $package, string $composerDir): ?string {
        $relPath = $package['install-path'] ?? null;
        if ($relPath === null) {
            return null;
        }

        $relPath = str_replace(['/', '\\'], DS, $relPath);
        $absPath = realpath($composerDir . $relPath);

        if ($absPath === false || !is_dir($absPath)) {
            return null;
        }

        return App::ds($absPath) . DS;
    }

/**
 * Resolve the vendor directory, defaulting to ROOT . 'vendor'.
 *
 * @param string|null $override
 * @return string Normalised path with trailing DS
 */
    protected static function _vendorDir(?string $override): string {
        $path = $override ?? ROOT . 'vendor';
        return rtrim(str_replace(['/', '\\'], DS, $path), DS) . DS;
    }

}
