<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Nata\Auth;

use Nata\Core\App;
use Nata\Auth\PasswordHasher\Base;
use RuntimeException;

/**
 * Builds password hashing objects
 */
class PasswordHasherFactory {

/**
 * Returns password hasher object out of a hasher name or a configuration array
 *
 * @param string|array $passwordHasher Name of the password hasher or an array with
 * at least the key `className` set to the name of the class to use
 * @return \Nata\Auth\PasswordHasher\Base Password hasher instance
 * @throws \RuntimeException If password hasher class not found or
 *   it does not extend Nata\Auth\PasswordHasher\Base
 */
    public static function build($passwordHasher) {
        $config = array();
        if (is_string($passwordHasher)) {
            $class = $passwordHasher;
        } else {
            $class = $passwordHasher['className'];
            $config = $passwordHasher;
            unset($config['className']);
        }

        $className = App::className($class, 'Auth/PasswordHasher');
        if (!$className) {
            throw new RuntimeException(sprintf('Password hasher class "%s" was not found.', $class));
        }

        $hasher = new $className($config);
        if (!($hasher instanceof Base)) {
            throw new RuntimeException('Password hasher must extend Base class.');
        }
        return $hasher;
    }

}
