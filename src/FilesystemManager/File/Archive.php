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

use Nata\Collection\Collection;
use Nata\Core\App;
use Nata\FilesystemManager\File;
use Nata\Utility\Inflector;

/**
 * Convenience class for reading, writing, renaming, extrating and appending to archive files.
 */
class Archive extends File {

/**
 * Archive contents.
 *
 * @var array
 */
    protected $_contents;

/**
 * Archive format type adapter.
 *
 * @var \Nata\FilesystemManager\File\ArchiveFormat\Base
 */
    protected $_formatAdapter;


/**
 * Extract file(s) from archive into current archive file location.
 *
 * @param string|array $files Files to extract, NULL to extract all
 * @param bool $overwrite Overwrite files
 * @return \Nata\FilesystemManager\File Unpacked
 */
    public function extract($files = null, $overwrite = false) {
        $pathTo = parent::folder()->pwd();
        return $this->_loadFormatAdapter()->extractTo($pathTo, $files, $overwrite);
    }

/**
 * Extract file(s) from archive into given location.
 *
 * @param string $pathTo Path to extract files to
 * @param string|array $files Files to extract, NULL to extract all
 * @param bool $overwrite Overwrite files
 * @return \Nata\FilesystemManager\File Unpacked
 */
    public function extractTo($pathTo, $files = null, $overwrite = false) {
        return $this->_loadFormatAdapter()->extractTo($pathTo, $files, $overwrite);
    }

/**
 * Get list of contents inside archive.
 *
 * @return \Nata\Collection\Collection Collection of files
 */
    public function getContents() {
        return new Collection($this->_loadFormatAdapter()->getContents());
    }

/**
 * Returns an array of all matching files in current directory.
 *
 * @param string $regexpPattern Preg_match pattern (Defaults to: .*)
 * @return \Nata\Collection\Collection Files that match given pattern
 */
    public function find($regexpPattern = '.*') {
        return new Collection(array_values(preg_grep('/' . $regexpPattern . '$/i', $this->getContents()->toArray())));
    }

/**
 * Load and return the current archive format adapter to manage the file.
 *
 * @return \Nata\FilesystemManager\File\ArchiveFormat\Base Archive format adapter
 */
    protected function _loadFormatAdapter() {
        if ($this->_formatAdapter === null) {
            $basename = parent::basename();

            if (stripos($basename, '.tar.gz') !== false) {
                $class = 'TarGzip';
            } else {
                $class = Inflector::camelize(parent::extension());
            }

            $className = App::className($class, 'FilesystemManager/File/ArchiveFormat');
            if (!$className) {
                $className = '\Nata\FilesystemManager\File\ArchiveFormat\Base';
            }

            $this->_formatAdapter = new $className($this);
        }
        return $this->_formatAdapter;
    }

}
