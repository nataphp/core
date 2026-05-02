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

namespace Nata\Cron\Task;

use Nata\Cron\Task;
use Nata\Network\Email as Mailer;

class Email extends Task {

/**
 * Email instance
 *
 * @var \Nata\Network\Email
 */
    private $_email = null;


/**
 * Initializer.
 *
 * @return void
 */
    public function initialize(array $config = array()) {
        $this->_email = new Mailer;
    }
    
/**
 * Call \Nata\Network\Email method.
 *
 * @param string $name Method name
 * @param array $args Method arguments
 * @return mixed
 */
    public function __call($name, $args) {
        return call_user_func_array(array($this->_email, $name), $args);            
    }
    
/**
 * Get \Nata\Network\Email property variable.
 *
 * @param string $varName Variable property name
 * @return mixed var value
 */
    public function __get($varName) {
        return $this->_email->{$varName};
    }
    
/**
 * Set \Nata\Network\Email property.
 *
 * @param string $varName Proper name
 * @param mixed $name Var value
 * @return mixed
 */
    public function __set($varName, $value) {
        return $this->_email->{$varName} = $value;
    }

}
