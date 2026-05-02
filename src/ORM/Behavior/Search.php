<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright 2015 - 2019, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * This behavior was inspired based on the work of Cake Development Corporation.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @link          https://github.com/CakeDC/Enum
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use Nata\Core\ConfigAwareTrait;

/**
 * Search behavior.
 *
 * @todo
 */
class Search extends Behavior {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedFinders' => [
            'search' => 'findSearch'
        ],
        'implementedMethods' => [
            'search' => 'findSearch'
        ]
    ];


/**
 * Initialize hook.
 *
 * If events are specified - do *not* merge them with existing events,
 * overwrite the events to listen on
 *
 * @param array $config The config for this behavior.
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $this->_normalizeConfig();
    }

}
