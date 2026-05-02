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

namespace Nata\FilesystemManager;

use Nata\Core\App;
use Nata\I18n\Time;
use Nata\FilesystemManager\File\DataSourceFactory;
use Nata\FilesystemManager\File\DataSourceInterface;
use Nata\FilesystemManager\Folder;
use Nata\Utility\Hash;
use Nata\Utility\Text;
use Closure;
use Exception;

/**
 * Convenience class for reading, writing, renaming and appending to files.
 * Allows also to manipulate/edit images.
 */
class File {

/**
 * Folder instance of the file.
 *
 * @var \Nata\FilesystemManager\Folder
 */
    protected readonly Folder $_folder;

/**
 * File name.
 *
 * @var string
 */
    protected ?string $_name = null;

/**
 * File realname.
 *
 * @var string
 */
    protected ?string $_realname = null;

/**
 * Current file's real extension.
 *
 * @var string
 */
    protected ?string $_realextension = null;

/**
 * File basename.
 *
 * @var string
 */
    protected ?string $_basename = null;

/**
 * Current file's extension.
 *
 * @var string
 */
    protected ?string $_extension = null;

/**
 * Current file's mime.
 *
 * @var string
 */
    protected ?string $_mime = null;

/**
 * Current file's size (in bytes).
 *
 * @var int
 */
    protected ?int $_size = null;

/**
 * File info.
 *
 * @var array
 */
    protected array $_info = [];

/**
 * Create file if it does not exist (if true).
 *
 * @var bool
 */
    protected ?bool $_create = false;

/**
 * Mode to apply to the folder holding the file.
 *
 * @var int
 */
    protected ?int $_mode;

/**
 * Holds the file handler resource if the file is opened.
 *
 * @var resource
 */
    protected mixed $_handle = null;

/**
 * Enable locking for file reading and writing.
 *
 * @var boolean
 */
    protected bool $_lock = false;

/**
 * Only reading is allowed.
 * This is useful to prevent any change to file
 * like writing, renaming, delete it (accidentally).
 *
 * @var boolean
 */
    protected bool $_readOnly = false;

/**
 * Freeze file instance to prevent any change to properties, like
 * trying to get from datasource mimetype, size, etc.
 *
 * @var boolean
 */
    protected bool $_freeze = false;

/**
 * Freeze file instance to prevent any change to properties, like
 * trying to get from datasource mimetype, size, etc.
 *
 * @var boolean
 */
    protected bool|null $_exists;

/**
 * Current absolute path to file.
 *
 * @var string
 */
    protected string|null $_path = null;

/**
 * Current absolute local path to file.
 *
 * @var string
 */
    protected string|null $_localPath = null;

/**
 * Current file's hashes.
 *
 * @var array
 */
    protected array $_hash = [];

/**
 * Metadata.
 *
 * @var array
 */
    protected array $_metadata = [];

/**
 * DataSource instance.
 *
 * @var DataSourceInterface
 */
    protected DataSourceInterface|null $_dataSource = null;

/**
 * File URL.
 *
 * @var string
 */
    protected ?string $_url = null;

/**
 * Constructor.
 *
 * @param mixed $source File contents/source
 * @param array $options Options
 * @return void
 */
    public function __construct(mixed $source, array $options = []) {
        $options += [
            'create' => false,
            'path' => null,
            'name' => null,
            'url' => null,
            'mime' => null,
            'size' => null,
            'datasource' => null,
            'metadata' => [],
            'freeze' => false,
            'exists' => null,
        ];

        $this->_path = $options['path'];
        $this->_url = $options['url'];
        $this->_name = $options['name'];
        $this->_mime = $options['mime'];
        $this->_size = $options['size'];
        $this->_freeze = $options['freeze'];
        $this->_exists = $options['exists'] ?? null;
        $this->_metadata = $options['metadata'];

        if ($this->_freeze === true) {
            // return;
        }

        if ($source instanceof DataSourceInterface) {
            $this->_dataSource = $source;
        } else {
            if (str_contains($source, 'natafs://')) {
                $parts = FileStorage::parseUri($source);
                unset($parts['scheme']);
                $this->metadata($parts + ['storageuri' => $source]);
            }
            $this->_dataSource = DataSourceFactory::build($source, $options);
        }

        if ($options['create'] === true) {
            $this->create();
        }
    }

/**
 * Returns the absolute path of the file.
 *
 * @return string Absolute path to the file
 */
    public function getAbsolutePath(): string|null {
        if ($this->_path === null && $this->_freeze === false) {
            $this->_path = $this->_dataSource->getAbsolutePath();
        }
        return $this->_path;
    }

/**
 * Returns the absolute path of the file.
 *
 * @return string Absolute path to the file
 */
    public function getAbsoluteLocalPath(): string|null {
        if ($this->_localPath === null && $this->_freeze === false) {
            $this->_localPath = $this->_dataSource->getAbsoluteLocalPath();
        }
        return $this->_localPath;
    }

/**
 * Returns the path (without filename).
 *
 * @todo Implement this method
 * @return string Path without filename
 */
    public function getPath(): string {
        if ($this->_path === null && $this->_freeze === false) {
            $this->_path = $this->_dataSource->getPath();
        }
        return $this->_path;
    }

/**
 * Returns the absolute path of the file.
 *
 * @deprecated Use getAbsolutePath() instead.
 * @return string Absolute path to the file
 */
    public function pwd(): string {
        return $this->getAbsoluteLocalPath();
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): ?string {
        if ($ext = $this->extension(true)) {
            return basename($this->_name ?? $this->basename(), $ext);
        } elseif ($this->_name) {
            return $this->_name ?? $this->realname();
        }
        return null;
    }

/**
 * Returns the file name with extension.
 *
 * @return string The file name with extension.
 */
    public function basename(): ?string {
        return $this->_name ?? $this->realname();
    }

/**
 * Get file's real name.
 *
 * @param bool $withExtension Include extension in the name
 * @return string Get file real name as it is in filesystem.
 */
    public function realname(bool $withExtension = true): string {
        if ($this->_realname === null && $this->_freeze === false) {
            $this->_realname = $this->_dataSource->realname();
        }
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
 * Returns the current file's extension.
 * If $dotPrepend set to 'true', it will return extension prepended with dot.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string The file extension
 */
    public function extension($dotPrepend = false) {
        if ($this->_extension === null) {
            $this->_extension = pathinfo($this->basename() ?? $this->realname(), PATHINFO_EXTENSION);
            if (!$this->_extension) {
                $this->_extension = $this->_dataSource->extension();
            }
        }
        $extension = $this->_extension;
        return ($dotPrepend && !empty($extension) ? '.' : '') . $extension;
    }

/**
 * Returns the current real file's extension.
 * If $dotPrepend set to 'true', it will return extension prepended with dot.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string The file extension
 */
    public function realextension($dotPrepend = false) {
        if ($this->_realextension === null) {
            $this->_realextension = pathinfo($this->realname(), PATHINFO_EXTENSION);
        }
        $extension = $this->_realextension;
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
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime() {
        if ($this->_mime === null && $this->_freeze === false) {
            if ($this->exists()) {
                $this->_mime = $this->_dataSource->mime();
            }
        }
        return $this->_mime;
    }

/**
 * Returns the current folder.
 *
 * @return \Nata\Filesystem\Folder Current folder
 */
    public function folder(): Folder {
        if ($this->_folder === null && $this->_freeze === false) {
            $this->_folder = $this->_dataSource->getFolder();
        }
        return $this->_folder;
    }

/**
 * Creates the file.
 *
 * @return boolean Success
 */
    public function create(): bool {
        return $this->_dataSource->create();
    }

/**
 * Get/Set lock for file reading and writing.
 *
 * @return boolean $lock True to lock
 * @return $this|bool
 */
    public function lock(bool $lock = null): bool|self {
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
    public function readOnly(bool $readOnly = null): bool|self {
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
 * @param bool $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return bool True on success, false on failure
 */
    public function open(string $mode = 'r', bool $force = false): bool {
        if ($this->_freeze === true) {
            return false;
        }
        if (is_resource($this->_handle) && $force === false) {
            return true;
        }

        if ($this->_readOnly === false && ($mode === 'r' || $mode === 'rb')) {
            $mode = 'r+';
        }

        $this->_handle = $this->_dataSource->open($mode);
        return is_resource($this->_handle);
    }

/**
 * Return the contents of this file as a string.
 *
 * @param string $bytes where to start
 * @param string $mode A `fread` compatible mode.
 * @param bool $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return string|false String on success, false on failure
 */
    public function read(int $length = null, $mode = 'rb', $force = false): string|false {
        if ($this->open($mode, $force) === false) {
            return false;
        }

        if ($this->_lock === true && flock($this->_handle, LOCK_SH) === false) {
            return false;
        }

        if (is_int($length)) {
            return fread($this->_handle, $length);
        }

        rewind($this->_handle);

        $chunkSize = $this->_getOptimalChunkSize();
        $data = '';
        while (!feof($this->_handle)) {
            $data .= fgets($this->_handle, $chunkSize);
        }

        rewind($this->_handle);

        if ($this->_lock === true) {
            flock($this->_handle, LOCK_UN);
        }

        return $data;
    }

/**
 * Determine the optimal chunk size based on file size and available memory.
 *
 * @return int The optimal chunk size in bytes.
 */
    private function _getOptimalChunkSize(): int {
        $fileSize = $this->size();
        $memoryLimit = $this->_getMemoryLimit();
        $availableMemory = $memoryLimit - memory_get_usage();

        // Base chunk size based on file size
        $baseChunkSize = match(true) {
            $fileSize < (1 * 1024 * 1024) => 4096,    // < 1MB
            $fileSize < (100 * 1024 * 1024) => 16384, // 1MB to 100MB
            default => 65536                          // > 100MB
        };

        // Adjust chunk size based on available memory
        // Use at most 5% of available memory for chunk size
        $memoryBasedSize = (int) ($availableMemory * 0.05);

        // Use the smaller of base chunk size and memory-based size
        // but ensure it's at least 4KB
        return max(4096, min($baseChunkSize, $memoryBasedSize));
    }

/**
 * Get the memory limit in bytes.
 *
 * @return int Memory limit in bytes
 */
    private function _getMemoryLimit(): int {
        $memoryLimit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
            if ($matches[2] == 'G') {
                return $matches[1] * 1024 * 1024 * 1024;
            }
            if ($matches[2] == 'M') {
                return $matches[1] * 1024 * 1024;
            }
            if ($matches[2] == 'K') {
                return $matches[1] * 1024;
            }
        }
        return (int) $memoryLimit;
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
 */
    public function offset(int $offset = null, int $seek = SEEK_SET): int|bool {
        if ($offset === null) {
            if (!is_resource($this->_handle)) {
                return false;
            }
            return ftell($this->_handle);
        } elseif ($this->open() === false) {
            return false;
        }
        return fseek($this->_handle, $offset, $seek) === 0;
    }

/**
 * Prepares a ASCII string for writing. Converts line endings to the
 * correct terminator for the current platform. If Windows, "\r\n" will be used,
 * all other platforms will use "\n"
 *
 * @param string $data Data to prepare for writing.
 * @param boolean $forceWindows
 * @return string The with converted line endings.
 */
    public static function prepare($data, $forceWindows = false) {
        $lineBreak = "\n";
        if (DIRECTORY_SEPARATOR === '\\' || $forceWindows === true) {
            $lineBreak = "\r\n";
        }
        return strtr($data, ["\r\n" => $lineBreak, "\n" => $lineBreak, "\r" => $lineBreak]);
    }

/**
 * Write given data to this file.
 *
 * @param string $data Data to write to this File.
 * @param string $mode Mode of writing. {@link http://php.net/fwrite See fwrite()}.
 * @param string $force Force the file to open
 * @return boolean Success
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
 */
    public function append($data, $force = false) {
        return $this->write($data, 'a', $force);
    }

/**
 * Closes the current file if it is opened.
 *
 * @return bool True if closing was successful or file was already closed, otherwise false
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
 * @param Closure $callable On deletion
 * @return boolean Success
 */
    public function delete(Closure $callable = null) {
        if ($this->_readOnly === true || $this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->delete($callable);
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
        if ($this->_info === []) {
            $path = $this->getAbsolutePath();
            $this->_info = $path ? pathinfo($path) : [];

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

        return $this->_info[$info] ?? null;
    }

/**
 * Rename file.
 *
 * @param string $name New file name.
 * @return $this|bool Success
 */
    public function rename($name) {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->rename($name);
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
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_hash[$algo] = $this->_dataSource->hash($algo, $maxsize, $rawOutput);
    }

/**
 * Returns true if the file exists.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function exists() {
        if ($this->_freeze === true) {
            return $this->_exists;
        }
        return $this->_dataSource->exists();
    }

/**
 * Returns the "chmod" (permissions) of the file.
 *
 * @return string|false Permissions for the file, or false in case of an error
 */
    public function perms() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->perms();
    }

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size(): int {
        if ($this->_size === null && $this->_freeze === false) {
            $this->_size = $this->_dataSource->size();
        }
        return $this->_size;
    }

/**
 * Returns true if the file is writable.
 *
 * @return boolean True if it's writable, false otherwise
 */
    public function isWritable() {
        return $this->_readOnly !== true
            && $this->_freeze !== true
            && $this->_dataSource->isWritable();
    }

/**
 * Returns true if the File is executable.
 *
 * @return boolean True if it's executable, false otherwise
 */
    public function isExecutable() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->isExecutable();
    }

/**
 * Returns true if the file is readable.
 *
 * @return boolean True if file is readable, false otherwise
 */
    public function isReadable() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->isReadable();
    }

/**
 * Returns the file's owner.
 *
 * @return integer|false The file owner, or false in case of an error
 */
    public function owner() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->owner();
    }

/**
 * Returns the file's group.
 *
 * @return integer|false The file group, or false in case of an error
 */
    public function group() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->group();
    }

/**
 * Returns last access time.
 *
 * @return Time|false Timestamp of last access time, or false in case of an error
 */
    public function lastAccess() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->lastAccess();
    }

/**
 * Returns last modified time.
 *
 * @return Time|false Timestamp of last modification, or false in case of an error
 */
    public function lastChange() {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->lastChange();
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
    public function touch($time = null, $accessTime = null) {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->touch($time, $accessTime);
    }

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param boolean $overwrite Overwrite $dest if exists
 * @return \Nata\FilesystemManager\File|bool File copy instance on success, false on error
 */
    public function copy($dest, $overwrite = true) {
        if ($this->_freeze === true) {
            return false;
        }
        return $this->_dataSource->copy($dest, $overwrite);
    }

/**
 * Check whether or not a file is a certain type.
 *
 * @param string $type Type to check
 * @return boolean True if it is, false otherwise
 */
    public function is($type): bool {
        $mime = $this->mime();
        if ($mime && Mimetype::is($type, $mime)) {
            return true;
        }

        if (str_contains($type, '/*')) {
            $type = str_replace('/*', '', $type);
        }
        [$info['_mimetoplevel'], $info['_mimepart']] = Text::explode('/', $mime, 2);
        if (in_array($type, $info, true)) {
            return true;
        }

        if (strtolower($type) === strtolower(App::classShortName($this->_dataSource))) {
            return true;
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
        return $this->_dataSource->clearStatCache($all);
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

        return $replaced;
    }

/**
 * Get/Set metadata.
 * If given key, will return respective value.
 *
 * This data is not persistent and will be lost when the object is destroyed.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed Metadata
 */
    public function metadata($key = null, $value = null, bool $merge = false) {
        if ($key === null) {
            return $this->_metadata;
        }

        if (is_string($key) && func_num_args() === 1) {
            return Hash::get($this->_metadata, $key);
        }

        if (func_num_args() === 2 && $value === null) {
            unset($this->_metadata[$key]);
            return $this;
        }

        if (is_array($key)) {
            $this->_metadata = $key + $this->_metadata;
            return $this;
        }

        $this->_metadata = Hash::insert($this->_metadata, $key, $value);

        return $this;
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
 * Get/set URL.
 *
 * @param string|null $url URL
 * @return string|self URL
 */
    public function url(?string $url = null): ?string {
        if ($url === null) {
            if ($this->_url === null && $this->_freeze === false) {
                $this->_url = $this->_dataSource->getUrl();
            }
            return $this->_url;
        }

        $this->_url = $url;

        return $this;
    }

/**
 * Returns the file:// protocol URL for this file.
 *
 * @return string File URL with file:// protocol
 */
    public function getFileUrl(): string {
        return 'file://' . str_replace('\\', '/', $this->getAbsoluteLocalPath());
    }

/**
 * Get storage URI.
 *
 * @return string|null Storage URI
 */
    public function getStorageUri(): string|null {
        return $this->metadata('storageuri');
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
    public function __toString(): string {
        return $this->_dataSource;
    }

/**
 * __clone
 *
 * @todo This needs alot of testing to prevent interference with open streams of other instances.
 * @return File
 */
    public function ___clone() {
        $file = new $this;
        $file->_dataSource = DataSourceFactory::build($this->getDataUri());
        $file->_handle = null;
        return $file;
    }

/**
 * __debugInfo
 *
 * @return array
 */
    public function ___debugInfo() {
        return [
            'datasource' => $this->_dataSource ? get_class($this->_dataSource) : null,
            'path' => $this->getAbsolutePath(),
            'url' => $this->url(),
            'name' => $this->name(),
            'realname' => $this->realname(),
            'basename' => $this->basename(),
            'extension' => $this->extension(),
            'mime' => $this->mime(),
            'size' => $this->size(),
            'perms' => $this->perms(),
            'owner' => $this->owner(),
            'group' => $this->group(),
            'lastAccess' => $this->lastAccess(),
            'lastChange' => $this->lastChange(),
            'isWritable' => $this->isWritable(),
            'isExecutable' => $this->isExecutable(),
            'isReadable' => $this->isReadable(),
            'metadata' => $this->metadata(),
            'hash' => $this->_hash,
        ];
    }

/**
 * Closes the current file if it's open.
 *
 * @return void
 */
    public function __destruct() {
        $this->close();
    }

}
