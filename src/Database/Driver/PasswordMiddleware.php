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
 * @since         NataPHP 1.1.5
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Database\Driver;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Nata\Database\Password;

/**
 * Doctrine DBAL middleware that gives the password to the driver only when
 * it connects: the parameters of the Doctrine connection never hold it.
 */
class PasswordMiddleware implements Middleware {

/**
 * Password.
 *
 * @var \Nata\Database\Password
 */
    private Password $_password;

/**
 * Constructor.
 *
 * @param \Nata\Database\Password $password Password
 */
    public function __construct(Password $password) {
        $this->_password = $password;
    }

/**
 * Wrap the driver.
 *
 * @param \Doctrine\DBAL\Driver $driver Driver
 * @return \Doctrine\DBAL\Driver Driver that adds the password on connect
 */
    public function wrap(Driver $driver): Driver {
        return new PasswordDriver($driver, $this->_password);
    }

}
