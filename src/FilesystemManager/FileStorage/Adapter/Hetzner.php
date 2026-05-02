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

namespace Nata\FilesystemManager\FileStorage\Adapter;

/**
 * An interface for Hetzner Object Storage (S3-compatible API).
 */
class Hetzner extends AwsS3 {

/**
 * Generate object URL.
 *
 * @param string $bucket Bucket name
 * @param string $key Object key
 * @param array $options Options
 * @return string
 */
    protected function _getObjectUrl(string $bucket, string $key, array $options): string {
        $options += ['cdn' => false];
        $host = $options['host'] ?? parent::config('host');
        $url = 'https://';
        if ($host === null) {
            $url .= $bucket . '.';
            $url .= parent::config('region');
            $url .= '.your-objectstorage.com/';
        } else {
            $url .= $host . '/';
        }
        $url .= $key;
        return $url;
    }

}