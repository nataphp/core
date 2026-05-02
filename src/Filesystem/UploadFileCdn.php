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

use Nata\Filesystem\File;


/**
 * This is a class to upload files to a CDN.
 */
class UploadFileCdn extends File {


/**
 * File to upload.
 *
 * @var string|File
 */
    protected $_file;


/**
 * Constructor.
 *
 * @param string|File $file File to upload
 * @return void
 */
    public function __construct(string|File $file) {
        $this->_file = $file;
    }

}
