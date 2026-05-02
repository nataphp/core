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

namespace Nata\Filesystem\File;

use Nata\Filesystem\File;
use Nata\Filesystem\File\Image\Exif;
use Nata\Filesystem\File\Image\Set;
use Nata\Filesystem\File\Image\Editor;
use Nata\Utility\Math;

/**
 * Image class for image files.
 */
class Image extends File {

/**
 * Image width.
 *
 * @var int
 */
    protected $_width;

/**
 * Image height.
 *
 * @var int
 */
    protected $_height;

/**
 * Image DPI.
 *
 * @var int|float
 */
    protected $_dpi;

/**
 * Image orientation.
 *
 * @var string
 */
    protected $_orientation;

/**
 * EXIF meta data.
 *
 * @var array
 */
    protected $_exif;

/**
 * Aspect ratio.
 *
 * @var string
 */
    protected $_aspectRatio;

/**
 * Image Set instance.
 *
 * @var \Nata\Filesystem\File\Image\Set
 */
    protected $_set;

/**
 * Image Editor instance.
 *
 * @var \Nata\Filesystem\File\Image\Editor
 */
    protected $_editor;


/**
 * Constructor.
 *
 * @see \Nata\Filesystem\File::__construct()
 */
    public function __construct($path, $create = false, $mode = 0755) {
        parent::__construct($path, $create, $mode);

        // Prevent GD warnings
        // (this is the default since PHP 7.1, but not in older versions)
        ini_set('gd.jpeg_ignore_warning', 1);
    }

/**
 * Returns the file info as an array with the following keys:
 *
 * - dirname
 * - basename
 * - extension
 * - filename
 * - filesize
 * - mime
 * - width
 * - height
 * - orientation
 *
 * @param string $info Image information option.
 * @return array|string Image information.
 */
    public function info($info = null) {
        if ($this->_info === null) {
            parent::info();
        }

        if (!isset($this->_info['width']) || !isset($this->_info['height'])) {
            $this->_info += $this->getDimensions();

            $this->_info['orientation'] = $this->getOrientation();
            $this->_info['aspectRatio'] = $this->getAspectRatio();
        }

        return parent::info($info);
    }

/**
 * Get EXIF instance.
 *
 * @return \Nata\Filesystem\File\Image\Exif
 */
    public function getExif() {
        if ($this->_exif === null) {
            $this->_exif = new Exif($this);
        }
        return $this->_exif;
    }

/**
 * Get current image width.
 *
 * @return int Image's width
 */
    public function getWidth() {
        if ($this->_width === null) {
            $this->getDimensions();
        }
        return $this->_width;
    }

/**
 * Get current image height.
 *
 * @return int Image's height
 */
    public function getHeight() {
        if ($this->_height === null) {
            $this->getDimensions();
        }
        return $this->_height;
    }

/**
 * Get current image dimensions.
 *
 * @return array Image's dimensions
 */
    public function getDimensions() {
        if ($this->_width === null) {
            if ($this->exists()) {
                [$this->_width, $this->_height] = getimagesize($this->pwd());
            }
        }

        return [
            'width' => $this->_width,
            'height' => $this->_height
        ];

    }

/**
 * Get image's aspect ratio.
 *
 * @param int $limit Precision limit
 * @return string Image's aspect ratio
 */
    public function getAspectRatio() {
        if ($this->_aspectRatio === null && $this->getWidth() > 0 && $this->getHeight() > 0) {
            $this->_aspectRatio = Math::calcAspectRatio($this->getWidth(), $this->getHeight());
        }
        return $this->_aspectRatio;
    }

/**
 * Get current image orientation (portrait or landscape).
 *
 * @return string Image's orientation
 */
    public function getOrientation() {
        if ($this->_orientation === null) {
            $this->_orientation = $this->getWidth() > $this->getHeight() ? 'landscape' : 'portrait';
        }
        return $this->_orientation;
    }

/**
 * Get current image's editor instance.
 *
 * @param array $config Editor config
 * @return \Nata\Filesystem\File\Image\Editor Image's editor instance
 */
    public function getEditor(array $config = []) {
        if ($this->_editor === null) {
            $this->_editor = new Editor($this, $config);
        }
        return $this->_editor;
    }

/**
 * Get current image's set instance.
 *
 * @param array $config Set config
 * @return \Nata\Filesystem\File\Image\Set Image's set/collection instance
 */
    public function getSet(array $config = []) {
        if ($this->_set === null) {
            $this->_set = new Set($this, $config);
        }
        return $this->_set;
    }

/**
 * Returns true if image is rasterized.
 *
 * @todo this is not reliable, in the future make a better detection
 * @return boolean True if it's rasterized, false otherwise
 */
    public function isRasterized() {
        return in_array($this->mime(), ['image/gif', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/bmp']);
    }

}
