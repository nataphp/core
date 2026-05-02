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

namespace Nata\ORM;

use Nata\Cache\Cache as Cacher;
use InvalidArgumentException;

/**
 * Query cache handling.
 */
class Cache {

/**
 * Cache config name.
 *
 * @var string
 */
    private $_config;

/**
 * Cache key.
 *
 * @var string
 */
    private $_key;


/**
 * Constructor.
 *
 * @param string $key Cache key
 * @param string $config Cache configuration
 * @return void
 * @throws \InvalidArgumentException
 */
    public function __construct($key, $config) {
        $this->_key = $key;
        if (!is_string($config)) {
            throw new InvalidArgumentException('Cache config must be a string.');
        }
        $this->_config = $config;
    }

/**
 * Write to cache.
 *
 * @param mixed $data Data to save
 * @return $this
 */
    public function write($data) {
        return Cacher::write($this->_key, $data, $this->_config);
    }

/**
 * Read from cache.
 *
 * @return mixed
 */
    public function fetch() {
        return Cacher::read($this->_key, $this->_config);
    }

/**
 * Clear cache.
 *
 * @return $this
 */
    public function clear() {
        Cacher::clear($this->_key, $this->_config);
        return $this;
    }

/**
 * Get current cache key.
 *
 * @return string
 */
    public function key() {
        return $this->_key;
    }

}
