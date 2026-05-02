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
 * Postal element.
 */
class Postal extends Input {

/**
 * Country code.
 *
 * @var string
 */
    protected $_country = 'pt';


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $config += array(
            'country' => null
        );
        if ($config['country']) {
            $this->_country = $config['country'];
        }
    }

/**
 * Get/Set country code to check agaisn't.
 *
 * @param bool $show Allow show password
 * @return $this|bool
 */
    public function country($country = null) {
        if ($country === null) {
            return $this->_country;
        }
        $this->_country = strtolower($country);
        return $this;
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        $country = $this->_country;
        $isValid = Validation::postal($data, $this->_match, $country);
        if ($isValid === null) {
            return true;
        }
        if ($isValid === false) {
            $this->_error = __('Must be a valid postal code.');
            $placeholder = Validation::postalPlaceholder($country);
            if ($country) {
                $this->_error = __('Must be a valid postal code for country "%s".', [strtoupper($country)]);
                if ($placeholder) {
                    $this->_error = __('Must be a valid postal code for country "%s". Example: %s.', [strtoupper($country), $placeholder]);
                }
            }
        }
        return $this->_error === null;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->_getView()->extend(array('input'));
    }

}
