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

namespace Nata\Log\Engine;

use Nata\Log\EngineInterface;

/**
 * Base log engine class.
 */
abstract class Base implements EngineInterface {

/**
 * Engine config
 *
 * @var string
 */
    protected $_config = [];


/**
 * __construct method.
 *
 * @return void
 */
    public function __construct($config = []) {
        $this->config($config);
    }

/**
 * Sets instance config. When $config is null, returns config array.
 *
 * Config
 *
 * - `levels` string or array, levels the engine is interested in
 * - `scopes` string or array, scopes the engine is interested in
 *
 * @param array $config engine configuration
 * @return array
 */
    public function config($config = []) {
        if (!empty($config)) {
            if (isset($config['levels']) && is_string($config['levels'])) {
                $config['levels'] = [$config['levels']];
            }

            $this->_config = $config;
        }
        return $this->_config;
    }

}
