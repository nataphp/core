<?php
/**
 * NataPHP Framework.
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

namespace Nata\Filesystem;

use Nata\I18n\Time;
use Nata\Http\Client;
use Nata\Filesystem\File;
use Nata\Utility\Validation;
use InvalidArgumentException;
use BadMethodCallException;

/**
 * Convenience class for reading remote files information.
 */
class RemoteFile extends File {

/**
 * File cache config.
 * If set 'true', uses config 'default'.
 *
 * @var string
 */
    protected $_cache;

/**
 * File URL.
 *
 * @var string
 */
    protected $_url;

/**
 * Current file's size (in bytes).
 *
 * @var int
 */
    protected $_size;

/**
 * File path to temporary file on local server.
 *
 * @var string
 */
    protected $_pwd;

/**
 * Read only.
 *
 * @var bool
 */
    protected $_readOnly = true;

/**
 * Last access.
 *
 * @var \Nata\I18n\Time
 */
    protected $_lastAccess;

/**
 * Last modified date.
 *
 * @var \Nata\I18n\Time
 */
    protected $_lastChange;

/**
 * HTTP response.
 *
 * @var \Nata\Http\Client\Response
 */
    private $_response;


/**
 * Constructor.
 *
 * @param string $url File URL
 * @param array $options Options
 * @return void
 */
    public function __construct($url, $options = []) {
        $options += [
            'cache' => null
        ];

        if (!Validation::url($url)) {
            throw new InvalidArgumentException(sprintf('Invalid URL given "%s"', $this->_url));
        }

        $this->_url = $url;
        $this->_cache = $options['cache'];
    }

/**
 * Get/Set cache config to be used when making the request.
 *
 * @return string|bool $cache Cache config
 * @return $this|bool|string
 */
    public function cache($cache = null) {
        if ($cache === null) {
            return $this->_cache;
        }

        $this->_cache = $cache;

        return $this;
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
        if ($this->_pwd === null && $this->exists()) {
            $tmppath = sys_get_temp_dir();
            $tmppath .= DS . $this->sha1() . '.nrfile';

            if (file_put_contents($tmppath, $this->read())) {
                $this->_pwd = $tmppath;
            }

        }
        return $this->_pwd;
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name() {
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
    public function basename() {
        if ($this->_basename === null) {
            if ($this->exists()) {
                $response = $this->getResponse();

                $contentDisposition = $response->getHeader('Content-Disposition');
                if (preg_match("/filename=\"(.*)\"/", $contentDisposition, $match) > 0) {
                    return $match[1];
                }

                $this->_basename = pathinfo($this->_url, PATHINFO_BASENAME);
            }

        }
        return $this->_basename;
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
        if ($this->_mime === null) {
            $this->_mime = false;
            if ($this->exists()) {
                $response = $this->getResponse();
                $contentType = $response->getHeader('Content-Type');
                list($this->_mime) = explode(';', $contentType);
            }
        }
        return $this->_mime;
    }

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size() {
        if ($this->_size === null) {
            $this->_size = false;
            if ($this->exists()) {
                $response = $this->getResponse();
                $this->_size = (int)$response->getHeader('Content-Length');
            }
        }
        return $this->_size;
    }

/**
 * Returns last access time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastAccess() {
        if ($this->_lastAccess === null) {
            if ($this->exists()) {
                $response = $this->getResponse();
                $this->_lastAccess = new Time($response->getHeader('Date'));

                if ($this->_lastAccess) {
                    $this->_lastAccess = new Time($this->_lastAccess);
                }

            }
        }
        return $this->_lastAccess;
    }

/**
 * Returns last modified time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastChange() {
        if ($this->_lastChange === null) {
            if ($this->exists()) {
                $response = $this->getResponse();
                $this->_lastChange = $response->getHeader('Last-Modified');

                if ($this->_lastChange) {
                    $this->_lastChange = new Time($this->_lastChange);
                }

            }
        }
        return $this->_lastChange;
    }

/**
 * Creates an file resource/handle with the response body.
 *
 * @param string $mode Read allowed only (this is for declaration compatibility with \Nata\Filesystem\File::open())
 * @param boolean $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return boolean True on success, false on failure
 */
    public function open($mode = 'r', $force = false) {
        if (!$force && is_resource($this->_handle)) {
            return true;
        }

        if ($this->exists() === false) {
            return false;
        }

        $response = $this->getResponse();

        $this->_handle = fopen('php://memory', 'r+');
        fwrite($this->_handle, $response->getBody());
        rewind($this->_handle);

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
 */
    public function read($bytes = false, $mode = 'rb', $force = false) {
        if ($this->open($mode, $force) === false) {
            return false;
        }

        if (is_int($bytes)) {
            return fread($this->_handle, $bytes);
        }

        $data = '';

        while (!feof($this->_handle)) {
            $data .= fgets($this->_handle, 4096);
        }

        if ($bytes === false) {
            $this->close();
        }

        return trim($data);
    }

/**
 * Write to a remote file is (ofcourse) not possible.
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
        return $this->exists();
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
        $response = $this->getResponse();
        return $response && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

/**
 * Get HTTP client to check/download file.
 *
 * @return \Nata\Http\Client\Response
 */
    public function getResponse() {
        if ($this->_response === null) {
            $this->_response = $this->_getHttpClient()->send();
        }
        return $this->_response;
    }

/**
 * Get HTTP client to check/download file.
 *
 * @return \Nata\Http\Client\Request
 */
    protected function _getHttpClient() {
        return Client::get($this->_url)
            ->cache($this->_cache)
            ->options(CURLOPT_SSL_VERIFYPEER, false);
    }

/**
 * Closes the current file if it's open.
 */
    public function __destruct() {
        if ($this->_pwd) {
            @unlink($this->_pwd);
        }
        $this->close();
    }

/**
 * Get file URL.
 *
 * @return string File URL
 */
    public function getUrl() {
        return $this->_url;
    }

}
