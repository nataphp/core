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

namespace Nata\FilesystemManager\File\DataSource;

use Closure;
use Nata\Core\App;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\DataSourceInterface;
use Nata\FilesystemManager\FileFactory;
use Nata\FilesystemManager\Folder;
use Nata\FilesystemManager\GarbageCollector;
use Nata\FilesystemManager\Mimetype;
use Nata\I18n\Time;

/**
 * Memory-based file data source implementation
 */
class Memory implements DataSourceInterface {

/**
 * File handle.
 *
 * @var resource|null
 */
    protected $_handle;

/**
 * File name.
 *
 * @var string|null
 */
    protected $_name;

/**
 * Absolute local path.
 *
 * @var string|null
 */
    protected $_absoluteLocalPath;

/**
 * File extension.
 *
 * @var string|null
 */
    protected $_extension;

/**
 * File size in bytes.
 *
 * @var int|null
 */
    protected $_size;

/**
 * File mime type.
 *
 * @var string|null
 */
    protected $_mime;

/**
 * File hashes.
 *
 * @var array
 */
    protected $_hash = [];

/**
 * Constructor.
 *
 * @param string|resource $contents File contents or handle
 * @param array $options Options
 */
    public function __construct(mixed $contents, array $options = []) {
        $this->_createHandle($contents);
    }

/**
 * File contents to create the handle/resource.
 *
 * @param mixed $contents Contents
 * @return false|resource
 */
    protected function _createHandle(mixed $contents): mixed {
        if (empty($contents)) {
            return null;
        }

        if (is_resource($contents)) {
            return $contents;
        }

        $this->_handle = fopen('php://memory', 'w+');
        fwrite($this->_handle, $contents);
        rewind($this->_handle);

        $this->_size = strlen($contents);
        $this->_hash = [
            'sha1' => sha1($contents),
            'md5' => md5($contents)
        ];
        $this->_mime = Mimetype::detect($contents);
        $this->_extension = Mimetype::getExtension($this->_mime);
        $contents = null;

        return $this->_handle;
    }

/**
 * Returns full path to a temporary local copy of the virtual file.
 *
 * @return string Path to local copy of virtual file
 */
    public function getAbsolutePath(): string {
        return 'php://memory';
    }

/**
 * Returns full path to a temporary local copy of the virtual file.
 *
 * @return string Path to local copy of virtual file
 */
    public function getAbsoluteLocalPath(): string {
        if ($this->_absoluteLocalPath === null) {
            // Create a local copy of this file in memory
            $sha1 = $this->hash('sha1', true);
            $dirname = TMP . 'cache' . DS;
            $prefix = 'nata_tmp_file_';
            $this->_absoluteLocalPath = tempnam($dirname, $prefix);
            if ($sha1) {
                $this->_absoluteLocalPath = $dirname . $prefix . $sha1;
            }
            if (!file_exists($this->_absoluteLocalPath)) {
                touch($this->_absoluteLocalPath);
                $handle = fopen($this->_absoluteLocalPath, 'w+');
                fwrite($handle, $this->_read());
                rewind($handle);
                fclose($handle);
                $handle = null;
            }
        }
        return $this->_absoluteLocalPath;
    }

/**
 * Return the contents of this file as a string.
 *
 * @param string $bytes where to start
 * @param string $mode A `fread` compatible mode.
 * @param boolean $force If true then the file will be re-opened even if its already opened, otherwise it won't
 * @return mixed string on success, false on failure
 */
    private function _read($bytes = false) {
        if (!$this->open()) {
            return false;
        }

        if (is_int($bytes)) {
            return fread($this->_handle, $bytes);
        }

        $data = '';
        $chunkSize = $this->_getOptimalChunkSize();
        while (!feof($this->_handle)) {
            $data .= fgets($this->_handle, $chunkSize);
        }

        rewind($this->_handle);

        return $data;
    }

/**
 * Determine the optimal chunk size based on the file size.
 *
 * @param int $fileSize The size of the file in bytes.
 * @return int The optimal chunk size in bytes.
 */
    private function _getOptimalChunkSize(): int {
        $size = $this->_size;
        if ($size < (1 * 1024 * 1024)) { // Less than 1 MB
            return 4096;
        } elseif ($size < (100 * 1024 * 1024)) { // 1 MB to 100 MB
            return 16384;
        }
        // Greater than 100 MB
        return 65536; // 64 KB
    }

/**
 * Creates the file - no-op for memory files.
 *
 * @return null Always null
 */
    public function create(): ?bool {
        return null;
    }

/**
 * Delete the file - just closes handle for memory files.
 */
    public function delete(?Closure $callable = null): bool {
        $this->close();
        return true;
    }

/**
 * Get file name.
 *
 * @return string File name
 */
    public function name(): ?string {
        return $this->_name ?? '';
    }

/**
 * Get real name.
 *
 * @return string File real name
 */
    public function realname(): ?string {
        return $this->_name ?? '';
    }

/**
 * Rename file - just updates name for memory files.
 */
    public function rename(string $name): bool {
        $this->_name = $name;
        return true;
    }

/**
 * Get extension.
 *
 * @param bool $fromMime Whether to get extension from mime type
 * @return string Extension
 */
    public function extension(bool $fromMime = false): ?string {
        if ($this->_extension === null) {
            $this->_extension = Mimetype::getExtension($this->mime());
        }
        return $this->_extension;
    }

/**
 * Force extension based on mime type.
 */
    public function forceExtension(): self {
        return $this;
    }

/**
 * Check if file exists.
 */
    public function exists(): bool {
        return is_resource($this->_handle);
    }

/**
 * Check if writable.
 */
    public function isWritable(): bool {
        return true;
    }

/**
 * Check if executable.
 */
    public function isExecutable(): bool {
        return false;
    }

/**
 * Check if readable.
 */
    public function isReadable(): bool {
        return true;
    }

/**
 * Get owner - always null for memory files.
 */
    public function owner(): ?int {
        return null;
    }

/**
 * Get group - always null for memory files.
 */
    public function group(): ?int {
        return null;
    }

/**
 * Get last access time.
 */
    public function lastAccess(): ?Time {
        return new Time();
    }

/**
 * Get last modified time.
 *
 * @return \Nata\I18n\Time
 */
    public function lastChange(): ?Time {
        return new Time();
    }

/**
 * Set modification time - no-op for memory files.
 *
 * @param int|null $time Access time
 * @param int|null $accessTime Modification time
 * @return boolean Always true for memory files
 */
    public function touch(?int $time = null, ?int $accessTime = null): bool {
        return true;
    }

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param boolean $overwrite Overwrite $dest if exists
 * @return File|bool File copy instance on success, false on error
 */
    public function copy(string $dest, bool $overwrite = true): ?File {
        $copy = FileFactory::build($dest, [
            'mime' => $this->mime(),
            'datasource' => 'Local',
        ]);

        if ($copy->exists() && $overwrite === false) {
            return $copy;
        }

        $copy->create();

        if (!$copy->exists()) {
            return null;
        }

        $copy->write($this->_read());

        return $copy;
    }

/**
 * Get mime type.
 *
 * @return string|null Mime type
 */
    public function mime(): ?string {
        if ($this->_mime === null) {
            $this->_mime = Mimetype::detect($this->_read());
        }

        if ($this->_mime === null && $ext = $this->extension()) {
            [$mime] = Mimetype::get($ext);
            return $mime;
        }

        return $this->_mime ?? 'application/octet-stream';
    }

/**
 * Clear stat cache - no-op for memory files.
 */
    public function clearStatCache(bool $all = false): void {
    }

/**
 * Get folder - memory files don't have folders.
 */
    public function getFolder(): ?Folder {
        return null;
    }

/**
 * Get URL.
 *
 * @return null Always null
 */
    public function getUrl(): ?string {
        return null;
    }

/**
 * Opens the file.
 *
 * @param string $mode The mode to open the file in
 * @return resource|false Handle if the file was opened successfully or false on failure
 */
    public function open(string $mode = 'rb'): mixed {
        return is_resource($this->_handle) ? $this->_handle : false;
    }

/**
 * Closes the file handle.
 *
 * @return bool True if the handle was closed successfully
 */
    public function close(): bool {
        if (is_resource($this->_handle)) {
            fclose($this->_handle);
            $this->_handle = null;
        }
        return true;
    }

/**
 * Get checksum of file with previous check of file size.
 *
 * @param string $algo Name of selected hashing algorithm
 * (i.e. "md5", "sha256", "haval160,4", etc..)
 * @param integer|boolean $maxsize in MB or true to force
 * @param bool $rawOutput When set to true, outputs raw binary data.
 * False outputs lowercase hexits.
 * @return string|null Algorithom Checksum
 */
    public function hash(string $algo = 'sha1', int|bool $maxsize = 20, bool $rawOutput = false): ?string {
        if (isset($this->_hash[$algo])) {
            return $this->_hash[$algo];
        }

        if (!$this->exists()) {
            return null;
        }

        if ($maxsize === true) {
            return $this->_hash[$algo] = hash($algo, $this->_read(), $rawOutput);
        }

        $size = $this->size();
        if ($size && $size < ($maxsize * 1024) * 1024) {
            return $this->_hash[$algo] = hash($algo, $this->_read(), $rawOutput);
        }

        return false;
    }

/**
 * Get file size.
 *
 * @return int|null File size in bytes
 */
    public function size(): int {
        return $this->_size;
    }

/**
 * Returns the file's permissions as a numeric mode.
 *
 * @return null
 */
    public function perms(): ?string {
        return null;
    }

/**
 * This is not valid for memory files.
 *
 * @return null
 */
    public function getFileUrl(): ?string {
        return null;
    }

/**
 * __destruct.
 *
 * @return void
 */
    public function __destruct() {
        GarbageCollector::run(TMP . 'cache' . DS, [
            'probability' => 50,
            'lifetime' => '12 hours',
            'pattern' => 'nata_tmp_file_(.*)'
        ]);
    }

/**
 * Returns the file data source as a string.
 *
 * @return string The file data source
 */
    public function __toString(): string {
        return $this->_read();
    }

}
