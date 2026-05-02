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

namespace Nata\Email\Client;

use BadMethodCallException;
use Nata\Core\App;
use Nata\Filesystem\File;
use Nata\Utility\Text;

/**
 * Email attachment file.
 */
class Attachment extends File {

/**
 * IMAP stream.
 *
 * @var \IMAP\Connection
 */
    private $_imapStream;

/**
 * MIME message part.
 *
 * @var object
 */
    private $_part;

/**
 * Disposition.
 *
 * @var string
 */
    private $_disposition;

/**
 * Read only.
 *
 * @var bool
 */
    protected $_readOnly = true;

/**
 * File path to temporary file on local server.
 *
 * @var string
 */
    protected $_pwd;

/**
 * File path to temporary file on local server.
 *
 * @var string
 */
    protected $_isCached;

/**
 * Attachment (C)ID.
 *
 * @var string
 */
    protected $_id;

/**
 * SHA1 hash to help manage the file in cache.
 *
 * @var string
 */
    protected $_sha1;


/**
 * Constructor.
 *
 * @param resource $stream IMAP stream
 * @param object $part MIME message part
 * @return void
 */
    public function __construct($imapStream, object $part) {
        $this->_imapStream = $imapStream;
        $this->_part = $part;
        $this->_name = Text::toUtf8($part->filename);

        if (strpos($part->id, '=?') === 0) {
            $part->id = iconv_mime_decode($part->id, 0, "UTF-8");
        }

        $this->_id = $part->id;
        $this->_disposition = $part->disposition;
        $this->_extension = $part->extension;
    }

/**
 * Get the respective attachment ID.
 *
 * @return string
 */
    public function getId(): ?string {
        return $this->_id;
    }

/**
 * Get the attachment disposition.
 *
 * @return string
 */
    public function getDisposition(): ?string {
        return $this->_disposition;
    }

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): ?string {
        $name = $this->basename();
        if ($ext = $this->extension(true)) {
            return basename($name, $ext);
        } elseif ($name) {
            return $name;
        }
        return null;
    }

/**
 * Get the basename.
 *
 * @return string
 */
    public function basename(): ?string {
        if (empty($this->_name) && $this->mime() === 'message/rfc822') {
            $content = $this->read();
            $header = MimeMessageParser::parseHeader($content);
            $this->_name = $header['Subject'] ?? __dx('__nata_core__', 'email', 'Message');
        }

        if (str_contains($this->_name, '=?')) {
            $this->_name = iconv_mime_decode($this->_name, 0, "UTF-8");
        }

        if ($this->extension() && str_contains($this->_name, '.') === false) {
            $this->_name .= $this->extension(true);
        }

        return trim($this->_name);
    }

/**
 * Returns full path to a temporary local copy of the attached file.
 *
 * @return string Path to local copy of the attached file.
 */
    public function getAbsolutePath() {
        if ($this->_pwd === null) {
            $this->_pwd = TMP . 'cache' . DS . $this->_part->uniqid . '.mailattach';
        }
        return $this->_pwd;
    }

/**
 * Returns full path to a temporary local copy of the attached file.
 *
 * @return string Path to local copy of the attached file.
 */
    public function getAbsoluteLocalPath() {
        return $this->getAbsolutePath();
    }

/**
 * Returns full path to a temporary local copy of the attached file.
 *
 * @return string Path to local copy of the attached file.
 */
    public function pwd() {
        return $this->getAbsolutePath();
    }

/**
 * Returns temporary filename for the email attachment.
 *
 * @return string Local temporary filename.
 */
    public function tmpBasename() {
        return $this->_part->uniqid . '.mailattach';
    }

/**
 * Keep local copy of the attachment.
 *
 * @return $this
 */
    public function keep() {
        if (!$this->_isCached()) {
            if (file_put_contents($this->pwd(), $this->read())) {
                $this->_isCached = true;
            }
        }
        return $this;
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

        if ($this->_isCached() === true) {
            $this->touch();
            return parent::open($mode, $force);
        }

        $part = $this->_part;
        if (!$data = imap_fetchbody($this->_imapStream, $part->uid, $part->section, FT_UID)) {
            return false;
        }

        $data = MimeMessageParser::decode($data, $part->encoding);
        // File's content real size
        $this->_size = strlen($data);
        $this->_sha1 = sha1($data);

        $this->_handle = fopen('php://memory', 'r+');
        fwrite($this->_handle, $data, $this->_size);
        rewind($this->_handle);

        $data = null;
        unset($data);

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

        rewind($this->_handle);

        return $data;
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
        throw new BadMethodCallException('Attachment file cannot be written.');
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

        $this->open();
        $this->mime();

        if (file_put_contents($dest, $this->read())) {
            return new File($dest);
        }

        return false;
    }

/**
 * Returns the file size.
 *
 * @param bool $encodedSize True to get the attachment encoded size,
 *  instead of file contents decoded
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size($encodedSize = false) {
        if ($encodedSize === true) {
            return $this->_part->size;
        }

        if ($this->_size === null) {
            if ($this->_isCached()) {
                $this->_size = filesize($this->pwd());
            } else {
                $this->open();
            }
        }

        return $this->_size;
    }

/**
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime() {
        if ($this->_mime === null) {
            if ($this->_isCached()) {
                $this->_mime = parent::mime();
            } else {
                return $this->_part->mime;
            }
        }
        return $this->_mime;
    }

/**
 * Get sha1 checksum of file with previous check of file size.
 *
 * @param integer|boolean $maxsize in MB or true to force
 * @return string|false sha1 Checksum {@link http://php.net/sha1_file See sha1_file()}, or false in case of an error
 */
    public function sha1($maxsize = 5) {
        if ($this->_sha1 === null) {
            $this->_sha1 = $this->hash('sha1', $maxsize);
        }
        return $this->_sha1;
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
        $target = $this->pwd();
        $func = 'hash_file';
        if (!$this->_isCached()) {
            $target = $this->read();
            $func = 'hash';
        }

        if ($maxsize === true) {
            return $func($algo, $target, $rawOutput);
        }

        $size = $this->size();
        if ($size && $size < ($maxsize * 1024) * 1024) {
            return $func($algo, $target, $rawOutput);
        }

        return false;
    }


/**
 * Always returns, since the file exists on the remote server, we just need to get it.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function exists() {
        return true;
    }

/**
 * Always returns, since the file exists on the remote server, we just need to get it.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function isReadable() {
        return true;
    }

/**
 * Always returns, since the file exists on the remote server, we just need to get it.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function isWritable() {
        return false;
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
            return true;
        }
        return $this;
    }

/**
 * Check if there's a local/cached version of the file.
 *
 * @return boolean True if it exists, false otherwise
 */
    protected function _isCached() {
        if ($this->_isCached === null) {
            $this->_isCached = file_exists($this->pwd());
        }
        return $this->_isCached;
    }

/**
 * __destruct.
 *
 * @return void
 */
    public function __destruct() {
        if (is_resource($this->_handle)) {
            fclose($this->_handle);
        }
    }

}
