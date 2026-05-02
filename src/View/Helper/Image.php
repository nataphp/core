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

namespace Nata\View\Helper;

use Nata\Core\App;
use Nata\View\Helper;
use Nata\Utility\Html;
use Nata\Utility\Math;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image as FileImage;
use Nata\FilesystemManager\FileStorage;
use Nata\ORM\Entity;

/**
 * Image manipulation Helper.
 */
class Image extends Helper {

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'resizeUp' => false,
        'dpi' => [1, 2],
        'srcSizes' => [],
        'safePlaceholder' => 'data:image/gif;base64,R0lGODlhAwACAIAAAP///wAAACH/C1hNUCBEYXRhWE1QPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4gPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iQWRvYmUgWE1QIENvcmUgNS4wLWMwNjAgNjEuMTM0Nzc3LCAyMDEwLzAyLzEyLTE3OjMyOjAwICAgICAgICAiPiA8cmRmOlJERiB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiPiA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6OEZEQjRGRkQ0RTVDMTFFQUJERDVCMEFFN0YwMTY5NjAiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6OEZEQjRGRkU0RTVDMTFFQUJERDVCMEFFN0YwMTY5NjAiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo4RkRCNEZGQjRFNUMxMUVBQkRENUIwQUU3RjAxNjk2MCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo4RkRCNEZGQzRFNUMxMUVBQkRENUIwQUU3RjAxNjk2MCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PgH//v38+/r5+Pf29fTz8vHw7+7t7Ovq6ejn5uXk4+Lh4N/e3dzb2tnY19bV1NPS0dDPzs3My8rJyMfGxcTDwsHAv769vLu6ubi3trW0s7KxsK+urayrqqmop6alpKOioaCfnp2cm5qZmJeWlZSTkpGQj46NjIuKiYiHhoWEg4KBgH9+fXx7enl4d3Z1dHNycXBvbm1sa2ppaGdmZWRjYmFgX15dXFtaWVhXVlVUU1JRUE9OTUxLSklIR0ZFRENCQUA/Pj08Ozo5ODc2NTQzMjEwLy4tLCsqKSgnJiUkIyIhIB8eHRwbGhkYFxYVFBMSERAPDg0MCwoJCAcGBQQDAgEAACH5BAEAAAAALAAAAAADAAIAAAIChF8AOw=='
    ];

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'lazyload' => false,
        'aspect_ratio' => 'original',
        'dpi' => null,
        'srcset' => true,
        'store' => null,
        'sizes' => 'auto',
        'alt' => null,
        'class' => null,
        'title' => null,
        'file' => null,
        'url' => null,
        'svg' => false,
        'src' => null,
        'scaling' => 'resize',
        'full' => false,
        'ssl' => null,
        'size' => null,
        'width' => null,
        'height' => null,
        'size_width' => null,
        'size_height' => null,
        'percent' => null,
        'resizeUp' => false,
        'filename' => null,
        'ratio' => null,
        'jpegQuality' => 100,
        'placeholder' => null,
        'watermark' => null,
        'position' => 'center',
        'opacity' => 100,
        'format' => null,
        'offsetX' => 20,
        'offsetY' => 20,
        'newsystem' => false,
        'print' => 'url',
        'cache' => null
    ];


/**
 * Pseudo-constructor.
 *
 * @param array $config Configuration parameters
 * @return void
 */
    public function initialize(array $config) {}
/**
 * Render <img ...> tag.
 *
 * @param array $params Smarty passed parameters
 * @return string <img> HTML tag
 */
    public function img(array $params) {
        $params = $this->_normalizeParams($params);
        $params = $this->_getSrcset($params);

        $attrs = [
            'alt' => $params['alt'],
            'class' => $params['class'],
            'title' => $params['title'],
            'width' => $params['width'],
            'height' => $params['height'],
            'srcset' => null,
            'src' => null
        ];

        $src = null;
        if ($params['src'] !== false) {
            $src = $this->_getSrc($params, $params['aspect_ratio']);
            // $attrs['src'] = $params['src'];
        }

        // $attrs = $this->_addLazyloadAttr($params, $attrs, 'src', $src);
        // $attrs = $this->_addLazyloadAttr($params, $attrs, 'srcset', $params['srcset']);

        if (array_key_exists('srcset', $attrs) || array_key_exists('data-srcset', $attrs)) {
            $attrs['sizes'] = $params['sizes'];
        }

        return Html::elem('<img>', $attrs);
    }

/**
 * Return srcset attribute value for <img ...> tag.
 *
 * @param array $params Smarty passed parameters
 * @return array srcset
 */
    protected function _addLazyloadAttr($params, $attrs, $attr, $value, $prepend = 'data') {
        if (!$value) {
            return $attrs;
        }
        $name = ($params['lazyload'] ? $prepend . '-' : '') . $attr;
        $attrs[$name] = $value;
        return $attrs;
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Path to image
 */
    public function datauri(array $params) {
        $params = $this->_normalizeParams($params);
        $img = $this->_getFile($params);
        if (!$img || !$img->is('image')) {
            return null;
        }
        return $img->getDataUri();
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Path to image
 */
    public function fileurl(array $params) {
        $params = $this->_normalizeParams($params);
        $img = $this->_getFile($params);
        if (!$img || !$img->is('image')) {
            return null;
        }
        return $img->getFileUrl();
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Path to image
 */
    public function src(array $params) {
        $params = $this->_normalizeParams($params);
        $filename = $params['file'] ?? $params['url'];
        if (is_string($filename) && (str_starts_with($filename, 'data:') || str_starts_with($filename, 'http'))) {
            return $filename;
        }

        $set = $this->_getSrc($params) ?? null;
        if (is_array($set)) {
            //@@ This is temporary, to catch missing files
            if ($params['file'] instanceof Entity && $set['url']) {
                $set['url'] .= '#fs-' . $params['file']->get('id');
            }
            return parent::_output($set['url'], $params);
        } elseif (is_string($set) && str_starts_with($set, 'data:')) {
            return $set;
        }
        return $this->_safePlaceholder();
    }

/**
 * Return srcset attribute value for <img ...> tag.
 *
 * @param array $params Smarty passed parameters
 * @return string srcset
 */
    public function srcset(array $params) {
        $params = $this->_normalizeParams($params);
        $params = $this->_getSrcset($params);
        return $params['srcset'];
    }

/**
 * Image resize.
 *
 * @param array $params Smarty passed parameters
 * @return string Url to cached image file
 */
    public function resize(array $params) {
        return $this->src($params);
    }

/**
 * Image adaptive resize.
 *
 * @param array $params Smarty passed parameters
 * @return string Url to cached image file
 */
    public function adaptive(array $params) {
        return $this->src($params);
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Image Url
 */
    protected function _getSrc(array $params) {
        if ($this->_isUrl($params)) {
            return ['url' => $params['file'] ?? $params['url']];
        }

        if ($params['svg']) {
            return ['url' => $this->_output($params['url'], $params)];
        }

        $img = $this->_getFile($params);
        if (!$img || !$img->is('image')) {
            return null;
        }

        if (!$img->isRaster()) {
            $img->set()->config('fallbackToOriginal', true);
        }

        if ($img->isRaster() && $params['watermark']) {
            $img = $img->watermarker()->get($params['watermark']);
            if (!$img) {
                return null;
            }
        }

        $dpi = $this->_getDpi($params, 1);
        $width = $this->_getWidth($params);
        $aspectRatio = $this->_getAspectRatio($params);
        return $img->set()->get($aspectRatio, $width, $dpi);
    }

/**
 * Get srcset to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return array Image Url
 */
    protected function _getSrcset(array $params) {
        if ($params['srcset'] == false || is_string($params['srcset'])) {
            return $params;
        }
        $img = $this->_getFile($params);
        if (!$img) {
            return $params;
        }
        $aspectRatio = $this->_getAspectRatio($params);
        return $img->getSet()->getSrcSet($aspectRatio);
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Image Url
 */
    protected function _getWidth($params, $fallback = 'original') {
        if ($params['size_width']) {
            return $params['size_width'];
        }
        return $params['width'] ? $params['width'] : $fallback;
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Image Url
 */
    protected function _getAspectRatio($params, $fallback = 'original') {
        $aspectRatio = $params['aspect_ratio'];
        if ($aspectRatio === null) {
            $aspectRatio = $fallback;
            if ($params['size_width'] > 0 && $params['size_height'] > 0) {
                $aspectRatio = Math::calcAspectRatio($params['size_width'], $params['size_height']);
            }
        }
        return $aspectRatio;
    }

/**
 * Get src to be used in <img>.
 *
 * @param array $params Smarty passed parameters
 * @return string Image Url
 */
    protected function _getDpi($params, $fallback = 1) {
        return $params['dpi'] ?? $fallback;
    }

/**
 * Get store name from given parameters.
 *
 * @param array $params Smarty passed parameters
 * @param string $fallback Fallback store name
 * @return string
 */
    protected function _getStoreName($params, $fallback = FileStorage::DEFAULT_STORE): string {
        if ($params['file'] instanceof Entity && $params['file']->has('store')) {
            return $params['file']->get('store');
        }
        return $params['store'] ?? $fallback;
    }

/**
 * Get URL from give file path/instance.
 *
 * @param mixed $url Data to check for url
 * @return string Url
 */
    protected function _isUrl($params) {
        return ((is_string($params['file']) && strpos($params['file'], 'http') !== false)
            || (is_string($params['url']) && strpos($params['url'], 'http') !== false));
    }

/**
 * Get image \Nata\Filesystem\File instance.
 *
 * @param array $params Smarty passed parameters
 * @return \Nata\FilesystemManager\File\Image
 */
    protected function _getFile(array $params) {
        if (is_string($params['url'])) {
            return FileStorage::get($params['url'], $this->_getStoreName($params), [
                'asObject' => true
            ]);
        }

        $file = $params['file'];
        if ($file instanceof File) {
            return $file;
        }

        if ($file instanceof Entity) {
            $storageFile = FileStorage::get($file->get('url'), $file->get('store') ?? FileStorage::DEFAULT_STORE, [
                'asObject' => true,
                'width' => $file->get('width'),
                'height' => $file->get('height'),
                'mime' => $file->get('mime'),
                'aspectRatio' => $file->get('aspect_ratio'),
                // 'name' => $file->get('name'),
                'size' => $file->get('size'),
                'freeze' => false
            ]);
            if ($storageFile) {
                $storageFile->metadata($this->_generateImageMetadata($storageFile, $file, $params));
                return $storageFile;
            }
        } elseif (is_string($file)) {
            return FileStorage::get($file, $this->_getStoreName($params), [
                'asObject' => true
            ]);
        }
        return null;
    }

/**
 * Normalize Smarty parameters.
 *
 * @param array $params Smarty passed parameters
 * @return array Smarty normalized parameters
 */
    protected function _generateImageMetadata(FileImage $storageFile, $fileEntity, array $params) {
        $metadata = $fileEntity->get('_joinData')?->get('metadata') ?? $fileEntity->get('metadata');
        if ($metadata && isset($metadata['set'])) {
            return $metadata;
        }

        $storageFile->set()->config('fallbackToOriginal', true);

        return $metadata;
    }

/**
 * Normalize Smarty parameters.
 *
 * @param array $params Smarty passed parameters
 * @return array Smarty normalized parameters
 */
    protected function _normalizeParams(array $params) {
        $params = parent::_normalizeParams($params);

        $size = $params['size'];
        if ($size) {
            if (!is_array($size)) {
                $size = splitter($size, 'x');
            }
            $params['size_width'] = $size[0];
            $params['size_height'] = $size[1];
        }

        if ($params['url'] instanceof Entity || $params['url'] instanceof File) {
            $params['file'] = $params['url'];
        } elseif ($params['file'] instanceof Entity && str_contains($params['file']->get('url'), 'http')) {
            $params['url'] = $params['file']->get('url');
            $params['file'] = null;
        }

        return $params;
    }

/**
 * Get image placeholder to prevent no file.
 * It will return a transparent 1x1 GIF.
 *
 * @return string Image data
 */
    protected function _safePlaceholder() {
        return $this->_defaultConfig['safePlaceholder'];
    }

}
