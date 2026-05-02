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

/**
 * Utility class to calculate image size based on given width and/or height.
 */
class SizeCalculator {

/**
 * Aspect ratio map.
 *
 * @var array
 */
    protected static $_aspectRatios = [
        '16:9' => [1920, 1080],
        '3:2' => [1080, 720],
        '4:3' => [1024, 768]
    ];


/**
 * Calculates the new image dimensions.
 * These calculations are based on both the given new dimensions and current image dimensions.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param int $newWidth New Image Width
 * @param int $newHeight New Image Height
 * @return array New image dimensions
 */
    public static function calcSize(int $width, int $height, int $newWidth = 0, int $newHeight = 0): array {
        if (!($width > 0) || !($height > 0)) {
            return [0, 0];
        }

        $newSize = [
            'width'  => $width,
            'height' => $height
        ];

        if ($newWidth > 0) {
            $newSize = static::calcWidth($width, $height, $newWidth);
            if ($newHeight > 0 && $newSize['height'] > $newHeight) {
                $newSize = static::calcHeight($width, $height, $newSize['height']);
            }
        }

        if ($newHeight > 0) {
            $newSize = static::calcHeight($width, $height, $newHeight);
            if ($newWidth > 0 && $newSize['width'] > $newWidth) {
                $newSize = static::calcWidth($width, $height, $newSize['width']);
            }
        }

        return $newSize;
    }

/**
 * Calculates new image dimensions, not allowing the width and height
 * to be less than either the max width or height.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param int $newWidth New Image Width
 * @param int $newHeight New Image Height
 * @return array New image dimensions
 */
    public static function calcSizeStrict(int $width, int $height, int $newWidth = 0, ?int $newHeight = 0): array {
        // first, we need to determine what the longest resize dimension is..
        if ($newWidth >= $newHeight) {
            // and determine the longest original dimension
            if ($width > $height) {
                $newDimensions = static::calcHeight($width, $height, $newHeight);
                if ($newDimensions['width'] < $newWidth) {
                    $newDimensions = static::calcWidth($width, $height, $newWidth);
                }
            } elseif ($height >= $width) {
                $newDimensions = static::calcWidth($width, $height, $newWidth);
                if ($newDimensions['height'] < $newHeight) {
                    $newDimensions = static::calcHeight($width, $height, $newHeight);
                }
            }
        } elseif ($newHeight > $newWidth) {
            if ($width >= $height) {
                $newDimensions = static::calcWidth($width, $height, $newWidth);
                if ($newDimensions['height'] < $newHeight) {
                    $newDimensions = static::calcHeight($width, $height, $newHeight);
                }
            } elseif ($height > $width) {
                $newDimensions = static::calcHeight($width, $height, $newHeight);
                if ($newDimensions['width'] < $newWidth) {
                    $newDimensions = static::calcWidth($width, $height, $newWidth);
                }
            }
        }
        return $newDimensions;
    }

/**
 * Calculates a new width and height for the image based on $newWidth
 * and the current image dimensions.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param int $newWidth New Image Width
 * @return array New image dimensions
 */
    public static function calcWidth(int $width, int $height, int $newWidth): array {
        if (!($width > 0) || !($height > 0)) {
            return [0, 0];
        }

        $newWidthPercentage = (100 * $newWidth) / $width;
        $newHeight = ($height * $newWidthPercentage) / 100;

        return [
            'width' => intval($newWidth),
            'height' => intval($newHeight)
        ];
    }

/**
 * Calculates a new width and height for the image based on $newHeight
 * and the current image dimensions.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param int $newHeight New height
 * @return array Calculated new width and height
 */
    public static function calcHeight(int $width, int $height, int $newHeight): array {
        if (!($width > 0) || !($height > 0)) {
            return [0, 0];
        }

        $newHeightPercentage = (100 * $newHeight) / $height;
        $newWidth = ($width * $newHeightPercentage) / 100;

        return [
            'width' => intval($newWidth),
            'height' => intval($newHeight)
        ];
    }

/**
 * Calculates a new width and height for the image based on $percent
 * and the current image dimensions.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param int $percent Percentage
 * @return array New size
 */
    public static function calcPercent(int $width, int $height, float $percent) {
        if (!($width > 0) || !($height > 0)) {
            return [0, 0];
        }

        $newWidth = ($width * $percent) / 100;
        $newHeight = ($height * $percent) / 100;

        return [
            'width' => intval($newWidth),
            'height' => intval($newHeight)
        ];

    }

/**
 * Calculates the new image dimensions.
 * These calculations are based on both the given dimensions and current image dimensions.
 *
 * @param int $width Image Width
 * @param int $height Image Height
 * @param string $newAspectRatio Image aspect ratio
 * @return array New image dimensions
 */
    public static function calcAspectRatioDimensions(int $width, int $height, string $newAspectRatio): array {
        $similar = [
            '16:10' => '8:5',
            '10:16' => '5:8'
        ];

        if (isset($similar[$newAspectRatio])) {
            $newAspectRatio = $similar[$newAspectRatio];
        }

        [$newWidthRatio, $newHeightRatio] = splitter($newAspectRatio, ':');
        $newWidthRatio = (int)$newWidthRatio;
        $newHeightRatio = (int)$newHeightRatio;

        $newWidth = $width;
        $newHeight = ($newHeightRatio * $newWidth) / $newWidthRatio;

        // If new height is longer then width
        if ($height < $newHeight) {
            $newHeight = $height;
            $newWidth = ($newWidthRatio * $newHeight) / $newHeightRatio;
        }

        return [
            'width'  => intval($newWidth),
            'height' => intval($newHeight)
        ];
    }

/**
 * Calculates aspect ratio of given dimensions.
 *
 * @param int $width New Image Width
 * @param int $height New Image Height
 * @param float $tolerance Tolerance
 * @return string Aspect ratio of given dimensions
 */
    public static function calcAspectRatio(int $width, int $height, float $tolerance = 0.02): ?string {
        $aspectRatio = null;

        if ($width <= 0 || $height <= 0) {
            return $aspectRatio;
        } elseif ($width === $height) {
            return '1:1';
        }

        $total = $width + $height;
        for ($i = 1; $i <= 40; $i++) {
            $widthrx = $i * 1.0 * $width / $total;
            $heightrx = $i * 1.0 * $height / $total;

            // Accept aspect ratios within a given tolerance
            if ($i == 40 || (abs($widthrx - round($widthrx)) <= $tolerance && abs($heightrx - round($heightrx)) <= $tolerance)) {
                $aspectRatio = round($widthrx) . ':' . round($heightrx);
                break;
            }

        }

        return $aspectRatio;
    }

}
