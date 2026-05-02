<?php
/**
 * PhpThumb : PHP Thumb Library <http://phpthumb.gxdlabs.com>
 * Copyright (c) 2009, Ian Selby/Gen X Design
 *
 * Author(s): Ian Selby <ian@gen-x-design.com>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Ian Selby <ian@gen-x-design.com>
 * @copyright Copyright (c) 2009 Gen X Design
 * @link http://phpthumb.gxdlabs.com
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Nata\FilesystemManager\File\Image;

use Nata\FilesystemManager\File\Image;
use Nata\Core\NataObject;
use InvalidArgumentException;
use GdImage;

abstract class Processor extends NataObject {

/**
 * Default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'resizeUp' => false,
        'jpegQuality' => 100,
        'correctPermissions' => false,
        'preserveAlpha' => true,
        'alphaMaskColor' => [255, 255, 255],
        'preserveTransparency' => true,
        'transparencyMaskColor' => [0, 0, 0],
        'interlace' => null
    ];

/**
 * The name of the file we're manipulating
 * This must include the path to the file (absolute paths recommended)
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $image;

/**
 * The source/prior image (before manipulation).
 *
 * @var GdImage
 */
    protected $sourceImage;

/**
 * The working image (used during manipulation).
 *
 * @var GdImage
 */
    protected $workingImage;


/**
 * Constructor.
 *
 * @param \Nata\FilesystemManager\File\Image $image Image instance
 * @param array $options
 */
    public function __construct(Image $image, array $options = []) {
        $this->image = $image;
        $this->config($options);
        $this->initialize($options);
    }

/**
 * Pseudo-constructor.
 *
 * @param array $options
 */
    public function initialize(array $options) {}

/**
 * Get/Set source image resource.
 *
 * @param resource $sourceImage Source image resource
 * @return $this|resource|GdImage Source image resource
 */
    public function sourceImage($sourceImage = null) {
        if (func_num_args() === 0) {
            return $this->sourceImage;
        }

        if (!is_resource($sourceImage)) {
            throw new InvalidArgumentException('Source image must be a valid resource!');
        }

        $this->sourceImage = $sourceImage;

        return $this;
    }

/**
 * Get/Set working image resource.
 *
 * @param resource $workingImage Working image resource
 * @return $this|resource|GdImage Working image resource
 */
    public function workingImage($workingImage = null) {
        if (func_num_args() === 0) {
            if ($this->workingImage === null) {
                return $this->sourceImage;
            }
            return $this->workingImage;
        }

        if (!is_resource($workingImage)) {
            throw new InvalidArgumentException('Working image must be a valid resource!');
        }

        $this->workingImage = $workingImage;

        return $this;
    }

/**
 * Resizes an image to be no larger than $maxWidth or $maxHeight.
 *
 * If either param is set to zero, then that dimension will not be considered as a part of the resize.
 * Additionally, if $this->options['resizeUp'] is set to true (false by default), then this function will
 * also scale the image up to the maximum dimensions provided.
 *
 * @param int $maxWidth The maximum width of the image in pixels
 * @param int $maxHeight The maximum height of the image in pixels
 * @return \Nata\FilesystemManager\File\Image\Editor
 */
    abstract public function resize($newWidth = 0, $newHeight = 0);

/**
 * Resizes an image by a given percent uniformly,
 * Percentage should be whole number representation (i.e. 1-100)
 *
 * @param int $percent
 * @return GD
 * @throws InvalidArgumentException
 */
    abstract public function resizePercent($percent = 0);

/**
 * Adaptively Resizes the Image.
 *
 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
 * remaining overflow (from the center) to get the image to be the size specified.
 *
 * @param int $maxWidth
 * @param int $maxHeight
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function adaptiveResize($width, $height);

/**
 * Adaptively Resizes the Image and Crops Using a Percentage
 *
 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
 * remaining overflow using a provided percentage to get the image to be the size specified.
 *
 * The percentage mean different things depending on the orientation of the original image.
 *
 * For Landscape images:
 * ---------------------
 *
 * A percentage of 1 would crop the image all the way to the left, which would be the same as
 * using adaptiveResizeQuadrant() with $quadrant = 'L'
 *
 * A percentage of 50 would crop the image to the center which would be the same as using
 * adaptiveResizeQuadrant() with $quadrant = 'C', or even the original adaptiveResize()
 *
 * A percentage of 100 would crop the image to the image all the way to the right, etc, etc.
 * Note that you can use any percentage between 1 and 100.
 *
 * For Portrait images:
 * --------------------
 *
 * This works the same as for Landscape images except that a percentage of 1 means top and 100 means bottom
 *
 * @param int $maxWidth
 * @param int $maxHeight
 * @param int $percent
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function adaptiveResizePercent($width, $height, $percent = 50);

/**
 * Adaptively Resizes the Image and Crops Using a Quadrant.
 *
 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
 * remaining overflow using the quadrant to get the image to be the size specified.
 *
 * The quadrants available are Top, Bottom, Center, Left, and Right:
 *
 *
 * +---+---+---+
 * |   | T |   |
 * +---+---+---+
 * | L | C | R |
 * +---+---+---+
 * |   | B |   |
 * +---+---+---+
 *
 * Note that if your image is Landscape and you choose either of the Top or Bottom quadrants (which won't
 * make sence since only the Left and Right would be available, then the Center quadrant will be used
 * to crop. This would have exactly the same result as using adaptiveResize().
 * The same goes if your image is portrait and you choose either the Left or Right quadrants.
 *
 * @param int $maxWidth
 * @param int $maxHeight
 * @param string $quadrant  T, B, C, L, R
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function adaptiveResizeQuadrant($width, $height, $quadrant = 'C');

/**
 * Vanilla Cropping - Crops from x,y with specified width and height.
 *
 * @param int $startX
 * @param int $startY
 * @param int $cropWidth
 * @param int $cropHeight
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function crop($startX, $startY, $cropWidth, $cropHeight);

/**
 * Crops an image from the center with provided dimensions.
 *
 * If no height is given, the width will be used as a height, thus creating a square crop.
 *
 * @param int $cropWidth
 * @param int $cropHeight
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function cropFromCenter($cropWidth, $cropHeight = null);

/**
 * Rotates image either 90 degrees clockwise or counter-clockwise.
 *
 * @param string $direction
 * @retunrn \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function rotateImage(string $direction = 'CW');

/**
 * Rotates image specified number of degrees.
 *
 * @param int $degrees
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function rotateImageNDegrees(int $degrees);

/**
 * Applies a filter to the image.
 *
 * @param int $filter
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function filter($filter, $arg1 = false, $arg2 = false, $arg3 = false, $arg4 = false);

/**
 * Pad an image to desired dimensions.
 * Moves the image into the center and fills the rest with $color.
 *
 * @param $width
 * @param $height
 * @param array $color
 * @return \Nata\FilesystemManager\File\Image\Processor
 */
    abstract public function pad($width, $height, $color = [255, 255, 255]);

/**
 * Destructor.
 */
    public function __destruct() {
        if (is_resource($this->sourceImage)) {
            imagedestroy($this->sourceImage);
        }

        if (is_resource($this->workingImage)) {
            imagedestroy($this->workingImage);
        }
    }

}
