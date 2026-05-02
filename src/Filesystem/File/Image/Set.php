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

use Nata\Core\NataObject;
use Nata\Cache\Cache;
use Nata\Filesystem\FileRepository;
use Nata\Filesystem\File\Image\Editor;
use Nata\Filesystem\File;
use Nata\Filesystem\File\Image;
use InvalidArgumentException;

/**
 * Create set of images from given Image instance.
 */
class Set extends NataObject {

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'resizeUp' => false,
        'crop' => 'adaptive',
        'jpqQuality' => 100,
        'cache' => 'core_filesystem_set',
        'sizes' => [
            128,
            512,
            992,
            1200,
            1600,
            1920
        ],
        'aspectRatios' => [
            'original',
            '3:2',
            '1:1'
        ],
        'dpi' => [1, 2]
    ];

/**
 * Map of most common aspect ratios.
 *
 * @var array
 */
    protected $_aspectRatiosMap = [
        'photo' => '3:2',
        'square' => '1:1',
        'hd' => '16:9'
    ];

/**
 * Image source to create the set with.
 *
 * @var \Nata\Filesystem\File\Image
 */
    protected $_image;

/**
 * Holds the image editor instance.
 *
 * @var \Nata\Filesystem\ImageEditor
 */
    protected $_imageEditor;

/**
 * Cache config name.
 *
 * @var string
 */
    protected $_cacheConfig = 'core_filesystem_set';

/**
 * Loaded image sets.
 *
 * @var array
 */
    protected static $_sets;


/**
 * Constructor.
 *
 * @param \Nata\Filesystem\File\Image|string $image Image
 * @param array $options Options
 */
    public function __construct($image, array $options = []) {
        if (is_string($image)) {
            $image = new Image($image);
        }

        $this->config($options);

        $this->_image = $image;

        $cache = $this->config('cache');
        if ($cache === 'core_filesystem_set') {
            Cache::config($cache, [
                'engine' => ['Apc', 'Memcache'],
                'duration'=> '1 month',
                'servers' => 'localhost',
                'probability'=> 90,
                'prefix' => 'imgset_',
                'lock' => false,
                'serialize' => 'php'
            ]);
        }

    }

/**
 * Get set of images.
 *
 * @param int $width Prefered width
 * @param string $aspectRatio Aspect ratio
 * @param int $dpi DPI ratio
 * @return array Set of images
 */
    public function get($width = null, $aspectRatio = 'original', $dpi = 1) {
        $set = $this->_filterData('set');
        if (!$set) {
            $data = $this->_create();
            $set = $this->_filterData('set', $data);
        }

        $aspectRatioSet = $this->_filterByAspectRatio($aspectRatio, $set);
        if ($aspectRatioSet && $width > 0) {
            return $this->_closestWidth($aspectRatioSet, $width * $dpi);
        }

        return $aspectRatioSet;
    }

/**
 * Get set of images.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Prefered width
 * @param int $dpi DPI ratio
 * @return array Set of images
 */
    public function getSrcset($aspectRatio = 'original') {
        $srcset = $this->_filterData('srcset');

        if (!$srcset) {
            $data = $this->_create();
            $srcset = $this->_filterData('srcset', $data);
        }

        return $this->_filterByAspectRatio($aspectRatio, $srcset);
    }

/**
 * Get set of images.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Prefered width
 * @param int $dpi DPI ratio
 * @return array Set of images
 */
    public function getPlaceholder($aspectRatio = 'original') {
        $placeholders = $this->_filterData('placeholders');

        if (!$placeholders) {
            $data = $this->_create();
            $placeholders = $this->_filterData('placeholders', $data);
        }

        return $this->_filterByAspectRatio($aspectRatio, $placeholders);
    }

/**
 * Add aspect ratio (or DPI) to existing set.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $dpi DPI
 * @return $this
 */
    public function add($aspectRatio, $dpi = 1) {

        if ($this->_isAspectRatio($aspectRatio) && !$this->exists($aspectRatio)) {
            $this->_create($aspectRatio, $dpi, $this->_sets());
        }

        return $this;
    }

/**
 * Remove aspect ratio to existing set.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $dpi DPI
 * @return $this
 */
    public function remove($aspectRatio) {
        if ($this->exists($aspectRatio)) {
            $data = $this->_sets();
            $set = $this->_filterData('set', $data);

            $this->_eachSetItem($set, function ($img, $imgAspectRatio) use ($aspectRatio, $data) {
                if ($aspectRatio == $imgAspectRatio) {
                    $this->_deleteFile($img);
                }
            });

            $data['set'][$aspectRatio] = null;
            $data['srcset'][$aspectRatio] = null;
            $data['sizes'] = null;

            unset(
                $data['set'][$aspectRatio],
                $data['srcset'][$aspectRatio],
                $data['sizes']
            );

            $this->_sets($data);
        }

        return $this;
    }

/**
 * Check if set exists and if aspect ratio is given, if it exists in the set.
 *
 * @param string $aspectRatio Aspect ratio to check
 * @return bool True if exists, false otherwise
 */
    public function exists($aspectRatio = null) {
        $index = $this->_sets();

        if (func_num_args() === 1) {
            $aspectRatio = $this->_getAspectRatio($aspectRatio, null);
            return $aspectRatio && isset($index['set'][$aspectRatio]);
        }

        return !empty($index);
    }

/**
 * Clear set of images.
 *
 * @param string $aspectRatio Aspect ratio to clear
 * @return $this
 */
    public function clear() {
        $data = $this->_sets();

        if (!empty($data)) {
            $set = $this->_filterData('set', $data);

            $this->_eachSetItem($set, function ($img) {
                $this->_deleteFile($img);
            });

            $this->_deleteIndexFile();

            $key = $this->_getRuntimeKey();
            static::$_sets[$key] = null;
        }

        return $this;
    }

/**
 * Generate the set of images.
 *
 * 'w'   - Expected width of image (not taking into account Hdpi)
 * 'rw'  - Real width of image (taking Hdpi into account)
 * 'img' - Image file
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $dpi DPI
 * @return array Data
 */
    protected function _create($aspectRatio = null, $dpi = null, $data = null) {
        $sourceImage = $this->_image;

        if (!$sourceImage->exists()) {
            throw new InvalidArgumentException("Image doesn't exist!");
        }

        if (empty($data) || !isset($data['set'])) {
            $data = [
                'set' => [],
                'srcset' => [],
                'placeholders' => []
            ];
        }

        $sizes = $this->config('sizes');
        $resizeUp = $this->config('resizeUp');
        $jpgQuality = $this->config('jpqQuality');
        $cropType = $this->config('crop');

        arsort($sizes);

        $sourceWidth = $sourceImage->getWidth();

        $dpis = $this->_normalizeDpis($dpi);
        $aspectRatios = $this->_normalizeAspectRatios($aspectRatio);
        $currentAspectRatio = $sourceImage->getAspectRatio();

        foreach ($aspectRatios as $aspectRatio) {
            $srcset = [$aspectRatio => []];

            if (!isset($data['set'][$aspectRatio])) {
                $data['set'][$aspectRatio] = [];
            }

            $editor = $this->_getEditor();

            // Different aspect ratio from the original
            if ($cropType && !in_array($aspectRatio, [$currentAspectRatio])) {
                $newDimensions = $sourceImage->getEditor()->calcAspectRatioDimensions($aspectRatio);

                $method = ($cropType == 'smart' ? 'smartCrop' : 'adaptiveResize');

                $newImage = $editor->{$method}($newDimensions['width'], $newDimensions['height'])->save();
                $editor = $newImage->getEditor();

                $data['set'][$aspectRatio][$newDimensions['width']] = $this->_getSetItem($newImage, $newDimensions['width']);
            }

            foreach ($dpis as $dpi) {
                $editor->dpi($dpi);

                foreach ($sizes as $width) {
                    $realWidth = $width * $dpi;

                    if (isset($data['set'][$aspectRatio][$realWidth])) {
                        continue;
                    }

                    if (!$resizeUp && ($width > $sourceWidth || $realWidth > $sourceWidth)) {
                        continue;
                    }

                    $img = $editor->jpegQuality($jpgQuality)->resizeUp($resizeUp)->resize($width)->save();

                    $data['set'][$aspectRatio][$realWidth] = $this->_getSetItem($img, $width, $dpi);
                    $data['sizes'][$img->getWidth() . 'x' . $img->getHeight()] = $aspectRatio . '|' . $realWidth;

                    $srcset[$aspectRatio][] = sprintf('%s %sw', FileRepository::url($img), $realWidth);
                }

            }

            krsort($data['set'][$aspectRatio]);

            $data['srcset'][$aspectRatio] = implode(', ', $srcset[$aspectRatio]);

            // Placeholder
            $placeholderImg = $editor->jpegQuality(40)->resizeUp($resizeUp)->resize(32)->save();
            // $placeholderData = $placeholderImg->getDataUri();
            // $placeholderImg->delete();
            $data['placeholders'][$aspectRatio] = FileRepository::url($placeholderImg);
        }

        $this->_sets($data);

        return $data;
    }

/**
 * Check requested aspect ratio.
 *
 * @param array &$set Set of images
 * @param string $aspectRatio Requested aspect ratio
 * @return void
 */
    protected function _getSetItem(Image $img, $width, $dpi = 1) {
        $meta = [
            'w' => $width,
            'rw' => $width * $dpi,
            'img' => $img
        ];
        return $meta;
    }

/**
 * Check requested aspect ratio.
 *
 * @param \Nata\Filesystem\File\Image $image Source image
 * @param string $aspectRatio Requested aspect ratio
 * @return array Aspect ratios
 */
    protected function _normalizeAspectRatios($aspectRatio) {
        $aspectRatios = $this->config('aspectRatios');

        if ($aspectRatio) {
            $aspectRatios += [$aspectRatio => $aspectRatio];
        }

        $originalAspectRatio = $this->_image->getAspectRatio();
        $aspectRatios[$originalAspectRatio] = $originalAspectRatio;

        $_aspectRatios = [];
        foreach ($aspectRatios as $aspectRatio) {
            $aspectRatio = $this->_getAspectRatio($aspectRatio, null);
            if (!$aspectRatio) {
                continue;
            }

            $_aspectRatios[$aspectRatio] = $aspectRatio;
        }

        return $_aspectRatios;
    }

/**
 * Normalize list of .
 *
 * @param int $dpi Requested DPI value
 * @return array DPI's
 */
    protected function _normalizeDpis($dpi) {
        $dpis = $this->config('dpi');

        if ($dpi) {
            $dpis[] = $dpi;
        }

        $dpis = array_flip(array_flip($dpis));

        arsort($dpis);

        return $dpis;
    }

/**
 * Get item from set that is closest to given width.
 *
 * @param array $set Set of images
 * @param int $width Requested width
 * @return array Closest item found for given width
 */
    protected function _closestWidth($set, $width) {

        $closest = null;
        foreach ($set as $realWidth => $item) {

            if ($closest === null
                || ($width > 0 && $realWidth >= $width && abs($width - $closest['rw']) > abs($realWidth - $width))) {
                $closest = $item;
            }

        }

        return $closest;
    }

/**
 * Load/save array with the set of images.
 *
 * @param array $data Image set data
 * @return array $data Image set data
 */
    protected function _filterByAspectRatio($aspectRatio, array $data) {
        $aspectRatio = $this->_getAspectRatio($aspectRatio);
        return isset($data[$aspectRatio]) ? $data[$aspectRatio] : null;
    }

/**
 * Load/save array with the set of images.
 *
 * @param array $data Image set data
 * @return array $data Image set data
 */
    protected function _filterData($type, array $data = null) {
        if (empty($data)) {
            $data = $this->_sets();
        }

        return isset($data[$type]) ? $data[$type] : null;
    }

/**
 * Load/save array with the set of images.
 *
 * @param array $data Image set data
 * @return array $data Image set data
 */
    protected function _sets(array $data = null) {
        $key = $this->_getRuntimeKey();
        $filename = $this->_getIndexFilename();

        if ($data !== null) {
            $this->_cache($key, $data);

            $data['config'] = $this->config();

            file_put_contents($filename, serialize($data));

            return $data;
        }

        if ($data = $this->_cache($key)) {
            return $data;
        }

        if (!file_exists($filename)) {
            return;
        }

        $data = unserialize(file_get_contents($filename));

        $this->config($data['config']);

        return $this->_cache($key, $data);
    }

/**
 * Load/save array with the set of images.
 *
 * @param array $data Image set data
 * @return array $data Image set data
 */
    protected function _cache($key, $data = null) {
        return null;
        /*
        if (!$this->config('cacheEnabled')) {
            return;
        }
        */

        if (func_num_args() === 1) {
            if (isset(static::$_sets[$key])) {
                return static::$_sets[$key];
            }
            return Cache::read($key, $this->_cacheConfig);
        } elseif (!is_array($data)) {
            unset(static::$_sets[$key]);
            return Cache::delete($key, $this->_cacheConfig);
        }

        Cache::write($key, $data, $this->_cacheConfig);
        static::$_sets[$key] = $data;

        return $data;
    }

/**
 * Get the index filename.
 *
 * @return string
 */
    public function getClosest($size) {
        $sizes = $this->_load('sizes');

        $screens = array_keys($sizes);

        list($x, $y) = explode('x', $size);

        // if exact match exists return original
        if (array_search($size, $screens)) {
            return $sizes[$size];
        }

        foreach ($screens as $screen) {
            $s = explode('x', $screen);
            if ($s[0] >= $x && $s[1] >= $y) {
                break;
            }
        }

        // just return largest if it gets this far.
        return $sizes[$screen]; // last one set to $screen is largest
    }

/**
 * Get the index filename.
 *
 * @return string
 */
    protected function _getIndexFilename() {
        return sprintf(
            '%s%simg.set',
            $this->_getEditor()->getCacheDirname(),
            DS
        );
    }

/**
 * Delete set's index file.
 *
 * @return void
 */
    protected function _deleteIndexFile() {
        $indexFile = $this->_getIndexFilename();
        $indexFile = new File($indexFile);

        $folder = $indexFile->folder();

        if ($indexFile->delete() && $folder->isEmpty()) {
            $folder->delete();
        }

    }

/**
 * Get a valid aspect ratio.
 *
 * @param string $aspectRatio Requested aspect ratio
 * @return string Aspect ratio
 */
    protected function _getAspectRatio($aspectRatio, $fallback = 'original') {
        if (empty($aspectRatio) || !$this->_isAspectRatio($aspectRatio)) {
            $aspectRatio = $fallback;
        }

        if ($aspectRatio === 'original') {
            $aspectRatio = $this->_image->getAspectRatio();
        }

        if ($aspectRatio && isset($this->_aspectRatiosMap[$aspectRatio])) {
            $aspectRatio = $this->_aspectRatiosMap[$aspectRatio];
        }

        return $aspectRatio;
    }

/**
 * Check requested aspect ratio.
 *
 * @param \Nata\Filesystem\File\Image $image Source image
 * @param string $aspectRatio Requested aspect ratio
 * @return array Aspect ratios
 */
    protected function _isAspectRatio($aspectRatio) {
        return strpos($aspectRatio, ':') !== false || isset($this->_aspectRatiosMap[$aspectRatio]);
    }

/**
 * Get/load ImageEditor instance.
 *
 * @param \Nata\Filesystem\ImageEditor ImageEditor
 */
    protected function _getEditor($reset = false) {
        if ($this->_imageEditor === null || $reset === true) {
            $this->_imageEditor = new Editor($this->_image);
            if ($this->_image->is('jpg')) {
                $this->_imageEditor->resizeUp($this->config('resizeUp'))->jpegQuality($this->config('jpqQuality'));
            }
        }
        return $this->_imageEditor;
    }

/**
 * Load/save array with the set of images.
 *
 * @param \Nata\Filesystem\File\Image $image Image
 * @param array $set Image set to save
 */
    protected function _getRuntimeKey() {
        return substr($this->_image->sha1(true), 0, 10);
    }

/**
 * Delete image from set.
 *
 * Check if folder is empty, if so, delete.
 *
 * @param \Nata\Filesystem\File\Image $image Image
 */
    protected function _deleteFile(Image $image) {
        $folder = $image->folder();

        $image->delete();

        if ($folder->isEmpty()) {
            $folder->delete();
        }

    }

/**
 * Prepare set.
 *
 * @param array &$set Set
 * @param closure $callback Per item image callback
 * @return void
 */
    protected function _eachSetItem(&$set, $callback) {
        foreach ($set as $aspectRatio => $images) {

            foreach ($images as $realwidth => $meta) {
                $set[$aspectRatio][$realwidth]['img'] = $callback($meta['img'], $aspectRatio);
            }

        }

    }

/**
 * Get list of aspect ratios.
 *
 * @return array Aspect ratios
 */
    public function getAspectRatios() {
        return $this->_aspectRatiosMap;
    }

/**
 * Get list of aspect ratios.
 *
 * @return array Aspect ratios
 */
    public function __debugInfo() {
        return [
            'aspectRatiosMap' => $this->_aspectRatiosMap,
            'index' => $this->_getIndexFilename()
        ];
    }

}
