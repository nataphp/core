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

use Nata\Utility\Html;

/**
 * Stylesheet files helper.
 *
 * Outputs CSS files inline (one <link> tag per file) instead of merging them.
 */
class Style extends StaticFile {

/**
 * Array with existing font extensions
 *
 * @var array
 */
    protected static $_extensions = [
        'fonts' => [
            'eot','woff','ttf','svg'
        ],
        'images' => [
            'gif','jpeg','jpg','png'
        ]
    ];

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'src' => 'url',
        'print' => 'url',
        'full' => false,
        'ssl' => null,
        'host' => null
    ];

/**
 * Array with web images extensions.
 *
 * @var array
 */
    protected static $_images = [];

/**
 * Mapping of available style/CSS packages.
 * Preset package names that map to actual file paths.
 *
 * @var array
 */
    protected static $_libMap = [
        'fontawesome-4.7' => [
            'file' => '/vendor/nata/vendor/font-awesome/css/font-awesome.min.css'
        ],
        'bootstrap-3.4' => [
            'file' => '/vendor/nata/vendor/bootstrap/css/bootstrap.css'
        ]
    ];

/**
 * Html <link> tags for stylesheet files.
 * Outputs multiple <link> tags, one per file, instead of merging.
 *
 * @param array $params Parameters with 'files' array
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

            $url = $this->_getFileUrl($fileConfig, 'css');
            if (!$url) {
                continue;
            }

            $output .= Html::elem('<link>', [
                'rel' => 'stylesheet',
                'href' => $url
            ]) . "\n            ";
        }

        return rtrim($output);
    }

/**
 * Html <link> for stylesheet.
 * Kept for backward compatibility.
 *
 * @param array $params Parameters
 * @return string File URL
 */
    public function get($params) {
        return $this->_get($params, 'css');
    }

}
