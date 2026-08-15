<?php
/**
 * NataPHP Framework
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
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

namespace Nata\Form\Element;

use Closure;
use Nata\Core\Configure;
use Nata\Form\Element;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image;
use Nata\Utility\Number as UtilityNumber;
use Nata\FilesystemManager\FileStorage;
use Nata\ORM\Entity;

/**
 * Upload element.
 */
class Upload extends Element {

/**
 * Accepted files Error.
 *
 * @const int
 */
    const UPLOAD_ERR_ACCEPTED_FILES = 1210;

/**
 * Minimum of files.
 *
 * @const int
 */
    const UPLOAD_ERR_MIN_FILES = 1211;

/**
 * Maximum of files.
 *
 * @const int
 */
    const UPLOAD_ERR_MAX_FILES = 1212;

/**
 * No mime type found error.
 *
 * @const int
 */
    const UPLOAD_ERR_NO_MIMETYPE = 1213;

/**
 * No extension found error.
 *
 * @const int
 */
    const UPLOAD_ERR_NO_EXTENSION = 1214;

/**
 * Mimetype mismatch.
 *
 * @const int
 */
    const UPLOAD_ERR_MIMETYPE_MISMATCH = 1215;

/**
 * Unable to write temporary file.
 *
 * @const int
 */
    const UPLOAD_ERR_CANT_WRITE_TMP_FILE = 1216;

/**
 * Default list of accepted files.
 * By default accept image files.
 *
 * @var array
 */
    protected $_accept = [
        'jpeg','jpg','png','gif', 'svg'
    ];

/**
 * Default list of refused files.
 * By default some script and binary files.
 *
 * @var array
 */
    protected $_refuse = [
        'php','x-php','js','exe','x-dosexec','x-msdownload'
    ];

/**
 * Keep file on a temporary folder and import to repository
 * only on submittion.
 *
 * @var bool
 */
    protected $_importOnSubmittion = true;

/**
 * Keep file on a temporary folder and import to repository
 * on submittion.
 *
 * @var bool
 */
    protected $_importToRepositoryOnSubmittion = true;

/**
 * Minimum number of files.
 *
 * @var int
 */
    protected $_min;

/**
 * Maximum number of files.
 *
 * @var int
 */
    protected $_max = 1;

/**
 * Maximum file size.
 *
 * @var int
 */
    protected $_maxSize;

/**
 * Multiple file upload.
 *
 * @var bool
 */
    protected $_multiple;

/**
 * Chunking.
 *
 * @var bool
 */
    protected $_chunking;

/**
 * Parallel uploads.
 *
 * @var int
 */
    protected $_parallelUploads = 5;

/**
 * Chunk size for chunked uploads.
 *
 * @var int
 */
    protected $_chunkSize = 2097152; // 2MB default

/**
 * Parallel chunk uploads.
 *
 * @var int
 */
    protected $_chunkParallelUploads = 1;

/**
 * Specific image dimensions.
 *
 * ### Example
 * 300x150
 *
 * @var string
 */
    protected $_dimensions;

/**
 * Resize image.
 *
 * ### Example
 * 300x150
 *
 * @var string
 */
    protected $_resize;

/**
 * Image maximum width size.
 *
 * ### Example
 * 300
 *
 * @var int
 */
    protected $_maxWidth;

/**
 * Image maximum height size.
 *
 * ### Example
 * 300
 *
 * @var int
 */
    protected $_maxHeight;

/**
 * Image minimum width size.
 *
 * ### Example
 * 300
 *
 * @var int
 */
    protected $_minWidth;

/**
 * Image minimum height size.
 *
 * ### Example
 * 300
 *
 * @var int
 */
    protected $_minHeight;

/**
 * Image portrait orientation requirement.
 *
 * @var bool
 */
    protected $_portrait;

/**
 * Image landscape orientation requirement.
 *
 * @var bool
 */
    protected $_landscape;

/**
 * Image aspect ratio requirement.
 *
 * @var string
 */
    protected $_aspectRatio;

/**
 * Image cropper requirement.
 *
 * @var bool
 */
    protected $_cropper;

/**
 * List of files.
 *
 * @var array
 */
    protected $_files;

/**
 * Metadata fields the user can edit after upload (key, label, type).
 *
 * @var array
 */
    protected $_editMetadata = [];

/**
 * Allow user select file(s) as featured.
 *
 * @var bool|int
 */
    protected $_featured = false;

/**
 * Allow user to reorder files position.
 *
 * @var bool
 */
    protected $_draggable = true;


/**
 * Store name to be used.
 *
 * @var string|null
 */
    protected $_store;

/**
 * After move callback.
 *
 * @var Closure
 */
    protected $_afterMove;

/**
 * Dropzone theme.
 *
 * @var string
 */
    protected $_dztheme = 'default';

/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'files' => null,
            'accept' => null,
            'store' => null,
            'refuse' => null,
            'min' => null,
            'max' => null,
            'maxSize' => null,
            'multiple' => null,
            'chunking' => null,
            'chunkSize' => null,
            'chunkParallelUploads' => null,
            'dimensions' => null,
            'parallelUploads' => null,
            'resize' => null,
            'maxWidth' => null,
            'maxHeight' => null,
            'minWidth' => null,
            'minHeight' => null,
            'portrait' => null,
            'landscape' => null,
            'aspectRatio' => null,
            'editMetadata' => null,
            'importOnSubmittion' => true,
            'importToRepositoryOnSubmittion' => null,
            'featured' => null,
            'draggable' => null,
            'afterMove' => null,
            'dztheme' => null
        );

        if ($config['files']) {
            $this->_files = $config['files'];
        }
        if ($config['accept']) {
            $this->accept($config['accept']);
        }
        if ($config['store'] !== null) {
            $this->store($config['store']);
        }
        if ($config['afterMove']) {
            $this->afterMove($config['afterMove']);
        }
        if ($config['refuse']) {
            $this->refuse($config['refuse']);
        }
        if ($config['importOnSubmittion']) {
            $this->_importOnSubmittion = $config['importOnSubmittion'];
        }
        if ($config['importToRepositoryOnSubmittion'] !== null) {
            $this->_importToRepositoryOnSubmittion = $config['importToRepositoryOnSubmittion'];
        }
        if ($config['multiple'] !== null) {
            $this->multiple($config['multiple']);
        }
        if ($config['chunking'] !== null) {
            $this->chunking($config['chunking']);
        }
        if ($config['chunkSize'] !== null) {
            $this->chunkSize($config['chunkSize']);
        }
        if ($config['chunkParallelUploads'] !== null) {
            $this->chunkParallelUploads($config['chunkParallelUploads']);
        }
        if ($config['dimensions']) {
            $this->dimensions($config['dimensions']);
        }
        if ($config['parallelUploads'] !== null) {
            $this->_parallelUploads = $config['parallelUploads'];
        }
        if ($config['min']) {
            $this->min($config['min']);
        }
        if ($config['max']) {
            $this->max($config['max']);
        }
        if ($config['maxSize']) {
            $this->maxSize($config['maxSize']);
        }
        if ($config['resize']) {
            $this->resize($config['resize']);
        }
        if ($config['maxWidth']) {
            $this->maxWidth($config['maxWidth']);
        }
        if ($config['maxHeight']) {
            $this->maxHeight($config['maxHeight']);
        }
        if ($config['minWidth']) {
            $this->minWidth($config['minWidth']);
        }
        if ($config['minHeight']) {
            $this->minHeight($config['minHeight']);
        }
        if ($config['portrait']) {
            $this->portrait($config['portrait']);
        }
        if ($config['landscape']) {
            $this->landscape($config['landscape']);
        }
        if ($config['aspectRatio']) {
            $this->_aspectRatio = $config['aspectRatio'];
        }
        if ($config['editMetadata'] !== null) {
            $this->_editMetadata = $config['editMetadata'] ?: [];
        }
        if ($config['featured'] !== null) {
            $this->_featured = $config['featured'];
        }
        if ($config['draggable']) {
            $this->_draggable = $config['draggable'];
        }
        if ($config['dztheme']) {
            $this->_dztheme = $config['dztheme'];
        }

        parent::initialize($config);
    }

/**
 * Get/Set metadata fields editable by the user after upload.
 * Each entry must have: key (_joinData key), label (display string), type (text|textarea).
 * Pass false or [] to disable.
 *
 * Via config:
 * ```php
 * $this->add('Nata\Form\Element\Upload', [
 *     'editMetadata' => [
 *         ['key' => 'caption', 'label' => 'Caption',  'type' => 'textarea'],
 *         ['key' => 'name',    'label' => 'Filename',  'type' => 'text'],
 *     ]
 * ]);
 * ```
 *
 * Via method:
 * ```php
 * $upload->editMetadata([
 *     ['key' => 'caption', 'label' => 'Caption', 'type' => 'textarea'],
 * ]);
 * ```
 *
 * To disable:
 * ```php
 * $upload->editMetadata(false);
 * ```
 *
 * @param array|false|null $fields Field definitions
 * @return $this|array
 */
    public function editMetadata(array|bool $fields = null) {
        if ($fields === null) {
            return $this->_editMetadata;
        }
        $this->_editMetadata = $fields ?: [];
        return $this;
    }

/**
 * Get/Set option to allow featured file(s).
 *
 * @param bool|int $featured Allow featured and if an int
 *  how many are allowed
 * @return $this|bool|int
 */
    public function featured($featured = null) {
        if ($featured === null) {
            return ($this->max() === 1 ? false : $this->_featured);
        }
        $this->_featured = $featured;
        return $this;
    }

/**
 * Get/Set allow to drag and order files position.
 *
 * @param bool $draggable Drag/Re order files
 * @return $this|bool
 */
    public function draggable($draggable = null) {
        if ($draggable === null) {
            return ($this->max() === 1 ? false : $this->_draggable);
        }
        $this->_draggable = $draggable;
        return $this;
    }

/**
 * Get/Set store name to be used.
 *
 * @param string|null $store Store name
 * @return $this|string|null
 */
    public function store($store = null) {
        if ($store === null) {
            if ($this->_store === null) {
                $this->_store = Configure::read('Filesystem.storage.defaultStore');
            }
            return $this->_store;
        }
        $this->_store = $store;
        return $this;
    }

/**
 * Get/set after move into storage callback.
 *
 * The callback will receive the element instance,
 * the file object and the current value.
 *
 * @param Closure $callback Callback
 * @return $this|Closure
 */
    public function afterMove(Closure $callback = null) {
        if ($callback === null) {
            return $this->_afterMove;
        }
        $this->_afterMove = $callback;
        return $this;
    }

/**
 * Get/Set dropzone theme.
 *
 * @param string $dztheme Dropzone theme
 * @return $this|string
 */
    public function dztheme($dztheme = null) {
        if ($dztheme === null) {
            return $this->_dztheme;
        }
        $this->_dztheme = $dztheme;
        return $this;
    }

/**
 * Get/Set comma separated list of accepted files.
 *
 * @param string|array $accept List of file extensions
 * @return array|$this
 */
    public function accept($accept = null) {
        if ($accept === null) {
            return$this->_accept;
        }

        $this->_accept = $this->_normalizeExtensionList($accept);

        return $this;
    }

/**
 * Get/Set comma separated list of refused files.
 *
 * @param string|array $refuse List of file extensions
 * @return array|$this
 */
    public function refuse($refuse = null) {
        if ($refuse === null) {
            return $this->_refuse;
        }

        $this->_refuse = $this->_normalizeExtensionList($refuse);

        return $this;
    }

/**
 * Get/Set comma separated list of accepted files.
 *
 * @param array|string $extensions List of file extensions
 * @return array|$this
 */
    protected function _normalizeExtensionList(array|string $extensions): array|string {
        if (!is_array($extensions)) {
            if ($extensions === '*' || $extensions === '*/*') {
                return '*';
            }
            $extensions = explode(',', $extensions);
        }

        return array_map(function ($ext) {
            return str_contains($ext, '/') ? $ext : ltrim($ext, '.');
        }, $extensions);
    }

/**
 * Get/Set minimum of files required.
 *
 * @param int $min Minimum number of files
 * @return int|$this
 */
    public function min($min = null) {
        if ($min === null) {
            return $this->_min;
        }
        $this->_min = (int)$min;
        return $this;
    }

/**
 * Get/Set number of maximum files allowed for upload.
 *
 * @param int $max Maximum number of files
 * @return int|$this
 */
    public function max($max = null) {
        if ($max === null) {
            if ($this->_multiple === false) {
                return 1;
            }
            if ($this->_max === null) {
                $this->_max = ini_get('max_file_uploads');
            }
            return $this->_max;
        }
        $this->_max = (int)$max;
        return $this;
    }

/**
 * Get/Set file size limit allowed for upload.
 *
 * @param int $maxSize Maximum file size allowed
 * @return int|$this
 */
    public function maxSize($maxSize = null) {
        if ($maxSize === null) {
            if ($this->_maxSize === null) {
                $this->_maxSize = min(
                    UtilityNumber::fromReadableSize(ini_get('upload_max_filesize')),
                    UtilityNumber::fromReadableSize(ini_get('post_max_size')),
                    UtilityNumber::fromReadableSize(ini_get('memory_limit'))
                );
            }
            return $this->_maxSize;
        }

        if (!is_numeric($maxSize)) {
            $maxSize = UtilityNumber::fromReadableSize($maxSize);
        }

        $this->_maxSize = $maxSize;

        return $this;
    }

/**
 * Get file size limit allowed for upload in a readable format.
 *
 * @return string
 */
    public function readableMaxFileSize() {
        return UtilityNumber::toReadableSize($this->maxSize());
    }

/**
 * Get/Set multiple files upload.
 *
 * @param bool $multiple Allow multiple files upload
 * @return bool|$this
 */
    public function multiple($multiple = null) {
        if ($multiple === null) {
            if ($this->_multiple === null) {
                $this->multiple($this->_max > 1);
            }
            return $this->_multiple;
        }

        $this->_multiple = $multiple;

        return $this;
    }

/**
 * Get/Set chunking.
 *
 * @param bool $chunking Allow chunking
 * @return bool|$this
 */
    public function chunking($chunking = null) {
        if ($chunking === null) {
            return $this->_chunking;
        }
        $this->_chunking = $chunking;
        return $this;
    }

/**
 * Get/Set parallel uploads.
 *
 * @param int $parallelUploads Parallel uploads
 * @return int|$this
 */
    public function parallelUploads($parallelUploads = null) {
        if ($parallelUploads === null) {
            return $this->_parallelUploads;
        }
        $this->_parallelUploads = $parallelUploads;
        return $this;
    }

/**
 * Get/Set chunk size for chunked uploads.
 *
 * @param int|string $chunkSize Chunk size in bytes or readable format (e.g., '2MB')
 * @return int|$this
 */
    public function chunkSize($chunkSize = null) {
        if ($chunkSize === null) {
            return $this->_chunkSize;
        }

        if (!is_numeric($chunkSize)) {
            $chunkSize = UtilityNumber::fromReadableSize($chunkSize);
        }

        $this->_chunkSize = (int)$chunkSize;
        return $this;
    }

/**
 * Get/Set parallel chunk uploads.
 *
 * @param int $chunkParallelUploads Parallel chunk uploads
 * @return int|$this
 */
    public function chunkParallelUploads($chunkParallelUploads = null) {
        if ($chunkParallelUploads === null) {
            return $this->_chunkParallelUploads;
        }
        $this->_chunkParallelUploads = (int)$chunkParallelUploads;
        return $this;
    }

/**
 * Get/Set image specific dimensions.
 *
 * @param string $dimensions Image size
 * @return string|$this
 */
    public function dimensions($dimensions = null) {
        if ($dimensions === null) {
            return $this->_dimensions;
        }
        $this->_dimensions = $dimensions;
        return $this;
    }

/**
 * Get/Set image maximum width and/or maximum height to resize.
 *
 * @param string $resize Image size
 * @return string|$this
 */
    public function resize($resize = null) {
        if ($resize === null) {
            return $this->_resize;
        }
        $this->_resize = $resize;
        return $this;
    }

/**
 * Get/Set maximum width of image.
 *
 * @param int $maxWidth Maximum image width
 * @return int|$this
 */
    public function maxWidth($maxWidth = null) {
        if ($maxWidth === null) {
            return $this->_maxWidth;
        }
        $this->_maxWidth = $maxWidth;
        return $this;
    }

/**
 * Get/Set maximum height of image.
 *
 * @param int $maxHeight Maximum image height
 * @return $this|int
 */
    public function maxHeight($maxHeight = null) {
        if ($maxHeight === null) {
            return $this->_maxHeight;
        }
        $this->_maxHeight = $maxHeight;
        return $this;
    }

/**
 * Get/Set minimum width of image.
 *
 * @param int $minWidth Maximum image width
 * @return $this|int
 */
    public function minWidth($minWidth = null) {
        if ($minWidth === null) {
            return $this->_minWidth;
        }
        $this->_minWidth = $minWidth;
        return $this;
    }

/**
 * Get/Set minimum height of image.
 *
 * @param int $minHeight Maximum image height
 * @return $this|int
 */
    public function minHeight($minHeight = null) {
        if ($minHeight === null) {
            return $this->_minHeight;
        }
        $this->_minHeight = $minHeight;
        return $this;
    }

/**
 * Get/Set image orientation as landscape.
 *
 * @param bool $landscape Landscape image
 * @return $this|bool
 */
    public function landscape($landscape = null) {
        if ($landscape === null) {
            return $this->_landscape;
        }
        $this->_landscape = $landscape;
        return $this;
    }

/**
 * Get/Set image orientation as portrait.
 *
 * @param bool $portrait Portrait image
 * @return $this|bool
 */
    public function portrait($portrait = null) {
        if ($portrait === null) {
            return $this->_portrait;
        }
        $this->_portrait = $portrait;
        return $this;
    }

/**
 * Get/Set image cropper requirement.
 *
 * @param bool $cropper Cropper requirement
 * @return bool|$this
 */
    public function cropper($cropper = null) {
        if ($cropper === null) {
            return $this->_cropper;
        }
        $this->_cropper = $cropper;
        return $this;
    }

/**
 * Get/Set image specific aspect ratio.
 *
 * @param string $aspectRatio Aspect ratio
 * @return $this|string
 */
    public function aspectRatio($aspectRatio = null) {
        if ($aspectRatio === null) {
            return $this->_aspectRatio;
        }
        $this->_aspectRatio = $aspectRatio;
        return $this;
    }

/**
 * Get/Set list of files.
 *
 * @param array $files Files
 * @return $this|array
 */
    public function files($files = null) {
        if ($files === null) {
            if ($this->_files === null) {
                $this->_files = $this->value();
            }
            return $this->_files;
        }

        $this->_files = $files;

        return $this;
    }

/**
 * Get/Set import file(s) to repository on submittion.
 *
 * @param bool $importToRepositoryOnSubmittion Import file(s) to repository on submittion
 * @return $this|bool
 */
    public function importToRepositoryOnSubmittion(bool $importToRepositoryOnSubmittion = null) {
        if ($importToRepositoryOnSubmittion === null) {
            return $this->_importToRepositoryOnSubmittion;
        }

        $this->_importToRepositoryOnSubmittion = $importToRepositoryOnSubmittion;
        return $this;
    }

/**
 * Prepare and set files data to be presented in view
 * as a JSON string.
 *
 * @return string
 */
    public function jsonEncodedFiles() {
        $files = $this->files();
        if (empty($files)) {
            $files = [];
        } elseif (isset($files['hash'])) {
            $files = [$files];
        }
        return json_encode($files);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $attrs = $this->attributes();

        $id = $this->id();
        if (!$attrs->get('id')) {
            $attrs->id($id);
        }

        // $attrs->id($attrs->id() . substr(md5(microtime()), 0, 5));

        $name = $this->_flattenData === true ? $this->name() : $id;
        $attrs->name($name);
        $attrs->hidden('name');

        if ($this->files()) {
            if (!is_array($this->_files)) {
                $this->_files = $this->_getPreview($this->_files);
            } else {
                foreach ($this->_files as $index => $file) {
                    $this->_files[$index] = $this->_getPreview($file);
                }
            }
        }

        $attrs->addData([
            'param-name' => $attrs->name(),
            'accepted-files' => $this->_extensionListAsString($this->_accept),
            'refused-files' => $this->_extensionListAsString($this->_refuse),
            'max-files' => $this->max(),
            'max-filesize' => $this->maxSize(),
            'store' => $this->store(),
            'auto-process-queue' => true,
            'upload-multiple' => !$this->chunking() && $this->multiple(),
            'multiple' => $this->multiple(),
            'chunking' => $this->chunking(),
            'chunk-size' => $this->chunkSize(),
            'chunk-parallel-uploads' => $this->chunkParallelUploads(),
            'parallel-uploads' => $this->parallelUploads(),
            'auto-discover' => 'false',
            'disabled' => $this->disabled(),
            'dimensions' => $this->dimensions(),
            'min-width' => $this->minWidth(),
            'max-width' => $this->maxWidth(),
            'min-height' => $this->minHeight(),
            'max-height' => $this->maxHeight(),
            'portrait' => $this->portrait(),
            'landscape' => $this->landscape(),
            'aspect-ratio' => $this->aspectRatio(),
            'edit-metadata' => $this->_editMetadata ? json_encode($this->_editMetadata) : null,
            'featured' => $this->featured(),
            'draggable' => $this->draggable(),
            'files' => $this->jsonEncodedFiles(),
            'dztheme' => $this->dztheme(),
            'cropper' => $this->cropper(),
            'cropper-required' => 1,
            //'cropper-type' => 'image/jpeg',
            'cropper-quality' => 1,
            'cropper-aspect-ratio' => $this->aspectRatio(),
            'cropper-fill-color' => '#000000',
            'cropper-image-smoothing-enabled' => 1,
            'cropper-image-smoothing-quality' => 100,
            'cropper-image-smoothing-filter' => 'lanczos',
            'cropper-image-smoothing-filter-quality' => 100
       ])->addClass('form-file-uploader dropzone-previews');

    }

/**
 * Normalize list of extensions for uploader.
 *
 * @param array|string $list List of extensions
 * @return string|null CSV extensions
 */
    protected function _extensionListAsString(array|string $list): ?string {
        if (!$list) {
            return null;
        }
        if ($list === '*') {
            return null;
        }

        return implode(',', array_map(function ($ext) {
            return str_contains($ext, '/') ? $ext : '.' . $ext;
        }, $list));
    }

/**
 * Get/Set list of files.
 *
 * @param array $file File
 * @return array File with thumbnail URL
 */
    protected function _getPreview(array|Entity $file): array {
        if ($file instanceof Entity) {
            $file = $file->toArray();
        }
        if (!isset($file['path'])) {
            return $file;
        }

        $file['_preview'] = $file['path'];
        if ($file && isset($file['mime']) && strpos($file['mime'], 'image') !== false) {
            $extension = pathinfo($file['path'], PATHINFO_EXTENSION);

            if (in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp']) && isset($file['path'])) {
                $file['_preview'] = $this->_getThumbnail($file);
            }
        }
        return $file;
    }

/**
 * Get/Set list of files.
 *
 * @param array $img Files
 * @return string Thumbnail URL
 */
    protected function _getThumbnail(array $img): string {
        return FileStorage::url($img['path'], $img['store'] ?? $this->store(), ['full' => true]);
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        if ($this->value()) {
            return __n('%d file', '%d files', count($this->files()));
        }
    }

/**
 * Normalize submitted upload value to a list of per-file arrays.
 *
 * @param array $data Request value
 * @return array<int, array<string, mixed>>
 */
    protected function _normalizeSubmittedFilesList(array $data): array {
        if (isset($data['path']) && is_string($data['path'])) {
            return [$data];
        }
        $out = [];
        foreach ($data as $item) {
            if (is_array($item) && isset($item['path']) && is_string($item['path'])) {
                $out[] = $item;
            }
        }
        return $out;
    }

/**
 * Pack validated file list back to the shape expected for request/model (single vs multiple).
 *
 * @param array<int, array<string, mixed>> $validated Validated files
 * @return array<string, mixed>
 */
    protected function _repackFilesForElement(array $validated): array {
        if (!$this->multiple()) {
            return $validated[0] ?? [];
        }
        return array_values($validated);
    }

/**
 * Whether the submitted list is the presigned / hidden-field storage metadata flow (no PHP upload array).
 *
 * @param array<int, array<string, mixed>> $filesList Normalized file list
 * @return bool
 */
    protected function _isPostedStorageMetadataBatch(array $filesList): bool {
        if ($filesList === []) {
            return false;
        }
        foreach ($filesList as $file) {
            if (!is_array($file)) {
                return false;
            }
            if (!empty($file['tmp_name'])) {
                return false;
            }
            if (empty($file['path']) || !is_string($file['path']) || empty($file['store'])) {
                return false;
            }
        }
        return true;
    }

/**
 * Build fileData for type error messages (always includes non-empty `name`).
 *
 * @param array<string, mixed> $posted Posted file row
 * @param File $file Loaded file
 * @return array<string, mixed>
 */
    protected function _displayNameForFileErrors(array $posted, File $file): array {
        $name = $posted['name'] ?? $posted['basename'] ?? null;
        if ($name === null || $name === '') {
            $name = $file->basename();
        }
        return array_merge($posted, ['name' => $name]);
    }

/**
 * Greatest common divisor (for aspect ratio label).
 *
 * @param int $a
 * @param int $b
 * @return int
 */
    protected function _gcdInt(int $a, int $b): int {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a ?: 1;
    }

/**
 * Reduced aspect ratio label (e.g. 1920×1080 → "16:9"), aligned with frontend upload validation.
 *
 * @param int $w Width
 * @param int $h Height
 * @return string|null Aspect ratio label
 */
    protected function _aspectRatioLabel(int $w, int $h): ?string {
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        $g = $this->_gcdInt($w, $h);
        return ((int)($w / $g)) . ':' . ((int)($h / $g));
    }

/**
 * Validate raster image constraints from element config (metadata / server-side path).
 *
 * @param Image $imageFile Loaded storage image file
 * @return string|null Error message or null when valid / not applicable
 */
    protected function _validateRasterConstraints(Image $imageFile): ?string {
        if (!$imageFile->isRaster()) {
            return null;
        }
        $w = (int)$imageFile->width();
        $h = (int)$imageFile->height();
        if ($this->minWidth() !== null && $w < (int)$this->minWidth()) {
            return __('Image is too small.');
        }
        if ($this->maxWidth() !== null && $w > (int)$this->maxWidth()) {
            return __('Image is too big.');
        }
        if ($this->minHeight() !== null && $h < (int)$this->minHeight()) {
            return __('Image is too small.');
        }
        if ($this->maxHeight() !== null && $h > (int)$this->maxHeight()) {
            return __('Image is too big.');
        }
        if ($this->dimensions()) {
            $parts = explode('x', strtolower($this->dimensions()));
            $dw = isset($parts[0]) ? (int)$parts[0] : 0;
            $dh = isset($parts[1]) ? (int)$parts[1] : 0;
            if ($w !== $dw || ($dh && $h !== $dh)) {
                return __('Image with invalid size.');
            }
        }
        if ($this->aspectRatio()) {
            $label = $this->_aspectRatioLabel($w, $h);
            if ($label === null || $label !== $this->aspectRatio()) {
                return __('Wrong image aspect ratio.');
            }
        }
        if ($this->portrait() && $w >= $h) {
            return __('Wrong image orientation.');
        }
        if ($this->landscape() && $w <= $h) {
            return __('Wrong image orientation.');
        }
        return null;
    }

/**
 * Validate posted storage metadata rows by reading the tmp object from FileStorage.
 *
 * @param array<int, array<string, mixed>> $filesList Normalized list
 * @return array<int, array<string, mixed>>|null Validated rows or null on error (element error set)
 */
    protected function _validatePostedStorageMetadata(array $filesList): ?array {
        $out = [];
        foreach ($filesList as $posted) {
            $path = $posted['path'] ?? '';
            $storeRaw = $posted['store'] ?? null;
            if (!is_string($path) || $path === '' || $storeRaw === null || $storeRaw === '') {
                $this->error(__('Missing data from file(s). Error processing image.'));
                return null;
            }
            if (str_contains($path, '..')) {
                $this->error(__('Invalid file path.'));
                return null;
            }
            if (!str_starts_with($path, 'uploads/tmp/') && empty($posted['id'])) {
                $this->error(__('Invalid file path.'));
                return null;
            }
            $postedStore = FileStorage::getStoreName((string)$storeRaw);
            $expectedStore = FileStorage::getStoreName($this->store());
            if ($postedStore !== $expectedStore) {
                $this->error(__('Store mismatch for uploaded file.'));
                return null;
            }
            if (!FileStorage::exists($path, $postedStore)) {
                $this->error(__('Uploaded file is no longer available.'));
                return null;
            }
            $file = FileStorage::get($path, $postedStore, ['asObject' => true]);
            if (!$file) {
                $this->error(__('Uploaded file is no longer available.'));
                return null;
            }
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $fileData = array_merge($this->_displayNameForFileErrors($posted, $file), ['error' => UPLOAD_ERR_OK]);
            $err = $this->_hasError($file, $fileData, $extension);
            if ($err !== false) {
                $this->error($err);
                return null;
            }
            if ($file instanceof Image) {
                $rasterErr = $this->_validateRasterConstraints($file);
                if ($rasterErr !== null) {
                    $this->error($rasterErr);
                    return null;
                }
            }

            $merged = $posted;
            $merged['path'] = $path;
            $merged['store'] = $postedStore;
            $merged['mime'] = $file->mime();
            if ($file->is('image') && $file->isRaster()) {
                $merged['width'] = $file->width();
                $merged['height'] = $file->height();
            } elseif ($file->is('video') && $file->width() > 0) {
                $merged['width'] = $file->width();
                $merged['height'] = $file->height();

                // Move the moov atom to the front (MP4 faststart) so the video can
                // stream from a small range request instead of forcing the browser
                // to download most of the file before playback. No-op when ffmpeg is
                // unavailable or the container is not MP4/QuickTime.
                // Done here, on the tmp file, rather than after the move: the move
                // is a full copy either way, and afterValidation() is skipped when
                // importToRepositoryOnSubmittion() is off.
                $file->fixMoovFlags();
            }
            // Read after the faststart remux: it rewrites the file in place.
            $merged['size'] = $file->size();
            $out[] = $merged;
        }
        return $out;
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!is_array($data)) {
            return $this->_error === null;
        }

        $filesList = $this->_normalizeSubmittedFilesList($data);
        if (!$this->_isPostedStorageMetadataBatch($filesList)) {
            $this->error(__('Invalid upload data. Use the upload control to select a file.'));
            return false;
        }

        $n = count($filesList);
        if ($this->_min && $n < $this->_min) {
            $this->_error = $this->_errorCode(static::UPLOAD_ERR_MIN_FILES);
            return false;
        }
        if ($this->max() && $n > $this->max()) {
            $this->_error = $this->_errorCode(static::UPLOAD_ERR_MAX_FILES);
            return false;
        }
        $validated = $this->_validatePostedStorageMetadata($filesList);
        if ($validated === null) {
            return false;
        }
        $this->files($this->_repackFilesForElement($validated));
        return $this->_error === null;
    }

/**
 * After validation move files with \Nata\Filesystem\FileRepository.
 *
 * @param \Nata\Event\Event $event Form event.
 * @param bool $isValid Validation result
 * @return void
 */
    public function afterValidation($event, $isValid) {
        if (!$this->importToRepositoryOnSubmittion()) {
            return;
        }
        if (!$isValid) {
            return;
        }

        $filesData = $this->_data->request();
        if (!is_array($filesData)) {
            return;
        }

        $list = $this->_normalizeSubmittedFilesList($filesData);
        if ($list === []) {
            return;
        }

        $multiple = $this->multiple();
        $updated = false;
        foreach ($list as $index => $fileData) {
            $path = $fileData['path'] ?? '';
            if (!is_string($path) || !str_starts_with($path, 'uploads/tmp/')) {
                continue;
            }
            $storeRaw = $fileData['store'] ?? null;
            if ($storeRaw === null || $storeRaw === '') {
                continue;
            }
            $postedStore = FileStorage::getStoreName((string)$storeRaw);
            $finalPath = str_replace('uploads/tmp/', 'uploads/', $path);
            if ($finalPath === $path) {
                continue;
            }

            $moveOptions = [
                'name' => $fileData['name'] ?? $fileData['basename'] ?? basename($path),
                'mimeFallback' => $fileData['mime'] ?? null,
                'existingFileStrategy' => 'overwrite',
            ];

            $newFile = FileStorage::move($path, $postedStore, $postedStore, $finalPath, $moveOptions);
            if (!$newFile) {
                continue;
            }

            $merged = $fileData;
            $merged['path'] = $finalPath;
            $merged['store'] = $postedStore;
            $merged['mime'] = $newFile->mime();
            $merged['size'] = $newFile->size();
            if ($newFile->is('image') && $newFile->isRaster()) {
                $merged['width'] = $newFile->width();
                $merged['height'] = $newFile->height();
            } elseif ($newFile->is('video') && $newFile->width() > 0) {
                $merged['width'] = $newFile->width();
                $merged['height'] = $newFile->height();
            }

            if ($callback = $this->afterMove()) {
                $data = $callback($this, $newFile, $merged);
                if (is_array($data) && isset($data['path'])) {
                    $merged = $data;
                }
            }
            $list[$index] = $merged;
            $updated = true;
        }

        if (!$updated) {
            return;
        }

        $packed = $multiple ? array_values($list) : ($list[0] ?? []);
        $this->data()->request($packed);
        $this->files($packed);
        $this->_value = null;

        if ($this->_dataManager !== null) {
            $this->_dataManager->model()->set($this->name(), $packed);
        }
    }

/**
 * Validate each file.
 *
 * @param File $file File array
 * @return array File data
 */
    private function _hasError(File $file, $fileData, $extension) {
        $mime = $file->mime();
        $error = $fileData['error'];
        if (!empty($error)) {
            return $this->_errorCode($error);
        } elseif (empty($mime)) {
            return $this->_errorCode(static::UPLOAD_ERR_NO_MIMETYPE);
        } elseif (empty($extension)) {
            return $this->_errorCode(static::UPLOAD_ERR_NO_EXTENSION);
        } elseif ($file->size() > $this->maxSize()) {
            return $this->_errorCode(UPLOAD_ERR_FORM_SIZE);
        } elseif ($error = $this->_fileTypeError($file, $fileData)) {
            return $error;
        }

        return false;
    }

/**
 * Get the Upload message error for the given error code.
 *
 * @param int $code Error code
 * @return string Error message
 */
    private function _fileTypeError(File $file, $fileData) {
        $refused = $this->_refuse;
        $accepted = $this->_accept;

        if ($accepted && $accepted !== '*') {
            foreach ($accepted as $type) {
                if ($file->is($type)) {
                    return false;
                }
            }
            return $this->_errorCode(static::UPLOAD_ERR_ACCEPTED_FILES, $fileData);
        }

        if ($refused) {
            foreach ($refused as $type) {
                if ($file->is($type)) {
                    return $this->_errorCode(static::UPLOAD_ERR_ACCEPTED_FILES, $fileData);
                }
            }
        }

        return false;
    }

/**
 * Get the Upload message error for the given error code.
 *
 * @param int $code Error code
 * @param File $file File
 * @return string Error message
 */
    private function _errorCode($code, array $fileData = null) {
        switch ($code) {
            case UPLOAD_ERR_OK:
                return false;
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension');
            case static::UPLOAD_ERR_NO_EXTENSION:
                return __('Unable to determine the file\'s extension.');
            case static::UPLOAD_ERR_ACCEPTED_FILES:
                $accept = $this->accept();
                if ($accept && $accept !== '*') {
                    return __('File "%s" is not allowed. Allowed files are: %s', [$fileData['name'], implode(', ', (array)$accept)]);
                }
                return __('File "%s" is not allowed. All files are allowed, except these: %s', [$fileData['name'], implode(', ', $this->refuse())]);
            case static::UPLOAD_ERR_MIN_FILES:
                return __n('You need to upload at least %d file', 'You need to upload at least %d files', $this->_min, $this->_min);
            case static::UPLOAD_ERR_MAX_FILES:
                return __('Maximum number of files of "%d"', $this->_max);
            case static::UPLOAD_ERR_NO_MIMETYPE:
                return __('Unable to determine the file\'s mime type.');
            case static::UPLOAD_ERR_MIMETYPE_MISMATCH:
                return __('Mismatch in the expected mimetype.');
            case static::UPLOAD_ERR_CANT_WRITE_TMP_FILE:
                return __('Failed to write temporary file to disk');
            default:
                return __('Unknown upload error');
        }
    }

/**
 * To array.
 *
 * @return void
 */
    public function toArray() {
        return ['files' => $this->files()] + parent::toArray();
    }

}
