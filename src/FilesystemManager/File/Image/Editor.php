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

use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\File\Image\Processor\GD;
use InvalidArgumentException;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\FileFactory;
use Nata\FilesystemManager\Mimetype;

/**
 * Image editor.
 */
class Editor extends NataObject {

/**
 * Default configuration.
 *
 * ## Options:
 *
 * - `quality` - JPEG quality
 * - `resizeUp` - Allow resize up
 * - `preserveAlpha` - Preserve the alpha channel on PNG
 * - `alphaMaskColor` - RGB values for alpha mask color
 * - `dpi` - Pixel density
 * - `interlace` - Interlace type
 * - `cacheFolder` - Cache folder
 * - `performantCallOrder` - Performant call order
 * - `transparencyMaskColor` - RGB values for transparency mask color
 *
 * @var array
 */
    protected $_defaultConfig = [
        'quality' => 100,
        'dpi' => 1,
        'performantCallOrder' => true,
        'resizeUp' => false,
        'preserveAlpha' => true,
        'alphaMaskColor' => [255, 255, 255],
        'transparencyMaskColor' => [0, 0, 0],
        'interlace' => null,
        'cacheFolder' => null,
    ];

/**
 * Supported formats.
 *
 * @var array
 */
    protected $_supportedFormats = [
        'bmp',
        'jpg',
        'jpeg',
        'webp',
        'png',
        'gif'
    ];

/**
 * Pixel density.
 *
 * ## Recommended
 *
 * - 1 - MDPI
 * - 1.5 - HDPI
 * - 2 - XHDPI
 * - 3 - XXHDPI
 *
 * @var int|float
 */
    protected $_dpi = 1;

/**
 * Loaded image instance.
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $_image;

/**
 * Edited image.
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $_editedImage;

/**
 * True if edited image was processed or
 * was already on cache.
 *
 * @var bool
 */
    protected $_cached = false;

/**
 * Cached image dirname.
 *
 * @var string
 */
    protected $_cacheDirname;

/**
 * Cached image filename.
 *
 * @var string
 */
    protected $_cachedFilename;

/**
 * Loaded processor instance.
 *
 * @todo Imagick support
 * @var \Nata\FilesystemManager\File\Image\Processor
 */
    protected $_processor;

/**
 * Called methods.
 *
 * @var array
 */
    protected $_calledMethods = [];

/**
 * Image new dimensions.
 *
 * @var array
 */
    protected $_newDimensions = [];

/**
 * Process imagem mode.
 *
 * @var bool
 */
    protected $_processMode = false;


/**
 * Constructor.
 *
 * @param mixed $image Image
 * @param array $options Options
 */
    public function __construct($image, array $options = []) {
        if (!($image instanceof Image)) {
            $image = FileFactory::build($image);
        }

        if (!($image instanceof Image)) {
            throw new InvalidArgumentException(sprintf("Invalid Image instance. Expected '%s' got '%s'.", 'Nata\FilesystemManager\File\Image', get_class($image)));
        }

        $this->config($options);

        $this->_image = $image;
        $this->_newDimensions = $image->dimensions();

        // Prevent GD warnings
        // (this is the default since PHP 7.1, but not in older versions)
        ini_set('gd.jpeg_ignore_warning', 1);
    }

/**
 * Fixes orientation based on image's EXIF information.
 *
 * @return $this
 */
    public function fixOrientation() {
        // Only for JPEG images and when EXIF is available
        if ($this->_image->is('jpeg') === false || $this->_image->is('jpg') === false) {
            return $this;
        }

        $orientation = $this->_image->exif()->orientation;

        if ($orientation > 1) {
            switch ($orientation) {
                case 2: // Flip horizontally
                    $this->flip(IMG_FLIP_HORIZONTAL);
                    break;
                case 3: // 180 rotate cw
                    $this->rotate(180);
                    break;
                case 4: // Flip horizontally and 180 rotate cw
                    $this->flip(IMG_FLIP_HORIZONTAL)->rotate(180);
                    break;
                case 5: // Flip and 180 rotate ccw
                    $this->rotate(-90)->flip(IMG_FLIP_HORIZONTAL);
                    break;
                case 6: // 90 rotate ccw
                    $this->rotate(-90);
                    break;
                case 7: // Flip and 180 rotate cw
                    $this->rotate(90)->flip(IMG_FLIP_HORIZONTAL);
                    break;
                case 8: // 90 rotate cw
                    $this->rotate(90);
                    break;
            }
        }

        return $this;
    }

/**
 * Pad an image to desired dimensions.
 * Moves the image into the center and fills the rest with $color.
 *
 * @param int $width Width
 * @param int $height Height
 * @param array $color RGB color
 * @return $this
 */
    public function pad($width, $height, $color = [255, 255, 255]) {
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $width = $this->_getDpiCalc($width);
        $height = $this->_getDpiCalc($height);

        $this->_loadProcessor()->pad($width, $height, $color);

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
    public function resize($maxWidth = 0, $maxHeight = 0) {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcSize($maxWidth, ($maxHeight ? $maxHeight : 0));
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $maxWidth = $this->_getDpiCalc($maxWidth);
        $maxHeight = $this->_getDpiCalc($maxHeight);

        $this->_loadProcessor()->resize($maxWidth, $maxHeight);

        return $this;
    }

/**
 * Resizes an image by a given percent uniformly,
 * Percentage should be whole number representation (i.e. 1-100)
 *
 * @param int $percent Percentage
 * @return $this
 * @throws \InvalidArgumentException
 */
    public function resizePercent($percent = 0) {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcPercent($percent);
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $this->_loadProcessor()->resizePercent($percent);

        return $this;
    }

/**
 * Adaptively Resizes the Image.
 *
 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
 * remaining overflow (from the center) to get the image to be the size specified
 *
 * @param int $width Width of image in pixels
 * @param int $height Height of image in pixels
 * @return $this
 */
    public function adaptiveResize(int $width, int $height) {
        if ($this->_processMode === false) {
            $this->_newDimensions = ['width' => $width, 'height' => $height];
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $width = $this->_getDpiCalc($width);
        $height = $this->_getDpiCalc($height);

        $this->_loadProcessor()->adaptiveResize($width, $height);

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
 * @param int $width Width of image in pixels
 * @param int $height Height of image in pixels
 * @param int $percent Percentage
 * @return $this
 */
    public function adaptiveResizePercent(int $width, int $height, int $percent = 50) {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcSize($width, $height);
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $width = $this->_getDpiCalc($width);
        $height = $this->_getDpiCalc($height);

        $this->_loadProcessor()->adaptiveResizePercent($width, $height, $percent);

        return $this;
    }

/**
 * Adaptively Resizes the Image and Crops Using a Quadrant
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
 * @param int $width
 * @param int $height
 * @param string $quadrant T, B, C, L, R
 * @return $this
 */
    public function adaptiveResizeQuadrant(int $width, int $height, string $quadrant = 'C') {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcSize($width, $height);
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $width = $this->_getDpiCalc($width);
        $height = $this->_getDpiCalc($height);

        $this->_loadProcessor()->adaptiveResizeQuadrant($width, $height, $quadrant);

        return $this;
    }

/**
 * Crops an image from the center with provided dimensions. *
 * If no height is given, the width will be used as a height, thus creating a square crop.
 *
 * @param int $cropWidth Crop width
 * @param int $cropHeight Crop height
 * @return $this
 */
    public function cropFromCenter(int $cropWidth, int $cropHeight = null) {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcSize($cropWidth, $cropHeight);
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $cropWidth = $this->_getDpiCalc($cropWidth);
        $cropHeight = $this->_getDpiCalc($cropHeight);

        $this->_loadProcessor()->cropFromCenter($cropWidth, $cropHeight);

        return $this;
    }

/**
 * Vanilla Cropping - Crops from x,y with specified width and height.
 *
 * @param int $startX
 * @param int $startY
 * @param int $cropWidth
 * @param int $cropHeight
 * @return $this
 */
    public function crop(int $startX, int $startY, int $cropWidth, int $cropHeight) {
        if ($this->_processMode === false) {
            $this->_newDimensions = $this->calcSize($cropWidth, $cropHeight);
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $cropWidth = $this->_getDpiCalc($cropWidth);
        $cropHeight = $this->_getDpiCalc($cropHeight);

        $this->_loadProcessor()->crop($startX, $startY, $cropWidth, $cropHeight);

        return $this;
    }

/**
 * Vanilla Cropping - Crops from x,y with specified width and height.
 *
 * @param int $cropWidth
 * @param int $cropHeight
 * @return $this
 */
    public function smartCrop(int $width, int $height, array $options = []) {
        if ($this->_processMode === false) {
            $this->_newDimensions = compact('width', 'height');
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $width = $this->_getDpiCalc($width);
        $height = $this->_getDpiCalc($height);

        $this->_loadProcessor()->smartCrop($width, $height, $options);

        return $this;
    }

/**
 * Rotates image given N degrees.
 *
 * @param string|int $degrees Degrees or direction (CW or CCW)
 * @retunrn $this
 */
    public function rotate($degrees = 'CW') {
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        if (is_numeric($degrees)) {
            $this->_loadProcessor()->rotateImageNDegrees($degrees);
        } else {
            $this->_loadProcessor()->rotateImage($degrees);
        }

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
 * @return $this
 */
    public function flip($mode) {
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }
        return $this;
    }

/**
 * Applies a filter to the image.
 *
 * @param int $filter
 * @param int $arg1
 * @param int $arg2
 * @param int $arg3
 * @param int $arg4
 * @return $this
 * @see https://secure.php.net/manual/en/function.imagefilter.php
 */
    public function filter($filter, $arg1 = false, $arg2 = false, $arg3 = false, $arg4 = false) {
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $this->_loadProcessor()->filter($filter, $arg1, $arg2, $arg3, $arg4);

        return $this;
    }

/**
 * Applies a Gaussian blur to the image.
 *
 * @param int $level Level of gaussian blur to apply
 * @return $this
 */
    public function gaussianBlur($level = 5) {
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }

        $this->_loadProcessor()->gaussianBlur($level);

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
        if ($this->_processMode === false) {
            return $this->_addCall(__FUNCTION__, func_get_args());
        }
        $this->_loadProcessor()->overlay($image, $position, $opacity, $offsetX, $offsetY);
        return $this;
    }

/**
 * BC method.
 *
 * @see Editor::overlay()
 */
    public function watermark($image, $position = 'center', $opacity = 100, $offsetX = 0, $offsetY = 0) {
        return $this->overlay($image, $position, $opacity, $offsetX, $offsetY);
    }

/**
 * Returns edited file.
 *
 * @return array
 */
    public function getNewDimensions(): array {
        return $this->_newDimensions;
    }

/**
 * Returns edited file.
 *
 * ## Options
 * - 'format' - Image format
 *
 * @param array $options Options
 * @return \Nata\FilesystemManager\File\Image Edited image
 */
    public function getFile(array $options = []): File {
        $options += $this->config();

        $done = $this->_processImage();
        $processor = $this->_loadProcessor();
        $dimensions = $processor->getCurrentDimensions();

        $mime = $this->_image->mime();
        $basename = $this->_image->basename();
        $format = $options['format'] ?? null;
        if ($done === false && ($format === null || $format === $this->_image->extension())) {
            return $this->_image;
        }

        if ($format) {
            [$mime] = Mimetype::get($format);
            $basename = Mimetype::replaceExtension($mime, $basename);
        }

        return FileFactory::build($processor->getImageAsString($format), [
            'type' => 'Image',
            'datasource' => 'Memory',
            'name' => $options['name'] ?? $basename,
            'mime' => $options['mime'] ?? $mime
        ] + $dimensions);
    }

/**
 * Shows an image.
 *
 * This function will show the current image by first sending the appropriate header
 * for the format, and then outputting the image data. If headers have already been sent,
 * a runtime exception will be thrown
 *
 * @param bool $rawData Whether or not the raw image stream should be output
 * @return $this
 */
    public function show($rawData = false) {
        $this->_processImage();
        $this->_loadProcessor()->show($rawData);
        return $this;
    }

/**
 * Saves changes as a new image.
 * If given extension in '$fileName' differs from current one, it will convert image format.
 *
 * This function will make sure the target directory is writeable, and then save the image.
 *
 * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
 * is set as an option ($this->_correctPermissions). If the target cannot be made writeable, then a
 * \RuntimeException is thrown.
 *
 * @param string $fileName The full path and filename of the image to save
 * @return \Nata\FilesystemManager\File
 */
    public function saveAs($fileName, array $options = []) {
        if (empty($fileName)) {
            throw new InvalidArgumentException('Trying to save with an empty filename.');
        }

        $format = null;
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($extension !== $this->_image->extension()) {
            $format = $extension;
        }

        if (!empty($format)) {
            $this->_throwInvalidFormat($format);
        }

        if (!$this->_processImage()) {
            return $this->_image;
        }

        if (!$this->_loadProcessor()->save($fileName, $format)) {
            return false;
        }

        $this->_processor = null;

        return FileFactory::build($fileName, $options);
    }

/**
 * Saves changes to an image file.
 *
 * If $overwrite if false, it will save the image in a subdirectory relative
 * to the source.
 * If overwrite is true, it will overwrite source image with changes.
 *
 * This function will make sure the target directory is writeable, and then save the image.
 *
 * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
 * is set as an option ($this->_correctPermissions). If the target cannot be made writeable, then a
 * \RuntimeException is thrown.
 *
 * @param string $format Image format to save (if true, will be as overwrite)
 * @param bool $overwrite True to overwrite file, false to generate a copy
 *  on a subfolder with the source image's filename
 * @return \Nata\FilesystemManager\File\Image Image with changes
 */
    public function save($format = null, $overwrite = false) {
        if ($format === true) {
            $overwrite = true;
            $format = null;
        }

        if (!empty($format)) {
            $this->_throwInvalidFormat($format);
        }

        return $overwrite === true ? $this->_saveOverwrite($format) : $this->_saveNew($format);
    }

/**
 * Overwrite the source image file with the changes.
 *
 * @param string $format Image format to save (if true, will be as overwrite)
 * @return \Nata\FilesystemManager\File\Image Image instance
 */
    protected function _saveOverwrite($format) {
        if ($this->_processImage()) {
            $this->_loadProcessor()->save($this->_image->getAbsolutePath(), $format);

            if ($this->_isHashName($this->_image)) {
                $this->_image->rename($this->_image->sha1(true) . $this->_image->extension(true));
            }

            $this->_image = new Image($this->_image->getAbsolutePath());

            $this->_processor = null;
            $this->_cacheDirname = null;
        }

        return $this->_image;
    }

/**
 * Save changes to a new file (is it doesn't exist already, if so, it will return the cached one).
 *
 * @param string $format Image format to save (if true, will be as overwrite)
 * @return \Nata\FilesystemManager\File\Image Image instance
 */
    protected function _saveNew($format) {
        if (!empty($this->_calledMethods) || $this->_editedImage === null) {
            $dirname = $this->getCacheDirname($this->_image);
            if (!file_exists($dirname) || (file_exists($dirname) && !is_dir($dirname))) {
                mkdir($dirname);
            }

            $filename = $this->_getCachedFilename($this->_image, $this->_calledMethods, $format);
            $path = $dirname . '/' . $filename;
            if ($exists = file_exists($path)) {
                $this->_editedImage = new Image($path);
                $this->_cached = true;
            }

            if ($exists === false) {
                $this->_editedImage = $this->saveAs($path);
                $this->_cached = false;
            }

            $this->_calledMethods = [];
        }
        return $this->_editedImage;
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
        $this->_processImage();
        return $this->_loadProcessor()->getImageAsString($format);
    }

/**
 * True if when an existing image already existed with the changes requested.
 *
 * @return bool True if an existing image already existed, false otherwise
 */
    public function cached() {
        return $this->_cached;
    }

/**
 * Get cache image's dirname.
 *
 * If changes where made to the image, the returned image
 * will be a cached one.
 *
 * @param \Nata\FilesystemManager\File\Image $image Source image
 * @return string Image cache dirname
 */
    public function getCacheDirname() {
        if ($this->_cacheDirname === null) {
            $info = $this->_image->info();

            $dirname = $info['dirname'];
            if ($this->config('cacheFolder')) {
                $dirname = App::path($this->config('cacheFolder'));
                $dirname = rtrim($dirname, '/');
            }

            $filename = substr($this->_image->sha1(true), 0, 10);
            $this->_cacheDirname = $dirname . '/' . $filename;
        }
        return $this->_cacheDirname;
    }

/**
 * Generate cache filename based on called methods and config.
 *
 * @param \Nata\FilesystemManager\Image $file Image file instance
 * @param array $calledMethods Called methods
 * @param string $format Image format
 * @return string Image filename
 */
    protected function _getCachedFilename(Image $image, $calledMethods, $format) {
        if ($this->_cachedFilename === null) {
            $key = array_keys($calledMethods) + array_filter([
                $this->_config['resizeUp'],
                $this->_config['quality'],
                $this->_config['preserveAlpha'],
                $this->_config['alphaMaskColor'],
                $this->_config['interlace']
            ]);

            $hash = sha1(serialize($key) . $image->sha1(true));
            $name = '';
            $name .= substr($hash, 0, 5);
            $name .= '-' . implode('x', [$this->_newDimensions['width'], $this->_newDimensions['height']]);
            if ($this->_config['dpi'] != 1) {
                $name .= '-' . $this->_config['dpi'] . 'x';
            }
            if ($format) {
                $name .= '.' . $format;
            } else {
                $name .= $image->extension(true);
            }
            $this->_cachedFilename = $name;
        }

        return $this->_cachedFilename;
    }

/**
 * Get current image dimensions.
 *
 * @return array Image's dimensions
 */
    protected function _processImage() {
        if (empty($this->_calledMethods)) {
            return false;
        }

        $this->_processMode = true;

        // @todo Make the sequence as much performant as possible.
        $sequence = [
            'pad',
            'resize',
            'resizePercent',
            'adaptiveResize',
            'adaptiveResizePercent',
            'adaptiveResizeQuadrant',
            'cropFromCenter',
            'crop',
            'rotate',
            'flip',
            'filter',
            'gaussianBlur',
        ];

        foreach ($this->_calledMethods as $calls) {
            foreach ($calls as $method => $args) {
                call_user_func_array([$this, $method], $args);
            }
        }

        $this->_calledMethods = [];
        $this->_processMode = false;

        return true;
    }

/**
 * Register image manipulation method calls.
 *
 * @param string $methodName Method name
 * @param array $args Method arguments
 */
    protected function _addCall($methodName, array $args) {
        $serializable = [];
        foreach ($args as $key => $arg) {
            if (is_object($arg)) {
                $arg = spl_object_id($arg);
            }
            $serializable[$key] = $arg;
        }
        $key = sha1($methodName . serialize($serializable));

        $this->_calledMethods[$key] = [$methodName => $args];

        $this->_editedImage = null;
        $this->_cachedFilename = null;

        return $this;
    }

/**
 * Calculates the new image dimensions.
 * These calculations are based on both the given dimensions and current image dimensions.
 *
 * @param int $newWidth New Image Width
 * @param int $newHeight New Image Height
 * @return array New image dimensions
 */
    public function calcSize(int $newWidth = 0, int $newHeight = 0) {
        return SizeCalculator::calcSize($this->_newDimensions['width'], $this->_newDimensions['height'], $newWidth, $newHeight);
    }

/**
 * Calculates new image dimensions, not allowing the width and height
 * to be less than either the max width or height.
 *
 * @param int $newWidth New Image Width
 * @param int $newHeight New Image Height
 * @return array New image dimensions
 */
    public function calcSizeStrict(int $newWidth = 0, int $newHeight = 0) {
        return SizeCalculator::calcSizeStrict($this->_newDimensions['width'], $this->_newDimensions['height'], $newWidth, $newHeight);
    }

/**
 * Calculates a new width and height for the image based on $newWidth
 * and the current image dimensions.
 *
 * @return array
 * @param int $newWidth New Image Width
 * @return array New image dimensions
 */
    public function calcWidth(int $newWidth) {
        return SizeCalculator::calcWidth($this->_newDimensions['width'], $this->_newDimensions['height'], $newWidth);
    }

/**
 * Calculates a new width and height for the image based on $newHeight
 * and the current image dimensions.
 *
 * @param int $newHeight New height
 * @return array Calculated new width and height
 */
    public function calcHeight(int $newHeight) {
        return SizeCalculator::calcWidth($this->_newDimensions['width'], $this->_newDimensions['height'], $newHeight);
    }

/**
 * Calculates a new width and height for the image based on $percent
 * and the current image dimensions.
 *
 * @param int $percent Percentage
 * @return array New size
 */
    public function calcPercent(int $percent) {
        return SizeCalculator::calcPercent($this->_newDimensions['width'], $this->_newDimensions['height'], $percent);
    }

/**
 * Calculates the new image dimensions.
 * These calculations are based on both the given dimensions and current image dimensions.
 *
 * @param string $newAspectRatio Image aspect ratio
 * @param int $newWidth New Image Width
 * @param int $newHeight New Image Height
 * @return array New image dimensions
 */
    public function calcAspectRatioDimensions($newAspectRatio) {
        return SizeCalculator::calcAspectRatioDimensions($this->_newDimensions['width'], $this->_newDimensions['height'], $newAspectRatio);
    }

/**
 * Get/Set DPI value.
 *
 * @param int|float $dpi DPI
 * @return int|float|$this DPI
 */
    public function dpi($dpi = null) {
        if ($dpi === null) {
            return $this->config('dpi');
        }

        if ($dpi > 4) {
            throw new InvalidArgumentException('Invalid DPI value.');
        }

        $this->config('dpi', $dpi);

        return $this;
    }

/**
 * Get/Set jpegQuality.
 *
 * @deprecated Use Editor::quality() instead.
 * @param int $jpegQuality Quality
 * @return $this
 */
    public function jpegQuality($jpegQuality = null) {
        if ($jpegQuality === null) {
            return $this->config('quality');
        }

        $this->config('quality', $jpegQuality);

        return $this;
    }

/**
 * Initiate \Nata\FilesystemManager\File\Image\Processor instance.
 *
 * @return \Nata\FilesystemManager\File\Image\Processor instance
 */
    protected function _loadProcessor() {
        if ($this->_processor === null) {
            $this->_processor = new GD($this->_image, $this->config());
        }
        return $this->_processor;
    }

/**
 * Get image size for current DPI.
 *
 * @param int $size Size
 * @return int Size for given DPI
 */
    protected function _getDpiCalc($size) {
        if (empty($size)) {
            return $size;
        }

        return intval($this->config('dpi') * $size);
    }

/**
 * Check if current image filename is the respective sha1 hash.
 *
 * @return bool True if is hash, false otherwise
 */
    protected function _isHashName(Image $image = null) {
        return $image->sha1(true) === $image->info('filename');
    }

/**
 * Check if given image format is valid.
 *
 * @param string $format Format to check
 * @return bool True if valid, false otherwise
 */
    protected function _isValidFormat($format) {
        return in_array($format, $this->_supportedFormats);
    }

/**
 * Throw \InvalidArgumentException for invalid format exception.
 *
 * @param string $format Format to check
 * @throws \InvalidArgumentException
 */
    protected function _throwInvalidFormat($format) {
        if (!empty($format) && !$this->_isValidFormat($format)) {
            throw new InvalidArgumentException(sprintf('Format "%s" is not supported. Supported formats: %s', $format, implode(', ', $this->_supportedFormats)));
        }
    }

/**
 * __toString
 *
 * @return string
 */
    public function __toString() {
        return $this->_loadProcessor()->getImageAsString();
    }

/**
 * __destruct.
 *
 * @return void
 */
    public function __destruct() {
        $this->_processor = null;
    }

}
