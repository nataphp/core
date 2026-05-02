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

namespace Nata\Ftp\Client;

use BadMethodCallException;
use Nata\Core\App;
use Nata\Filesystem\Mimetype;
use Nata\I18n\Time;
use Nata\Utility\Text;

/**
 * Convenience class for reading, writing, renaming and appending to remote FTP files.
 */
class File {

/**
 * File name.
 *
 * @var string
 */
    protected $_name;

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
 */
    protected $_handle;

/**
 * Enable locking for file reading and writing.
 *
 * @var boolean
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
 * File exists on FTP server.
 *
 * @var boolean
 */
    protected $_exists = true;

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
    protected $_hash;

/**
 * FTP connection.
 *
 * @var Resource
 */
    protected $_connection;



/**
 * Constructor.
 *
 * @param string $url File URL
 * @param Resource $connection FTP connection
 * @return void
 */
    public function __construct($path, $connection) {
        $this->_path = dirname($path);
        $this->_basename = basename($path);
        $this->_connection = $connection;
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
            $this->_info = [
                'filename' => $this->name(),
                'basename' => $this->basename(),
                'extension' => $this->extension(),
                'mime' => $this->mime(),
                'filesize' => $this->size()
            ];
        }

        if ($info === null) {
            return $this->_info;
        }

        return $this->_info[$info];
    }

/**
 * Returns full path to a temporary local copy of the remote file.
 *
 * @return string Path to local copy of remote file
 */
    public function pwd() {
        $path = $this->_path;
        if ($path === '.') {
            $path = '';
        }
        return $path . $this->_basename;
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): string {
        if ($this->_name === null) {
            $basename = $this->basename();
            if ($basename) {
                $this->_name = pathinfo($basename, PATHINFO_FILENAME);
            }
        }
        return $this->_name;
    }

/**
 * Returns the file name with extension.
 *
 * @return string The file name with extension.
 */
    public function basename(): string {
        return $this->_basename;
    }

/**
 * Rename file.
 *
 * @param string $name New file name.
 * @return $this|bool Success
 */
    public function rename($name) {
        $basename = basename($name);

        $renamed = ftp_rename($this->_connection, $this->pwd(), $basename);
        if (!$renamed) {
            return false;
        }

        $this->_name = null;
        $this->_basename = $basename;
        $this->_info = null;

        return $this;
    }

/**
 * Returns the current file's extension.
 * If $dotPrepend set to 'true', it will return extension prepended with dot.
 *
 * @param bool $dotPrepend Prepend dot to extension
 * @return string The file extension
 */
    public function extension($dotPrepend = false): string {
        if ($this->_extension === null) {
            if ($basename = $this->basename()) {
                $this->_extension = pathinfo($basename, PATHINFO_EXTENSION);
            }
        }

        $extension = $this->_extension;

        if ($extension && $dotPrepend) {
            $extension = '.' . $extension;
        }

        return $extension;
    }

/**
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime() {
        $mime = null;
        if ($this->_exists === true) {
            [$mime] = Mimetype::get($this->extension());
        }
        return $mime;
    }

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size() {
        $size = null;
        if ($this->_exists === true) {
            $size = ftp_size($this->_connection, $this->pwd());
        }
        return $size;
    }

/**
 * Returns last modified time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastChange() {
        $lastChange = null;
        if ($this->_exists === true) {
            $lastChange = ftp_mdtm($this->_connection, $this->pwd());
            if ($lastChange) {
                $lastChange = new Time($lastChange);
            }
        }
        return $lastChange;
    }

/**
 * Download FTP file contents.
 *
 * @param int $mode FTP_ASCII or FTP_BINARY.
 * @param int $offset The position in the remote file to start downloading from.
 * @return boolean True on success, false on failure
 */
    public function open($mode = FTP_BINARY, $offset = 0) {
        if (is_resource($this->_handle)) {
            return true;
        }

        $tmpfilepath = TMP . 'cache' . DS . 'ftp-' . Text::uuid();
        if (!ftp_get($this->_connection, $tmpfilepath, $this->pwd(), $mode, $offset)) {
            return false;
        }

        $data = file_get_contents($tmpfilepath);

        unlink($tmpfilepath);

        // File's content real size
        $this->_size = strlen($data);
        $this->_sha1 = sha1($data);

        $this->_handle = fopen('php://memory', 'r+');
        fwrite($this->_handle, $data, $this->_size);
        rewind($this->_handle);

        $data = null;
        unset($data);

        return is_resource($this->_handle);
    }

/**
 * Read file contents.
 *
 * @param int $mode FTP_ASCII or FTP_BINARY.
 * @param int $offset The position in the remote file to start downloading from.
 * @return mixed string on success, false on failure
 */
    public function read($mode = FTP_BINARY, $offset = 0) {
        if ($this->open($mode, $offset) === false) {
            return false;
        }

        $data = '';
        while (!feof($this->_handle)) {
            $data .= fgets($this->_handle, 4096);
        }

        return $data;
    }

/**
 * @todo Write into FTP file and upload.
 *
 * @param string $data For compatibility with parent method.
 * @param string $mode For compatibility with parent method.
 * @param string $force For compatibility with parent method.
 * @throws \BadMethodCallException Write to remote file is (ofcourse) not possible
 */
    public function write($data, $mode = 'w', $force = false) {
        throw new BadMethodCallException(sprintf('Remote file cannot be written.'));
    }

/**
 * Sets or gets the offset for the currently opened file.
 *
 * @param integer|boolean $offset The $offset in bytes to seek. If set to false then the current offset is returned.
 * @param integer $seek PHP Constant SEEK_SET | SEEK_CUR | SEEK_END determining what the $offset is relative to
 * @return mixed True on success, false on failure (set mode), false on failure or integer offset on success (get mode)
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
 * Closes the current file if it is opened.
 *
 * @return boolean True if closing was successful or file was already closed, otherwise false
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
 * @return boolean Success
 */
    public function delete() {
        $deleted = ftp_delete($this->_connection, $this->pwd());
        if ($deleted === true) {
            $this->_exists = false;
        }
        return $deleted;
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
 * Returns true if the file is writable.
 *
 * @return boolean True if it's writable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::writable
 */
    public function isWritable() {
        return false;
    }

/**
 * Returns true if the File is executable.
 *
 * @return boolean True if it's executable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::executable
 */
    public function isExecutable() {
        return false;
    }

/**
 * Returns true if the file is readable.
 *
 * @return boolean True if file is readable, false otherwise
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::readable
 */
    public function isReadable() {
        return $this->_exists();
    }

/**
 * Returns the file's owner.
 *
 * @return integer|false The file owner, or false in case of an error
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/file-folder.html#File::owner
 */
    public function owner() {
        if ($this->_exists()) {
            return fileowner($this->pwd());
        }
        return false;
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
        if ($maxsize === true) {
            return hash($algo, $this->read(), $rawOutput);
        }

        $size = $this->size();

        if ($size && $size < ($maxsize * 1024) * 1024) {
            return hash($algo, $this->read(), $rawOutput);
        }

        return false;
    }

/**
 * Check if file exists.
 *
 * @return bool True if exists, false otherwise
 */
    public function exists() {
        return $this->_exists;
    }

/**
 * Closes the current file if it's open.
 */
    public function __destruct() {
        $this->close();
    }

}
