<?php
/**
 * NataPHP Framework
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

namespace Nata\Form;

use Nata\Core\NataObject;

/**
 * Element Label.
 */
class Label extends NataObject {

/**
 * Construtor.
 *
 * @param array $config Options
 */
    public function __construct(array $config) {
        $config += [
            'text' => null,
            'description' => null,
            'tooltip' => null,
            'theme' => null,
            'template' => null,
            'layout' => null,
        ];

    }

}
