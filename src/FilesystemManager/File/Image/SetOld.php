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
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\File\Image\Editor;
use Nata\Core\ConfigAwareTrait;
use Nata\Core\Configure;
use Nata\Core\ErrorAwareTrait;
use Nata\FilesystemManager\Exception\FilesystemException;
use Nata\FilesystemManager\FileStorage;
use Nata\FilesystemManager\Mimetype;
use Nata\Utility\Math;

/**
 * Create set of images from given Image instance
 */
class SetOld {

    use ConfigAwareTrait;
    use ErrorAwareTrait;

/**
 * Default configuration.
 *
 * ## Options:
 * - `basePath` - Base path for the image set
 * - `path` - Path
 * - `pathModifier` - Path modifier
 * - `resizeUp` - Resize up
 * - `setKey` - Set key
 * - `dryRun` - Dry run
 * - `fallbackToOriginal` - Create set from original image (true or false) (default: false)
 * - `includeOriginal` - Include original image in the set (true or false) (default: true)
 * - `crop` - Crop
 * - `quality` - Quality
 * - `format` - Format
 * - `store` - Store
 * - `sizes` - Sizes
 * - `dpi` - DPI
 *
 * @var array
 */
    protected $_defaultConfig = [
        'basePath' => null,
        'path' => null,
        'pathModifier' => null,
        'resizeUp' => true,
        'crop' => 'adaptive',
        'quality' => 80,
        'format' => 'webp',
        'store' => null,
        'setKey' => 'set',
        'dryRun' => false,
        'fallbackToOriginal' => false,
        'includeOriginal' => true,
        'sizes' => [
            64,
            512,
            1024,
            1920,
            'original'
        ],
        'dpi' => [1, 2, 3]
    ];

/**
 * Map of most common aspect ratios.
 *
 * @var array
 */
    protected $_aspectRatiosMap = [
        'photo' => '3:2',
        'analog' => '4:3',
        'square' => '1:1',
        'hd' => '16:9',
        'wide' => '16:10',
        'ultrawide' => '21:9',
        'poster' => '5:4'
    ];

/**
 * Image source to create the set with.
 *
 * @var \Nata\FilesystemManager\File\Image
 */
    protected $_image;

/**
 * Base path.
 *
 * @var string
 */
    protected $_basePath;

/**
 * SHA1 hash of the source image path.
 *
 * @var string
 */
    protected $_sha1;

/**
 * Width.
 *
 * @var int
 */
    protected $_width;

/**
 * Height.
 *
 * @var int
 */
    protected $_height;

/**
 * Aspect ratio.
 *
 * @var string
 */
    protected $_aspectRatio;

/**
 * Store.
 *
 * @var string
 */
    protected $_storeName;

/**
 * Source image base path.
 *
 * @var string
 */
    protected $_sourceImageBasePath;

/**
 * Holds the image editor instance.
 *
 * @var \Nata\FilesystemManager\File\Image\Editor
 */
    protected $_imageEditor;

/**
 * Map of aspect ratio to sizes (width => height).
 *
 * @var array
 */
    protected $_aspectRatioSizesMap = [];

/**
 * Loaded image sets.
 *
 * @var array
 */
    protected static $_set;

/**
 * Missing sizes.
 *
 * @var array
 */
    protected $_missingSizes = [];

/**
 * Original image sizes.
 *
 * @var array
 */
    protected $_originalAsSet;


/**
 * Constructor.
 *
 * @param Image $image Source image
 * @param array $options Options
 * @return void
 */
    public function __construct(Image $image, array $options = []) {
        $options += (array)Configure::read('Filesystem.imageSet');
        $this->config($options);
        $this->_image = $image;
        if (!$this->_image->metadata('path')) {
            throw new FilesystemException('Cannot create image set. Missing image path/key.');
        }
        if (!$this->_image->isRaster()) {
            $this->config('fallbackToOriginal', true);
        }

        $this->_sha1 = md5(ltrim($this->_image->metadata('path'), '/'));
        if ($set = $this->_image->metadata('set')) {
            static::$_set[$this->_sha1] = $set;
        }
    }

/**
 * Get collection of images in the given aspect ratio.
 *
 * @param string $aspectRatio Aspect ratio or preset name
 * @param int|null $width Preferred width in pixels
 * @param int $dpi DPI ratio (1-3)
 * @param string|null $store Storage name
 * @param string $key Image set key name
 * @return array Get collection of images or single file info
 */
    public function get(string $aspectRatio = 'original', int|string $width = null, int $dpi = 1, string $store = null, string $key = null): array {
        if ($this->config('fallbackToOriginal') === true) {
            return $this->_getOriginalAsSet();
        }
        $aspectRatio = $this->_getAspectRatio($aspectRatio);
        $key = $this->_getSetKey($key);
        if ($width !== null) {
            if ($width === 'original') {
                $width = $this->_image->width();
            }
            return $this->_getWidth($aspectRatio, $width * $dpi, false, $store, $key);
        }
        return $this->_get($aspectRatio, $store, $key);
    }

/**
 * Creates a collection of images in the given aspect ratio.
 * Will throw exception if source image doesn't exist.
 *
 * @param string $aspectRatio Aspect ratio or preset name
 * @param string|null $store Storage name
 * @param string $key Image set key name
 * @return array Set of images in given aspect ratio created
 * @throws FilesystemException When source image doesn't exist or path is missing
 */
    public function create(string $aspectRatio = 'original', string $store = null, string $key = null): array {
        if (!$this->_image->exists() && $this->config('dryRun') === false) {
            throw new FilesystemException(sprintf('Cannot create image set. "%s" does not exist.', $this->_image->metadata('path')));
        }

        if ($this->_image->metadata('set')) {
            static::$_set[$this->_sha1] = $this->_image->metadata('set');
        }

        $path = $this->_image->metadata('path');
        if ($path === null) {
            throw new FilesystemException('Cannot create image set. Missing image path/key.');
        }

        $key = $this->_getSetKey($key);
        $aspectRatio = $this->_getAspectRatio($aspectRatio);
        $this->_mapAspectRatioSizes($aspectRatio, $this->_config['sizes'], $key);

        $missingSizes = $this->_getMissingSizes($aspectRatio, $key);
        if ($missingSizes) {
            $this->_createSizes($aspectRatio, $missingSizes, true, $store, $key);
        }

        return static::$_set[$this->_sha1][$aspectRatio][$key];
    }

/**
 * Get srcset string for use in HTML img tag.
 *
 * @param string $aspectRatio Aspect ratio or preset name
 * @param string|null $store Storage name
 * @param string $key Image set key name
 * @return string Srcset string for use in <img srcset="...">
 */
    public function getSrcset($aspectRatio = 'original', string $store = null, string $key = null): string {
        $aspectRatio = $this->_getAspectRatio($aspectRatio);
        $key = $this->_getSetKey($key);
        if (isset(static::$_set[$this->_sha1][$aspectRatio][$key . '_srcset'])) {
            return static::$_set[$this->_sha1][$aspectRatio][$key . 'srcset'];
        }

        $srcset = '';
        $dpis = $this->config('dpi');
        foreach ($this->_get($aspectRatio, $store, $key) as $width => $img) {
            foreach ($dpis as $dpi) {
                if ($srcset) {
                    $srcset .= ', ';
                }
                $srcset .= $this->_getSrcsetItem($img['url'], ($width / $dpi), $dpi);
            }
        }

        return static::$_set[$this->_sha1][$aspectRatio][$key . 'srcset'] = $srcset;
    }

/**
 * Delete set of images in given aspect ratio.
 *
 * @todo Implement this method
 * @param string $aspectRatio Aspect ratio or preset name
 * @return void
 */
    public function delete($aspectRatio = 'original') {}

/**
 * Get set of images in given aspect ratio.
 *
 * It will generate the set if 'lazy' is set to false.
 *
 * @param string $aspectRatio Aspect ratio
 * @param string $store Store name
 * @return array Files
 */
    protected function _get(string $aspectRatio, ?string $store, string $key) {
        if (isset(static::$_set[$this->_sha1][$aspectRatio][$key])) {
            return static::$_set[$this->_sha1][$aspectRatio][$key];
        }

        $this->_mapAspectRatioSizes($aspectRatio, $this->_config['sizes'], $key);

        $missingSizes = $this->_getMissingSizes($aspectRatio, $key);
        if ($missingSizes) {
            $this->_createSizes($aspectRatio, $missingSizes, false, $store, $key);
        }

        return static::$_set[$this->_sha1][$aspectRatio][$key];
    }

/**
 * Get image file width in given aspect ratio.
 * It will generate the set if 'lazy' is set to false.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Width
 * @param bool $resize Resize image
 * @param string $store Store name
 * @return array Image file info
 */
    protected function _getWidth(string $aspectRatio, int $width, bool $resize, ?string $store, string $key) {
        // Check if it matches an already existing width
        if ($img = $this->_getSize($aspectRatio, $width, $key)) {
            return $img;
        }

        $this->_mapAspectRatioSizes($aspectRatio, $this->_config['sizes'], $key);

        $width = $this->_getClosestWidth($this->_aspectRatioSizesMap[$aspectRatio], $width);

        // Check now if it matches an closest width
        if ($img = $this->_getSize($aspectRatio, $width, $key)) {
            return $img;
        }

        $missingSizes = $this->_getMissingSizes($aspectRatio, $key);
        if ($missingSizes && isset($missingSizes[$width])) {
            $url = $this->_createWidthSize($aspectRatio, $width, $missingSizes[$width], $resize, $store);
            $this->_addSizeToSet(
                $aspectRatio,
                $url,
                $width,
                $missingSizes[$width],
                false,
                $key
            );
        }

        return static::$_set[$this->_sha1][$aspectRatio][$key][$width];
    }

/**
 * Get missing sizes for the given aspect ratio given the aspect ratio.
 *
 * It loads existing versions and what's in the set to compare the diff.
 *
 * @param string $aspectRatio Aspect Ratio
 * @return array Missing sizes
 */
    protected function _getMissingSizes($aspectRatio, string $key) {
        if (!isset($this->_missingSizes[$aspectRatio])) {
            $this->_missingSizes[$aspectRatio] = array_diff_key(
                $this->_aspectRatioSizesMap[$aspectRatio],
                static::$_set[$this->_sha1][$aspectRatio][$key] ?? []
            );
        }
        return $this->_missingSizes[$aspectRatio];
    }

/**
 * Get set of images.
 *
 * @param int $width Prefered width
 * @param string $aspectRatio Aspect ratio
 * @param int $dpi DPI ratio
 * @return array Set of images
 */
    protected function _createSizes(string $aspectRatio, array $sizes, bool $resize, ?string $store, string $key) {
        foreach ($sizes as $width => $height) {
            $url = $this->_createWidthSize($aspectRatio, $width, $height, $resize, $store);
            $this->_addSizeToSet(
                $aspectRatio,
                $url,
                $width,
                $height,
                false,
                $key
            );
        }
        $this->_image->metadata('set', static::$_set[$this->_sha1]);
    }

/**
 * Resize image in given aspect ratio and width.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width
 * @param int $height
 * @return string Image URL
 */
    protected function _createWidthSize(string $aspectRatio, int $width, int $height, bool $resize, ?string $store): string {
        $path = $this->_getPath($aspectRatio, $width, $height);
        if (!$resize || $this->config('dryRun') === true) {
            return FileStorage::url(path:$path, store:$this->_getStoreName($store));
        }

        $isOriginalAspectRatio = $aspectRatio === $this->_aspectRatio;

        $editor = $this->_loadEditor(true);
        $editor->quality($this->config('quality'));

        if ($isOriginalAspectRatio === false) {
            $editor->adaptiveResize($width, $height);
        } else {
            $editor->resize($width, 0);
        }

        $thumb = $editor->getFile([
            'format' => $this->config('format')
        ]);

        $thumb->setAspectRatio($aspectRatio);

        $img = FileStorage::import(
            source:$thumb,
            path:$path,
            store:$this->_getStoreName($store),
            options:[
                'existingFileStrategy' => 'ignore',
                'dryRun' => $this->config('dryRun')
            ]
        );
        if (!$img && $error = FileStorage::getLastError()) {
            throw new FilesystemException(sprintf('Cannot create image "%s": %s.', $path, $error));
        }

        $thumb->close();
        $editor = null;

        return FileStorage::url(path:$path, store:$this->_getStoreName($store));
    }

/**
 * Map expected sizes for aspect ratio given the list of sizes.
 *
 * @param string $aspectRatio Aspect ratio
 * @param array $sizes Width sizes to map into aspect ratio
 * @param string $key Image set key name
 * @return void
 */
    protected function _mapAspectRatioSizes(string $aspectRatio, array $sizes, string $key) {
        if (isset($this->_aspectRatioSizesMap[$aspectRatio])) {
            return;
        }

        $this->_aspectRatioSizesMap[$aspectRatio] = [];

        $sizes = $sizes ?: $this->config('sizes');
        $isOriginalAspectRatio = $aspectRatio === $this->_getOriginalAspectRatio();
        $resizeUp = $this->config('resizeUp');
        $originalWidth = $this->_image->width();
        $originalHeight = $this->_image->height();

        krsort($sizes);

        foreach ($sizes as $width) {
            if ($width === 'original') {
                $width = $originalWidth;
                // Set original
                if ($isOriginalAspectRatio === true && !isset(static::$_set[$this->_sha1][$aspectRatio][$key][$width])) {
                    $this->_addSizeToSet(
                        $aspectRatio,
                        FileStorage::url($this->_image),
                        $originalWidth,
                        $originalHeight,
                        true,
                        $key
                    );
                }
            }

            $width = (int)$width;

            $result = SizeCalculator::calcWidth($originalWidth, $originalHeight, $width);
            if ($isOriginalAspectRatio === false) {
                $result = SizeCalculator::calcAspectRatioDimensions($result['width'], $result['height'], $aspectRatio);
            }

            // If the size is already set for this aspect ratio
            // OR that we want it's bigger and resize up it's off, ignore
            if (isset($this->_aspectRatioSizesMap[$aspectRatio][$result['width']]) || ($resizeUp === false && $result['width'] > $originalWidth)) {
                continue;
            }

            $this->_aspectRatioSizesMap[$aspectRatio][$result['width']] = $result['height'];
        }
    }

/**
 * Get/Generate the path for the given image.
 * It will use the basePath and filename to generate the path.
 *
 * Is will call the path modifier if set.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Width
 * @param int $height Height
 * @return string Path to file
 */
    protected function _getPath(string $aspectRatio, int $width, int $height): string {
        $basePath = $this->_getBasePath($aspectRatio, $width);
        $filename = $this->_getFilename($aspectRatio, $width, $height);
        $path = $basePath . '/' . $filename;

        $configpath = $this->config('path');
        if ($configpath instanceof Closure) {
            $path = $path($this->_image, $basePath, $filename, $aspectRatio, $width);
        }

        $modifier = $this->config('pathModifier');
        if ($modifier instanceof Closure) {
            $path = $modifier($path);
        }
        return $path;
    }

/**
 * Get/Generate the base path (without filename).
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Width
 * @return string Base path
 */
    protected function _getBasePath(string $aspectRatio, int $width): string {
        $aspectRatioAlias = $this->getAspectRatioAlias($aspectRatio) ?? str_replace(':', '-', $aspectRatio);
        $path = $this->basePath();
        if ($path instanceof Closure) {
            $path = $path($this->_image, $aspectRatioAlias, $aspectRatio, $width);
            if ($path) {
                return $path;
            }
        }
        $pathinfo = pathinfo($path);
        return $pathinfo['dirname'] . '/' . substr(md5($pathinfo['basename']), 0, 10);
    }

/**
 * Get/Generate the relative path for the given image.
 *
 * @param int $aspectRatio AspectRatio
 * @param int $width Width
 * @param int $height Height
 * @return string Filename
 */
    protected function _getFilename(string $aspectRatio, int $width, int $height): string {
        $aspectRatio = str_replace(':', '', $aspectRatio);
        $extension = Mimetype::getExtension('image/' . $this->config('format'), true);
        return sprintf(
            '%sx%s_%s%s',
            $width,
            $height,
            $aspectRatio,
            $extension
        );
    }

/**
 * From existing set of sizes in given aspect ratio,
 * get the closest width size for requested one
 *
 * @param array $sizes Sizes (width => height)
 * @param string $aspectRatio Aspect ratio
 * @return int Closest width
 */
    protected function _getClosestWidth(array $sizes, $preferedWidth): ?int {
        if ($preferedWidth === null) {
            return null;
        }

        $closest = null;
        $highest = null;
        foreach (array_keys($sizes) as $width) {
            if ($preferedWidth == $width) {
                $closest = $width;
                break;
            }

            if ($width > $preferedWidth && ($closest === null || abs($width - $preferedWidth) < abs($closest - $preferedWidth))) {
                $closest = $width;
            }

            if ($highest === null || $width > $highest) {
                $highest = $width;
            }
        }

        return $closest ?? $highest;
    }

/**
 * Get a valid aspect ratio.
 *
 * @param string $aspectRatio Requested aspect ratio
 * @param string $fallback Fallback aspect ratio
 * @return string Aspect ratio
 */
    protected function _getAspectRatio($aspectRatio, $fallback = 'original'): string {
        if (empty($aspectRatio) || !$this->_isAspectRatio($aspectRatio)) {
            $aspectRatio = $fallback;
        }

        if ($aspectRatio === 'original') {
            $aspectRatio = $this->_getOriginalAspectRatio();
        }

        if ($aspectRatio && isset($this->_aspectRatiosMap[$aspectRatio])) {
            $aspectRatio = $this->_aspectRatiosMap[$aspectRatio];
        }

        return $aspectRatio;
    }

/**
 * Check aspect ratio is valid.
 *
 * @param string $aspectRatio Aspect ratio
 * @return bool
 */
    protected function _isAspectRatio($aspectRatio): bool {
        return strpos($aspectRatio, ':') !== false || isset($this->_aspectRatiosMap[$aspectRatio]);
    }

/**
 * Get image size in given width.
 *
 * @param string $aspectRatio Aspect ratio
 * @param int $width Width
 * @return array Image set for given width
 */
    protected function _getSize($aspectRatio, int|null $width = null, string $key): array|null {
        if ($width === null) {
            return static::$_set[$this->_sha1][$aspectRatio][$key];
        }
        return static::$_set[$this->_sha1][$aspectRatio][$key][$width] ?? null;
    }

/**
 * Append image size in given width into the set.
 *
 * @param string $aspectRatio Aspect ratio
 * @param string $url Image URL
 * @param int $width Width
 * @param int $height Height
 * @param bool $isSource Is source image
 * @return void
 */
    protected function _addSizeToSet(string $aspectRatio, string $url, int $width, int $height, bool $isSource, string $key): void {
        static::$_set[$this->_sha1][$aspectRatio][$key][$width] = [
            'url' => $url,
            'w' => $width,
            'h' => $height,
            's' => $isSource
        ];
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Image srcset
 */
    protected function _getSrcsetItem($url, $size, $dpi) {
        return $url . ' ' . floor($size) . 'w' . ($dpi > 1 ? ' ' . $dpi . 'x' : '');
    }

/**
 * Get/load ImageEditor instance.
 *
 * @param bool $reset Whether to reset the existing editor instance
 * @return \Nata\FilesystemManager\File\Image\Editor Image editor instance
 */
    protected function _loadEditor($reset = false) {
        return new Editor($this->_image, ['resizeUp' => $this->config('resizeUp')]);
    }

/**
 * Get store name from configuration or metadata.
 *
 * @param string|null $store Optional store name override
 * @return string Store name
 * @throws FilesystemException When no store name could be determined
 */
    protected function _getStoreName(?string $store): string {
        $storeName = $store ?? $this->config('store') ?? $this->_image->metadata('store');
        if (empty($storeName)) {
            throw new FilesystemException('Missing storage store name.');
        }
        return $storeName;
    }

/**
 * Get aspect ratio.
 *
 * @return string Aspect ratio
 */
    private function _getOriginalAspectRatio(): string {
        if ($this->_aspectRatio === null) {
            $this->_aspectRatio = Math::calcAspectRatio($this->_image->width(), $this->_image->height());
        }
        return $this->_aspectRatio;
    }

/**
 * Get source image (relative) path/key.
 *
 * @param string $basePath Base path
 * @return string Source image path
 */
    public function basePath(string $basePath = null) {
        if ($basePath === null) {
            if ($this->_basePath === null) {
                $this->_basePath = $this->config('basePath') ?? $this->_image->metadata('path');
            }
            return $this->_basePath;
        }
        $this->_basePath = $basePath;
        return $this;
    }

/**
 * Get aspect ratio alias if exists for given aspect ratio.
 *
 * @param string $aspectRatio The aspect ratio to look up
 * @return string|null Alias name or null if not found
 */
    public function getAspectRatioAlias($aspectRatio) {
        return array_flip($this->_aspectRatiosMap)[$aspectRatio] ?? null;
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
    protected function _getOriginalAsSet() {
        if ($this->_originalAsSet === null) {
            $this->_originalAsSet = [];
            $this->_originalAsSet = [
                'url' => $this->_image->url() ?? FileStorage::url($this->_image->metadata('path'), $this->_image->metadata('store')),
                'w' => $this->_image->width(),
                'h' => $this->_image->height(),
                's' => true,
                'otf' => true
            ];
        }
        return $this->_originalAsSet;
    }

/**
 * Get/Set list of images.
 *
 * @param array|string $list List of images
 * @return array|self List of images or self
 */
    public function list(array|string $list = null) {
        if ($list === null) {
            return static::$_set[$this->_sha1] ?? [];
        }

        if (is_string($list)) {
            $list = json_decode($list, true);
            if (!$list) {
                throw new FilesystemException(json_last_error_msg());
            }
        }

        static::$_set[$this->_sha1] = $list;

        return $this;
    }

/**
 * Select set key to be used.
 *
 * @param string|null $setKey Set key
 * @return self
 */
    public function select(string|null $setKey) {
        $this->config('setKey', $setKey);
        return $this;
    }

/**
 * Get set key.
 *
 * @param string|null $setKey Set key
 * @return string Set key
 */
    protected function _getSetKey(string|null $setKey) {
        if ($setKey !== null) {
            return $setKey;
        }
        return $this->config('setKey') ?? 'set';
    }

/**
 * __debugInfo.
 *
 * @return array Set
 */
    public function __debugInfo() {
        return [
            'config' => $this->_config,
            'set' => static::$_set[$this->_sha1] ?? []
        ];
    }

}
