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

namespace Nata\Form\Exception;

use Nata\Form\Exception\FormException;

/**
 * Parent class for all of the form related exceptions in NataPHP.
 * All HTTP status/error related exceptions should extend this class so
 * catch blocks can be specifically typed.
 */
class ElementConfigurationException extends FormException {

/**
 * Element configuration.
 *
 * @var array
 */
    protected $_config;


/**
 * Constructor
 *
 * @param string $message If no message is given 'Bad Request' will be the message
 * @param string $code Status code, defaults to 400
 */
    public function __construct($message = null, $config = array()) {
        if (empty($message)) {
            $message = 'Invalid form element configuration.';
        }
        $this->_config = $config;
        parent::__construct($message, 500);
    }

/**
 * Get config.
 *
 * @return array
 */
    public function getConfig() {
        return $this->_config;
    }

}
