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
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Nata\Database\Password;

/**
 * Driver that adds the password to the parameters at connect time
 * (PasswordMiddleware).
 */
class PasswordDriver extends AbstractDriverMiddleware {

/**
 * Password.
 *
 * @var \Nata\Database\Password
 */
    private Password $_password;

/**
 * Constructor.
 *
 * @param \Doctrine\DBAL\Driver $driver Wrapped driver
 * @param \Nata\Database\Password $password Password
 */
    public function __construct(Driver $driver, Password $password) {
        parent::__construct($driver);
        $this->_password = $password;
    }

/**
 * Connect with the password.
 *
 * @param array $params Connection parameters, without the password
 * @return \Doctrine\DBAL\Driver\Connection Driver connection
 */
    public function connect(#[\SensitiveParameter] array $params): DriverConnection {
        $params['password'] = $this->_password->reveal();

        return parent::connect($params);
    }

}
