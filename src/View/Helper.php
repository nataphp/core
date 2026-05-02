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
use Nata\Cache\Cache;
use Nata\Core\NataObject;
use Nata\Utility\Hash;
use Nata\Filesystem\File;
use Nata\Filesystem\FileRepository;
use Nata\FilesystemManager\File as FilesystemManagerFile;
use Nata\FilesystemManager\FileFactory;
use Nata\FilesystemManager\FileStorage;
use Nata\Routing\Router;

class Helper extends NataObject {

/**
 * View instance
 *
 * @var \Nata\View\View
 */
    protected $_view;

/**
 * Default helper configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [];

/**
 * Default Smarty parameters.
 *
 * @var array
 */
    protected $_defaultParams = [];

/**
 * Smarty parameters.
 *
 * @var array
 */
    protected $_params = [];

/**
 * Runtime cache.
 *
 * @var array
 */
    protected $_rtCache = [];


/**
 * Constructor.
 *
 * @param \Nata\View\View $view View instance
 * @param array $config Helper configuration
 * @return void
 */
    public function __construct(View $view, array $config = array()) {
        $this->_view = $view;
        $this->config($config + $this->_defaultConfig);
        $this->initialize($config);
    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Get/set parameter(s).
 *
 * @param array|string $path Path to value
 * @param mixed $default Value returned if path not found
 * @return mixed
 */
    public function params($param = null, $value = null) {
        if (func_num_args() === 0) {
            return $this->_params;
        }

        if (func_num_args() === 1) {
            return isset($this->_params[$param]) ? $this->_params[$param] : null;
        }

        $this->_params[$param] = $value;

        return $this;
    }

/**
 * Get parameter.
 *
 * @param array|string $path Path to value
 * @param mixed $default Value returned if path not found
 * @return mixed
 */
    public function param($path, $default = null) {
        return Hash::get($this->params(), $path, $default);
    }

/**
 * Normalize Smarty parameters.
 *
 * @param array $params Null to get, array to set
 * @param array|null $params Null to get, array to set
 * @return array|$this
 */
    protected function _normalizeParams(array $params) {
        return $params + $this->_defaultParams;
    }

/**
 * Get URL from give file path/instance.
 *
 * @param mixed $url Data to check for url
 * @return string Url
 */
    protected function _output($file, $params) {
        $params = $this->_normalizeParams($params);
        if (is_string($file) && strpos($file, 'data:') === 0) {
            return $file;
        }

        $options = ['url', 'base64', 'dataUri', 'path', 'fileUrl'];

        // For BC reasons
        // use 'print' instead!
        if (in_array($params['src'], $options)) {
            $params['print'] = $params['src'];
        }

        // BC reasons
        if ($params['print'] !== 'url') {
            if (!($file instanceof File)) {
                if (substr($file, 0, 7) !== '/public') {
                    $file = '/public' . $file;
                }
                $file = App::path($file);
                $file = FileFactory::build($file);
            }

            if ($params['print'] == 'base64') {
                return $file->getBase64();
            } elseif ($params['print'] == 'dataUri') {
                return $file->getDataUri();
            } elseif ($params['print'] == 'path' || $params['print'] == 'fileUrl') {
                return $file->getFileUrl();
            }
        }

        return $this->_routeUrl($file, $params);
    }

/**
 * Get routed url.
 *
 * @param string $url URL to route
 * @param array $params Smarty passed parameters
 * @return string Routed Url
 */
    protected function _routeUrl($url, array $params) {
        $params += [
            'secure' => null,
            'full' => false,
            'host' => null
        ];

        if ($url instanceof File) {
            $url = FileRepository::url($url);
        } elseif ($url instanceof FilesystemManagerFile) {
            $url = FileStorage::url($url, $url->metadata('store'), options:$params);
        }

        if ($params['secure'] !== null) {
            $params['full'] = true;
        }

        $url = Router::url($url, $params['full']);
        if ($params['host']) {
            $host = parse_url($url, PHP_URL_HOST);
            $url = str_replace($host, $params['host'], $url);
        }

        if ($params['secure'] !== null) {
            $url = $params['secure'] == false
                ? str_replace('https:', 'http:', $url)
                : str_replace('http:', 'https:', $url);
        }

        return $url;
    }

/**
 * Runtime cache.
 *
 * @param string $key Cache key
 * @param mixed $data Data to cache
 * @return mixed Cached data
 */
    protected function _runtimeCache($key, $data = null) {
        if (func_num_args() === 1) {
            return isset($this->_rtCache[$key]) ? $this->_rtCache[$key] : null;
        }
        return $this->_rtCache[$key] = $data;
    }

/**
 * Cache.
 *
 * @param string $key Cache key
 * @param mixed $data Data to cache
 * @return mixed Cached data
 */
    protected function cache($key, $data = null) {
        $cache = $this->params('cache');

        if (!$cache) {
            return;
        }

        if ($data === null) {
            return Cache::read($key, $cache);
        }

        Cache::write($key, $data, $cache);
    }

}
