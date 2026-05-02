<?php
/**
 * NataPHP Framework.
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

namespace Nata\Http\Client;

/**
 * Cookie collection.
 */
class CookieCollection {

/**
 * Map of loaded objects.
 *
 * @var array
 */
    protected $_loaded = [];

/**
 * Element's default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'name' => null,
        'value' => null,
        'path' => '/',
        'expires' => null,
        'domain' => null,
        'maxAge' => null,
        'httpOnly' => true,
        'secure' => false
    ];


/**
 * Add cookie instance.
 *
 * @param string|array $name Element name of array of config
 * @param array $config Element configuration
 * @return \Nata\Form\Element
 */
    public function add($name, $config = []) {
        if (is_array($name)) {
            $config = $name;
        } else {
            if (!is_array($config)) {
                $config = ['value' => $config];
            }
            $config['name'] = $name;
        }
        $config += $this->_defaultConfig;
        return $this->_loaded[$config['name']] = new Cookie($config);
    }

/**
 * Get cookie.
 *
 * @param string|array $name Element name of array of config
 * @param array $config Element configuration
 * @return \Nata\Form\Element
 */
    public function get($name = null) {
        if ($name === null) {
            return $this->_loaded;
        }
        return isset($this->_loaded[$name]) ? $this->_loaded[$name] : null;
    }

/**
 * Clear all cookies.
 *
 * @return void
 */
    public function clear() {
        $this->_loaded = [];
    }

/**
 * Get cookie.
 *
 * @param string|array $name Element name of array of config
 * @param array $config Element configuration
 * @return \Nata\Form\Element
 */
    public function toString() {

    }

/**
 * Get cookie.
 *
 * @param string|array $name Element name of array of config
 * @param array $config Element configuration
 * @return \Nata\Form\Element
 */
    public function __toString() {
        return $this->toString();
    }

}