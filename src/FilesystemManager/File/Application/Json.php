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

namespace Nata\FilesystemManager\File\Application;

use Nata\FilesystemManager\File\Application;

/**
 * JSON file instance class for reading and writing JSON files.
 */
class Json extends Application {


/**
 * JSON data as array.
 *
 * @return array
 */
    public function toArray() {
        return $this->decode(true);
    }

/**
 * Decode JSON data.
 *
 * @param bool $associative When true, returned objects will be converted into associative arrays.
 * @return array|object|null
 */
    public function decode(?bool $associative = null, int $depth = 512, ?int $options = null) {
        if (!$this->exists()) {
            return null;
        }
        return json_decode($this->read(), $associative, $depth, $options);
    }

/**
 * Write given data to this file.
 *
 * @param string $data Data to write to this File.
 * @param string $mode Mode of writing. {@link http://php.net/fwrite See fwrite()}.
 * @param string $force Force the file to open
 * @return boolean Success
 */
    public function write($data, $mode = 'w', $force = false) {
        if ($this->_readOnly === true || ($this->_lock === true && flock($this->_handle, LOCK_EX) === false)) {
            return false;
        }

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }

        return parent::write($data, $mode, $force);
    }

}
