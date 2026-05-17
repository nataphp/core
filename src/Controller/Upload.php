<?php
/**
 * NataPHP Framework
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\Controller;

use Nata\Controller\Controller;
use Nata\Core\Configure;
use Nata\FilesystemManager\FileStorage;
use Nata\FilesystemManager\FileStorage\RelativePathBuilder;

/**
 * File storage's handling Controller.
 *
 * Allow to edit/manipulate images, like resizing, croppping, etc using
 * image editor
 */
class Upload extends Controller {

/**
 * File output.
 *
 * @return \Nata\Http\Response
 */
    public function presignedFormUploadUrl() {
        $this->loadComponent('Csrf');

        if (!$this->Auth->isAuthorized()) {
            return $this->response->json([
                'error' => 'Unauthorized',
            ]);
        }

        $path = RelativePathBuilder::build(
            [
                'mime' => $this->request->data('mime'),
                'name' => $this->request->data('sha256'),
                'extension' => $this->request->data('extension'),
                'sha256' => $this->request->data('sha256'),
                'sha256_5' => substr($this->request->data('sha256'), 0, 5),
            ],
            'uploads/tmp/{mime_top_level}/{sha256_5}/{name}{extension}',
            [
                'checkMissing' => true,
                'before' => '{',
                'after' => '}'
            ]
        );

        if (!$path) {
            return $this->response->json([
                'error' => 'Invalid path',
            ]);
        }

        $store = $this->request->data('store') ?? Configure::read('Filesystem.storage.defaultStore') ?? 'local';
        $url = FileStorage::presignedFormUploadUrl($path, $store, $this->request->data('mime'));

        $fields = [
            'key' => $path,
            'acl' => 'public-read',
            'policy' => 'policy',
            'signature' => 'signature',
            'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
        ];

        return $this->response->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
            'store' => $store,
        ]);
    }

}
