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

namespace Nata\FilesystemManager\File;

use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image\Set;
use Nata\Utility\Math;

/**
 * Video class for video files.
 */
class Video extends File {

/**
 * Video width.
 *
 * @var int
 */
    protected $_width;

/**
 * Video height.
 *
 * @var int
 */
    protected $_height;

/**
 * Video DPI.
 *
 * @var int|float
 */
    protected $_dpi;

/**
 * Video orientation.
 *
 * @var string
 */
    protected $_orientation;

/**
 * Video meta data.
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
 * Video Set instance.
 *
 * @var \Nata\FilesystemManager\File\Video\Set
 */
    protected $_set;


/**
 * Constructor.
 *
 * @see \Nata\FilesystemManager\File::__construct()
 */
    public function __construct($path, array $options = []) {
        $options += [
            'width' => null,
            'height' => null,
            'aspectRatio' => null,
        ];

        if ($options['width'] !== null) {
            $this->_width = $options['width'];
        }
        if ($options['height'] !== null) {
            $this->_height = $options['height'];
        }
        if ($options['aspectRatio'] !== null) {
            $this->_aspectRatio = $options['aspectRatio'];
        }

        parent::__construct($path, $options);

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
        parent::info($info);
        if (!isset($this->_info['width']) || !isset($this->_info['height'])) {
            $this->_info += $this->dimensions();
            $this->_info['orientation'] = $this->orientation();
            $this->_info['aspectRatio'] = $this->aspectRatio();
        }

        if ($info === null) {
            return $this->_info;
        }
        return $this->_info[$info] ?? null;
    }

/**
 * Get current image width.
 *
 * @return int Image's width
 */
    public function width() {
        if ($this->_width === null) {
            $this->dimensions();
        }
        return $this->_width;
    }

/**
 * Get current image height.
 *
 * @return int Image's height
 */
    public function height() {
        if ($this->_height === null) {
            $this->dimensions();
        }
        return $this->_height;
    }

/**
 * Get current image dimensions.
 *
 * @return array Image's dimensions
 */
    public function dimensions() {
        if ($this->_freeze === false && $this->_width === null) {
            $command = "ffprobe -v quiet -print_format json -show_streams " . escapeshellarg($this->getAbsoluteLocalPath());
            $output = shell_exec($command);
            $data = json_decode($output, true);

            if ($data && isset($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    if ($stream['codec_type'] === 'video') {
                        $this->_width = $stream['width'];
                        $this->_height = $stream['height'];
                    }
                }

                return [
                    'width' => $this->_width,
                    'height' => $this->_height
                ];
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
 * @return string Image's aspect ratio
 */
    public function aspectRatio() {
        if ($this->_aspectRatio === null && $this->width() > 0 && $this->height() > 0) {
            $this->_aspectRatio = Math::calcAspectRatio($this->width(), $this->height());
        }
        return $this->_aspectRatio;
    }

/**
 * Set image's aspect ratio.
 *
 * @param string $aspectRatio Image's aspect ratio
 * @return $this
 */
    public function setAspectRatio($aspectRatio) {
        $this->_aspectRatio = $aspectRatio;
        return $this;
    }

/**
 * Get current image orientation (portrait or landscape).
 *
 * @return string Image's orientation
 */
    public function orientation() {
        if ($this->_orientation === null && $this->width() > 0 && $this->height() > 0) {
            $this->_orientation = $this->width() > $this->height() ? 'landscape' : 'portrait';
        }
        return $this->_orientation;
    }

/**
 * Get/Set image set instance.
 *
 * @param Set|null $set Set instance
 * @return Set Image's Set instance
 */
    public function set(?Set $set = null) {
        if ($set === null) {
            if ($this->_set === null) {
                $this->_set = new Set($this);
            }
            return $this->_set;
        }
        $this->_set = $set;
        return $this;
    }

/**
 * Shorthand for current image's Set instance.
 *
 * @param array|string $config Config array or name
 * @return \Nata\FilesystemManager\File\Image\Set Image's set/collection instance
 */
    public function getSet(array $config = []) {
        return $this->set()->config($config);
    }

/**
 * Close the image resource.
 *
 * @return boolean
 */
    public function close() {
        $this->_set = null;
        return parent::close();
    }

/**
 * __clone.
 *
 * @return Image
 */
    public function __clone() {
        $img = $this;
        $img->_set = null;
        return $img;
    }

}
