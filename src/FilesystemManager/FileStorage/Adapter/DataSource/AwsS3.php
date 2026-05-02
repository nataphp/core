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

namespace Nata\FilesystemManager\FileStorage\Adapter\DataSource;

use Nata\FilesystemManager\File\DataSource\Web;

/**
 * An interface for AWS S3.
 */
class AwsS3 extends Web {

/**
 * Returns metadata.
 *
 * If given key, will return respective value
 *
 * @param string $key
 * @return mixed Metadata
 */
    public function metadata($key = null) {
        if (!$this->_metadata) {
            foreach ($this->getResponse()->getHeader() as $name => $value) {
                if (!str_starts_with($name, 'X-Amz-Meta-')) {
                    continue;
                }
                $name = strtolower(str_replace('X-Amz-Meta-', '', $name));
                $this->_metadata[$name] = $value;
            }
        }

        if ($key === null) {
            return $this->_metadata;
        }

        return $this->_metadata[$key] ?? null;
    }

}
