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

namespace Nata\Filesystem\File\Image;

use Nata\Filesystem\File;
use Nata\Filesystem\File\Image\Exif\PngMetadata;

/**
 * EXIF extractor.
 */
class Exif {

/**
 * EXIF data.
 *
 * @var array
 */
    private $_data;


/**
 * Constructor.
 *
 * @param \Nata\Filesystem\File|string $file
 */
    public function __construct($file) {
        if (!($file instanceof File)) {
            $file = new File($file);
        }

        if ($file->exists()) {
            if ($file->is('png')) {
                // $pngMetadata = new PngMetadata($file->pwd());
                // $this->_data = $pngMetadata->toArray();
            } elseif ($file->is('svg') || $file->is('svg+xml')) {
            } else {
                $this->_data = exif_read_data($file->pwd());
            }
        }

    }

/**
 * __get method.
 *
 * @param string $name Property name
 * @return mixed
 */
    public function __get($name) {
        return $this->get($name);
    }

/**
 * __set method.
 *
 * @param string $name Property name
 * @return mixed
 */
    public function __set($name, $value) {
        $this->{$name} = $value;
    }

/**
 * Get property value.
 *
 * @param string| $property EXIF data
 * @return $this|bool
 */
    public function get($property = null) {
        if ($this->_data) {
            if ($property === null) {
                return $this->_data;
            }
            return $this->_get($this->_data, $property);
        }
    }

/**
 * Get property value.
 *
 * @param string| $property EXIF data
 * @return $this|bool
 */
    public function _get($data, $property) {
        if (isset($this->{$property})) {
            return $this->{$property};
        }

        $value = null;

        foreach ($data as $_property => $_value) {
            if (is_array($_value)) {
                $value = $this->_get($_value, $property);
            } elseif (strtolower($property) === strtolower($_property)) {
                $value = $_value;
            }

            if ($value !== null) {
                break;
            }
        }

        return $this->{$property} = $value;
    }

}
