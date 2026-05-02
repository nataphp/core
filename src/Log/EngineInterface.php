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

namespace Nata\Log;

/**
 * \Nata\Log\EngineInterface is the interface that should be implemented
 * by all classes that are going to be used as Log streams.
 */
interface EngineInterface {

/**
 * Write method to handle writes being made to the Logger.
 *
 * @param string $type
 * @param string $message
 * @return void
 */
    public function write($type, $message);

}
