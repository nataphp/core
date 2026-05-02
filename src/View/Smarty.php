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
use Nata\Core\Configure;
use Nata\I18n\I18n;
use Smarty\Smarty as SmartyClass;
use Smarty\Exception as SmartyException;
use Smarty\CompilerException as SmartyCompilerException;
use ViewException;
use NataException;
use MissingTemplateException;
use RunTimeException;

/**
 * The View is the V in MVC.
 */
class Smarty extends View {

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
 * View template name.
 *
 * @var string
 */
    protected $_template;

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
    public $helpers = array(
        'Alert',
        'Style',
        'Script',
        'Url',
        'Image'
    );

/**
 * Helper Registry.
 *
 * @var \Nata\View\HelperRegistry
 */
    protected $_helpers;

/**
 * Helpers loaded.
 *
 * @var boolean
 */
    private $_helpersLoaded = false;

/**
 * File extension. Defaults to NataPHP's template '.tpl'.
 *
 * @var string
 */
    protected $_ext = '.tpl';

/**
 * Smarty instance.
 *
 * @var \Smarty
 */
    protected $_smarty;

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

        $paths = array();
        $path = $this->_templatePath;
        $theme = $basePath = '';

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

        // Plugin
        if ($plugin) {
            $absolutePath = App::path('Template' . $basePath . $theme . $path, $plugin);
            $paths = $this->_parentPaths($absolutePath, $paths);
        }

        // App
        $absolutePath = App::path('Template' . $basePath . $theme . $path);
        $paths = $this->_parentPaths($absolutePath, $paths);

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
        $smarty = $this->_smarty();

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

        $smarty->assign($this->vars + array(
            'layout' => $this->_layout
        ));

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
 * @return string Template filename
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
 * Load/Get Smarty instance.
 *
 * @return \Smarty
 */
    protected function _smarty() {
        if ($this->_smarty === null) {
            $this->_smarty = new SmartyClass;

            $this->_smarty->compile_check = $this->compileCheck();
            $this->_smarty->force_compile = $this->forceCompile();
            $this->_smarty->inheritance_merge_compiled_includes = false;

            $cacheBase = TMP . 'cache' . DS . 'smarty' . DS;
            $this->_smarty->setCompileDir($cacheBase . 'compile' . DS);
            $this->_smarty->setCacheDir($cacheBase . 'cache' . DS);

            if ($this->_cacheConfig) {
                $this->_smarty->addPluginsDir(NATA . App::ds('View/SmartyPlugin'));
                $this->_smarty->caching_type = 'natacache';
                $this->_smarty->inheritance_merge_compiled_includes = true;
                $this->_smarty->setCaching(SmartyClass::CACHING_LIFETIME_SAVED);
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
 * $view->cache(function($view) {
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

        $_config = Cache::settings($config);

        $this->_cacheConfig = array(
            'probability' => isset($_config['probability']) ? $_config['duration'] : 10,
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

        $smarty = $this->_smarty();
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

        $smarty = $this->_smarty();
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
        return $this->_smarty()->clearCache(null, strtolower($group));
    }

/**
 * Template cache ID.
 *
 * @return string Cache ID
 */
    protected function _getCacheId() {
        if ($this->_cacheConfig) {
            $key = $this->_cacheConfig['key'];
            
            $id = '';
            
            if (strpos($key, '.') === false) {
                $id = $this->_templatePath . '|' . $this->_basePath;
            }

            if ($key) {
                $id .= '|' . $key;
            }
            $id .= '_' . $this->_cacheConfig['config'];

            return strtolower($id);
        }

    }

/**
 * Template compile ID.
 *
 * @return string
 */
    protected function _getCompileId() {
        $id = str_replace(array(DS, '/'), '_', $this->_basePath . $this->_templatePath);

        if ($this->_themed) {
            $id .= $this->_theme;
        }

        return $id;
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

        $this->vars['nata'] = array(
            'configure' => $configure,
            'config' => $configure['Config'],
            'lang' => I18n::catalog(),
            'request' => $this->request,
            'app' => $configure['App'] + $configure['Config']
        );

    }

}
