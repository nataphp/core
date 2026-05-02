<?php
/**
 * APC storage engine for cache.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Cache.Engine
 * @since         CakePHP(tm) v 1.2.0.4933
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Cache\Engine;

use Nata\Cache\Engine;

/**
 * APCu storage engine for cache.
 */
class Apcu extends Engine {

/**
 * Contains the compiled group names
 * (prefixed witht the global configuration prefix)
 *
 * @var array
 **/
    protected $_compiledGroupNames = [];

/**
 * Initialize the Cache Engine
 *
 * Called automatically by the cache frontend
 * To reinitialize the settings call Cache::engine('EngineName', [optional] settings = array());
 *
 * @param array $settings array of setting for the engine
 * @return boolean True if the engine has been successfully initialized, false if not
 * @see \Nata\Cache\Engine::__defaults
 */
    public function init($settings = []) {
        if (!isset($settings['prefix'])) {
            $settings['prefix'] = $this->_getAutoPrefix();
        }

        $settings += ['engine' => 'Apcu'];

        parent::init($settings);

        if (!function_exists('apcu_dec')) {
            return false;
        }

        return !env('CLI_MODE') || ini_get('apc.enable_cli') == 1;
    }

/**
 * Read information about given key.
 *
 * @param string $key Identifier for the data
 * @return array Information
 */
    public function info($key) {
        return apcu_key_info($key);
    }

/**
 * Write data for key into cache
 *
 * @param string $key Identifier for the data
 * @param mixed $value Data to be cached
 * @param integer $duration How long to cache the data, in seconds
 * @return boolean True if the data was successfully cached, false on failure
 */
    public function write($key, $value, $duration) {
        return apcu_store($key, $value, $duration);
    }

/**
 * Read a key from the cache
 *
 * @param string $key Identifier for the data
 * @return mixed The cached data, or false if the data doesn't exist, has expired, or if there was an error fetching it
 */
    public function read($key) {
        return apcu_exists($key) ? apcu_fetch($key) : null;
    }

/**
 * Increments the value of an integer cached key
 *
 * @param string $key Identifier for the data
 * @param integer $offset How much to increment
 * @return New incremented value, false otherwise
 */
    public function increment($key, $offset = 1) {
        return apcu_inc($key, $offset);
    }

/**
 * Decrements the value of an integer cached key
 *
 * @param string $key Identifier for the data
 * @param integer $offset How much to subtract
 * @return New decremented value, false otherwise
 */
    public function decrement($key, $offset = 1) {
        return apcu_dec($key, $offset);
    }

/**
 * Delete a key from the cache
 *
 * @param string $key Identifier for the data
 * @return boolean True if the value was successfully deleted, false if it didn't exist or couldn't be removed
 */
    public function delete($key) {
        return apcu_delete($key);
    }

/**
 * Check if a key exists.
 *
 * @param string $key Identifier for the data
 * @return bool True if exists, false otherwise
 */
    public function check($key) {
        return apcu_exists($key);
    }

/**
 * Delete all keys from the cache.  This will clear every cache config using APC.
 *
 * @param boolean $check If true, nothing will be cleared, as entries are removed
 *    from APC as they expired.  This flag is really only used by FileEngine.
 * @return boolean True Returns true.
 */
    public function clear($check) {
        if ($check) {
            return true;
        }

        $info = apcu_cache_info('user');
        $cacheKeys = $info['cache_list'];
        unset($info);

        foreach ($cacheKeys as $key) {
            if (strpos($key['info'], $this->_settings['prefix']) === 0) {
                apcu_delete($key['info']);
            }
        }

        return true;
    }

/**
 * Returns the `group value` for each of the configured groups
 * If the group initial value was not found, then it initializes
 * the group accordingly.
 *
 * @return array
 **/
    public function groups() {
        if (empty($this->_compiledGroupNames)) {
            foreach ($this->_settings['groups'] as $group) {
                $this->_compiledGroupNames[] = $this->_settings['prefix'] . $group;
            }
        }

        $groups = apcu_fetch($this->_compiledGroupNames);
        if (count($groups) !== count($this->_settings['groups'])) {
            foreach ($this->_compiledGroupNames as $group) {
                if (!isset($groups[$group])) {
                    apcu_store($group, 1);
                    $groups[$group] = 1;
                }
            }
            ksort($groups);
        }

        $result = [];
        $groups = array_values($groups);
        foreach ($this->_settings['groups'] as $i => $group) {
            $result[] = $group . $groups[$i];
        }
        return $result;
    }

/**
 * Increments the group value to simulate deletion of all keys under a group
 * old values will remain in storage until they expire.
 *
 * @return boolean success
 **/
    public function clearGroup($group) {
        $success = false;
        apcu_inc($this->_settings['prefix'] . $group, 1, $success);
        return $success;
    }

}
