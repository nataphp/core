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

use Nata\View\Helper;
use Nata\FilesystemManager\File as StorageFile;
use Nata\FilesystemManager\FileStorage;
use Nata\ORM\Entity;

/**
 * File storage getter.
 */
class File extends Helper {

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
 * Get file instance.
 *
 * @param array $params Smarty passed parameters
 * @return StorageFile File instance
 */
    public function get(array $params): StorageFile|null {
        return null;
    }

/**
 * Get public url to file.
 *
 * @param array $params Smarty passed parameters
 * @return string Url
 */
    public function url(array $params): string|null {
        return null;
    }

/**
 * Get file URI.
 *
 * @param array $params Smarty passed parameters
 * @return string|null URI
 */
    public function fileurl(array $params) {
        $params = $this->_normalizeParams($params);
        $file = $this->_getFile($params);
        return $file->getFileUrl();
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
            return FileStorage::get($file->get('url'), $file->get('store') ?? FileStorage::DEFAULT_STORE, [
                'asObject' => true,
                'width' => $file->get('width'),
                'height' => $file->get('height'),
                'mime' => $file->get('mime'),
                'aspectRatio' => $file->get('aspect_ratio'),
                // 'name' => $file->get('name'),
                'size' => $file->get('size'),
                'freeze' => false
            ]);
        } elseif (is_string($file)) {
            return FileStorage::get($file, $this->_getStoreName($params), [
                'asObject' => true
            ]);
        }
        return null;
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

}
