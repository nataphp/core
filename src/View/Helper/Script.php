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

namespace Nata\View\Helper;

use Nata\Core\App;
use Nata\Utility\Html;

/**
 * Javascript files helper.
 *
 * Outputs JS files inline (one <script> tag per file) instead of merging them.
 */
class Script extends StaticFile {

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'src' => 'url',
        'param' => 'url',
        'full' => false,
        'ssl' => null,
        'host' => null
    ];

/**
 * Mapping of available script/JS packages.
 * Preset package names that map to actual file paths.
 *
 * @var array
 */
    protected static $_libMap = [
        'nata' => [
            'file' => '/vendor/nata/jquery.nata.min.js'
        ],
        'nata-dev' => [
            'file' => '/vendor/nata/jquery.nata.js'
        ],
        'bootstrap-3.4' => [
            'file' => '/vendor/nata/vendor/bootstrap/js/bootstrap.min.js'
        ],
        'jquery-1.11.1' => [
            'file' => '/vendor/nata/vendor/jquery/jquery-1.11.1.min.js'
        ],
        'jquery-1.11' => 'jquery-1.11.1',
        'jquery-3.3.1' => [
            'file' => '/vendor/nata/vendor/jquery/jquery-3.3.1.min.js'
        ],
        'jquery-3.6.0' => [
            'file' => '/vendor/nata/vendor/jquery/jquery-3.6.0.min.js'
        ]
    ];

/**
 * Html <script> tags for javascript files.
 * Outputs multiple <script> tags, one per file, instead of merging.
 *
 * @param array $params Parameters with 'files' array or 'name' => 'lang'
 * @return string HTML tags
 */
    public function render($params) {
        if (!is_array($params)) {
            $params = ['files' => $params];
        }

        if (!isset($params['files']) || !is_array($params['files'])) {
            return '';
        }

        $output = '';
        $params = $this->_normalizeParams($params);
        foreach ($params['files'] as $key => $file) {
            $fileConfig = $this->_resolveFile($key, $file);
            if (!$fileConfig) {
                continue;
            }

            $url = $this->_getFileUrl($fileConfig, 'js');
            if (!$url) {
                continue;
            }

            $output .= Html::elem('<script>', '', [
                'type' => 'text/javascript',
                'src' => $url
            ]) . "\n            ";
        }

        return rtrim($output);
    }

}
