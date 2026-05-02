<?php
/**
 * NataPHP Framework.
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

use Nata\Utility\Hash;
use BadMethodCallException;

/**
 * Managment of configuration for objects.
 */
trait ConfigAwareTrait {

/**
 * Configuration map.
 *
 * @var array
 */
    protected $_config = [];

/**
 * Configuration contexts map.
 *
 * @var array
 */
    protected $_configContexts = [];

/**
 * Current context.
 *
 * @var string
 */
    protected $_context = 'default';

/**
 * @todo Strict configuration.
 *
 * If true, it will check default Config
 * parameters and throw an exception if given
 * config parameter to get/set is not valid.
 *
 * @var bool
 */
    protected $_strictConfig = false;


/**
 * Get/Set configuration.
 *
 * It will merge the default config with the passed config if merge is true.
 * It will replace the config if merge is false.
 *
 * ## Examples
 *
 * ```php
 * $this->config('apiKey');
 * $this->config('apiKey', '1234567890');
 * $this->config('apiKey', '1234567890', true);
 * $this->config(['apiKey' => '1234567890']);
 * $this->config(['apiKey' => '1234567890'], false);
 * $this->config(['apiKey' => 'env:API_KEY']);
 * $this->config(['apiKey' => 'env:API_KEY'], true);
 * $this->config(['apiKey' => 'env:API_KEY'], false);
 * $this->config(['apiKey' => 'env:API_KEY'], true);
 * ```
 *
 * @param array|string $varName Parameter name.
 * @param mixed $value Parameter value.
 * @param bool $merge True to merge, false to replace
 * @return $this|mixed
 */
    final public function config($name = null, $value = null, $merge = true) {
        // Initialize context if not exists
        if ($this->_config === []) {
            $this->switchContext('default');
        }

        if ($name === null) {
            return $this->_config;
        }

        if (is_string($name) && $value !== null) {
            $value = $this->_resolveEnvValue($value);
            $this->_config = Hash::insert($this->_config, $name, $value);
            return $this;
        }

        if (!is_array($name)) {
            return Hash::get($this->_config, $name);
        }

        foreach ($name as $varName => $value) {
            if (method_exists($this, $varName)) {
                $this->{$varName}($value);
            }
        }

        $name = $this->_resolveEnvInConfig($name);
        $this->_config = $merge ?
            array_merge($this->_config, $name) :
            array_merge($this->_getDefaultConfig(), $name);

        return $this;
    }

/**
 * Add config context.
 *
 * @param string|array $contextName Name of the context to switch to
 * @param array $config Configuration for the context
 * @return $this
 * @throws \BadMethodCallException
 */
    final public function addContext(string|array $contextName, array $config = []): self {
        $default = $this->_getDefaultConfig();
        if (is_array($contextName)) {
            foreach ($contextName as $name => $config) {
                $this->addContext($name, $config);
            }
            return $this;
        }

        if ($contextName === 'default' && !empty($this->_config)) {
            throw new BadMethodCallException("Context name 'default' is reserved.");
        }

        $this->_configContexts[$contextName] = $this->_resolveEnvInConfig(array_merge($default, $config));

        if ($this->_config === []) {
            $this->switchContext('default');
        }

        return $this;
    }

/**
 * Switch to a different config context.
 *
 * @param string $contextName Name of the context to switch to
 * @return $this
 * @throws \BadMethodCallException
 */
    final public function switchContext(string $contextName): self {
        if (!isset($this->_configContexts[$contextName])) {
            $this->_configContexts[$contextName] = $this->_resolveEnvInConfig($this->_getDefaultConfig());
        }

        $this->_context = $contextName;
        $this->_config = &$this->_configContexts[$contextName];

        return $this;
    }

/**
 * Remove a config context.
 *
 * @param string $contextName Name of the context to remove
 * @return $this
 */
    final public function removeContext(string $contextName) {
        if ($contextName !== 'default' && isset($this->_configContexts[$contextName])) {
            unset($this->_configContexts[$contextName]);
        }
        return $this;
    }

/**
 * Get default configuration.
 *
 * @return array
 */
    private function _getDefaultConfig(): array {
        $default = [];
        if (property_exists($this, '_defaultConfig')) {
            $default = $this->_defaultConfig;
        }
        return $default;
    }

/**
 * Resolve a single value: if string "env:NAME", return getenv("NAME").
 *
 * @param mixed $value
 * @return mixed
 */
    private function _resolveEnvValue(mixed $value): mixed {
        if (is_string($value) && str_starts_with($value, 'env:')) {
            $env = getenv(substr($value, 4));
            return $env !== false ? $env : $value;
        }
        return $value;
    }

/**
 * Recursively resolve "env:NAME" string values in a config array.
 *
 * @param array $config
 * @return array
 */
    private function _resolveEnvInConfig(array $config): array {
        $out = [];
        foreach ($config as $k => $v) {
            $out[$k] = is_array($v) ? $this->_resolveEnvInConfig($v) : $this->_resolveEnvValue($v);
        }
        return $out;
    }

}
