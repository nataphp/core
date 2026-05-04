<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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
use ReflectionClass;
use RegexIterator;
use DirectoryIterator;

/**
 * Application class.
 */
class App {

/**
 * Holds and key => value array of object types.
 *
 * @var array
 */
    protected static $_objects = [];

/**
 * Indicates whether the object cache should be stored again because of an addition to it
 *
 * @var boolean
 */
    protected static $_objectCacheChange = false;

/**
 * Application path unique key
 *
 * @var array
 */
    protected static $_classNames = [];


/**
 * Return the classname namespaced. This method check if the class is defined on the
 * application/plugin, otherwise try to load from the NataPHP core
 *
 * @param string $class Classname
 * @param strign $type Type of class
 * @param string $suffix Classname suffix
 * @return boolean|string False if the class is not found or namespaced classname
 */
    public static function className($class, $type = '', $suffix = '') {
        if (is_array($type)) {
            $type = implode('\\', $type);
        }

        $key = $class . $type . $suffix;

        if (isset(static::$_classNames[$key])) {
            return static::$_classNames[$key];
        }

        if ($class && strpos($class, '\\') !== false) {
            return $class;
        }

        [$plugin, $name] = pluginSplit($class);

        if ($plugin) {
            $base = Plugin::getNamespace($plugin);
        } else {
            $base = 'App';
        }

        $base = rtrim($base, '\\');

        $fullname = '\\' . str_replace('/', '\\', $type . '\\' . $name) . $suffix;
        if (static::_classExistsInBase($fullname, $base)) {
            return static::$_classNames[$key] = $base . $fullname;
        }

        if (static::_classExistsInBase($fullname, 'Nata')) {
            return static::$_classNames[$key] = 'Nata' . $fullname;
        }

        return false;
    }

/**
 * _classExistsInBase
 *
 * Test isolation wrapper
 *
 * @param string $name
 * @param string $namespace
 * @return bool
 */
    protected static function _classExistsInBase($name, $namespace) {
        return class_exists($namespace . $name);
    }

/**
 * Return the class's short name (without the namespace).
 *
 * @param string|object $class Classname or object
 * @return string Class's short name
 */
    public static function classShortName($class) {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (strpos($class, '\\') === false) {
            return $class;
        }

        return (new ReflectionClass($class))->getShortName();
    }

/**
 * Get the path to a given package.
 *
 * ### Examples
 *
 *  App::path('Controller');
 *  /absolute/path/to/src/Controller
 *
 *  App::path('Controller/');
 *  /absolute/path/to/src/Controller/
 *
 *  App::path('Controller', 'MyPlugin');
 *  /absolute/path/to/plugins/MyPlugin/Controller/
 *
 *  App::path('Template', 'MyPlugin');
 *  If plugins/MyPlugin/src/Template exists: .../plugins/MyPlugin/src/Template/
 *  Otherwise: .../plugins/MyPlugin/Template/ (legacy layout)
 *
 * @param string $relative App folder relative path
 * @param string $plugin Plugin name if is inside plugin
 * @return string Full path
 */
    public static function path($package = null, $plugin = null) {
        $package = static::ds(ltrim($package, '/'));

        if (!empty($plugin)) {
            $root = static::pluginPath($plugin . DS);
            if (($package === 'Template' || strpos($package, 'Template' . DS) === 0)
                && is_dir($root . 'src' . DS . 'Template')
            ) {
                $rest = strpos($package, 'Template' . DS) === 0
                    ? substr($package, strlen('Template' . DS))
                    : '';

                return $root . 'src' . DS . 'Template' . DS . $rest;
            }

            if (($package === 'Template' || strpos($package, 'Template' . DS) === 0)
                && is_dir($root . 'templates')
            ) {
                $rest = strpos($package, 'Template' . DS) === 0
                    ? substr($package, strlen('Template' . DS))
                    : '';

                return $root . 'templates' . DS . $rest;
            }

            return $root . $package;
        }

        if ($package === 'public' || strpos($package, 'public' . DS) === 0) {
            return ROOT . rtrim($package, DS) . DS;
        }

        if ($package === 'Config' || strpos($package, 'Config' . DS) === 0) {
            $rest = ($package === 'Config' || $package === 'Config' . DS)
                ? ''
                : substr($package, strlen('Config' . DS));

            return ROOT . 'config' . DS . $rest;
        }

        if ($package === 'Plugin' || strpos($package, 'Plugin' . DS) === 0) {
            $rest = ($package === 'Plugin' || $package === 'Plugin' . DS)
                ? ''
                : substr($package, strlen('Plugin' . DS));

            return ROOT . 'plugins' . DS . $rest;
        }

        if ($package === 'Template' || strpos($package, 'Template' . DS) === 0) {
            $rest = ($package === 'Template' || $package === 'Template' . DS)
                ? ''
                : substr($package, strlen('Template' . DS));

            $base = is_dir(APP . 'Template') ? APP . 'Template' . DS : ROOT . 'templates' . DS;

            return $base . $rest;
        }

        return APP . $package;
    }

/**
 * Get the path to plugin.
 *
 * ### Example
 *
 *  App::pluginPath('PluginName');
 *  /absolute/path/to/plugins/PluginName/
 *
 * @param string $pluginName Plugin name
 * @return string Full path to plugin
 */
    public static function pluginPath($pluginName) {
        $key = trim(static::ds($pluginName), DS);
        [$pluginPart] = pluginSplit($key);
        if ($pluginPart !== null) {
            $key = $pluginPart;
        }
        if (Plugin::loaded($key)) {
            return Plugin::path($key);
        }
        $fallback = ROOT . 'plugins' . DS . $key . DS;
        if (is_dir($fallback)) {
            return static::ds($fallback);
        }

        return ROOT . 'plugins' . DS . $key . DS;
    }

/**
 * Get the path to a given package in core.
 *
 * ### Example
 *
 *  App::core('Controller');
 * /absolute/path/to/Nata/Controller
 *
 * @param string $relative Relative path
 * @return string Full path
 */
    public static function core($package) {
        $coreRoot = dirname(dirname(__DIR__));
        if ($package === 'Template' || strpos($package, 'Template' . DS) === 0) {
            $rest = $package === 'Template'
                ? ''
                : substr($package, strlen('Template' . DS));
            return $coreRoot . DS . 'templates' . DS . static::ds($rest);
        }
        return dirname(__DIR__) . DS . static::ds($package);
    }

/**
 * Get the absolute path based on base path.
 *
 * ### Example
 *
 *  App::absolutize('public', 'css', 'file.css');
 *  // Will return /absolute/path/to/public/css/file.css
 *
 *  App::absolutize('public', 'css', '/file.css');
 *  // Will return /absolute/path/to/public/file.css
 *
 *  App::absolutize('public', 'css', '/custom/file.css');
 *  // Will return /absolute/path/to/public/custom/file.css
 *
 *  App::absolutize('public', 'css', 'custom/file.css');
 *  // Will return /absolute/path/to/public/css/custom/file.css
 *
 * @param string $base Base path
 * @param string $relative Relative path
 * @param string $path File path
 * @return string Full path
 */
    public static function absolutize($base, $relative, $path) {
        $base = static::path($base);
        if (!empty($base) && substr($base, -1) !== DS) {
            $base .= DS;
        }

        $path = static::ds($path);
        if (static::isRelativePath($path)) {
            $relative = static::ds($relative);
            $base .= $relative . DS . $path;
        } else {
            $base .= substr($path, 1);
        }

        return $base;
    }

/**
 * Normalize given path to use '/' as directory separator.
 *
 * @param string $path Path to normalize
 * @return string Path with unix ('/') directory separator file system
 */
    public static function normalize($path) {
        return str_replace('\\', '/', $path);
    }

/**
 * Replace given path's directory separator with DIRECTOR_SEPARATOR value.
 *
 * @param string $path Path to replace DS
 * @return string Path with correct directory separator file system
 */
    public static function ds($path) {
        return str_replace(['\\', '/'], DS, $path);
    }

/**
 * Checks if path starts with a slash, returning true if it does.
 *
 * @param string $path Path to replace DS
 * @return bool True if path it starts with '/''
 */
    public static function isRelativePath($path) {
        return substr($path, 0, 1) !== DS && substr($path, 0, 1) !== '/';
    }

/**
 * Returns an array of objects of the given type.
 *
 * Example:
 *
 * `App::objects('Plugin');` returns `['DebugKit', 'Blog', 'User'];`
 *
 * `App::objects('Controller');` returns `['PagesController', 'BlogController'];`
 *
 * You can also search only within a plugin's objects by using the plugin dot
 * syntax.
 *
 * `App::objects('MyPlugin.Model');` returns `['MyPluginPost', 'MyPluginComment'];`
 *
 * When scanning directories, files and directories beginning with `.` will be excluded as these
 * are commonly used by version control systems.
 *
 * @param string $type Type of object, i.e. 'Model', 'Controller', 'View/Helper', 'file', 'class' or 'Plugin'
 * @param string|array $path Optional Scan only the path given. If null, paths for the chosen type will be used.
 * @param boolean $cache Set to false to rescan objects of the chosen type. Defaults to true.
 * @return mixed Either false on incorrect / miss. Or an array of found objects.
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/app.html#App::objects
 */
    public static function objects($type, $path = null, $cache = true) {
        if (empty(static::$_objects) && $cache === true && $objects = Cache::read('object_map', '__nata_core__')) {
            static::$_objects = (array)$objects;
        }

        $extension = '/\.php$/';
        $includeDirectories = false;
        $name = $type;

        if ($type === 'Plugin') {
            $extension = '/.*/';
            $includeDirectories = true;
        }

        [$plugin, $type] = pluginSplit($type);

        if ($plugin === null && $type === 'Plugin' && $path === null) {
            return Plugin::loaded();
        }

        if ($type === 'file' && !$path) {
            return false;
        } elseif ($type === 'file') {
            $extension = '/\.php$/';
            $name = $type . str_replace(DS, '', $path);
        }

        $cacheLocation = empty($plugin) ? 'App' : $plugin;

        if ($cache !== true || !isset(static::$_objects[$cacheLocation][$name])) {
            $objects = [];

            if (empty($path)) {
                $path = static::path($type, $plugin);
            }

            foreach ((array)$path as $dir) {
                if ($dir === APP || !is_dir($dir)) {
                    continue;
                }

                $files = new RegexIterator(new DirectoryIterator($dir), $extension);
                foreach ($files as $file) {
                    $fileName = basename($file);
                    if (substr($fileName, 0, 1) === '.') {
                        continue;
                    }

                    $isDir = $file->isDir();
                    if ($isDir && $includeDirectories) {
                        $objects[] = $fileName;
                    } elseif (!$includeDirectories && !$isDir) {
                        $objects[] = substr($fileName, 0, -4);
                    }

                }
            }

            if ($type !== 'file') {
                foreach ($objects as $key => $value) {
                    $objects[$key] = Inflector::camelize($value);
                }
            }

            sort($objects);
            if ($plugin) {
                return $objects;
            }

            static::$_objects[$cacheLocation][$name] = $objects;

            if ($cache) {
                static::$_objectCacheChange = true;
            }
        }

        return static::$_objects[$cacheLocation][$name];
    }

/**
 * Shutdown app.
 */
    public static function shutdown() {
        if (static::$_objectCacheChange) {
            Cache::write('object_map', static::$_objects, '__nata_core__');
        }
    }

/**
 * Check if we're in a windows env.
 *
 * @return bool True if PHP was built on a windows OS.
 */
    public static function isWindows(): bool {
        return PHP_OS_FAMILY === 'Windows' || strncasecmp(PHP_OS, 'win', 3) === 0;
    }

}
