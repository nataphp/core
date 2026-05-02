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

use Nata\Core\NataObject;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Archive;
use Nata\FilesystemManager\File\ArchiveFormat\ArchivedFile\PharFile;
use PharData;

/**
 * Base class for archive file format adapter's classes.
 */
class Base extends NataObject {

/**
 * Archive contents.
 *
 * @var array
 */
    protected $_contents;

/**
 * File archive.
 *
 * @var \Nata\FilesystemManager\File\Archive
 */
    protected $_archiveFile;


/**
 * Unpack into given folder/filename.
 *
 * @param \Nata\FilesystemManager\File\Archive $archiveFile Archive file instance
 * @return void
 */
    public function __construct(Archive $archiveFile) {
        $this->_archiveFile = $archiveFile;
    }

/**
 * Get archive file contents.
 *
 * @return array File contents
 */
    public function getContents() {
        if ($this->_contents === null) {
            $this->_contents = $this->_getFilesRecursively($this->_archiveFile->getAbsolutePath());
        }
        return $this->_contents;
    }

/**
 * Get list of files recursively.
 *
 * @param string $path Path to get list of files
 * @return array List of files
 */
    protected function _getFilesRecursively($path) {
        $archive = new PharData($path);
        $contents = [];
        foreach ($archive as $file) {
            if ($file->isDir()) {
                $contents = array_merge($contents, $this->_getFilesRecursively($file->getPathname()));
                continue;
            }
            $contents[] = new File($file);
        }
        return $contents;
    }

}
