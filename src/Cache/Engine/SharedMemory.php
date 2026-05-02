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
 * @todo Shared memory engine for cache.
 */
class Shmop extends Engine {

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
            $settings['prefix'] = parent::_getAutoPrefix();
        }

        // Base API
        // Open the memory location; create/write.
        $memoryHandle = shmop_open(13523, "c", 0644, 100);

        // Set the data from the GET variable.
        shmop_write($memoryHandle, 'coolstuff', 0);

        $memoryHandle = shmop_open(13523, "a", 0644, 100);
        $string = shmop_read($memoryHandle, 0, 0);

        echo $string;
        die;

        $settings += ['engine' => 'Shmop'];

        parent::init($settings);

        if (!function_exists('shmop_open')) {
            return false;
        }

        return !env('CLI_MODE') || ini_get('apc.enable_cli') == 1;
    }

}
