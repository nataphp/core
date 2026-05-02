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

namespace Nata\FilesystemManager\File\DataSource;

use InvalidArgumentException;
use Nata\FilesystemManager\Mimetype;

/**
 * Data URI file.
 */
class DataUri extends Memory {


/**
 * Constructor.
 *
 * @param string $data Data URI
 * @param array $part MIME message part
 * @return void
 */
    public function __construct(string $dataUri, array $options = []) {
        parent::_createHandle($this->_decodeData($dataUri));
    }

/**
 * Decode file data.
 *
 * @param string $data Data
 * @return mixed
 */
    protected function _decodeData($data) {
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

}
