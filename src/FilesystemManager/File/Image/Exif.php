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

namespace Nata\FilesystemManager\File\Image;

use Nata\Filesystem\File as FilesystemFile;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\FileFactory;

/**
 * EXIF extractor.
 */
class Exif {

/**
 * EXIF data.
 *
 * @var array
 */
    private $_data = [
        'orientation' => 1,
    ];


/**
 * Constructor.
 *
 * @param \Nata\FilesystemManager\File|string $file
 * @return void
 */
    public function __construct(File|string $file) {
        if (!($file instanceof File)) {
            $file = FileFactory::build($file);
        }

        if ($file->exists() && ($file->is('jpg') || $file->is('tiff'))) {
            if ($file->open() === true && $handle = $file->handle()) {
                $this->_data = exif_read_data($handle);
                rewind($handle);
            }
        }
    }

/**
 *
 * @param string $name Property name
 * @return mixed
 */
    public function __get(string $name): mixed {
        return $this->get($name);
    }

/**
 * __set.
 *
 * @param string $name Property name
 * @param mixed $name Property name
 * @return void
 */
    public function __set(string $name, mixed $value) {
        $this->_data[$name] = $value;
    }

/**
 * Get property value.
 *
 * @param string $property EXIF data
 * @return $this|bool
 */
    public function get($property = null): mixed {
        if (!$this->_data) {
            return null;
        }
        if ($property === null) {
            return $this->_data;
        }
        return $this->_get($this->_data, $property);
    }

/**
 * Get property value.
 *
 * @param string $property EXIF data
 * @return $this|bool
 */
    protected function _get($data, $property) {
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
