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

namespace Nata\FilesystemManager\File;

use Closure;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\Folder;
use Nata\I18n\Time;

/**
 * Base class for file data source.
 */
interface DataSourceInterface {

/**
 * Returns the absolute path of the file.
 *
 * @return string Absolute path to the file localy.
 */
    public function getAbsolutePath(): string;

/**
 * Returns the absolute local path of the file.
 * This is the path to the file that should be on the local filesystem.
 *
 * @return string Absolute path to localy the file.
 */
    public function getAbsoluteLocalPath(): string;

/**
 * Open the file.
 *
 * @param string $mode Mode of file operation
 * @return resource|false File handle or false on failure
 */
    public function open(string $mode = 'r'): mixed;

/**
 * Creates the file.
 *
 * @return boolean Success
 */
    public function create(): ?bool;

/**
 * Deletes the file.
 *
 * @param closure $callable On deletion
 * @return boolean Success
 */
    public function delete(?Closure $callable = null): bool;

/**
 * Get file name without extension.
 *
 * @return string The file name without extension.
 */
    public function name(): ?string;

/**
 * Get file's real name.
 *
 * @return string Get file real name as it is in filesystem.
 */
    public function realname(): ?string;

/**
 * Rename file.
 *
 * @param string $name New file name.
 * @return boolean Success
 */
    public function rename(string $name): bool;

/**
 * Returns the current file's extension.
 * If $dotPrepend set to 'true', it will return extension prepended with dot.
 *
 * @return string The file extension
 */
    public function extension(): ?string;

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
    public function hash(string $algo = 'sha1', int|bool $maxsize = 5, bool $rawOutput = false): ?string;

/**
 * Returns true if the file exists.
 *
 * @return boolean True if it exists, false otherwise
 */
    public function exists(): bool;

/**
 * Returns the file size.
 *
 * @return integer|false Size of the file in bytes, or false in case of an error
 */
    public function size(): int;

/**
 * Returns true if the file is writable.
 *
 * @return boolean True if it's writable, false otherwise
 */
    public function isWritable(): bool;

/**
 * Returns true if the File is executable.
 *
 * @return boolean True if it's executable, false otherwise
 */
    public function isExecutable(): bool;

/**
 * Returns true if the file is readable.
 *
 * @return boolean True if file is readable, false otherwise
 */
    public function isReadable(): bool;

/**
 * Returns the "chmod" (permissions) of the file.
 *
 * @return string|false Permissions for the file, or false in case of an error
 */
    public function perms(): ?string;

/**
 * Returns the file's owner.
 *
 * @return integer|false The file owner, or false in case of an error
 */
    public function owner(): ?int;

/**
 * Returns the file's group.
 *
 * @return integer|false The file group, or false in case of an error
 */
    public function group(): ?int;

/**
 * Returns last access time.
 *
 * @return integer|false Timestamp of last access time, or false in case of an error
 */
    public function lastAccess(): ?Time;

/**
 * Returns last modified time.
 *
 * @return integer|false Timestamp of last modification, or false in case of an error
 */
    public function lastChange(): ?Time;

/**
 * Sets access and modification time of file.
 *
 * Attempts to set the access and modification times of the file named in the
 * filename parameter to the value given in time. Note that the access time is always modified,
 * regardless of the number of parameters.
 *
 * @param int $time The touch time. If time is not supplied, the current system time is used.
 * @param int $accessTime If present, the access time of the given filename is set to the value of atime.
 * @return integer|false Timestamp of last access time, or false in case of an error
 */
    public function touch(?int $time = null, ?int $accessTime = null): bool;

/**
 * Copy the file to $dest.
 *
 * @param string $dest Destination for the copy
 * @param boolean $overwrite Overwrite $dest if exists
 * @return File|false File copy instance on success, false on error
 */
    public function copy(string $dest, bool $overwrite = true): ?File;

/**
 * Get the mime type of the file. Uses the finfo extension if
 * its available, otherwise falls back to mime_content_type.
 *
 * @return false|string The mimetype of the file, or false if reading fails.
 */
    public function mime(): ?string;

/**
 * Clear PHP's internal stat cache.
 *
 * @param boolean $all Clear all cache or not. Passing false will clear
 *   the stat cache for the current path only.
 * @return void
 */
    public function clearStatCache(bool $all = false): void;

/**
 * Get folder instance.
 *
 * @return Folder|false
 */
    public function getFolder(): ?Folder;

/**
 * Get URL.
 *
 * @return string|null
 */
    public function getUrl(): ?string;

/**
 * Returns the file:// protocol URL for this file.
 * This is a file system path, and should be used for generating URLs.
 *
 * @return string|null File URL with file:// protocol
 */
    public function getFileUrl(): ?string;

/**
 * Returns the file data source as a string.
 *
 * @return string The file data source
 */
    public function __toString(): string;

}
