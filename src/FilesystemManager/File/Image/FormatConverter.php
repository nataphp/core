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

use Nata\Core\ConfigAwareTrait;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\Exception\FilesystemException;
use Nata\FilesystemManager\FileFactory;
use Imagick;
use Throwable;

/**
 * Convert image formats.
 */
class FormatConverter {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [];

/**
 * Image instance.
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $_image;


/**
 * Constructor.
 *
 * @param Image $image Main image to convert
 * @param array $options Options
 * @return void
 */
    public function __construct(Image $image, array $options = []) {
        if (!extension_loaded('imagick')) {
            throw new FilesystemException('Imagick extension is not installed.');
        }
        $this->_image = $image;
    }

/**
 * Convert image format.
 *
 * @param string $format Format to convert to
 * @return void
 */
    public function convertTo(string $format) {

        match ($format) {
            'jpg' => $this->convertToJpg(),
            'png' => $this->convertToPng(),
            'gif' => $this->convertToGif(),
            'webp' => $this->convertToWebp(),
            default => throw new FilesystemException('Invalid format.'),
        };


    }

/**
 * Convert image to given format.
 *
 * @param string $format Format to convert to
 * @return File
 */
    protected function _convert(string $format) {
        try {
            // Load the image
            $imagick = new Imagick('path/to/your/image.heic');

            // Set the image format to JPG or PNG
            $imagick->setImageFormat($format);

            // Get the image data as a string
            $imageData = $imagick->getImageBlob();

            // Clear the Imagick object
            $imagick->clear();
            $imagick->destroy();

            return FileFactory::build($imageData, [
                'mime' => 'image/' . $format,
            ]);
        } catch (Throwable $e) {
            throw new FilesystemException('Error converting image to ' . $format . ': ' . $e->getMessage());
        }
    }
}
