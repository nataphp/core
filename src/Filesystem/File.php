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

namespace Nata\Filesystem;

use Nata\I18n\Time;
use Nata\Http\Response;

/**
 * Convenience class for reading, writing, renaming and appending to files.
 * Allows also to manipulate/edit images.
 */
class File {

/**
 * Folder instance of the file.
 *
 * @var \Nata\Filesystem\Folder
 */
    protected $_folder;

/**
 * File name.
 *
 * @var string
 */
    protected $_name;

/**
 * File realname.
 *
 * @var string
 */
    protected $_realname;

/**
 * File basename.
 *
 * @var string
 */
    protected $_basename;

/**
 * Current file's extension.
 *
 * @var string
 */
    protected $_extension;

/**
 * Current file's mime.
 *
 * @var string
 */
    protected $_mime;

/**
 * Current file's size (in bytes).
 *
 * @var int
 */
    protected $_size;

/**
 * File info.
 *
 * @var array
 */
    protected $_info;

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
 * Holds the file handler resource if the file is opened.
 *
 * @var resource
 * http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::$handle
 */
    protected $_handle;

/**
 * Enable locking for file reading and writing.
 *
 * @var boolean
 * http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::$lock
 */
    protected $_lock;

/**
 * Only reading is allowed.
 * This is useful to prevent any change to file
 * like writing, renaming, delete it (accidentally).
 *
 * @var boolean
 */
    protected $_readOnly;

/**
 * Current absolute path to file.
 *
 * @var string
 */
    protected $_path;

/**
 * Current file's hashes.
 *
 * @var array
 */
    protected $_hash = [];


/**
 * Constructor.
 *
 * @param string $path Path to file
 * @param boolean $create Create file if it does not exist (if true)
 * @param integer $mode Mode to apply to the folder holding the file
 */
    public function __construct($path, $create = false, $mode = 0755, $name = null) {
        $this->_path = dirname($path);
        $this->_create = $create;
        $this->_mode = $mode;

        $this->_name = $name;
        if (!is_dir($path)) {
            $this->_realname = basename($path);
            if ($this->_name === null) {
                $this->_name = $this->_realname;
            }
        }

        if (PHP_VERSION_ID < 80100) {
            ini_set('auto_detect_line_endings', true);
        }
    }

/**
 * Closes the current file if it's open.
 */
    public function __destruct() {
        $this->close();
    }

/**
 * Returns the full path of the file.
 *
 * @return string Full path to the file
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::pwd
 */
    public function pwd() {
        return $this->_path . DS . ($this->_realname ? $this->_realname : $this->_name);
    }

/**
 * Returns the current folder.
 *
 * @return \Nata\Filesystem\Folder Current folder
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::Folder
 */
    public function folder() {
        if ($this->_folder === null) {
            $this->_folder = new Folder($this->_path, $this->_create, $this->_mode);
        }
        return $this->_folder;
    }

/**
 * Creates the file.
 *
 * @return boolean Success
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::create
 */
    public function create() {
        $dir = $this->folder()->pwd();

        if (is_dir($dir) && is_writable($dir) && !$this->exists()) {
            if (touch($this->pwd())) {
                return true;
            }
        }

        return false;
    }

/**
 * Get/Set locking for file reading and writing.
 *
 * @return boolean $lock True to lock
 * @return $this|bool
 */
    public function lock($lock = null) {
        if ($lock === null) {
            return $this->_lock;
        }

        $this->_lock = $lock;

        return $this;
    }

/**
 * Get/Set allow file reading only.
 * Disables renaming, writing, deleting.
 *
 * @return boolean $readOnly True to read only mode
 * @return $this|bool
 */
    public function readOnly($readOnly = null) {
        if ($readOnly === null) {
            return $this->_readOnly;
        }
        $this->_readOnly = $readOnly;
        return $this;
    }

/**
 * Opens the current file with a given $mode.
 *
 * @param string $mode A valid 'fopen' mode string (r|w|a ...)
 * @param boolean $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return boolean True on success, false on failure
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::open
 */
    public function open($mode = 'r', $force = false) {
        if (!$force && is_resource($this->_handle)) {
            return true;
        }

        if ($this->exists() === false && $this->create() === false) {
            return false;
        }

        $this->_handle = fopen($this->pwd(), $mode);

        if (is_resource($this->_handle)) {
            return true;
        }

        return false;
    }

/**
 * Return the contents of this file as a string.
 *
 * @param string $bytes where to start
 * @param string $mode A `fread` compatible mode.
 * @param boolean $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return mixed string on success, false on failure
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::read
 */
    public function read($bytes = false, $mode = 'rb', $force = false) {
        if ($bytes === false && !$this->_lock) {
            return file_get_contents($this->pwd());
        }

        if ($this->open($mode, $force) === false) {
            return false;
        }

        if ($this->_lock === true && flock($this->_handle, LOCK_SH) === false) {
            return false;
        }

        if (is_int($bytes)) {
            return fread($this->_handle, $bytes);
        }

        $data = '';

        while (!feof($this->_handle)) {
            $data .= fgets($this->_handle, 4096);
        }

        if ($this->_lock === true) {
            flock($this->_handle, LOCK_UN);
        }

        if ($bytes === false) {
            $this->close();
        }

        return trim($data);
    }

/**
 * Get/Set file resource handle.
 *
 * @return resource $handle Resource
 * @return $this|resource
 */
    public function handle($handle = null) {
        if ($handle === null) {
            return $this->_handle;
        }

        if (is_resource($handle)) {
            $this->_handle = $handle;
        }

        return $this;
    }

/**
 * Sets or gets the offset for the currently opened file.
 *
 * @param integer|boolean $offset The $offset in bytes to seek. If set to false then the current offset is returned.
 * @param integer $seek PHP Constant SEEK_SET | SEEK_CUR | SEEK_END determining what the $offset is relative to
 * @return mixed True on success, false on failure (set mode), false on failure or integer offset on success (get mode)
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::offset
 */
    public function offset($offset = false, $seek = SEEK_SET) {
        if ($offset === false) {
            if (is_resource($this->_handle)) {
                return ftell($this->_handle);
            }
        } elseif ($this->open() === true) {
            return fseek($this->_handle, $offset, $seek) === 0;
        }
        return false;
    }

/**
 * Prepares a ASCII string for writing. Converts line endings to the
 * correct terminator for the current platform. If Windows, "\r\n" will be used,
 * all other platforms will use "\n"
 *
 * @param string $data Data to prepare for writing.
 * @param boolean $forceWindows
 * @return string The with converted line endings.
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::prepare
 */
    public static function prepare($data, $forceWindows = false) {
        $lineBreak = "\n";
        if (DIRECTORY_SEPARATOR === '\\' || $forceWindows === true) {
            $lineBreak = "\r\n";
        }
        return strtr($data, array("\r\n" => $lineBreak, "\n" => $lineBreak, "\r" => $lineBreak));
    }

/**
 * Write given data to this file.
 *
 * @param string $data Data to write to this File.
 * @param string $mode Mode of writing. {@link http://php.net/fwrite See fwrite()}.
 * @param string $force Force the file to open
 * @return boolean Success
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::write
 */
    public function write($data, $mode = 'w', $force = false) {
        if ($this->_readOnly === true || $this->open($mode, $force) === false) {
            return false;
        }

        if ($this->_lock === true && flock($this->_handle, LOCK_EX) === false) {
            return false;
        }

        $success = fwrite($this->_handle, $data) !== false;

        if ($this->_lock === true) {
            flock($this->_handle, LOCK_UN);
        }

        $this->_hash = [];

        return $success;
    }

/**
 * Append given data string to this file.
 *
 * @param string $data Data to write
 * @param string $force Force the file to open
 * @return boolean Success
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::append
 */
    public function append($data, $force = false) {
        return $this->write($data, 'a', $force);
    }

/**
 * Closes the current file if it is opened.
 *
 * @return boolean True if closing was successful or file was already closed, otherwise false
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::close
 */
    public function close() {
        if (!is_resource($this->_handle)) {
            return true;
        }
        return fclose($this->_handle);
    }

/**
 * Deletes the file.
 *
 * @param closure $callable On deletion
 * @return boolean Success
 */
    public function delete($callable = null) {
        if ($this->_readOnly !== true) {

            if (is_resource($this->_handle)) {
                fclose($this->_handle);
                $this->_handle = null;
            }

            if ($this->exists()) {
                $deleted = @unlink($this->pwd());
                if (is_callable($callable)) {
                    $deleted = $callable($deleted, $this);
                }
                return $deleted;
            }

        }

        return false;
    }

/**
 * Returns the file info as an array with the following keys:
 *
 * - dirname
 * - basename
 * - extension
 * - filename
 * - filesize
 * - mime
 *
 * @param string $info File information option.
 * @return array|string File information.
 */
    public function info($info = null) {
        if ($this->_info === null) {
            $this->_info = pathinfo($this->pwd());

            if (!isset($this->_info['filename'])) {
                $this->_info['filename'] = $this->name();
            }

            $this->_info['extension'] = $this->extension();

            if (!isset($this->_info['filesize'])) {
                $this->_info['filesize'] = $this->size();
            }

            if (!isset($this->_info['mime'])) {
                $this->_info['mime'] = $this->mime();
            }
        }

        if ($info === null) {
            return $this->_info;
        }

        return $this->_info[$info];
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name() {
        if ($ext = $this->extension(true)) {
            return basename($this->_name, $ext);
        } elseif ($this->_name) {
            return $this->_name;
        }
        return null;
    }

/**
 * Returns the file name with extension.
 *
 * @return string The file name with extension.
 */
    public function basename() {
        return $this->_name;
    }

/**
 * Get file's real name.
 *
 * @return string Get file real name as it is in filesystem.
 */
    public function realname() {
        return $this->_realname;
    }

/**
 * Rename file.
 *
 * @param string $name New file name.
 * @return $this|bool Success
 */
    public function rename($name) {
        if (!$this->exists() || $this->_readOnly === true || $this->_lock === true) {
            return false;
        }

        $name = basename($name);
        $file = new File($this->_path . DS . $name);
        // If file with this name already exists
        if ($file->exists()) {
            return false;
        }

        if (!rename($this->pwd(), $file->pwd())) {
            return false;
        }

        $this->_name = $name;
        $this->_info = null;

        return true;
    }

/**
 * Returns the current file's extension.
 * If $dotPrepend set to 'true', it will return extension prepended with dot.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string The file extension
 */
    public function extension($dotPrepend = false) {
        if ($this->_extension === null) {
            $this->_extension = strtolower(pathinfo($this->_name, PATHINFO_EXTENSION));
        }
        $extension = $this->_extension;
        return ($dotPrepend && !empty($extension) ? '.' : '') . $extension;
    }

/**
 * Force the extension on file's name (when one was is not present on the filename)
 * based on the mimetype.
 *
 * @return $this
 */
    public function forceExtension() {
        $extension = $this->extension();
        if (empty($extension)) {
            $mime = $this->mime();
            if (!empty($mime)) {
                $extension = Mimetype::getExtension($mime);
                if (!empty($extension)) {
                    if ($this->_name) {
                        if ($this->rename($this->_name . '.' . $extension)) {
                            $this->_extension = $extension;
                        }
                    }
                }
            }
        }
        return $this;
    }

/**
 * Makes file name safe for saving.
 *
 * @param string $name The name of the file to make safe if different from $this->_name
 * @param string $ext The name of the extension to make safe if different from $this->ext
 * @return string $ext The extension of the file
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::safe
 */
    public function safe($name = null, $ext = null) {
        if ($name === null) {
            $name = $this->name();
        }

        if ($ext === null) {
            $ext = $this->extension();
        }

        return preg_replace("/(?:[^\w\.-]+)/", "_", basename($name, $ext));
    }

/**
 * Get md5 Checksum of file with previous check of Filesize.
 *
 * @param integer|boolean $maxsize in MB or true to force
 * @return string|false md5 Checksum {@link http://php.net/md5_file See md5_file()}, or false in case of an error
 */
    public function md5($maxsize = 5) {
        return $this->hash('md5', $maxsize);
    }

/**
 * Get sha1 checksum of file with previous check of file size.
 *
 * @param integer|boolean $maxsize in MB or true to force
 * @return string|false sha1 Checksum {@link http://php.net/sha1_file See sha1_file()}, or false in case of an error
 */
    public function sha1($maxsize = 5) {
        return $this->hash('sha1', $maxsize);
    }

/**
 * Get checksum of file with previous check of file size.
 *
 * @param string $algo Name of selected hashing algorithm
 * (i.e. "md5", "sha256", "haval160,4", etc..)
 * @param integer|boolean $maxsize in MB or true to force
 * @param bool $rawOutput When set to true, outputs raw binary data.
 * False outputs lowercase hexits.
 * @return string|false Algorithom Checksum
 */
    public function hash($algo = 'sha1', $maxsize = 5, $rawOutput = false) {
        if (isset($this->_hash[$algo])) {
            return $this->_hash[$algo];
        }

        if (!$this->exists()) {
            return null;
        }

        if ($maxsize === true) {
            return $this->_hash[$algo] = hash_file($algo, $this->pwd(), $rawOutput);
        }

        $size = $this->size();
        if ($size && $size < ($maxsize * 1024) * 1024) {
            return $this->_hash[$algo] = hash_file($algo, $this->pwd(), $rawOutput);
        }

        return false;
    }

/**
 * Returns true if the file exists.
 *
 * @return boolean True if it exists, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::exists
 */
    public function exists() {
        $path = $this->pwd();
        return (file_exists($path) && is_file($path));
    }

/**
 * Returns the "chmod" (permissions) of the file.
 *
 * @return string|false Permissions for the file, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::perms
 */
    public function perms() {
        if ($this->exists()) {
            return substr(sprintf('%o', fileperms($this->pwd())), -4);
        }
        return false;
    }

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::size
 */
    public function size() {
        if (!$this->exists()) {
            return false;
        }

        if ($this->_size === null) {
            $this->_size = filesize($this->pwd());
        }

        return $this->_size;
    }

/**
 * Returns true if the file is writable.
 *
 * @return boolean True if it's writable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::writable
 */
    public function isWritable() {
        return $this->_readOnly !== true && is_writable($this->pwd());
    }

/**
 * Returns true if the File is executable.
 *
 * @return boolean True if it's executable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::executable
 */
    public function isExecutable() {
        return is_executable($this->pwd());
    }

/**
 * Returns true if the file is readable.
 *
 * @return boolean True if file is readable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::readable
 */
    public function isReadable() {
        return is_readable($this->pwd());
    }

/**
 * Returns the file's owner.
 *
 * @return integer|false The file owner, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::owner
 */
    public function owner() {
        if ($this->exists()) {
            return fileowner($this->pwd());
        }
        return false;
    }

/**
 * Returns the file's group.
 *
 * @return integer|false The file group, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::group
 */
    public function group() {
        if ($this->exists()) {
            return filegroup($this->pwd());
        }
        return false;
    }

/**
 * Returns last access time.
 *
 * @return integer|false Timestamp of last access time, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::lastAccess
 */
    public function lastAccess() {
        if ($this->exists()) {
            return fileatime($this->pwd());
        }
        return false;
    }

/**
 * Returns last modified time.
 *
 * @return integer|false Timestamp of last modification, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::lastChange
 */
    public function lastChange() {
        if ($this->exists()) {
            return filemtime($this->pwd());
        }
        return false;
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
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::lastAccess
 */
    public function touch($time = null, $accessTime = null) {
        if (!$this->exists()) {
            return false;
        }

        if ($time === null) {
            $time = Time::now();
        }

        if ($time instanceof Time) {
            $time = $time->timestamp();
        }

        return touch($this->pwd(), $time, $accessTime);
    }

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param boolean $overwrite Overwrite $dest if exists
 * @return \Nata\Filesystem\File|bool File copy instance on success, false on error
 */
    public function copy($dest, $overwrite = true) {
        if (!$this->exists() || is_file($dest) && !$overwrite) {
            return false;
        }

        if (copy($this->pwd(), $dest)) {
            return new self($dest);
        }

        return false;
    }

/**
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime() {
        if ($this->_mime === null) {
            $this->_mime = false;

            if ($this->exists()) {
                $path = $this->pwd();

                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimetype = finfo_file($finfo, $path);
                    finfo_close($finfo);
                    if (!$mimetype) {
                        return false;
                    }

                    [$type] = explode(';', $mimetype);
                    $this->_mime = trim($type);
                }

                if (!$this->_mime && function_exists('mime_content_type')) {
                    $this->_mime = mime_content_type($path);
                }

                // If finfo not installed, fallback to the possibility of being an image file
                // and use image libraries to get the mime type
                if (!$this->_mime && function_exists('exif_imagetype') && $type = exif_imagetype($path)) {
                    $this->_mime = image_type_to_mime_type($type);
                }

                if (!$this->_mime && $type = getimagesize($path)) {
                    $this->_mime = $type['mime'];
                }

            }

        }

        return $this->_mime;
    }

/**
 * Check whether or not a file is a certain type.
 *
 * @param string $type Type to check
 * @return boolean True if it is, false otherwise
 */
    public function is($type) {
        if ($this->exists()) {
            $info = $this->info();
            if (str_contains($type, '/*')) {
                $type = str_replace('/*', '', $type);
            }
            list($info['_mimetoplevel'], $info['_mimepart']) = explode('/', $info['mime']);

            return in_array($type, $info, true);
        }
        return false;
    }

/**
 * Clear PHP's internal stat cache.
 *
 * @param boolean $all Clear all cache or not. Passing false will clear
 *   the stat cache for the current path only.
 * @return void
 */
    public function clearStatCache($all = false) {
        if ($all === false) {
            return clearstatcache(true, $this->pwd());
        }
        return clearstatcache();
    }

/**
 * Searches for a given text and replaces the text if found.
 *
 * @param string|array $search Text(s) to search for.
 * @param string|array $replace Text(s) to replace with.
 * @return boolean Success
 */
    public function replaceText($search, $replace) {
        if (!$this->open('r+')) {
            return false;
        }

        if ($this->_lock === true) {
            if (flock($this->_handle, LOCK_EX) === false) {
                return false;
            }
        }

        $replaced = $this->write(str_replace($search, $replace, $this->read()), 'w', true);

        if ($this->_lock === true) {
            flock($this->_handle, LOCK_UN);
        }

        $this->close();

        return $replaced;
    }

/**
 * Base64 encoded data URI.
 * This is useful to show in HTML.
 *
 * @return string Base64 encoded data URI
 */
    public function getDataUri() {
        return 'data:' . $this->mime() . ';base64,' . $this->getBase64();
    }

/**
 * Encode file's binary blob to base64.
 *
 * @return string Base64 encoded file's binary
 */
    public function getBase64() {
        return base64_encode($this->read());
    }

/**
 * Get file URL.
 *
 * @return string File URL
 */
    public function getUrl() {
        return 'file://' . str_replace(DS, '/', $this->pwd());
    }

/**
 * @see \Nata\Filesystem\File::info()
 * @return array File info
 */
    public function toArray() {
        return $this->info();
    }

/**
 * __toString
 *
 * @return string
 */
    public function __toString() {
        return $this->pwd();
    }

/**
 * Get file's extension based on mime type.
 * Useful when source of file is not reliable (file upload, etc).
 * This method will return the file extension based on the mime type from
 * a list of mime types in \Nata\Http\Response.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string File extension
 * @uses \Nata\Http\Response
 */
    public static function extensionFromMime($mime, $dotPrepend = false) {
        $response = new Response;
        $extension = $response->mapType($mime);
        return ($dotPrepend && !empty($extension) ? '.' : '') . $extension;
    }

/**
 * Get file's mime type from extension.
 * Useful when all we have is the file's basename.
 *
 * @return string File mimetype
 * @uses \Nata\Http\Response
 */
    public static function mimeFromExtension($extension) {
        $response = new Response;
        return $response->getMimeType($extension);
    }

}
