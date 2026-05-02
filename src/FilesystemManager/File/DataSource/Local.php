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

namespace Nata\FilesystemManager\File\DataSource;

use Closure;
use Nata\Core\App;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\DataSourceInterface;
use Nata\FilesystemManager\FileFactory;
use Nata\FilesystemManager\Folder;
use Nata\I18n\Time;

/**
 * Convenience class for reading, writing, renaming and appending to files.
 * Allows also to manipulate/edit images.
 */
class Local implements DataSourceInterface {

/**
 * File handle.
 *
 * @var resource
 */
    protected $_handle;

/**
 * File name.
 *
 * @var string
 */
    protected $_name;

/**
 * Real file name.
 *
 * @var string
 */
    protected $_realname;

/**
 * Create file if it does not exist (if true).
 *
 * @var bool
 */
    protected $_create;

/**
 * Mode to apply to the folder holding the file.
 *
 * @var string
 */
    protected $_mode;

/**
 * Current absolute path to file.
 *
 * @var string
 */
    protected $_path;

/**
 * URL to view file.
 *
 * @var string
 */
    protected $_url;


/**
 * Constructor.
 *
 * @param string $path Path to file
 * @param array $options Options
 */
    public function __construct(string $path, array $options = []) {
        $options += [
            'name' => null,
            'create' => false,
            'mode' => 0755
        ];

        $this->_path = dirname(App::normalize($path));
        $this->_create = $options['create'];
        $this->_mode = $options['mode'];

        $this->_name = $options['name'];
        if (!is_dir($path)) {
            $this->_realname = basename($path);
            if ($this->_name === null) {
                $this->_name = $this->_realname;
            }
        }

        if (PHP_VERSION_ID < 80100) {
            ini_set('auto_detect_line_endings', true);
        }

        if ($this->_create === true) {
            $this->create();
        }
    }

/**
 * Returns the absolute path of the file.
 *
 * @return string Absolute path to the file
 */
    public function getAbsolutePath(): string {
        $realname = $this->realname();
        return $this->_path . '/' . ($realname ? $realname : $this->_name);
    }

/**
 * Returns the absolute local path of the file.
 *
 * @return string Absolute local path to the file
 */
    public function getAbsoluteLocalPath(): string {
        return $this->getAbsolutePath();
    }

/**
 * Returns the current folder.
 *
 * @return \Nata\FilesystemManager\Folder Current folder
 */
    public function getFolder(): Folder {
        return new Folder($this->_path);
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): string {
        return $this->realname(false);
    }

/**
 * Returns the current file's extension.
 *
 * @return string The file extension
 */
    public function extension(): ?string {
        return pathinfo($this->_name, PATHINFO_EXTENSION);
    }

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param boolean $overwrite Overwrite $dest if exists
 * @return File|false File copy instance on success, false on error
 */
    public function copy(string $dest, bool $overwrite = true): ?File {
        if (!$this->exists() || (is_file($dest) && !$overwrite)) {
            return null;
        }

        if (copy($this->getAbsolutePath(), $dest)) {
            return new File($dest);
        }

        return null;
    }

/**
 * Get checksum of file with previous check of file size.
 *
 * @param string $algo Hashing algorithm
 * @param int|bool $maxsize Max size in MB or true to force
 * @param bool $rawOutput Raw binary output
 * @return string|false Hash or false on error
 */
    public function hash(string $algo = 'sha1', int|bool $maxsize = 5, bool $rawOutput = false): ?string {
        if (!$this->exists()) {
            return null;
        }

        if ($maxsize === true) {
            return hash_file($algo, $this->getAbsolutePath(), $rawOutput);
        }

        $size = $this->size();
        if ($size && $size < ($maxsize * 1024) * 1024) {
            return hash_file($algo, $this->getAbsolutePath(), $rawOutput);
        }

        return null;
    }

/**
 * Returns the current folder.
 *
 * @return \Nata\FilesystemManager\Folder Current folder
 */
    public function folder() {
        return new Folder($this->getAbsolutePath(), $this->_create, $this->_mode);
    }

/**
 * Creates the file.
 *
 * @return boolean Success
 */
    public function create(): bool {
        $dir = dirname($this->getAbsolutePath());
        if (is_dir($dir) && is_writable($dir) && $this->exists() === false) {
            if (touch($this->getAbsolutePath())) {
                return true;
            }
        }
        return false;
    }

/**
 * Opens the current file with a given $mode.
 *
 * @param string $mode A valid 'fopen' mode string (r|w|a ...)
 * @param boolean $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return resource|false Handle on success, false on failure
 */
    public function open($mode = 'r'): mixed {
        $absPath = $this->getAbsolutePath();
        if ($this->_create === true && file_exists($absPath) === false) {
            if ($this->create()) {
                $this->_create = false;
            }
        }
        return fopen($absPath, $mode);
    }

/**
 * Deletes the file.
 *
 * @param closure $callable On deletion
 * @return boolean Success
 */
    public function delete(?Closure $callable = null): bool {
        if (is_resource($this->_handle)) {
            fclose($this->_handle);
            $this->_handle = null;
        }

        if (!$this->exists()) {
            return false;
        }

        $deleted = @unlink($this->getAbsolutePath());
        if ($callable !== null) {
            $deleted = $callable($deleted, $this);
        }
        return $deleted;
    }

/**
 * Closes the current file if it is opened.
 *
 * @return boolean True if closing was successful or file was already closed, otherwise false
 */
    public function close(): bool {
        if (!is_resource($this->_handle)) {
            return true;
        }
        return fclose($this->_handle);
    }

/**
 * Returns the real file name (with or ).
 *
 * @param bool $withExtension Whether to include the extension or not.
 * @return string The file name with or without extension.
 */
    public function realname(bool $withExtension = true): string {
        $name = $this->_realname;
        if ($name === null) {
            $name = $this->_realname = $this->_name;
        }
        if ($withExtension === false) {
            return basename($name, $this->extension(true));
        }
        return $name;
    }

/**
 * Rename file.
 *
 * @param string $name New file name.
 * @return $this|bool Success
 */
    public function rename(string $name): bool {
        $newPath = dirname($this->getAbsolutePath()) . '/' . basename($name);
        if (!$this->exists() || is_file($newPath)) {
            return false;
        }

        if (rename($this->getAbsolutePath(), $newPath)) {
            $this->_name = basename($name);
            return true;
        }
        return false;
    }

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size(): int {
        if (!$this->exists()) {
            return 0;
        }
        return filesize($this->getAbsolutePath());
    }

/**
 * Returns true if the file exists.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function exists(): bool {
        $this->clearStatCache();
        $path = $this->getAbsolutePath();
        return file_exists($path) && is_file($path);
    }

/**
 * Returns the "chmod" (permissions) of the file.
 *
 * @return string|false Permissions for the file, or false in case of an error
 */
    public function perms(): ?string {
        if (!$this->exists()) {
            return false;
        }
        return substr(sprintf('%o', fileperms($this->getAbsolutePath())), -4);
    }

/**
 * Returns true if the file is writable.
 *
 * @return boolean True if it's writable, false otherwise
 */
    public function isWritable(): bool {
        return is_writable($this->getAbsolutePath());
    }

/**
 * Returns true if the File is executable.
 *
 * @return boolean True if it's executable, false otherwise
 */
    public function isExecutable(): bool {
        return is_executable($this->getAbsolutePath());
    }

/**
 * Returns true if the file is readable.
 *
 * @return boolean True if file is readable, false otherwise
 */
    public function isReadable(): bool {
        return is_readable($this->getAbsolutePath());
    }

/**
 * Returns the file's owner.
 *
 * @return integer|false The file owner, or false in case of an error
 */
    public function owner(): ?int {
        if (!$this->exists()) {
            return null;
        }

        if (!$owner = fileowner($this->getAbsolutePath())) {
            return null;
        }
        return $owner;
    }

/**
 * Returns the file's group.
 *
 * @return integer|false The file group, or false in case of an error
 */
    public function group(): ?int {
        if (!$this->exists()) {
            return null;
        }

        if (!$group = filegroup($this->getAbsolutePath())) {
            return null;
        }
        return $group;
    }

/**
 * Returns last access time.
 *
 * @return integer|false Timestamp of last access time, or false in case of an error
 */
    public function lastAccess(): ?Time {
        if (!$this->exists()) {
            return new Time();
        }
        return new Time(fileatime($this->getAbsolutePath()));
    }

/**
 * Returns last modified time.
 *
 * @return integer|false Timestamp of last modification, or false in case of an error
 */
    public function lastChange(): ?Time {
        if (!$this->exists()) {
            return new Time();
        }
        return new Time(filemtime($this->getAbsolutePath()));
    }

/**
 * Sets access and modification time of file.
 *
 * Attempts to set the access and modification times of the file named in the
 * filename parameter to the value given in time. Note that the access time is always modified,
 * regardless of the number of parameters.
 *
 * @param int $time The touch time. If time is not supplied, the current system time is used.
 * @param int $accessTime If present, the access time of the given filename
 *   is set to the value of atime. Otherwise, it is set to the value passed to the time parameter.
 *   If neither are present, the current system time is used.
 * @return integer|false Timestamp of last access time, or false in case of an error
 */
    public function touch($time = null, $accessTime = null): bool {
        if (!$this->exists()) {
            return false;
        }

        if ($time === null) {
            $time = Time::now();
        }

        if ($time instanceof Time) {
            $time = $time->timestamp();
        }

        return touch($this->getAbsolutePath(), $time, $accessTime);
    }

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param array $config Options for copy. It will also pass config FileFactory::build().
 * @return \Nata\FilesystemManager\File|bool File copy instance on success, false on error
 */
    public function _copy($dest, $config = []) {
        // BC
        if (is_bool($config)) {
            $config = ['overwrite' => $config];
        }

        $config += [
            'overwrite' => true
        ];

        if (!$this->exists() || (is_file($dest) && !$config['overwrite'])) {
            return false;
        }

        if (!copy($this->getAbsolutePath(), $dest)) {
            return false;
        }

        return FileFactory::build($dest, $config);
    }

/**
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime(): ?string {
        $path = $this->getAbsolutePath();

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (!$mimetype) {
                return false;
            }

            [$type] = explode(';', $mimetype);
            $mime = trim($type);
        }

        if (!$mime && function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
        }

        // If finfo not installed, fallback to the possibility of being an image file
        // and use image libraries to get the mime type
        if (!$mime && function_exists('exif_imagetype') && $type = exif_imagetype($path)) {
            $mime = image_type_to_mime_type($type);
        }

        if (!$mime && $type = getimagesize($path)) {
            $mime = $type['mime'];
        }
        return $mime;
    }

/**
 * Clear PHP's internal stat cache.
 *
 * @param boolean $all Clear all cache or not. Passing false will clear
 *   the stat cache for the current path only.
 * @return void
 */
    public function clearStatCache(bool $all = false): void {
        if ($all === false) {
            clearstatcache(true, $this->getAbsolutePath());
        } else {
            clearstatcache();
        }
    }

/**
 * Get file URL.
 *
 * @return string File URL
 */
    public function getUrl(): ?string {
        return $this->_url;
    }

/**
 * Returns the file:// protocol URL for this file.
 *
 * @return string File URL with file:// protocol
 */
    public function getFileUrl(): ?string {
        return 'file://' . str_replace('\\', '/', $this->getAbsolutePath());
    }

/**
 * Returns the file data source as a string.
 *
 * @return string The file data source
 */
    public function __toString(): string {
        return $this->getAbsolutePath();
    }

}
