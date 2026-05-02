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

namespace Nata\FilesystemManager\File\Image\Processor;

use Exception;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\File\Image\Processor;
use Nata\FilesystemManager\File\Image\Processor\GD\SmartCrop;
use Nata\FilesystemManager\FileFactory;
use RuntimeException;
use InvalidArgumentException;

class GD extends Processor {

/**
 * The current dimensions of the image.
 *
 * @var array
 */
    protected $currentDimensions;

/**
 * The new, calculated dimensions of the image.
 *
 * @var array
 */
    protected $newDimensions;

/**
 * The maximum width an image can be after resizing (in pixels).
 *
 * @var int
 */
    protected $maxWidth;

/**
 * The maximum height an image can be after resizing (in pixels).
 *
 * @var int
 */
    protected $maxHeight;

/**
 * The percentage to resize the image by.
 *
 * @var int
 */
    protected $percent;

/**
 * Image format.
 *
 * @var string
 */
    protected $format;

/**
 * Options.
 *
 * @var array
 */
    protected $options;


/**
 * Pseudo-constructor.
 *
 * @param array $options
 */
    public function initialize(array $options) {
        $this->determineFormat();
        $this->verifyFormatCompatibility();

        $this->sourceImage = imagecreatefromstring($this->image->read());

        $this->setCurrentDimensions([
            'width' => imagesx($this->sourceImage),
            'height' => imagesy($this->sourceImage)
        ]);
    }
/**
 * Add text to an image.
 *
 * @wip Needs to be tested and could have some improvements.
 * @param string $text Text to add
 * @param int $size Font size in points
 * @param int $angle Angle in degrees
 * @param int $offsetX X coordinate for text starting position
 * @param int $offsetY Y coordinate for text starting position
 * @param array $color RGB color array for text
 * @param string|null $fontFile Path to TTF font file. If null, uses built-in font.
 * @return $this
 * @throws \InvalidArgumentException When invalid color provided
 */
    public function text(string $text, int $size, int $angle = 0, int $offsetX = 0, int $offsetY = 0, array $color = [255, 255, 255], string $fontFile = null) {
        if (count($color) < 3) {
            throw new InvalidArgumentException(
                'Color must be an RGB array with 3 values'
            );
        }

        $workingImage = $this->workingImage();

        // Allocate the text color
        $textColor = imagecolorallocate($workingImage, $color[0], $color[1], $color[2]);

        if ($fontFile !== null) {
            // Use TrueType font
            if (!file_exists($fontFile)) {
                throw new InvalidArgumentException(
                    sprintf('Font file not found: %s', $fontFile)
                );
            }

            // Add text with TrueType font
            imagettftext(
                $workingImage,
                $size,
                $angle,
                $offsetX,
                $offsetY,
                $textColor,
                $fontFile,
                $text
            );
        } else {
            // Use built-in font
            // Convert size to GD built-in font size (1-5)
            $fontSize = min(5, max(1, intval($size / 12)));

            // Add text with built-in font
            imagestring(
                $workingImage,
                $fontSize,
                $offsetX,
                $offsetY,
                $text,
                $textColor
            );
        }

        $this->workingImage = $workingImage;

        return $this;
    }
/**
 * Pad an image to desired dimensions.
 * Moves the image into the center and fills the rest with $color.
 *
 * @param $width
 * @param $height
 * @param array $color
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function pad($width, $height, $color = [255, 255, 255]) {
        // No resize - woohoo!
        if ($width == $this->currentDimensions['width'] && $height == $this->currentDimensions['height']) {
            return $this;
        }

        // Create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($width, $height);
        } else {
            $this->workingImage = imagecreate($width, $height);
        }

        // Create the fill color
        $fillColor = imagecolorallocate(
            $this->workingImage,
            $color[0],
            $color[1],
            $color[2]
        );

        // Fill our working image with the fill color
        imagefill(
            $this->workingImage,
            0,
            0,
            $fillColor
        );

        // Copy the image into the center of our working image
        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            intval(($width-$this->currentDimensions['width']) / 2),
            intval(($height-$this->currentDimensions['height']) / 2),
            0,
            0,
            $this->currentDimensions['width'],
            $this->currentDimensions['height'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        // Update all the variables and resources to be correct
        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Resizes an image to be no larger than $maxWidth or $maxHeight.
 *
 * If either param is set to zero, then that dimension will not be considered as a part of the resize.
 * Additionally, if $this->_config['resizeUp'] is set to true (false by default), then this function will
 * also scale the image up to the maximum dimensions provided.
 *
 * @param int $maxWidth The maximum width of the image in pixels
 * @param int $maxHeight The maximum height of the image in pixels
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function resize($maxWidth = 0, $maxHeight = 0) {
        // make sure our arguments are valid
        if (!is_numeric($maxWidth)) {
            throw new InvalidArgumentException('$maxWidth must be numeric');
        }

        if (!is_numeric($maxHeight)) {
            throw new InvalidArgumentException('$maxHeight must be numeric');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($maxHeight) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $maxHeight;
            $this->maxWidth  = (intval($maxWidth) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $maxWidth;
        } else {
            $this->maxHeight = intval($maxHeight);
            $this->maxWidth  = intval($maxWidth);
        }

        // get the new dimensions...
        $this->calcImageSize($this->currentDimensions['width'], $this->currentDimensions['height']);

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        // and create the newly sized image
        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            0,
            0,
            $this->newDimensions['newWidth'],
            $this->newDimensions['newHeight'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        // update all the variables and resources to be correct
        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Adaptively Resizes the Image.
 *
 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
 * remaining overflow (from the center) to get the image to be the size specified.
 *
 * @param int $maxWidth
 * @param int $maxHeight
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function adaptiveResize($width, $height) {
        // make sure our arguments are valid
        if ((!is_numeric($width) || $width  == 0) && (!is_numeric($height) || $height == 0)) {
            throw new InvalidArgumentException('$width and $height must be numeric and greater than zero');
        }

        if (!is_numeric($width) || $width  == 0) {
            $width = ($height * $this->currentDimensions['width']) / $this->currentDimensions['height'];
        }

        if (!is_numeric($height) || $height  == 0) {
            $height = ($width * $this->currentDimensions['height']) / $this->currentDimensions['width'];
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth  = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth  = intval($width);
        }

        $this->calcImageSizeStrict($this->currentDimensions['width'], $this->currentDimensions['height']);

        // resize the image to be close to our desired dimensions
        $this->resize($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);

        // reset the max dimensions...
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth  = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth  = intval($width);
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->maxWidth, $this->maxHeight);
        } else {
            $this->workingImage = imagecreate($this->maxWidth, $this->maxHeight);
        }

        $this->preserveAlpha();

        $cropWidth = $this->maxWidth;
        $cropHeight = $this->maxHeight;
        $cropX = 0;
        $cropY = 0;

        // now, figure out how to crop the rest of the image...
        if ($this->currentDimensions['width'] > $this->maxWidth) {
            $cropX = intval(($this->currentDimensions['width'] - $this->maxWidth) / 2);
        } elseif ($this->currentDimensions['height'] > $this->maxHeight) {
            $cropY = intval(($this->currentDimensions['height'] - $this->maxHeight) / 2);
        }

        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        // update all the variables and resources to be correct
        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

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
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function adaptiveResizePercent($width, $height, $percent = 50) {
        // make sure our arguments are valid
        if (!is_numeric($width) || $width  == 0) {
            throw new InvalidArgumentException('$width must be numeric and greater than zero');
        }

        if (!is_numeric($height) || $height == 0) {
            throw new InvalidArgumentException('$height must be numeric and greater than zero');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        $this->calcImageSizeStrict($this->currentDimensions['width'], $this->currentDimensions['height']);

        // resize the image to be close to our desired dimensions
        $this->resize($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);

        // reset the max dimensions...
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->maxWidth, $this->maxHeight);
        } else {
            $this->workingImage = imagecreate($this->maxWidth, $this->maxHeight);
        }

        $this->preserveAlpha();

        $cropWidth = $this->maxWidth;
        $cropHeight = $this->maxHeight;
        $cropX = 0;
        $cropY = 0;

        // Crop the rest of the image using the quadrant

        if ($percent > 100) {
            $percent = 100;
        } elseif ($percent < 1) {
            $percent = 1;
        }

        if ($this->currentDimensions['width'] > $this->maxWidth) {
            // Image is landscape
            $maxCropX = $this->currentDimensions['width'] - $this->maxWidth;
            $cropX = intval(($percent / 100) * $maxCropX);

        } elseif ($this->currentDimensions['height'] > $this->maxHeight) {
            // Image is portrait
            $maxCropY = $this->currentDimensions['height'] - $this->maxHeight;
            $cropY = intval(($percent / 100) * $maxCropY);
        }

        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        // update all the variables and resources to be correct
        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

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
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function adaptiveResizeQuadrant($width, $height, $quadrant = 'C') {
        // make sure our arguments are valid
        if (!is_numeric($width) || $width  == 0) {
            throw new InvalidArgumentException('$width must be numeric and greater than zero');
        }

        if (!is_numeric($height) || $height == 0) {
            throw new InvalidArgumentException('$height must be numeric and greater than zero');
        }

        // make sure we're not exceeding our image size if we're not supposed to
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        $this->calcImageSizeStrict($this->currentDimensions['width'], $this->currentDimensions['height']);

        // resize the image to be close to our desired dimensions
        $this->resize($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);

        // reset the max dimensions...
        if ($this->_config['resizeUp'] === false) {
            $this->maxHeight = (intval($height) > $this->currentDimensions['height']) ? $this->currentDimensions['height'] : $height;
            $this->maxWidth = (intval($width) > $this->currentDimensions['width']) ? $this->currentDimensions['width'] : $width;
        } else {
            $this->maxHeight = intval($height);
            $this->maxWidth = intval($width);
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->maxWidth, $this->maxHeight);
        } else {
            $this->workingImage = imagecreate($this->maxWidth, $this->maxHeight);
        }

        $this->preserveAlpha();

        $cropWidth = $this->maxWidth;
        $cropHeight = $this->maxHeight;
        $cropX = 0;
        $cropY = 0;

        // Crop the rest of the image using the quadrant

        if ($this->currentDimensions['width'] > $this->maxWidth) {
            // Image is landscape
            switch ($quadrant) {
                case 'L':
                    $cropX = 0;
                    break;
                case 'R':
                    $cropX = intval(($this->currentDimensions['width'] - $this->maxWidth));
                    break;
                case 'C':
                default:
                    $cropX = intval(($this->currentDimensions['width'] - $this->maxWidth) / 2);
                    break;
            }
        } elseif ($this->currentDimensions['height'] > $this->maxHeight) {
            // Image is portrait
            switch ($quadrant) {
                case 'T':
                    $cropY = 0;
                    break;
                case 'B':
                    $cropY = intval(($this->currentDimensions['height'] - $this->maxHeight));
                    break;
                case 'C':
                default:
                    $cropY = intval(($this->currentDimensions['height'] - $this->maxHeight) / 2);
                    break;
            }
        }

        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        // update all the variables and resources to be correct
        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Resizes an image by a given percent uniformly,
 * Percentage should be whole number representation (i.e. 1-100)
 *
 * @param int $percent
 * @return GD
 * @throws InvalidArgumentException
 */
    public function resizePercent($percent = 0) {
        if (!is_numeric($percent)) {
            throw new InvalidArgumentException ('$percent must be numeric');
        }

        $this->percent = intval($percent);

        $this->calcImageSizePercent($this->currentDimensions['width'], $this->currentDimensions['height']);

        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        } else {
            $this->workingImage = imagecreate($this->newDimensions['newWidth'], $this->newDimensions['newHeight']);
        }

        $this->preserveAlpha();

        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            0,
            0,
            $this->newDimensions['newWidth'],
            $this->newDimensions['newHeight'],
            $this->currentDimensions['width'],
            $this->currentDimensions['height']
        );

        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Crops an image from the center with provided dimensions.
 *
 * If no height is given, the width will be used as a height, thus creating a square crop.
 *
 * @param int $cropWidth
 * @param int $cropHeight
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function cropFromCenter($cropWidth, $cropHeight = null) {
        if (!is_numeric($cropWidth)) {
            throw new InvalidArgumentException('$cropWidth must be numeric');
        }

        if ($cropHeight !== null && !is_numeric($cropHeight)) {
            throw new InvalidArgumentException('$cropHeight must be numeric');
        }

        if ($cropHeight === null) {
            $cropHeight = $cropWidth;
        }

        $cropWidth  = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        $cropX = intval(($this->currentDimensions['width'] - $cropWidth) / 2);
        $cropY = intval(($this->currentDimensions['height'] - $cropHeight) / 2);

        $this->crop($cropX, $cropY, $cropWidth, $cropHeight);

        return $this;
    }

/**
 * Vanilla Cropping - Crops from x,y with specified width and height.
 *
 * @param int $startX
 * @param int $startY
 * @param int $cropWidth
 * @param int $cropHeight
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function crop($startX, $startY, $cropWidth, $cropHeight) {
        // validate input
        if (!is_numeric($startX)) {
            throw new InvalidArgumentException('$startX must be numeric');
        }

        if (!is_numeric($startY)) {
            throw new InvalidArgumentException('$startY must be numeric');
        }

        if (!is_numeric($cropWidth)) {
            throw new InvalidArgumentException('$cropWidth must be numeric');
        }

        if (!is_numeric($cropHeight)) {
            throw new InvalidArgumentException('$cropHeight must be numeric');
        }

        // do some calculations
        $cropWidth  = ($this->currentDimensions['width'] < $cropWidth) ? $this->currentDimensions['width'] : $cropWidth;
        $cropHeight = ($this->currentDimensions['height'] < $cropHeight) ? $this->currentDimensions['height'] : $cropHeight;

        // ensure everything's in bounds
        if (($startX + $cropWidth) > $this->currentDimensions['width']) {
            $startX = ($this->currentDimensions['width'] - $cropWidth);
        }

        if (($startY + $cropHeight) > $this->currentDimensions['height']) {
            $startY = ($this->currentDimensions['height'] - $cropHeight);
        }

        if ($startX < 0) {
            $startX = 0;
        }

        if ($startY < 0) {
            $startY = 0;
        }

        // create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($cropWidth, $cropHeight);
        } else {
            $this->workingImage = imagecreate($cropWidth, $cropHeight);
        }

        $this->preserveAlpha();

        imagecopyresampled(
            $this->workingImage,
            $this->sourceImage,
            0,
            0,
            $startX,
            $startY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Smart Cropping - Crops from x,y with specified width and height.
 *
 * @param int $width
 * @param int $height
 * @return $this
 */
    public function smartCrop($width, $height, array $options = []) {

        if (!is_numeric($width)) {
            throw new InvalidArgumentException("Smartcrop 'width' must be numeric");
        }

        if (!is_numeric($height)) {
            throw new InvalidArgumentException("Smartcrop 'height' must be numeric");
        }

        // Smart Crop instance
        $smartCrop = new SmartCrop($this->workingImage(), [
            'newWidth' => $width,
            'newHeight' => $height
        ] + $options);

        // Analyse the image and get the optimal crop scheme
        $result = $smartCrop->analyse();

        // Create the working image
        if (function_exists('imagecreatetruecolor')) {
            $this->workingImage = imagecreatetruecolor($width, $height);
        } else {
            $this->workingImage = imagecreate($width, $height);
        }

        $this->preserveAlpha();

        imagecopyresampled(
            $this->workingImage,
            $result['oldImage'],
            0,
            0,
            $result['x'],
            $result['y'],
            $width,
            $height,
            $width,
            $height
        );

        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Rotates image either 90 degrees clockwise or counter-clockwise.
 *
 * @param string $direction ('CW' or 'CCW')
 * @retunrn \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function rotateImage(string $direction = 'CW') {
        if ($direction !== 'CW' && $direction !== 'CCW') {
            throw new InvalidArgumentException(sprintf(
                'Invalid rotation direction given "%s". Valid directions: %s',
                $direction, implode('", "', ['CW', 'CCW'])
            ));
        }

        $direction = $direction == 'CW' ? 90 : -90;
        $this->rotateImageNDegrees($direction);
        return $this;
    }

/**
 * Rotates image specified number of degrees.
 *
 * @param int $degrees
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function rotateImageNDegrees(int $degrees) {
        if (!is_numeric($degrees)) {
            throw new InvalidArgumentException('$degrees must be numeric');
        }

        if (!function_exists('imagerotate')) {
            throw new \RuntimeException('Your version of GD does not support image rotation');
        }

        $this->workingImage = imagerotate($this->sourceImage, $degrees, 0);

        $this->sourceImage = $this->workingImage;
        $this->_resetCurrentDimensions();

        return $this;
    }

/**
 * Flip image using a given mode.
 *
 * ## Options
 * - 'horizontal'
 * - 'vertical'
 * - 'both'
 *
 * @param int|string $mode Flipping mode
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function flip($mode) {
        if (is_string($mode)) {
            $constant = 'IMG_FLIP_' . strtoupper($mode);
            if (!defined($constant)) {
                throw new InvalidArgumentException(sprintf("Flip mode '%s' is invalid! Supported modes: 'horizontal', 'vertical', 'both'.", $mode));
            }
            $mode = constant($constant);
        }

        $resource = $this->workingImage();
        imageflip($resource, $mode);
        $this->sourceImage = $resource;
    }

/**
 * Applies a filter to the image.
 *
 * @param int $filter
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function filter($filter, $arg1 = false, $arg2 = false, $arg3 = false, $arg4 = false) {
        if (!is_numeric($filter)) {
            throw new InvalidArgumentException('$filter must be numeric');
        }

        if (!function_exists('imagefilter')) {
            throw new \RuntimeException('Your version of GD does not support image filters');
        }

        $result = false;
        if ($arg1 === false) {
            $result = imagefilter($this->sourceImage, $filter);
        } elseif ($arg2 === false) {
            $result = imagefilter($this->sourceImage, $filter, $arg1);
        } elseif ($arg3 === false) {
            $result = imagefilter($this->sourceImage, $filter, $arg1, $arg2);
        } elseif ($arg4 === false) {
            $result = imagefilter($this->sourceImage, $filter, $arg1, $arg2, $arg3);
        } else {
            $result = imagefilter($this->sourceImage, $filter, $arg1, $arg2, $arg3, $arg4);
        }

        if (!$result) {
            throw new RuntimeException('GD imagefilter failed');
        }

        $this->workingImage = $this->sourceImage;

        return $this;
    }

/**
 * Applies a Gaussian blur to the image.
 *
 * @param int $level Level of gaussian blur to apply
 * @return $this
 */
    public function gaussianBlur($level = 5) {
        // blurFactor has to be an integer
        $blurFactor = round($level);

        $dimension = $this->getCurrentDimensions();
        $workingImage = $this->workingImage();

        $originalWidth = $dimension['width'];
        $originalHeight = $dimension['height'];

        $smallestWidth = ceil($originalWidth * pow(0.5, $blurFactor));
        $smallestHeight = ceil($originalHeight * pow(0.5, $blurFactor));

        // for the first run, the previous image is the original input
        $prevImage = $workingImage;
        $prevWidth = $originalWidth;
        $prevHeight = $originalHeight;

        // Scale way down and gradually scale back up, blurring all the way
        for ($i = 0; $i < $blurFactor; $i += 1) {
            // Determine dimensions of next image
            $nextWidth = $smallestWidth * pow(2, $i);
            $nextHeight = $smallestHeight * pow(2, $i);

            // Resize previous image to next size
            $nextImage = imagecreatetruecolor($nextWidth, $nextHeight);

            imagecopyresized(
                $nextImage,
                $prevImage,
                0,
                0,
                0,
                0,
                $nextWidth,
                $nextHeight,
                $prevWidth,
                $prevHeight
            );

            // Apply blur filter
            imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);

            // Now the new image becomes the previous image for the next step
            $prevImage = $nextImage;
            $prevWidth = $nextWidth;
            $prevHeight = $nextHeight;
        }

        // Scale back to original size and blur one more time
        imagecopyresized(
            $workingImage,
            $nextImage,
            0,
            0,
            0,
            0,
            $originalWidth,
            $originalHeight,
            $nextWidth, $nextHeight
        );

        imagefilter($workingImage, IMG_FILTER_GAUSSIAN_BLUR);

        // Clean up
        imagedestroy($nextImage);
        imagedestroy($prevImage);

        // Set working image
        $this->workingImage = $workingImage;

        $workingImage = null;
        unset($workingImage);

        return $this;
    }

/**
 * Overlay an image to current one.
 *
 * @param \Nata\FilesystemManager\File|string $image Watermark image path or File instance
 * @param string $position Can be: left/west, right/east, center for the x-axis and top/north/upper, bottom/lower/south, center for the y-axis
 * @param int $opacity Opacity of the watermark in percent, 0 = total transparent, 100 = total opaque
 * @param int $offsetX Offset on the x-axis. can be negative to set an offset to the left
 * @param int $offsetY Offset on the y-axis. can be negative to set an offset to the top
 * @return $this
 */
    public function overlay($image, $position = 'center', $opacity = 100, $offsetX = 0, $offsetY = 0) {
        if (is_string($image)) {
            $image = FileFactory::build($image);
        }

        if (!($image instanceof Image)) {
            throw new InvalidArgumentException(sprintf("Invalid Image instance for GD::overlay(). Expected '%s' got '%s'.", 'Nata\FilesystemManager\File\Image', get_class($image)));
        }

        // Watermark processor
        $watermark = new GD($image);
        $watermarkDimensions = $watermark->getCurrentDimensions();

        $currentDimensions = $this->getCurrentDimensions();
        if ($currentDimensions['width'] < $watermarkDimensions['width'] || $currentDimensions['height'] < $watermarkDimensions['height']) {
            $watermark->resize($currentDimensions['width'], $currentDimensions['height']);
            $watermarkDimensions = $watermark->getCurrentDimensions();
        }

        if (preg_match('/right|east/i', $position)) {
            $offsetX += $currentDimensions['width'] - $watermarkDimensions['width'];
        } elseif (!preg_match('/left|west/i', $position)) {
            $offsetX += intval($currentDimensions['width'] / 2 - $watermarkDimensions['width'] / 2);
        }

        if (preg_match('/bottom|lower|south/i', $position)) {
            $offsetY += $currentDimensions['height'] - $watermarkDimensions['height'];
        } elseif (!preg_match('/upper|top|north/i', $position)) {
            $offsetY += intval($currentDimensions['height'] / 2 - $watermarkDimensions['height'] / 2);
        }

        $workingImage = $this->workingImage();
        $watermarkImage = $watermark->workingImage();

        $this->_imageCopyMergeAlpha(
            $workingImage,
            $watermarkImage,
            $offsetX,
            $offsetY,
            0,
            0,
            $watermarkDimensions['width'],
            $watermarkDimensions['height'],
            $opacity
        );

        $this->workingImage = $workingImage;

        $workingImage = null;
        unset($workingImage);

        return $this;
    }

/**
 * Does the same as "imagecopymerge" but preserves the alpha-channel.
 *
 * @see http://www.php.net/manual/en/function.imagecopymerge.php
 * @see http://www.php.net/manual/en/function.imagecopymerge.php#92787
 */
    protected function _imageCopyMergeAlpha(&$dst_im, &$src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct);
    }

/**
 * Resets current dimensions from working image.
 *
 * @return $this
 */
    protected function _resetCurrentDimensions() {
        if ($this->workingImage) {
            $this->setCurrentDimensions([
                'width' => imagesx($this->workingImage),
                'height' => imagesy($this->workingImage)
            ]);
        }
        return $this;
    }

/**
 * Shows an image.
 *
 * This function will show the current image by first sending the appropriate header
 * for the format, and then outputting the image data. If headers have already been sent,
 * a runtime exception will be thrown.
 *
 * @param bool $rawData Whether or not the raw image stream should be output
 * @param string $format Format
 * @return \Nata\FilesystemManager\File\Image\Processor\GD
 */
    public function show(bool $rawData = false, string|null $format = null) {
        $validFormats = ['GIF', 'JPG', 'PNG', 'WEBP'];
        $format = ($format !== null) ? strtoupper($format) : $this->format;
        if ($format == 'BMP') {
            $format = 'WEBP';
        }

        if (!in_array($format, $validFormats)) {
            throw new InvalidArgumentException("Unsupported format type specified in show function: {$format}");
        }

        if (headers_sent() && php_sapi_name() != 'cli') {
            throw new RuntimeException('Cannot show image, headers have already been sent');
        }

        ini_set('memory_limit', '2G');

        // When the interlace option equals true or false call imageinterlace else leave it to default
        if ($this->_config['interlace'] === true) {
            imageinterlace($this->sourceImage, 1);
        } elseif ($this->_config['interlace'] === false) {
            imageinterlace($this->sourceImage, 0);
        }

        switch ($format) {
            case 'GIF':
                if ($rawData === false) {
                    header('Content-type: image/gif');
                }
                imagegif($this->sourceImage);
                break;
            case 'JPG':
                if ($rawData === false) {
                    header('Content-type: image/jpeg');
                }

                // @fix PNG to JPG background transparency fix
                if ($this->format == 'PNG') {
                    extract($this->currentDimensions);
                    $input = $this->sourceImage;
                    $output = imagecreatetruecolor($width, $height);
                    $white = imagecolorallocate($output,  255, 255, 255);
                    imagefilledrectangle($output, 0, 0, $width, $height, $white);
                    imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
                    imagejpeg($output, null, $this->_config['quality']);
                } else {
                    imagejpeg($this->sourceImage, null, $this->_config['quality']);
                }
                break;
            case 'WEBP':
                if ($rawData === false) {
                    header('Content-type: image/webp');
                }
                imagewebp($this->sourceImage, null, $this->_config['quality']);
                break;
            case 'PNG':
            case 'STRING':
                if ($rawData === false) {
                    header('Content-type: image/png');
                }
                imagepng($this->sourceImage);
                break;
        }

        return $this;
    }

/**
 * Returns the Working Image as a String.
 *
 * This function is useful for getting the raw image data as a string for storage in
 * a database, or other similar things.
 *
 * @param string $format Format
 * @return string
 */
    public function getImageAsString(string $format = null) {
        $data = null;
        ob_start();
        $this->show(true, $format);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }

/**
 * Saves an image.
 *
 * This function will make sure the target directory is writeable, and then save the image.
 *
 * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
 * is set as an option ($this->_config['correctPermissions']). If the target cannot be made writeable, then a
 * \RuntimeException is thrown.
 *
 * @param string $fileName The full path and filename of the image to save
 * @param string $format The format to save the image in (optional, must be one of [GIF,JPG,PNG]
 * @return bool
 */
    public function save($fileName, $format = null) {
        $validFormats = ['GIF', 'JPG', 'PNG', 'WEBP'];
        $format = ($format !== null) ? strtoupper($format) : $this->format;
        if ($format == 'BMP') {
            $format = 'WEBP';
        }

        if (!in_array($format, $validFormats)) {
            throw new InvalidArgumentException("Invalid format type specified in save function: {$format}");
        }

        // Make sure the directory is writeable
        if (!is_writeable(dirname($fileName))) {
            // try to correct the permissions
            if ($this->_config['correctPermissions'] === true) {
                @chmod(dirname($fileName), 0777);

                // throw an exception if not writeable
                if (!is_writeable(dirname($fileName))) {
                    throw new RuntimeException("File is not writeable, and could not correct permissions: {$fileName}");
                }
            } else { // throw an exception if not writeable
                throw new RuntimeException("File not writeable: {$fileName}");
            }
        }

        // When the interlace option equals true or false call imageinterlace else leave it to default
        if ($this->_config['interlace'] === true) {
            imageinterlace($this->sourceImage, 1);
        } elseif ($this->_config['interlace'] === false) {
            imageinterlace($this->sourceImage, 0);
        }

        $success = false;
        switch ($format) {
            case 'GIF':
                $success = imagegif($this->sourceImage, $fileName);
                break;
            case 'JPG':
                // @fix PNG to JPG background transparency fix
                if ($this->format == 'PNG') {
                    extract($this->currentDimensions);
                    $input = $this->sourceImage;
                    $output = imagecreatetruecolor($width, $height);
                    $white = imagecolorallocate($output,  255, 255, 255);
                    imagefilledrectangle($output, 0, 0, $width, $height, $white);
                    imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
                    $success = imagejpeg($output, $fileName, $this->_config['quality']);
                } else {
                    $success = imagejpeg($this->sourceImage, $fileName, $this->_config['quality']);
                }
                break;
            case 'PNG':
                $success = imagepng($this->sourceImage, $fileName);
                break;
            case 'WEBP':
                $success = imagewebp($this->sourceImage, $fileName, $this->_config['quality']);
                break;
        }
        return $success;
    }

/**
 * Returns $currentDimensions.
 *
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$currentDimensions
 */
    public function getCurrentDimensions() {
        return $this->currentDimensions;
    }

/**
 * @param $currentDimensions
 * @return GD
 */
    public function setCurrentDimensions($currentDimensions) {
        $this->currentDimensions = $currentDimensions;
        return $this;
    }

/**
 * @return int
 */
    public function getMaxHeight() {
        return $this->maxHeight;
    }

/**
 * @param $maxHeight
 * @return GD
 */
    public function setMaxHeight($maxHeight) {
        $this->maxHeight = $maxHeight;

        return $this;
    }

/**
 * @return int
 */
    public function getMaxWidth() {
        return $this->maxWidth;
    }

/**
 * @param $maxWidth
 * @return GD
 */
    public function setMaxWidth($maxWidth) {
        $this->maxWidth = $maxWidth;
        return $this;
    }

/**
 * Returns $newDimensions.
 *
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$newDimensions
 */
    public function getNewDimensions() {
        return $this->newDimensions;
    }

/**
 * Sets $newDimensions.
 *
 * @param object $newDimensions
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$newDimensions
 */
    public function setNewDimensions($newDimensions) {
        $this->newDimensions = $newDimensions;
        return $this;
    }

/**
 * Returns $options.
 *
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$options
 */
    public function getOptions() {
        return $this->options;
    }

/**
 * Returns $percent.
 *
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$percent
 */
    public function getPercent() {
        return $this->percent;
    }

/**
 * Sets $percent.
 *
 * @param object $percent
 * @see \Nata\FilesystemManager\File\Image\Processor\GD::$percent
 */
    public function setPercent($percent) {
        $this->percent = $percent;

        return $this;
    }


    #################################
    # ----- UTILITY FUNCTIONS ----- #
    #################################

/**
 * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
 *
 * @return array
 * @param  int   $width
 * @param  int   $height
 */
    protected function calcWidth($width, $height) {
        $newWidthPercentage = (100 * $this->maxWidth) / $width;
        $newHeight = ($height * $newWidthPercentage) / 100;

        return array(
            'newWidth'  => intval($this->maxWidth),
            'newHeight' => intval($newHeight)
        );
    }

/**
 * Calculates a new width and height for the image based on $this->maxWidth and the provided dimensions
 *
 * @return array
 * @param  int   $width
 * @param  int   $height
 */
    protected function calcHeight($width, $height) {
        $newHeightPercentage = (100 * $this->maxHeight) / $height;
        $newWidth = ($width * $newHeightPercentage) / 100;

        return [
            'newWidth'  => ceil($newWidth),
            'newHeight' => ceil($this->maxHeight)
        ];
    }

/**
 * Calculates a new width and height for the image based on $this->percent and the provided dimensions
 *
 * @return array
 * @param  int   $width
 * @param  int   $height
 */
    protected function calcPercent($width, $height) {
        $newWidth  = ($width * $this->percent) / 100;
        $newHeight = ($height * $this->percent) / 100;
        return [
            'newWidth'  => ceil($newWidth),
            'newHeight' => ceil($newHeight)
        ];
    }

/**
 * Calculates the new image dimensions
 *
 * These calculations are based on both the provided dimensions and $this->maxWidth and $this->maxHeight
 *
 * @param int $width
 * @param int $height
 */
    protected function calcImageSize($width, $height) {
        $newSize = [
            'newWidth'  => $width,
            'newHeight' => $height
        ];

        if ($this->maxWidth > 0) {
            $newSize = $this->calcWidth($width, $height);

            if ($this->maxHeight > 0 && $newSize['newHeight'] > $this->maxHeight) {
                $newSize = $this->calcHeight($newSize['newWidth'], $newSize['newHeight']);
            }
        }

        if ($this->maxHeight > 0) {
            $newSize = $this->calcHeight($width, $height);

            if ($this->maxWidth > 0 && $newSize['newWidth'] > $this->maxWidth) {
                $newSize = $this->calcWidth($newSize['newWidth'], $newSize['newHeight']);
            }
        }

        $this->newDimensions = $newSize;
    }

/**
 * Calculates new image dimensions, not allowing the width and height to be less than either the max width or height
 *
 * @param int $width
 * @param int $height
 */
    protected function calcImageSizeStrict($width, $height) {
        // first, we need to determine what the longest resize dimension is..
        if ($this->maxWidth >= $this->maxHeight) {
            // and determine the longest original dimension
            if ($width > $height) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            } elseif ($height >= $width) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            }
        } elseif ($this->maxHeight > $this->maxWidth) {
            if ($width >= $height) {
                $newDimensions = $this->calcWidth($width, $height);

                if ($newDimensions['newHeight'] < $this->maxHeight) {
                    $newDimensions = $this->calcHeight($width, $height);
                }
            } elseif ($height > $width) {
                $newDimensions = $this->calcHeight($width, $height);

                if ($newDimensions['newWidth'] < $this->maxWidth) {
                    $newDimensions = $this->calcWidth($width, $height);
                }
            }
        }

        $this->newDimensions = $newDimensions;
    }

/**
 * Calculates new dimensions based on $this->percent and the provided dimensions
 *
 * @param int $width
 * @param int $height
 */
    protected function calcImageSizePercent($width, $height) {
        if ($this->percent > 0) {
            $this->newDimensions = $this->calcPercent($width, $height);
        }
    }

/**
 * Determines the file format by mime-type.
 * This function will throw exceptions for invalid images / mime-types
 *
 * @throws \Exception
 */
    protected function determineFormat() {
        $mimeType = $this->image->mime();
        if ($mimeType === null) {
            throw new Exception(sprintf("Image mimetype not determined for %s.", $this->image->getAbsolutePath()));
        }
        switch ($mimeType) {
            case 'image/gif':
                $this->format = 'GIF';
                break;
            case 'image/jpeg':
                $this->format = 'JPG';
                break;
            case 'image/webp':
                $this->format = 'WEBP';
                break;
            case 'image/png':
                $this->format = 'PNG';
                break;
            case 'image/x-ms-bmp':
                $this->format = 'BMP';
                break;
            default:
                throw new Exception("Image format not supported: '{$mimeType}'");
        }
    }

/**
 * Makes sure the correct GD implementation exists for the file type.
 */
    protected function verifyFormatCompatibility() {
        $isCompatible = true;
        $gdInfo = gd_info();

        switch ($this->format) {
            case 'GIF':
                $isCompatible = $gdInfo['GIF Create Support'];
                break;
            case 'JPG':
                $isCompatible = (isset($gdInfo['JPG Support']) || isset($gdInfo['JPEG Support'])) ? true : false;
                break;
            case 'PNG':
                $isCompatible = $gdInfo[$this->format . ' Support'];
                break;
            case 'WEBP':
                $isCompatible = isset($gdInfo['WebP Support']) && $gdInfo['WebP Support'] == 1;
                break;
            case 'BMP':
                $isCompatible = true;
                break;
            default:
                $isCompatible = false;
        }

        if (!$isCompatible) {
            // one last check for "JPEG" instead
            $isCompatible = $gdInfo['JPEG Support'];

            if (!$isCompatible) {
                throw new Exception("Your GD installation does not support {$this->format} image types");
            }
        }
    }

/**
 * Preserves the alpha or transparency for PNG and GIF files.
 *
 * Alpha / transparency will not be preserved if the appropriate options are set to false.
 * Also, the GIF transparency is pretty skunky (the results aren't awesome), but it works like a
 * champ... that's the nature of GIFs tho, so no huge surprise.
 *
 * This functionality was originally suggested by commenter Aimi (no links / site provided) - Thanks! :)
 *
 * @return void
 */
    protected function preserveAlpha() {
        if ($this->format == 'PNG' && $this->_config['preserveAlpha'] === true) {
            imagealphablending($this->workingImage, false);

            $colorTransparent = imagecolorallocatealpha(
                $this->workingImage,
                $this->_config['alphaMaskColor'][0],
                $this->_config['alphaMaskColor'][1],
                $this->_config['alphaMaskColor'][2],
                0
            );

            imagefill($this->workingImage, 0, 0, $colorTransparent);
            imagesavealpha($this->workingImage, true);
        }

        // preserve transparency in GIFs... this is usually pretty rough tho
        if ($this->format == 'GIF' && $this->_config['preserveTransparency'] === true) {
            $colorTransparent = imagecolorallocate(
                $this->workingImage,
                $this->_config['transparencyMaskColor'][0],
                $this->_config['transparencyMaskColor'][1],
                $this->_config['transparencyMaskColor'][2]
            );

            imagecolortransparent($this->workingImage, $colorTransparent);
            imagetruecolortopalette($this->workingImage, true, 256);
        }
    }

/**
 * __toString.
 *
 * @return string
 */
    public function __toString() {
        return $this->getImageAsString($this->format);
    }

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
