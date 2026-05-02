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

namespace Nata\Filesystem\File\ArchiveFormat;

/**
 * Convenience class for reading, writing, renaming and appending to files.
 * Allows also to manipulate/edit images.
 */
class Gzip extends Base {

/**
 * Unpack into given folder/filename.
 *
 * @param int $bufferSize Amount of bytes to read at a time (raising this value may increase performance)
 * @return \Nata\Filesystem\File Unpacked
 */
    public function __extract($overwrite = false) {
        $bufferSize = 4096;
        $archiveFile = $this->_archiveFile;
        // Make sure the file is closed
        $archiveFile->close();

        $extension = $archiveFile->extension() === 'gz' ? '.' . $archiveFile->extension() : '';
        $filename = str_replace($extension, '', $archiveFile->pwd());

        // Open our files (in binary mode)
        $sourceFile = gzopen($this->pwd(), 'rb');
        $targetFile = fopen($filename, 'wb');

        // Keep repeating until the end of the input file
        while (!gzeof($sourceFile)) {
            // Read buffer-size bytes
            // Both fwrite and gzread and binary-safe
            fwrite($targetFile, gzread($sourceFile, $bufferSize));
        }

        // Files are done, close files
        fclose($targetFile);
        gzclose($sourceFile);

        return $filename;




    }


}
