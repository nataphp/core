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
 * An interface for Cloudflare R2 Storage (S3-compatible API).
 */
class CloudflareR2 extends AwsS3 {

/**
 * Default config.
 *
 * - `pathTemplate` Path template
 * - `version` AWS SDK version
 * - `bucket` Bucket name
 * - `region` Region
 * - `endpoint` Endpoint
 * - `usePathStyleEndpoint` Use path style endpoint
 * - `publicKey` Public key
 * - `secretKey` Secret key
 * - `acl` ACL
 *
 * @var array
 */
    protected $_defaultConfig = [
        'pathTemplate' => '{mime_top_level}/{sha1_5}/{sha1}{extension}',
        'version' => 'latest',
        'bucket' => null,
        'region'  => 'auto',
        'endpoint' => null,
        'usePathStyleEndpoint' => true,
        'accountId' => null,
        'publicKey' => null,
        'secretKey' => null,
        'acl' => 'private'
    ];


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
            $accountId = parent::config('accountId');
            $url .= $accountId . '.r2.cloudflarestorage.com/';
            $url .= $bucket . '/';
        } else {
            $url .= $host . '/';
        }
        $url .= $key;
        return $url;
    }

}