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

use Closure;
use Nata\Collection\Collection;
use Nata\Core\ConfigAwareTrait;
use Nata\Core\Configure;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\FileStorage;
use Nata\FilesystemManager\Exception\FilesystemException;
use Nata\FilesystemManager\Mimetype;

/**
 * Create and apply watermarks to images.
 */
class Watermarker {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * ## Options:
 *
 * - `provider` - File provider (defaults to the original image storage provider)
 * - `format` - Image format (defaults to the original image)
 * - `quality` - JPEG quality
 * - `position` - position
 * - `opacity` - opacity
 * - `offsetX` - offset X position
 * - `offsetY` - offset Y position
 *
 * @var array
 */
    protected $_defaultConfig = [
        'quality' => 100,
        'format' => 'webp',
        'store' => null, // defaults to the original image store name
        'prefix' => 'wm-',
        'position' => 'center',
        'opacity' => 100,
        'offsetX' => 0,
        'offsetY' => 0,
        'strict' => false,
        'dryRun' => false,
    ];

/**
 * Options that should be treated as keys to generate
 * the options uniqid.
 *
 * @var array
 */
    protected $_optionsAsKey = [
        'position',
        'opacity',
        'offsetX',
        'offsetY',
        'quality',
    ];

/**
 * Image instance.
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $_image;


/**
 * Constructor.
 *
 * @param Image $image Main image to apply watermark
 * @param array $options Options
 * @return void
 */
    public function __construct(Image $image, array $options = []) {
        $options += (array)Configure::read('Filesystem.watermarker');
        $this->config($options);
        $this->_image = $image;

        if (!$this->_image->metadata('path')) {
            throw new FilesystemException('Cannot create watermarked image. Missing image path/key.');
        }
    }

/**
 * Get watermark image instance.
 *
 * @param Collection|array|Image $watermarks Watermarks to be applied
 * @param string $store Store name
 * @param array $options Options
 * @return Image
 */
    public function get(array|Image|string $watermarks, ?string $store = null, array $options = []): Image {
        $options += $this->config();

        $watermarks = $this->_prepareWatermarks($watermarks);
        $path = $this->_getPath($watermarks, $options);
        return FileStorage::get($path, $this->_getStoreName($store), [
            'asObject' => true,
            'width' => $this->_image->width(),
            'height' => $this->_image->height(),
            'mime' => $this->_image->mime()
        ]);
    }

/**
 * Generate watermarked image.
 *
 * @param array|Image|string $watermarks Watermark file(s)
 * @param string $store Store name for the watermarked image
 * @param array $options Options
 * @return Image
 */
    public function generate(array|Image|string $watermarks, ?string $store = null, array $options = []): Image {
        if (!$this->_image->exists()) {
            throw new FilesystemException(sprintf('Cannot get watermarked image. "%s" does not exist.', $this->_image->metadata('path')));
        }

        $options += $this->config();

        $watermarks = $this->_prepareWatermarks($watermarks);
        if (!$watermarks) {
            if ($this->config('strict')) {
                throw new FilesystemException('Missing watermark image.');
            }
            return $this->_image;
        }

        $editor = $this->_image->editor();
        foreach ((array)$watermarks as $watermarkPath => $watermarkStore) {
            $watermark = $watermarkStore;
            if (!($watermark instanceof Image)) {
                $watermark = FileStorage::get(
                    path: $watermarkPath,
                    store: $watermarkStore,
                    options: ['asObject' => true]
                );
            }

            if ($watermark->exists() === false) {
                throw new FilesystemException(sprintf('Watermark image "%s" does not exist in store "%s".', $watermarkPath, $watermark->metadata('store')));
            }

            $editor->overlay(
                $watermark,
                $options['position'],
                $options['opacity'],
                $options['offsetX'],
                $options['offsetY']
            );
        }

        $watermarkedFile = $editor->getFile([
            'format' => $options['format'],
        ]);

        $store = $this->_getStoreName($store);
        if ($store === null) {
            return $watermarkedFile;
        }

        $file = FileStorage::import(
            source: $watermarkedFile,
            store: $store,
            path: $this->_getPath($watermarks, $options),
            options: [
                'asObject' => true,
                'width' => $this->_image->width(),
                'height' => $this->_image->height(),
                'dryRun' => $this->config('dryRun')
            ]
        );

        if ($error = FileStorage::getLastError()) {
            throw new FilesystemException($error);
        }

        $watermarkedFile->close();
        $watermarkedFile = null;

        return $file;
    }

/**
 * Get watermark image path.
 *
 * @param array|string|Image $watermarkFile Watermark file(s)
 * @param array $options Options
 * @return string
 */
    public function path(array|string|Image $watermarks, array $options = []): string {
        $options += $this->config();
        $watermarks = $this->_prepareWatermarks($watermarks);
        return $this->_getPath($watermarks, $options);
    }

/**
 * Get watermark image path.
 *
 * @param array|string|Image $watermark Watermark file(s)
 * @param array $options Options
 * @return string
 */
    protected function _getPath(array|string|Image $watermark, array $options): string {
        return $this->_generatePath(
            $this->_generateUniqid($watermark, $options)
        );
    }

/**
 * Generate uniqid.
 *
 * @param array $watermarks Watermark file(s)
 * @param array $options Options
 * @return string
 */
    protected function _generateUniqid(array $watermarks, array $options): string {
        $key = ltrim($this->_image->metadata('path'), '/');
        foreach ($watermarks as $watermarkPath => $watermarkStore) {
            $key .= ltrim($watermarkPath, '/');
        }

        ksort($options);

        $optionsKey = '';
        foreach ($this->_optionsAsKey as $option) {
            $optionsKey .= (string)$options[$option];
        }

        return substr(md5($key), 0, 10) . '-' . substr(md5($optionsKey), 0, 10);
    }

/**
 * Prepare watermark files.
 *
 * @param array $watermarks Watermark file(s)
 * @return array
 */
    protected function _prepareWatermarks(array $watermarks): array {
        $prepared = [];
        foreach ((array)$watermarks as $watermarkPath => $watermarkStore) {
            if (is_int($watermarkPath)) {
                if (is_string($watermarkStore)) {
                    $watermarkPath = $watermarkStore;
                    $watermarkStore = null;
                } elseif ($watermarkStore instanceof Image) {
                    $watermarkPath = $watermarkStore->metadata('path');
                }
            }

            if (empty($watermarkPath)) {
                continue;
            }

            $prepared[$watermarkPath] = $watermarkStore;
        }
        return $prepared;
    }

/**
 * Get store name.
 *
 * @param string $store Store name
 * @return string Store name
 */
    protected function _getStoreName(?string $store): string {
        $storeName = $store ?? $this->config('store') ?? $this->_image->metadata('store');
        if (empty($storeName)) {
            throw new FilesystemException('Missing storage store name.');
        }
        return $storeName;
    }

/**
 * Generate relative path.
 *
 * @param string $uniqid Uniqid
 * @return string
 */
    protected function _generatePath(string $uniqid): string {
        $relativePath = $this->_image->metadata('path');
        $relativePath = dirname($relativePath) .
            '/' . substr(md5(basename($relativePath)), 0, 10) .
            '/' . $this->config('prefix') . $uniqid . Mimetype::getExtension('image/' . $this->config('format'), true);

        $modifier = $this->config('pathModifier');
        if ($modifier instanceof Closure) {
            $relativePath = $modifier($relativePath);
        }

        return $relativePath;
    }

/**
 * Get set key.
 *
 * @return string
 */
    public function getSetKey(): string {
        $relativePath = $this->_image->metadata('path');
        $relativePath = dirname($relativePath);
        return substr(md5(basename($relativePath)), 0, 10);
    }

/**
 * Generate relative path to the watermark set.
 *
 * @param string $path Path for the set
 * @return string
 */
    protected function _generateSetBasePath(string $path): string {
        return pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME);
    }

}
