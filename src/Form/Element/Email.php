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

namespace Nata\Form\Element;

use Nata\Utility\Validation;

/**
 * Email element.
 */
class Email extends Input {

/**
 * Preset email host.
 *
 * @var string
 */
    protected $_host;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'host' => null
        );

        if ($config['host']) {
            $this->_host = $config['host'];

            if ($this->_append === null) {
                $this->_append = '@' . $this->_host;
            }

        }

        // Normalize the value from request
        if ($this->_host && $this->_data->submitted() && !$this->isEmpty()) {
            $value = $this->value();
            $this->_value = $this->_getEmail($value);
            $this->_data->request($this->_value);
        }

        parent::initialize($config);
    }

/**
 * Get email by given account and set host.
 *
 * @param string $account Account
 * @return string Email
 */
    protected function _getEmail($account) {
        if (strpos($account, '@') !== false) {
            return $account;
        }
        return $account . '@' . $this->_host;
    }

/**
 * Extrat email acocunt from given email.
 *
 * @param string $email Email
 * @return string Email account
 */
    protected function _getAccount($email) {
        list($account) = explode('@', $email);
        return $account;
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!Validation::email($data)) {
            $this->_error = __('This email is invalid.');
            return false;
        }
        return true;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        if ($this->_host) {
            $this->_value = $this->_getAccount($this->value());
        }

        parent::beforeRender($event);

        $this->_getView()->extend(['input']);
    }

}
