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

use Nata\Log\Log;

/**
 * Trait for classes that need to log messages.
 */
trait LogAwareTrait {

/**
 * Convenience method to write a message to Nata\Log\Log. See Nata\Log\Log::write()
 * for more information on writing to logs.
 *
 * @param string $msg Log message
 * @param integer $level Error level constant.
 * @return boolean Success of log write
 */
    public function log($msg, $level = LOG_ERR) {
        if (!is_string($msg)) {
            $msg = print_r($msg, true);
        }
        return Log::write($level, $msg);
    }

}
