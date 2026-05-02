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

namespace Nata\View;

use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Core\Configure;
use Nata\Cache\Cache;
use Nata\ORM\Entity;
use Nata\Event\Listener;
use Nata\Event\Manager;
use Nata\Routing\Router;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;
use Nata\I18n\I18n;
use Nata\View\NataCacheResource;
use Smarty\Smarty;
use Smarty\Exception as SmartyException;
use Smarty\CompilerException as SmartyCompilerException;
use ViewException;
use NataException;
use RunTimeException;

/**
 * The View is the V in MVC.
 */
class View extends NataObject implements Listener {

/**
 * Template base path.
 *
 * @var string
 */
    protected $_basePath;

/**
 * Template path.
 *
 * @var string
 */
    protected $_templatePath;

/**
 * Plugin name.
 *
 * @var string
 */
    protected $_plugin;

/**
 * View template name.
 *
 * @var string
 */
    protected $_template;

/**
 * View template locale.
 *
 * @var string
 */
    protected $_locale;

/**
 * View layout name.
 *
 * @var string
 */
    protected $_layout;

/**
 * Auto layout.
 *
 * @var bool
 */
    protected $_autoLayout = true;

/**
 * Template inheritance.
 *
 * @var array
 */
    protected $_extend;

/**
 * View theme.
 *
 * @var string
 */
    protected $_theme = 'default';

/**
 * Themed templates.
 *
 * @var bool
 */
    protected $_themed = false;

/**
 * Array of paths.
 *
 * @var array
 */
    protected $_paths = array();

/**
 * Cache configuration.
 *
 * @var array
 */
    protected $_cacheConfig;

/**
 * Array of helpers.
 *
 * @var array
 */
    public $helpers = [
        'Alert',
        'Style',
        'Script',
        'Url',
        'Form',
        'Image',
        'File'
    ];

/**
 * Helper Registry.
 *
 * @var \Nata\View\HelperRegistry
 */
    protected $_helpers;

/**
 * Passed variables to view.
 *
 * @var array
 */
    public $vars = [];

/**
 * File extension. Defaults to NataPHP's template '.tpl'.
 *
 * @var string
 */
    protected $_ext = '.tpl';

/**
 * Request.
 *
 * @var \Nata\Http\Request
 */
    public $request;

/**
 * Response.
 *
 * @var \Nata\Http\Response
 */
    public $response;

/**
 * Smarty instance.
 *
 * @var \Smarty
 */
    private $_smarty;

/**
 * Compile check.
 *
 * @var bool
 */
    protected $_compileCheck;

/**
 * Force compile.
 *
 * @var bool
 */
    protected $_forceCompile = false;


/**
 * View Constructor.
 *
 * @param \Nata\Http\Request $request Request instance
 * @param \Nata\Http\Response $response Response instance
 * @param \Nata\Event\Manager $eventManager Event manager instance
 * @param array $options View options
 */
    public function __construct(Request $request = null, Response $response = null, Manager $eventManager = null, array $options = array()) {
        $this->request = $request;
        $this->response = $response;

        if ($request === null) {
            $this->request = Router::getRequest();
        }

        $this->startupProcess();
    }

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @return \Nata\Event\Manager
 */
    public function initialize() {}

/**
 * Interact with the HelperRegistry to load all the helpers.
 *
 * @param array $helpers List of helpers.
 * @return $this
 */
    public function loadHelpers(array $helpers = null) {
        $registry = $this->helpers();

        if ($helpers !== null) {
            $this->helpers = array_merge($this->helpers, $helpers);
        }

        $helpers = $registry->normalizeArray($this->helpers);

        foreach ($helpers as $properties) {
            $this->loadHelper($properties['class'], $properties['config']);
        }

        $this->helpers = array();

        return $this;
    }

/**
 * Loads a helper. Delegates to the `HelperRegistry::load()` to load the helper
 *
 * @param string $name Name of the helper to load.
 * @param array $config Settings for the helper
 * @return Helper a constructed helper object.
 * @see HelperRegistry::load()
 */
    public function loadHelper($name, array $config = array()) {
        list($p, $class) = pluginSplit($name);
        return $this->{$class} = $this->helpers()->load($name, $config);
    }

/**
 * Get the helper registry in use by this View class.
 *
 * @return \Nata\View\HelperRegistry
 */
    public function helpers() {
        if ($this->_helpers === null) {
            $this->_helpers = new HelperRegistry($this);
        }
        return $this->_helpers;
    }

/**
 * Lazy loading of Helper class.
 *
 * @param string $name Class name
 * @return mixed
 */
    public function __get($name) {
        if ($this->helpers()->exists($name)) {
            return $this->{$name} = $this->helpers()->load($name);
        }
    }

/**
 * Perform the startup process for this controller.
 * Fire the Components and Controller callbacks in the correct order.
 *
 * - Initializes components, which fires their `initialize` callback
 * - Calls the controller `beforeFilter`.
 * - triggers Component `startup` methods.
 *
 * @return void
 */
    public function startupProcess() {
        $this->dispatchEvent('View.initialize');
        $this->dispatchEvent('View.startup');
    }

/**
 * Returns a list of all events that will fire in the View during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return [
            'View.beforeRender' => 'beforeRender',
            'View.afterRender' => 'afterRender',
            'View.shutdown' => 'beforeShutdown'
        ];
    }

/**
 * Perform the various shutdown processes for this View.
 * Fire the Components and Controller callbacks in the correct order.
 *
 * - triggers the component `shutdown` callback.
 * - calls the Controller's `afterFilter` method.
 *
 * @return void
 */
    public function shutdownProcess() {
        $this->dispatchEvent('View.shutdown');
    }

/**
 * Saves a variable for use inside a view template.
 *
 * @param string|array $one A string or an array of data to set.
 * @param string|array $two Value in case $one is a string (which then works as the key).
 *   Unused if $one is an associative array, otherwise serves as the values to $one's keys.
 * @return $this
 */
    public function set($one, $two = null) {
        if (($one instanceof Entity)) {
            $one = $one->toArray();
        }

        if (is_array($one)) {
            if (is_array($two)) {
                $data = array_combine($one, $two);
            } else {
                $data = $one;
            }
        } else {
            if (strpos($one, '.') !== false) {
                $this->vars = Hash::insert($this->vars, $one, $two);
                return $this;
            }
            $data = array($one => $two);
        }

        $this->vars = $data + $this->vars;

        return $this;
    }

/**
 * Get a variable value.
 *
 * @param string $var Null to get all, var name to return value.
 * @param mixed $default The default/fallback content of $var.
 * @return string|array
 */
    public function get($var = null, $default = null) {
        if ($var === null) {
            return $this->vars;
        }
        return Hash::get($this->vars, $var, $default);
    }

/**
 * Returns a list of variables available in the current View context.
 *
 * @return array Array of the set view variable names.
 */
    public function getVars() {
        return array_keys($this->vars);
    }

/**
 * Set/Get template base path.
 * Base path is applied before the theme and/or template path.
 *
 * @param string $basePath Prefix.
 * @return string|$this
 */
    public function basePath($basePath = null) {
        if (func_num_args() === 0) {
            return $this->_basePath;
        }

        $this->_basePath = $basePath;

        return $this;
    }

/**
 * Set/Get template path.
 *
 * @param string $templatePath View path.
 * @return string|$this
 */
    public function templatePath($templatePath = null) {
        if (func_num_args() === 0) {
            return $this->_templatePath;
        }
        $this->_templatePath = $templatePath;
        return $this;
    }

/**
 * Set/Get the plugin.
 *
 * @param string $plugin Plugin name.
 * @return string|$this
 */
    public function plugin($plugin = null) {
        if (func_num_args() === 0) {
            return $this->_plugin;
        }
        $this->_plugin = $plugin;
        return $this;
    }

/**
 * Set/Get auto layout option.
 *
 * @param bool $autoLayout Auto layout.
 * @return bool|$this
 */
    public function autoLayout($autoLayout = null) {
        if (func_num_args() === 0) {
            return $this->_autoLayout;
        }
        $this->_autoLayout = $autoLayout;
        return $this;
    }

/**
 * Set/Get template inheritance.
 *
 * @param string|array $extend Inheritance
 * @return array|$this
 */
    public function extend($extend = null) {
        if (func_num_args() === 0) {
            return $this->_extend;
        }
        $this->_extend = $extend ? $extend : [];
        return $this;
    }

/**
 * Set/Get template layout name.
 *
 * @param string $layout Layout name
 * @return string|$this
 */
    public function layout($layout = null) {
        if (func_num_args() === 0) {
            return $this->_layout;
        }
        $this->_layout = $layout;
        return $this;
    }

/**
 * Set/Get template name.
 *
 * @param string $template Template name
 * @return string|$this
 */
    public function template($template = null) {
        if (func_num_args() === 0) {
            return $this->_template;
        }
        $this->_template = $template;
        return $this;
    }

/**
 * Set/Get template locale.
 *
 * This is used to append given locale to a template name.
 *
 * @param string $locale Locale
 * @return string|$this
 */
    public function locale($locale = null) {
        if (func_num_args() === 0) {
            return $this->_locale;
        }
        $this->_locale = $locale;
        return $this;
    }

/**
 * Set/Get NataPHP template extension.
 *
 * @param string $ext Template extension.
 * @return string|$this
 */
    public function ext($ext = null) {
        if (func_num_args() === 0) {
            return $this->_ext;
        }

        $this->_ext = $ext;
        return $this;
    }

/**
 * Set/Get themed setting.
 *
 * @param bool $themed Enable themed template
 * @return bool|$this
 */
    public function themed($themed = null) {
        if ($themed === null) {
            return $this->_themed;
        }
        $this->_themed = $themed;
        return $this;
    }

/**
 * Set/Get theme.
 *
 * @param string $theme Null to get, theme name to set.
 * @return string|$this
 */
    public function theme($theme = null) {
        if (func_num_args() === 0) {
            return $this->_theme;
        }
        $this->_theme = $theme;
        return $this;
    }

/**
 * Set/get template paths.
 *
 * @param string $template Null to get, template name to set.
 * @return array List of possible paths for template
 */
    private function _paths($template = null) {
        if ($template === null) {
            return $this->_paths;
        }

        $paths = [];
        $path = $this->_templatePath;
        $theme = $basePath = '';

        // @todo Support for template name with localized content
        if ($this->_locale) {
            $template .= '_' . $this->_locale;
        }

        // Template path
        list($plugin, $_template) = pluginSplit($template);

        if (strpos($_template, '/') !== false) {
            $templatePath = explode('/', $_template);

            array_pop($templatePath);

            $_path = implode('/', $templatePath);
            if (substr($_template, 0, 1) === '/') {
                $path = ltrim($_path, '/');
            } else {
                $path .= '/' . $_path;
            }
        }

        if (!empty($path)) {
            $path = DS . $path;
        }

        if (!$plugin) {
            $plugin = $this->_plugin;
            if ($plugin) {
                $plugin = Inflector::camelize($plugin);
            }
        }

        if ($this->_basePath) {
            $basePath = DS . $this->_basePath;
        }

        if ($this->_themed) {
            $theme = DS . $this->_theme;
        }

        // Avoid redo/reset paths
        $_pathKey = $plugin . $basePath . $theme . $path;
        if (isset($this->_paths[$_pathKey])) {
            return $this->_paths[$_pathKey];
        }

        // App
        $absolutePath = App::path('Template' . $basePath . $theme . $path);
        $paths = $this->_parentPaths($absolutePath, $paths);

        // Plugin
        if ($plugin) {
            $absolutePath = App::path('Template' . $basePath . $theme . $path, $plugin);
            $paths = $this->_parentPaths($absolutePath, $paths);
        }

        // Nata
        $absolutePath = App::core('Template' . $path);
        $paths = $this->_parentPaths($absolutePath, $paths);
        $this->_paths[$_pathKey] = $paths;

        return $this->_paths[$_pathKey];
    }

/**
 * Get paths to folder's parent.
 *
 * @param string $deepPath Deepest path.
 * @param array $paths Paths array container.
 * @return array
 */
    protected function _parentPaths($deepPath, array $paths) {
        $paths[] = $deepPath;
        $exploded = explode(DS, $deepPath);
        $index = (array_search('Template', $exploded) + 1);
        for ($int = count($exploded); $int > $index; $int--) {
            $deepPath = $paths[] = dirname($deepPath);
        }
        return $paths;
    }

/**
 * Check if view template exists.
 *
 * @param string $template View parameters
 * @param boolean $recursive True to check on all paths
 * @return boolean True if exists, false otherwise
 */
    public function exists($template = null, $recursive = false) {
        if ($template === null) {
            $template = $this->template();
        }
        $_template = $this->_normalizeTemplate($template);
        foreach ($this->_paths($template) as $index => $path) {
            if (file_exists($path . DS . $_template)) {
                return true;
            }
            if (!$recursive) {
                break;
            }
        }
        return false;
    }

/**
 * Render the view.
 * Uses Smarty as rendering engine.
 *
 * @param string $template Name of the view template to use.
 * @param string $layout Name of the view layout to use.
 * @return string
 */
    public function render($template = null, $layout = null) {
        $smarty = $this->_loadSmarty();

        if ($layout !== null) {
            $this->_layout = $layout;
        }

        if ($template !== null) {
            $this->_template = $template;
        }

        if (empty($this->_template)) {
            throw new NataException('Template name not specified.');
        }

        $paths = $this->_paths($this->_template);

        $smarty->setTemplateDir($paths);
        $resource = $this->_templateResource($this->_template, $this->_layout);

        $this->loadHelpers();
        foreach ($this->helpers()->loaded() as $objectName) {
            $smarty->registerObject($objectName, $this->{$objectName});
        }

        $this->_setNataVars();

        $smarty->assign($this->vars + [
            'layout' => $this->_layout
        ]);

        $this->dispatchEvent('View.beforeRender');

        try {
            $render = $smarty->fetch($resource, $this->_getCacheId(), $this->_getCompileId());
            $this->dispatchEvent('View.afterRender');
            return $render;
        } catch (SmartyException $e) {
            $this->_handleSmartyException($e, $paths);
        }

    }

/**
 * Template inheritance.
 *
 * @param string $template Template name.
 * @param string $layout Layout name.
 * @return string Resource
 */
    protected function _templateResource($template, $layout) {
        $resource = '';
        $extend = (array)$this->extend();

        if (($this->_autoLayout && $layout) || !empty($extend)) {
            if ($layout) {
                array_unshift($extend, $layout);
            }

            $extend = $this->_normalizeTemplate($extend);
            $resource = 'extends:';
            $resource .= implode('|', $extend);
            $resource .= '|';
        }

        $resource .= $this->_normalizeTemplate($template);

        return $resource;
    }

/**
 * Normalize template file name.
 * It will add the file extension to the filename.
 *
 * @param string $template Template name.
 * @return string|array Template filename
 */
    protected function _normalizeTemplate($template) {
        $name = null;

        if (!empty($template)) {
            if (is_array($template)) {
                foreach ($template as $index => $tpl) {
                    $template[$index] = $this->_normalizeTemplate($tpl);
                }
                return $template;
            }

            list($p, $name) = pluginSplit($template);

            $name = pathinfo($name, PATHINFO_FILENAME);

            if (!empty($name)) {
                $name .= $this->_ext;
            }

        }

        return $name;
    }

/**
 * Handle Smarty exceptions.
 *
 * @param \SmartyException $exception Smarty Exception
 * @param array $paths Constructed paths to view
 * @return void
 */
    private function _handleSmartyException(SmartyException $exception, $paths) {
        $exceptionName = 'MissingTemplateException';
        $message = $exception->getMessage();

        $_message = array(
            'template' => $this->_template,
            'layout' => $this->_layout,
            'plugin' => $this->_plugin,
            'paths' => $paths,
            'template_path' => $this->_templatePath,
            'template_file' => $this->_normalizeTemplate($this->_template),
            'defaultpath' => (isset($paths[0]) ? $paths[0] : null),
        );

        if ((strpos($message, 'Unable to load') === false && strpos($message, 'extends') === false) || $exception instanceof SmartyCompilerException) {
            $exceptionName = 'TemplateCompilerException';
            $_message = $exception->getMessage();
        }

        throw new $exceptionName($_message, 500, $exception->getPrevious());
    }

/**
 * Load bundled Smarty plugins from NATA View/SmartyPlugin.
 *
 * @param Smarty $smarty Smarty instance
 * @return void
 */
    protected function _registerNataSmartyPlugins(Smarty $smarty): void {
        $dir = rtrim(NATA . App::ds('View/SmartyPlugin'), '/\\') . DIRECTORY_SEPARATOR;
        if (is_dir($dir)) {
            $smarty->addPluginsDir($dir);
        }
    }

/**
 * Load/Get Smarty instance.
 *
 * @return Smarty
 */
    public function _loadSmarty() {
        if ($this->_smarty === null) {
            $this->_smarty = new Smarty;

            $this->_smarty->compile_check = $this->compileCheck();
            $this->_smarty->force_compile = $this->forceCompile();
            $this->_smarty->merge_compiled_includes = false;

            $cacheBase = TMP . 'cache' . DS . 'smarty' . DS;
            $this->_smarty->setCompileDir($cacheBase . 'compile' . DS);
            $this->_smarty->setCacheDir($cacheBase . 'cache' . DS);
            $this->_registerNataSmartyPlugins($this->_smarty);
            $this->_smarty->registerFilter('pre', [I18nSmartyPrefilter::class, 'filter']);

            if ($this->_cacheConfig) {
                $this->_smarty->registerCacheResource('natacache', new NataCacheResource($this->_cacheConfig));
                $this->_smarty->merge_compiled_includes = true;
                $this->_smarty->caching_type = 'natacache';
                $this->_smarty->setCaching(Smarty::CACHING_LIFETIME_SAVED);
                $this->_smarty->setCacheLifetime($this->_cacheConfig['duration']);
            }
        }

        return $this->_smarty;
    }

/**
 * Set cache configuration.
 * By default cache key uses current:
 *  - template name
 *  - view path
 *  - theme (if set)
 *  - layout name
 *
 * ### Usage
 *
 * // Enable cache (passing true uses template name as key).
 * $view->cache(true);
 *
 * // Simple key and config set
 * $view->cache('my_view_key', 'view_templates');
 *
 * // Set key with a callback
 * $view->cache(function ($view) {
 *      return $view->_path . 'my_key';
 * }, 'view_templates');
 *
 * // Disable cache
 * $view->cache(false);
 *
 * @param string|bool $key Cache key
 * @param string $config Cache configuration
 * @return \Nata\View\View View instance
 * @throws \RunTimeException
 */
    public function cache($key, $config = 'default') {
        if ($key === false) {
            $this->_cacheConfig = null;
            return $this;
        }

        if (!is_string($config)) {
            throw new RunTimeException('Cache configs must be strings.');
        }

        if (is_callable($key)) {
            $key = $key($this);
        } elseif ($key === true) {
            $key = null;
        }

        $_config = [
            'duration' => 60,
            'groups' => null
        ] + Cache::settings($config);

        $this->_cacheConfig = array(
            'probability' => isset($_config['probability']) ? $_config['probability'] : 10,
            'duration' => $_config['duration'],
            'groups' => $_config['groups'],
            'key' => $key,
            'config' => $config
        );

        return $this;
    }

/**
 * Check is template is cached.
 *
 * @param string $template Name of the view template to use.
 * @param string $layout Name of the view layout to use.
 * @return boolean True if cached, false otherwise
 */
    public function isCached($template = null, $layout = null) {
        if ($template === null) {
            $template = $this->_template;
        }
        if ($layout === null) {
            $layout = $this->_layout;
        }

        if (empty($template)) {
            throw new ViewException('Unable to check cache exists. Missing template name.');
        }

        $smarty = $this->_loadSmarty();
        $paths = $this->_paths($template);

        $smarty->setTemplateDir($paths);
        $resource = $this->_templateResource($template, $layout);

        return $smarty->isCached($resource, $this->_getCacheId(), $this->_getCompileId());
    }

/**
 * Temporary.
 * This should be in \Nata\View\Cache
 *
 * @return array
 */
    public function _cacheConfig() {
        return $this->_cacheConfig;
    }

/**
 * Clear cache.
 *
 * @param string $template Name of the view template to clear.
 * @param string $layout Name of the view layout to use.
 * @return boolean True if cached, false otherwise
 */
    public function clearCache($template = null, $layout = null) {
        if ($template === null) {
            $template = $this->_template;
        }
        if ($layout === null) {
            $layout = $this->_layout;
        }

        $smarty = $this->_loadSmarty();
        $paths = $this->_paths($template);

        $smarty->setTemplateDir($paths);
        $resource = $this->_templateResource($template, $layout);

        return $smarty->clearCache($resource, $this->_getCacheId(), $this->_getCompileId());
    }

/**
 * Clear cache group.
 *
 * @param string $group Name of the view template to clear.
 * @return boolean True if cached, false otherwise
 */
    public function clearCacheGroup($group) {
        if (!$this->_cacheConfig) {
            $this->cache(true);
        }

        $group = strtolower(str_replace('.', '|', $group));
        return $this->_loadSmarty()->clearCache(null, $group);
    }

/**
 * Template cache ID.
 *
 * @return string Cache ID
 */
    protected function _getCacheId() {
        if (empty($this->_cacheConfig)) {
            return;
        }

        $key = $this->_cacheConfig['key'];

        $id = '';
        if (strpos($key, '.') === false) {
            $id .= $this->_basePath . '|' . $this->_templatePath;
        } else {
            $key = str_replace('.', '|', $key);
        }

        if ($key) {
            if (!empty($id)) {
                $id .= '|';
            }
            $id .= $key;
        }

        return strtolower($id);
    }

/**
 * Template compile ID.
 *
 * @return string
 */
    protected function _getCompileId() {
        $id = [];

        if ($this->_basePath) {
            $id[] = $this->_basePath;
        }

        if ($this->_layout) {
            $id[] = implode('_', (array)$this->_layout);
        }

        if ($this->_templatePath) {
            $id[] = $this->_templatePath;
        }

        if ($this->_themed) {
            $id[] = $this->_theme;
        }

        return strtolower(implode('_', $id));
    }

/**
 * Check if compile is required.
 *
 * @param bool $compileCheck Compile check
 * @return $this
 */
    public function compileCheck($compileCheck = null) {
        if ($compileCheck === null) {
            if ($this->_compileCheck === null) {
                $this->_compileCheck = Configure::read('development');
            }
            return $this->_compileCheck;
        }

        $this->_compileCheck = $compileCheck;
        return $this;
    }

/**
 * Force compile.
 *
 * @param bool $forceCompile Force compile
 * @return $this
 */
    public function forceCompile($forceCompile = null) {
        if ($forceCompile === null) {
            return $this->_forceCompile;
        }

        $this->_forceCompile = $forceCompile;
        return $this;
    }

/**
 * Set basic and static system data.
 *
 * @return void
 */
    private function _setNataVars() {
        if (isset($this->vars['nata'])) {
            return;
        }

        $configure = Configure::read();

        $this->vars['nata'] = new Entity([
            'configure' => $configure,
            'config' => $configure['Config'],
            'lang' => I18n::catalog(),
            'request' => $this->request,
            'app' => (array)$configure['App'] + (array)$configure['Config']
        ]);

    }

/**
 * Set/Get property.
 *
 * @param string $property Property name
 * @return string|$this
 */
    protected function _property($property, $value = null) {
        if ($value === null) {
            return $this->{$property};
        }

        $this->{$property} = $value;
        return $this;
    }

/**
 * Called before view is rendered. You can use this method
 * to perform logic or set view variables that are required on every request.
 *
 * @return void
 */
    public function beforeRender() {}

/**
 * Called after view is rendered. You can use this method
 * to perform logic or set view variables that are required on every request.
 *
 * @return void
 */
    public function afterRender() {}

}
