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

namespace Nata\FilesystemManager\File\ArchiveFormat;

use Exception;
use Nata\Collection\Collection;
use Nata\Core\App;
use Nata\FilesystemManager\File;
use Phar;
use PharData;

/**
 * Archive file adapter for Gzipped Tarball files.
 */
class TarGzip extends Base {

/**
 * Extract file(s) from archive into given location.
 *
 * @param string $pathTo Path to extract files to
 * @param string|array $files Files to extract, NULL to extract all
 * @param bool $overwrite Overwrite files
 * @return \Nata\Collection\Collection Collection of files extracted
 */
    public function extractTo($pathTo, $files, $overwrite) {
        $archiveFile = $this->_archiveFile;
        // Make sure the file is closed
        $archiveFile->close();

        $tarFilename = str_replace('.gz', '', $archiveFile->getAbsolutePath());
        (new File($tarFilename))->delete();

        $gzip = new PharData($archiveFile->getAbsolutePath());
        // Decompress and extract TAR file
        $gzip->decompress();

        $gzip = null;
        unset($gzip);

        $phar = new PharData($tarFilename);

        $files = $this->_normalizeFilesToExtract($files);

        try {
            $extracted = $phar->extractTo($pathTo, $files, $overwrite);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        $phar = null;
        unset($phar);

        if ($extracted !== true) {
            $files = [];
        }

        // Unlink/Delete extracted TAR
        Phar::unlinkArchive($tarFilename);

        return $this->_extractedFiles($files);
    }

/**
 * Normalize file paths to be extracted.
 *
 * @param string|array $files File or array of files to extract
 * @return array|string File(s) path normalized for extraction
 */
    protected function _normalizeFilesToExtract($files) {
        if ($files === '*' || $files === null) {
            return null;
        }

        if (!is_array($files)) {
            return $this->_getFileRelativePath($files);
        }

        $files = array_map(function ($file) {
            return $this->_getFileRelativePath($file);
        }, $files);

        return $files;
    }

/**
 * Unpack into given folder/filename.
 *
 * @param string|array $files File or array of files to extract
 * @return \Nata\FilesystemManager\File|array File or array of file instances
 */
    protected function _extractedFiles($files) {
        $absPath = $this->_archiveFile->folder()->pwd();

        if (!is_array($files)) {
            return new File($absPath . $files);
        }

        foreach ($files as $index => $filename) {
            $files[$index] = new File($absPath . $filename);
        }

        return $files;
    }

/**
 * Get relative path of given file to be used for extraction.
 *
 * @param \Nata\FilesystemManager\File|string $file File or file path
 * @return string File's relative path for extraction
 */
    protected function _getFileRelativePath($file) {
        if ($file instanceof File) {
            $file = $file->getAbsolutePath();
        }

        $parts = explode($this->_archiveFile->basename(), $file);
        return str_replace('\\', '/', ltrim($parts[1], '/'));
    }

}
