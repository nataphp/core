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

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Handle the loading of classes across core,
 * application and respective vendors.
 */
class ClassLoader {

/**
 * File extension.
 *
 * @var string
 */
    protected static $_fileExtension = '.php';

/**
 * Composer autoloader file path.
 *
 * @var string
 */
    protected static $_composerAutoLoader = ROOT . 'vendor/autoload.php';

/**
 * Namespace delimiter.
 *
 * @var string
 */
    protected static $_namespaceDelimiter = '\\';

/**
 * Collection of registered classes.
 *
 * @var array
 */
    protected static $_map = [];

/**
 * Vendor directory name.
 *
 * @var string
 */
    protected static $_vendorDir = 'vendor';


/**
 * Gets the base include path for all class files in the _namespace of this class loader.
 *
 * @param string $autoloader The autoloader file path.
 * @return string
 */
    public static function autoloader(string $autoloader = null) {
        if ($autoloader === null) {
            return static::$_composerAutoLoader;
        }
        static::$_composerAutoLoader = $autoloader;
        return true;
    }

/**
 * Gets the base include path for all class files in the _namespace of this class loader.
 *
 * @return string
 */
    public static function getVendorDir() {
        return static::$_vendorDir;
    }

/**
 * Gets the file extension of class files in the _namespace of this class loader.
 *
 * @return string
 */
    public static function getFileExtension() {
        return static::$_fileExtension;
    }

/**
 * Installs this class loader on the SPL autoload stack.
 */
    public static function register() {
        spl_autoload_register([__CLASS__, 'load']);

        // Composer autoloader
        $composerAutoLoader = static::autoloader();
        if (file_exists($composerAutoLoader)) {
            require $composerAutoLoader;
        }
    }

/**
 * Uninstalls this class loader from the SPL autoloader stack.
 */
    public static function unregister() {
        spl_autoload_unregister([__CLASS__, 'load']);
    }

/**
 * Loads the given class file.
 *
 * @param string $className The name of the class to load.
 */
    public static function load($className) {
        $file = static::getFilePath($className);

        if ($file && file_exists($file)) {
            require $file;
        }

    }

/**
 * Get the given class file path.
 *
 * @param string $className The name of the class to load.
 * @return string Class's file path.
 */
    public static function getFilePath($className) {
        $className = ltrim($className, static::$_namespaceDelimiter);

        $file = null;

        // App or Core classes
        if (static::isAppClass($className)) {
            $file = APP . substr(static::_getClassFilename($className), strlen('App' . DS));
        } elseif (static::isCoreClass($className)) {
            $file = ROOT . static::_getClassFilename($className);
        } else {
            $options = static::map($className);
            if ($options && $options['type'] === 'class') {
                $file = $options['path'];
            } elseif ($options && $options['type'] === 'package') {
                $file = static::_getPackageClassFilename($className, $options);
                static::_loadFiles($options, $className);
            } elseif (static::isPluginClass($className)) {
                $file = static::_getPluginClassFilename($className, $options);
            }
        }
        return $file;
    }

/**
 * Get class filename from class name.
 *
 * @param string $className Class name.
 * @return string Class filename
 */
    protected static function _getClassFilename($className) {
        return static::_ds($className) . static::$_fileExtension;
    }

/**
 * Get absolute path to plugin class.
 *
 * @param string $className Class name.
 * @param array $options Mapped options.
 * @return string Absolute path to plugin's class file
 */
    protected static function _getPluginClassFilename($className, $options) {
        $vendorNamespace = static::getVendorNamespace($className);

        $className = ltrim($className, $vendorNamespace);
        $className = ltrim($className, static::$_namespaceDelimiter);

        $path = '';

        if ($options) {
            $path .= DS . static::$_vendorDir;
        }

        $path .= static::_getClassFilename($className);

        return static::_pluginPath($vendorNamespace, $path);
    }

/**
 * Get absolute path to package's class.
 *
 * @param string $className Class name.
 * @param array $options Mapped options.
 * @return string Absolute path to package's class file
 */
    protected static function _getPackageClassFilename($className, $options) {
        $path = $options['path'];
        $className = str_replace($options['prefix'], '', $className);
        return $path . DS . static::_getClassFilename($className);
    }

/**
 * Loads the given class.
 *
 * @param string $className The name of the class to load.
 * @return void
 */
    protected static function _loadFiles($options, $className) {
        if (empty($options['files'])) {
            return;
        }

        $vendorNamespace = static::getVendorNamespace($className);
        $path = $options['path'];
        foreach ($options['files'] as $file) {
            $filePath = static::_ds($path . $vendorNamespace . DS . $file);
            if (file_exists($filePath)) {
                include_once $filePath;
            }
        }
    }

/**
 * Normalize class namespace.
 *
 * @param string $className Namespaced classname.
 * @return string Normalized namespace
 */
    protected static function _normalizeNamespace($className) {
        return str_replace('/', static::$_namespaceDelimiter, $className);
    }

/**
 * Get the vendor namespace from given namespaced class name.
 *
 * @param string $className The name of the class to check.
 * @return string Vendor namespace
 */
    public static function getVendorNamespace($className) {
        $className = static::_normalizeNamespace($className);
        [$vendor] = splitter(ltrim($className, static::$_namespaceDelimiter), static::$_namespaceDelimiter);
        return $vendor;
    }

/**
 * Import vendor single class that's not compliant with NataPHP convention.
 *
 * @param string|array $className Valid namespace root for this vendor
 * @param string|array $options Options or path to class file
 * @param bool $core If this class is in the core (Nata) folder, set to true
 */
    public static function registerClass($className, $options = [], $core = false) {
        $options = static::_normalizeOptions($options, $core);

        if (!is_array($className)) {
            $className = [$className => $options];
        }

        foreach ($className as $name => $options) {
            $file = null;

            extract($options);

            if ($file === null && isset($baseDir)) {
                $file = $baseDir;
            }

            if (is_int($name)) {
                $name = $file;
                $file = null;
            }

            [$plugin, $name] = pluginSplit($name);
            if ($plugin) {
                $path = static::_pluginPath($plugin) . DS;
            } else {
                $path = (!$core ? ROOT : NATA);
            }

            $path .= 'vendor' . DS;
            if ($file === null) {
                $path .= $name . DS . $name . static::$_fileExtension;
            } else {
                $path .= $file;
            }

            static::map($name, [
                'type' => 'class',
                'name' => $name,
                'path' => static::_ds($path),
                'plugin' => $plugin
            ] + $options);

        }

        return true;
    }

/**
 * Unregister class.
 *
 * @param string $className Class name to unregister from map
 * @return void
 */
    public static function unregisterClass($className) {
        unset(static::$_map[$className]);
    }

/**
 * Register package (PSR-4).
 *
 *  // Load \Zend\Acl class in 'App/vendor/usr/includes/Zend/Acl.php'
 *  ClassLoader::registerPackage('Zend\\', '/usr/includes/Zend/');
 *
 *  // Load \PhpRbac\Rbac class in 'Nata/vendor/PhpRbac/Rbac.php'
 *  ClassLoader::registerPackage('PhpRbac\\', ['core' => true]);
 *
 * @param string|array $namespacePrefix Namespace Prefix
 * @param string|array $options Optional Base Directory
 * @param bool $core If this package is in the core (Nata/vendor/*) folder, set to true
 * @see http://www.php-fig.org/psr/psr-4/
 */
    public static function registerPackage($namespacePrefix, $options = [], $core = false) {
        if (is_string($namespacePrefix)) {
            $namespacePrefix = [$namespacePrefix => $options];
        }

        $default = [
            'type' => 'package',
            'baseDir' => null,
            'core' => $core,
            'plugin' => null
        ];

        foreach ($namespacePrefix as $prefix => $options) {
            if (is_int($prefix)) {
                $prefix = $options;
                $options = [];
            } elseif (is_string($options)) {
                $options = ['baseDir' => $options];
            } elseif (!is_array($options)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid options for class package "%s". Options must be an array, %s given.',
                    $prefix,
                    gettype($options)
                ));
            }

            [$options['plugin'], $prefix] = pluginSplit($prefix);

            $options['prefix'] = trim($prefix, static::$_namespaceDelimiter) . static::$_namespaceDelimiter;

            $options += $default;

            if ($options['plugin']) {
                $path = static::_pluginPath($options['plugin']);
            } else {
                $path = rtrim((!$options['core'] ? ROOT : NATA), DS);
            }

            $options['path'] = $path . DS . static::$_vendorDir;

            if (empty($options['baseDir'])) {
                $options['baseDir'] = $prefix;
            }

            $options['baseDir'] = trim($options['baseDir'], '/');
            $options['path'] .= DS . static::_ds($options['baseDir']);

            static::map($options['prefix'], $options);
        }

    }

/**
 * Normalize options.
 *
 * @param array|string $options Options to normalize
 * @return array
 */
    private static function _normalizeOptions($options, $core) {
        $default = [
            'baseDir' => (is_string($options) ? $options : null),
            'core' => (is_bool($options) ? $options : $core)
        ];

        if (!is_array($options)) {
            $options = [];
        }

        return $options + $default;
    }

/**
 * Unregister package.
 *
 * @param string $packageName Package name to unregister from map
 * @return void
 */
    public static function unregisterPackage($packageName) {
        unset(static::$_map[$packageName]);
    }

/**
 * Check if is a App class name.
 *
 * @param string $className The name of the class to check.
 * @return boolean True if is a Nata class
 */
    public static function isAppClass($className) {
        return strpos($className, 'App' . static::$_namespaceDelimiter) === 0;
    }

/**
 * Check if is a core/Nata class name.
 *
 * @param string $className The name of the class to check.
 * @return boolean True if is a Nata class
 */
    public static function isCoreClass($className) {
        return strpos($className, 'Nata' . static::$_namespaceDelimiter) === 0;
    }

/**
 * Check if is a plugin class name.
 *
 * @param string $className The name of the class to check.
 * @return boolean True if is a plugin class
 */
    public static function isPluginClass($className) {
        return !static::isAppClass($className) && !static::isCoreClass($className) && !static::map($className);
    }

/**
 * Get/set registered classes and packages.
 *
 * @param string $prefix Namespace prefix
 * @param array $config Config
 * @return array Mapped classes and packages or config
 */
    public static function map($prefix = null, array $config = []) {
        if ($prefix === null) {
            return static::$_map;
        }

        if ($config) {
            static::$_map[$prefix] = $config;
            return true;
        }

        foreach (static::$_map as $_prefix => $options) {
            if ($prefix === $_prefix && $options['type'] === 'class') {
                return $options;
            } elseif ($options['type'] === 'package' && stripos($prefix, $_prefix) === 0) {
                return $options;
            }
        }
    }

/**
 * Normalize directory separator in given path.
 *
 * @param string $path Path to normalize
 * @return string Normalize path
 */
    protected static function _ds($path) {
        return str_replace(['\\', '/'], DS, $path);
    }

/**
 * Get absolute path to plugin folder.
 * If $path is given it will add the relative path.
 *
 * @param string $plugin Plugin name
 * @param string $relativePath Relative path
 * @return string Absolute plugin path to folder
 */
    protected static function _pluginPath($plugin, $relativePath = null) {
        $path = rtrim(App::pluginPath($plugin), DS);

        if ($relativePath) {
            $path .= DS . static::_ds(ltrim($relativePath, DS));
        }

        return $path;
    }

/**
 * Loads the given class or interface.
 *
 * @param string $className The name of the class to load.
 * @return boolean
 */
    private static function _find($className, $location = 'vendor') {
        $location = str_replace(array('/','.'), DS, $location);
        $paths = array();

        if (is_dir(APP . $location)) {
            $paths[] = APP . $location;
        }

        if (is_dir(NATA . $location)) {
            $paths[] = NATA . $location;
        }

        foreach ($paths as $path) {
            $directory = new RecursiveDirectoryIterator($path);
            $it = new RecursiveIteratorIterator($directory);

            while ($it->valid()) {
                if ($it->isFile() && strpos($it->getFilename(), '.php') !== false) {
                    $filepath = $it->getRealPath();
                    $contents = file_get_contents( $filepath );
                    preg_match_all("/(class|interface)[\s\n]+([a-zA-Z0-9_,\\\\]+)[\s\na-zA-Z0-9_,\\\\]+\{/", $contents, $classes);
                    $namespace = array();

                    if (strpos($contents, 'namespace') !== false) {
                        preg_match_all("/namespace[\s\n]+([a-zA-Z0-9_\\\\]+)+[;|\{]/", $contents, $namespace);
                    }

                    /*
                     * Parse the file in search for class names
                     */
                    if (isset($classes[2]) && !empty($classes[2])) {
                        $pathinfo = pathinfo($filepath);
                        $relativePath = str_replace(array(APP, NATA), '', $pathinfo['dirname']);
                        $arrayPath = str_replace(DS, '.', $relativePath);
                        $arrayPath = str_replace('Plugin.', '', $arrayPath);

                        foreach ($classes[2] as $class) {
                            if (isset($namespace[1][0]) && !empty($namespace[1][0])) {
                                $class = trim($namespace[1][0]) . static::$_namespaceDelimiter . $class;
                            }

                            $classInfo = array(
                                $class,
                                $filepath,
                                $arrayPath
                            );

                            if ($class == $className) {
                                return $classInfo;
                            }

                        }
                    }
                }

                $it->next();
            }

        }

        unset($directory, $it, $contents, $classes);

        return true;
    }

}
