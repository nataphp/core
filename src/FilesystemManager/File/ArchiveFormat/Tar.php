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

use PharData;

/**
 * Convenience class for reading, writing, renaming and appending to files.
 * Allows also to manipulate/edit images.
 */
class Tar extends Base {

/**
 * Unpack into given folder/filename.
 *
 * @param int $bufferSize Amount of bytes to read at a time (raising this value may increase performance)
 * @return \Nata\FilesystemManager\File Unpacked
 */
    public function extract($overwrite = false) {
        $archiveFile = $this->_archiveFile;
        $phar = new PharData($archiveFile);

        $folderPath = $archiveFile->folder()->pwd();
        return $phar->extractTo($folderPath, ['my-db.mmdb'], true);
    }


}
