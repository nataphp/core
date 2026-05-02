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

namespace Nata\Core\Configure;

use Nata\Core\App;
use Exception;

/**
 * An interface for creating objects compatible with Configure::load()
 */
abstract class Engine {

/**
 * The path this engine finds files on.
 *
 * @var string
 */
    protected $_path = '';

/**
 * The path this engine finds files on.
 *
 * @var string
 */
    protected $_extension;


/**
 * Read a configuration file/storage key
 *
 * This method is used for reading configuration information from sources.
 * These sources can either be static resources like files, or dynamic ones like
 * a database, or other datasource.
 *
 * @param string $key Key to read.
 * @return array An array of data to merge into the runtime configuration
 */
    public function read($key) {}

/**
 * Dumps the configure data into the storage key/file of the given `$key`.
 *
 * @param string $key The identifier to write to.
 * @param array $data The data to dump.
 * @return bool True on success or false on failure.
 */
    public function dump($key, array $data) {}

/**
 * Get file path.
 *
 * @param string $key The identifier to write to. If the key has a . it will be treated
 *  as a plugin prefix.
 * @param bool $checkExists Whether to check if file exists. Defaults to false.
 * @return string Full file path
 * @throws \Exception When files don't exist or when
 *  files contain '..' as this could lead to abusive reads.
 */
    protected function _getFilePath($key, $checkExists = false) {
        if (strpos($key, '..') !== false) {
            throw new Exception('Cannot load/dump configuration files with ../ in them.');
        }
        $file = $this->_path . DS . '.' . $key;
        [$plugin, $key] = pluginSplit($key);
        if ($plugin) {
            $file = App::path('Config', $plugin) . DS . $key;
        }
        $file .= $this->_extension;
        if (!$checkExists || is_file($file)) {
            return $file;
        }
        if (is_file(realpath($file))) {
            return realpath($file);
        }
        throw new Exception(sprintf('Could not load configuration file: %s', $file));
    }

}