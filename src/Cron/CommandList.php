<?php
/**
 * NataPHP Framework
 *
 * @author      Sergio Dinis Lopes <sergiodlopes at gmail dot com>
 * @copyright   Copyright (c) Sérgio Dinis Lopes
 * @file        Application's Cron Job class template
 */

namespace Nata\Cron;

use Nata\Core\App;
use Nata\Core\Plugin;
use Nata\Filesystem\Folder;
use Nata\Utility\Inflector;
use ReflectionClass;
use ReflectionMethod;

class CommandList extends Job {


/**
 * List of commands.
 *
 * @return void
 */
    public function main() {
        $appCommands = $this->_findCommands(App::path('Cron'));
        $pluginCommands = [];
        foreach (Plugin::loaded() as $plugin) {
            $commands = $this->_findCommands(Plugin::path($plugin) . 'Cron' . DS, $plugin);
            if (!$commands) {
                continue;
            }
            $pluginCommands = array_merge($pluginCommands, [$plugin => []]);
            $pluginCommands[$plugin] = $commands;
        }

        $this->warn('Available commands:');
        $this->success('  App:');
        foreach ($appCommands as $command) {
            $this->out(sprintf('    %s', $command));
        }

        foreach ($pluginCommands as $plugin => $commands) {
            $this->success(sprintf('  %s:', $plugin));
            foreach ($commands as $command) {
                $this->out(sprintf('    %s', $command));
            }
        }
    }

/**
 * find the commands given cron folder.
 *
 * @param string $cronFolderPath Cron folder path.
 * @param string|null $plugin Plugin name.
 * @return array List of commands found
 */
    private function _findCommands($cronFolderPath, ?string $plugin = null): array {
        $commands = [];
        $appFolder = new Folder($cronFolderPath);
        $files = $appFolder->find('^.+\.php');
        foreach ($files as $file) {
            if (strpos($file, '.') === 0) {
                continue;
            }
            $className = str_replace('.php', '', $file);
            $className = ($plugin ? $plugin : 'App') . '\Cron\\' . $className;
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if ($method->class !== $className || in_array($method->name, ['initialize'])) {
                    continue;
                }

                $methodName = Inflector::dasherize($method->name);
                if ($methodName === 'main') {
                    $methodName = '';
                }

                $shortClassName = str_replace(($plugin ? $plugin : 'App') . '\Cron\\', '', $className);
                $cmd = sprintf('%s%s %s', ($plugin ? Inflector::dasherize($plugin) . '.' : ''), Inflector::dasherize($shortClassName), $methodName);
                $commands[] = trim($cmd);
            }
        }

        sort($commands);

        return $commands;
    }

}
