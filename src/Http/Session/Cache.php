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

namespace Nata\Http\Session;

use Nata\Cache\Cache as NataCache;
use InvalidArgumentException;
use SessionHandlerInterface;

/**
 * Http\Session\Cache provides method for saving sessions into a Cache engine. Used with Http\Session.
 */
class Cache implements SessionHandlerInterface {

/**
 * Options for this session engine
 *
 * @var array
 */
    protected $_options = [];


/**
 * Constructor.
 *
 * @param array $config The configuration to use for this engine
 * It requires the key 'config' which is the name of the Cache config to use for
 * storing the session
 *
 * @throws \InvalidArgumentException if the 'config' key is not provided
 */
    public function __construct(array $config = []) {
        if (empty($config['config'])) {
            throw new InvalidArgumentException('The cache configuration name to use is required');
        }
        $this->_options = $config;
    }

/**
 * Method called on open of a database session.
 *
 * @param string $savePath The path where to store/retrieve the session.
 * @param string $name The session name.
 * @return bool Success
 */
    public function open($savePath, $name): bool {
        return true;
    }

/**
 * Method called on close of a database session.
 *
 * @return bool Success
 */
    public function close(): bool {
        return true;
    }

/**
 * Method used to read from a cache session.
 *
 * @param string|int $id ID that uniquely identifies session in cache.
 * @return string Session data or empty string if it does not exist.
 */
    public function read($id): string {
        $value = NataCache::read($id, $this->_options['config']);

        if (empty($value)) {
            return '';
        }

        return $value;
    }

/**
 * Helper function called on write for cache sessions.
 *
 * @param string|int $id ID that uniquely identifies session in cache.
 * @param mixed $data The data to be saved.
 * @return bool True for successful write, false otherwise.
 */
    public function write($id, $data): bool {
        if (!$id) {
            return false;
        }

        return (bool)NataCache::write($id, $data, $this->_options['config']);
    }

/**
 * Method called on the destruction of a cache session.
 *
 * @param string|int $id ID that uniquely identifies session in cache.
 * @return bool Always true.
 */
    public function destroy($id): bool {
        NataCache::delete($id, $this->_options['config']);
        return true;
    }

/**
 * Helper function called on gc for cache sessions.
 *
 * @param int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed.
 * @return bool Always true.
 */
    public function gc($maxlifetime): bool {
        NataCache::gc($this->_options['config'], time() - $maxlifetime);
        return true;
    }

}
