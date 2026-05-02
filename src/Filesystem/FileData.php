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

namespace Nata\Filesystem;

use InvalidArgumentException;
use Nata\Filesystem\File;
use Nata\Core\App;

/**
 * File resource as data URI.
 *
 * This is a temporary implementation, this will become \File\Resource\DataUri.
 */
class FileData extends File {

/**
 * SHA1 hash to help manage the file in cache.
 *
 * @var string
 */
    protected $_sha1;

/**
 * File path to temporary file on local server.
 *
 * @var string
 */
    protected $_pwd;


/**
 * Constructor.
 *
 * @param resource $stream IMAP stream
 * @param object $part MIME message part
 * @return void
 */
    public function __construct($data, array $options = []) {
        $options += [
            'name' => null,
            'mime' => null
        ];

        $this->_name = $options['name'];
        $this->_basename = $this->_name;
        $this->_mime = $options['mime'];

        $this->_createHandle($data);

        register_shutdown_function([$this, 'delete']);
    }

/**
 * Create file.
 *
 * @param resource $stream IMAP stream
 * @param object $part MIME message part
 * @return void
 */
    protected function _createHandle($data) {
        $data = $this->_decodeData($data);

        $this->_size = strlen($data);
        $this->_sha1 = sha1($data);
        $this->_hash['sha1'] = $this->_sha1;
        if (!$this->_name) {
            $this->_name = $this->_sha1 . $this->extension(true);
        }

        file_put_contents($this->pwd(), $data);

        $data = null;
        unset($data);
    }

/**
 * Decode file data.
 *
 * @param string $data Data
 * @return mixed
 */
    protected function _decodeData($data) {
        if (strpos($data, 'data:') !== 0) {
            return $data;
        }

        $data = str_replace('data:', '', $data);

        [$this->_mime, $data] = explode(';', $data);
        [$encoding, $data] = explode(',', $data);

        $this->_extension = Mimetype::getExtension($this->_mime);

        switch ($encoding) {
            case 'base64':
                $data = base64_decode($data);
                break;
            default:
                throw new InvalidArgumentException(sprintf('File "%s" decoding is not currently supported.', $encoding));
        }

        return $data;
    }

/**
 * Returns full path to a temporary file.
 *
 * @return string Path to local copy of the file.
 */
    public function pwd() {
        if ($this->_pwd === null) {
            $this->_pwd = TMP . 'cache' . DS . $this->_sha1 . '.tmp';
        }
        return $this->_pwd;
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
                'filename' => parent::basename(),
                'extension' => parent::extension(),
                'filesize' => parent::size(),
                'mime' => parent::mime()
            ];
        }

        if ($info === null) {
            return $this->_info;
        }

        return $this->_info[$info];
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

}
