<?php
/**
 * NataPHP Framework
 *
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

namespace Nata\FilesystemManager;

use Nata\Core\App;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\FileFactory;
use Nata\FilesystemManager\FileStorage;
use Nata\FilesystemManager\Mimetype;
use InvalidArgumentException;
use Exception;

/**
 * Thumbnail Generator Service
 *
 * Generates thumbnails for various file formats including:
 * - Images (JPEG, PNG, GIF, WebP, HEIF, BMP, TIFF)
 * - PDFs (first page)
 * - Videos (first frame)
 * - Documents (with fallback icons)
 */
class ThumbnailGenerator {

/**
 * Default configuration.
 *
 * @var array
 */
    protected static $_defaultConfig = [
        'defaultSize' => [150, 150],
        'quality' => 85,
        'format' => 'jpeg',
        'cacheStore' => 'thumbnails',
        'cachePrefix' => 'thumb_',
        'fallbackIcons' => [
            'application/pdf' => 'pdf-icon.png',
            'application/msword' => 'doc-icon.png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx-icon.png',
            'application/vnd.ms-excel' => 'xls-icon.png',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx-icon.png',
            'application/vnd.ms-powerpoint' => 'ppt-icon.png',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx-icon.png',
            'text/plain' => 'txt-icon.png',
            'application/zip' => 'zip-icon.png',
            'audio/*' => 'audio-icon.png',
            'video/*' => 'video-icon.png',
            'default' => 'file-icon.png'
        ],
        'supportedImageFormats' => ['gif', 'jpeg', 'jpg', 'png', 'webp', 'heif', 'heic', 'bmp', 'tiff', 'tif'],
        'maxFileSize' => 50 * 1024 * 1024, // 50MB
        'timeout' => 30, // seconds
    ];

/**
 * Configuration.
 *
 * @var array
 */
    protected $_config;

/**
 * Constructor.
 *
 * @param array $config Configuration options
 */
    public function __construct(array $config = []) {
        $this->_config = array_merge(static::$_defaultConfig, $config);
    }

/**
 * Generate thumbnail for a file.
 *
 * @param string|File $file File path or File instance
 * @param array $options Thumbnail options
 * @return string|false Thumbnail URL or false on failure
 */
    public function generate($file, array $options = []) {
        $options = array_merge([
            'width' => $this->_config['defaultSize'][0],
            'height' => $this->_config['defaultSize'][1],
            'quality' => $this->_config['quality'],
            'format' => $this->_config['format'],
            'store' => $this->_config['cacheStore'],
            'forceRegenerate' => false,
            'aspectRatio' => 'fit', // fit, crop, stretch
            'background' => [255, 255, 255], // white background
        ], $options);

        try {
            // Load file
            $fileInstance = $this->_loadFile($file);
            if (!$fileInstance) {
                return $this->_getFallbackIcon('default', $options);
            }

            // Check if thumbnail already exists
            $cacheKey = $this->_generateCacheKey($fileInstance, $options);
            if (!$options['forceRegenerate'] && $this->_thumbnailExists($cacheKey, $options['store'])) {
                return $this->_getThumbnailUrl($cacheKey, $options['store']);
            }

            // Generate thumbnail based on file type
            $thumbnailFile = $this->_generateThumbnail($fileInstance, $options);
            if (!$thumbnailFile) {
                return $this->_getFallbackIcon($fileInstance->mime(), $options);
            }

            // Store thumbnail
            $storedThumbnail = $this->_storeThumbnail($thumbnailFile, $cacheKey, $options);
            if (!$storedThumbnail) {
                return $this->_getFallbackIcon($fileInstance->mime(), $options);
            }

            return $this->_getThumbnailUrl($cacheKey, $options['store']);

        } catch (Exception $e) {
            App::log('Thumbnail generation failed: ' . $e->getMessage(), 'error');
            return $this->_getFallbackIcon('default', $options);
        }
    }

/**
 * Generate thumbnail for multiple files.
 *
 * @param array $files Array of file paths or File instances
 * @param array $options Thumbnail options
 * @return array Array of thumbnail URLs
 */
    public function generateBatch(array $files, array $options = []) {
        $results = [];
        foreach ($files as $key => $file) {
            $results[$key] = $this->generate($file, $options);
        }
        return $results;
    }

/**
 * Check if file can have a thumbnail generated.
 *
 * @param string|File $file File path or File instance
 * @return bool True if thumbnail can be generated
 */
    public function canGenerateThumbnail($file): bool {
        try {
            $fileInstance = $this->_loadFile($file);
            if (!$fileInstance) {
                return false;
            }

            $mime = $fileInstance->mime();

            // Check if it's a supported image format
            if (Mimetype::isRaster($mime)) {
                return true;
            }

            // Check if it's a PDF
            if ($mime === 'application/pdf') {
                return $this->_hasImagickSupport();
            }

            // Check if it's a video
            if (strpos($mime, 'video/') === 0) {
                return $this->_hasFfmpegSupport();
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

/**
 * Get supported file types for thumbnail generation.
 *
 * @return array Array of supported MIME types
 */
    public function getSupportedTypes(): array {
        $types = [
            // Raster images
            'image/gif', 'image/jpeg', 'image/png', 'image/webp',
            'image/heif', 'image/bmp', 'image/tiff'
        ];

        // Add PDF if Imagick is available
        if ($this->_hasImagickSupport()) {
            $types[] = 'application/pdf';
        }

        // Add video formats if FFmpeg is available
        if ($this->_hasFfmpegSupport()) {
            $types = array_merge($types, [
                'video/mp4', 'video/avi', 'video/mov', 'video/wmv',
                'video/flv', 'video/webm', 'video/mkv', 'video/m4v'
            ]);
        }

        return $types;
    }

/**
 * Load file instance.
 *
 * @param string|File $file File path or File instance
 * @return File|false File instance or false on failure
 */
    protected function _loadFile($file) {
        if ($file instanceof File) {
            return $file;
        }

        if (is_string($file)) {
            // Check if it's a storage URI
            if (strpos($file, '://') !== false) {
                $parsed = FileStorage::parseUri($file);
                return FileStorage::get($parsed['path'], $parsed['store'], ['asObject' => true]);
            }

            // Check if it's a file path
            if (is_file($file)) {
                return FileFactory::build($file);
            }
        }

        return false;
    }

/**
 * Generate thumbnail based on file type.
 *
 * @param File $file File instance
 * @param array $options Thumbnail options
 * @return File|false Thumbnail file or false on failure
 */
    protected function _generateThumbnail(File $file, array $options) {
        $mime = $file->mime();

        // Handle raster images
        if (Mimetype::isRaster($mime)) {
            return $this->_generateImageThumbnail($file, $options);
        }

        // Handle PDFs
        if ($mime === 'application/pdf') {
            return $this->_generatePdfThumbnail($file, $options);
        }

        // Handle videos
        if (strpos($mime, 'video/') === 0) {
            return $this->_generateVideoThumbnail($file, $options);
        }

        return false;
    }

/**
 * Generate thumbnail for image files.
 *
 * @param File $file Image file
 * @param array $options Thumbnail options
 * @return File|false Thumbnail file or false on failure
 */
    protected function _generateImageThumbnail(File $file, array $options) {
        if (!($file instanceof Image)) {
            $file = FileFactory::build($file->getAbsolutePath());
            if (!($file instanceof Image)) {
                return false;
            }
        }

        try {
            $editor = $file->editor();

            // Fix orientation if needed
            if ($file->is('jpeg') || $file->is('jpg')) {
                $editor->fixOrientation();
            }

            // Resize based on aspect ratio option
            switch ($options['aspectRatio']) {
                case 'crop':
                    $editor->adaptiveResize($options['width'], $options['height']);
                    break;
                case 'stretch':
                    $editor->resize($options['width'], $options['height']);
                    break;
                case 'fit':
                default:
                    $editor->resize($options['width'], $options['height']);
                    break;
            }

            // Set quality
            if ($options['format'] === 'jpeg' || $options['format'] === 'jpg') {
                $editor->jpegQuality($options['quality']);
            }

            // Generate thumbnail file
            $thumbnailFile = $editor->getFile(['format' => $options['format']]);

            return $thumbnailFile;

        } catch (Exception $e) {
            App::log('Image thumbnail generation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

/**
 * Generate thumbnail for PDF files.
 *
 * @param File $file PDF file
 * @param array $options Thumbnail options
 * @return File|false Thumbnail file or false on failure
 */
    protected function _generatePdfThumbnail(File $file, array $options) {
        if (!$this->_hasImagickSupport()) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150); // Set resolution for better quality
            $imagick->readImage($file->getAbsolutePath() . '[0]'); // Read first page
            $imagick->setImageFormat($options['format']);

            // Resize
            $imagick->resizeImage($options['width'], $options['height'], \Imagick::FILTER_LANCZOS, 1, true);

            // Set quality
            if ($options['format'] === 'jpeg') {
                $imagick->setImageCompressionQuality($options['quality']);
            }

            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_thumb_');
            $imagick->writeImage($tempPath);
            $imagick->clear();
            $imagick->destroy();

            return FileFactory::build($tempPath);

        } catch (Exception $e) {
            App::log('PDF thumbnail generation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

/**
 * Generate thumbnail for video files.
 *
 * @param File $file Video file
 * @param array $options Thumbnail options
 * @return File|false Thumbnail file or false on failure
 */
    protected function _generateVideoThumbnail(File $file, array $options) {
        if (!$this->_hasFfmpegSupport()) {
            return false;
        }

        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'video_thumb_') . '.' . $options['format'];

            $command = sprintf(
                'ffmpeg -i "%s" -ss 00:00:01 -vframes 1 -vf "scale=%d:%d" -y "%s" 2>&1',
                $file->getAbsolutePath(),
                $options['width'],
                $options['height'],
                $tempPath
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($tempPath)) {
                App::log('FFmpeg thumbnail generation failed: ' . implode("\n", $output), 'error');
                return false;
            }

            return FileFactory::build($tempPath);

        } catch (Exception $e) {
            App::log('Video thumbnail generation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

/**
 * Generate cache key for thumbnail.
 *
 * @param File $file File instance
 * @param array $options Thumbnail options
 * @return string Cache key
 */
    protected function _generateCacheKey(File $file, array $options): string {
        $key = $this->_config['cachePrefix'];
        $key .= md5($file->getAbsolutePath() . $file->size() . $file->lastChange());
        $key .= '_' . $options['width'] . 'x' . $options['height'];
        $key .= '_' . $options['quality'];
        $key .= '_' . $options['format'];
        $key .= '_' . $options['aspectRatio'];

        return $key . '.' . $options['format'];
    }

/**
 * Check if thumbnail exists in cache.
 *
 * @param string $cacheKey Cache key
 * @param string $store Store name
 * @return bool True if thumbnail exists
 */
    protected function _thumbnailExists(string $cacheKey, string $store): bool {
        return FileStorage::exists($cacheKey, $store);
    }

/**
 * Store thumbnail in cache.
 *
 * @param File $thumbnailFile Thumbnail file
 * @param string $cacheKey Cache key
 * @param array $options Options
 * @return File|false Stored thumbnail or false on failure
 */
    protected function _storeThumbnail(File $thumbnailFile, string $cacheKey, array $options) {
        try {
            $stored = FileStorage::import(
                $thumbnailFile,
                $options['store'],
                $cacheKey,
                [
                    'overwrite' => true,
                    'asObject' => true,
                    'metadata' => [
                        'original_file' => $thumbnailFile->getAbsolutePath(),
                        'generated_at' => time(),
                        'options' => $options
                    ]
                ]
            );

            // Clean up temporary file
            if ($thumbnailFile->getAbsolutePath() !== $stored->getAbsolutePath()) {
                $thumbnailFile->delete();
            }

            return $stored;

        } catch (Exception $e) {
            App::log('Thumbnail storage failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

/**
 * Get thumbnail URL.
 *
 * @param string $cacheKey Cache key
 * @param string $store Store name
 * @return string Thumbnail URL
 */
    protected function _getThumbnailUrl(string $cacheKey, string $store): string {
        return FileStorage::url($cacheKey, $store);
    }

/**
 * Get fallback icon for unsupported file types.
 *
 * @param string $mime MIME type
 * @param array $options Options
 * @return string Fallback icon URL
 */
    protected function _getFallbackIcon(string $mime, array $options): string {
        $icons = $this->_config['fallbackIcons'];

        // Find specific icon for MIME type
        if (isset($icons[$mime])) {
            $iconPath = $icons[$mime];
        } else {
            // Check for wildcard match
            $type = explode('/', $mime)[0] . '/*';
            if (isset($icons[$type])) {
                $iconPath = $icons[$type];
            } else {
                $iconPath = $icons['default'];
            }
        }

        // Return full URL to icon
        return App::url('/images/icons/' . $iconPath);
    }

/**
 * Check if Imagick is available for PDF processing.
 *
 * @return bool True if Imagick is available
 */
    protected function _hasImagickSupport(): bool {
        return extension_loaded('imagick') && class_exists('Imagick');
    }

/**
 * Check if FFmpeg is available for video processing.
 *
 * @return bool True if FFmpeg is available
 */
    protected function _hasFfmpegSupport(): bool {
        $output = [];
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

/**
 * Get configuration.
 *
 * @param string|null $key Configuration key
 * @return mixed Configuration value
 */
    public function config($key = null) {
        if ($key === null) {
            return $this->_config;
        }
        return $this->_config[$key] ?? null;
    }

/**
 * Set configuration.
 *
 * @param string|array $key Configuration key or array
 * @param mixed $value Configuration value
 * @return $this
 */
    public function setConfig($key, $value = null) {
        if (is_array($key)) {
            $this->_config = array_merge($this->_config, $key);
        } else {
            $this->_config[$key] = $value;
        }
        return $this;
    }
}