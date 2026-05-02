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

namespace Nata\Http\Client;

use DateTime;

/**
 * HTTP request.
 */
class Cookie {

/**
 * Cookie name.
 *
 * @var string
 */
    protected $_name;

/**
 * Cookie value.
 *
 * @var string
 */
    protected $_value;

/**
 * Cookie path.
 *
 * @var string
 */
    protected $_path = '/';

/**
 * Cookie domain.
 *
 * @var string
 */
    protected $_domain;

/**
 * Cookie expires data.
 *
 * @var string
 */
    protected $_expires;

/**
 * Cookie maximum age in seconds.
 *
 * @var int
 */
    protected $_maxAge;

/**
 * Cookie http only.
 *
 * @var bool
 */
    protected $_httpOnly;

/**
 * Cookie HTTPS.
 *
 * @var bool
 */
    protected $_secure;


/**
 * Constructor.
 *
 * @param array $config Cookie configuration
 * @return void
 */
    public function __construct($config) {
        $this->_name = $config['name'];
        $this->_value = $config['value'];
        $this->_path = $config['path'];
        $this->_domain = $config['domain'];
        $this->expires($config['expires']);
        $this->maxAge($config['maxAge']);
        $this->_httpOnly = $config['httpOnly'];
        $this->_secure = $config['secure'];
    }

/**
 * Get/Set name.
 *
 * @param string $name Cookie name
 * @return $this|string
 */
    public function name($name = null) {
        return $this->_property('_name', $name);
    }

/**
 * Get/Set value.
 *
 * @param string $value Cookie value
 * @return $this|string
 */
    public function value($value = null) {
        return $this->_property('_value', $value);
    }

/**
 * Get/Set path.
 *
 * @param string $path Cookie path
 * @return $this|string
 */
    public function path($path = null) {
        return $this->_property('_path', $path);
    }

/**
 * Get/Set domain.
 *
 * @param string $domain Cookie domain
 * @return $this|string
 */
    public function domain($domain = null) {
        return $this->_property('_domain', $domain);
    }

/**
 * Get/Set expiration date.
 *
 * @param string|int $expires Cookie expiration date
 * @return $this|string
 */
    public function expires($expires = null) {
        if (func_num_args() === 0) {
            return $this->_expires;
        }

        if (!empty($expires)) {
            $expires = $this->_expires($expires);
        }

        return $this->_property('_expires', $expires);
    }

/**
 * Get/Set maximum age of cookie.
 * Is will set also 'expires' attribute.
 *
 * @param string|int $maxAge Cookie age in seconds
 * @return $this|int
 */
    public function maxAge($maxAge = null) {
        if (func_num_args() === 0) {
            return $this->_maxAge;
        }

        if (!empty($maxAge)) {
            $maxAge = $this->_maxAge($maxAge);
        }

        return $this->_property('_maxAge', $maxAge);
    }

/**
 * Max age calculation.
 *
 * @param string|int $maxAge Date/Timestamp
 * @return int
 */
    private function _maxAge($date) {
        $date = $this->_toUnixtime($date);
        $date = $date - time();
        if ($date < 0) {
            $date = 0;
        }
        return $date;
    }

/**
 * Expiration date calculation.
 *
 * @param string|int $date Date/Timestamp
 * @return int
 */
    private function _expires($date) {
        $date = $this->_toUnixtime($date);
        return date(DateTime::COOKIE, $date);
    }

/**
 * Get/Set maximum age of cookie (in seconds).
 *
 * @param string|int $maxAge Cookie age in seconds
 * @return $this|int
 */
    private function _toUnixtime($date) {
        if (!is_numeric($date)) {
            $date = new DateTime($date);
            $date = $date->getTimestamp();
        } elseif ($date < time()) {
            $date = time() + $date;
        }
        if ($date <= 0 || empty($date)) {
            $date = 0;
        }
        return $date;
    }

/**
 * Get/Set httpOnly attribute.
 *
 * @param bool $httpOnly HTTP only attribute
 * @return $this|bool
 */
    public function httpOnly($httpOnly = null) {
        return $this->_property('_httpOnly', $httpOnly);
    }

/**
 * Get/Set secure/HTTPS.
 *
 * @param bool $secure Secure attribute
 * @return $this|bool
 */
    public function secure($secure = null) {
        return $this->_property('_secure', $secure);
    }

/**
 * Get/Set property value.
 *
 * @param string $property Property name
 * @param string $value Value
 * @return $this|mixed
 */
    protected function _property($property, $value = null) {
        if ($value === null) {
            return $this->{$property};
        }
        $this->{$property} = $value;
        return $this;
    }

/**
 * __toString.
 *
 * @return string Cookie
 */
    public function __toString() {
        $cookie = $this->_name . '=' . $this->_value;

        if ($this->_expires) {
            $cookie .= '; Expires=' . $this->_expires($this->_expires);
        }

        if ($this->_maxAge) {
            $cookie .= '; Max-Age=' . $this->_maxAge($this->_maxAge);
        }

        if ($this->_path) {
            $cookie .= '; Path=' . $this->_path;
        }

        if ($this->_httpOnly) {
            $cookie .= '; httpOnly';
        }

        if ($this->_secure) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

}