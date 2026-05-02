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
 * Fiscal ID element.
 */
class FiscalId extends Number {

/**
 * Country code.
 *
 * @var string
 */
    protected $_country;

/**
 * Default country code.
 *
 * @var string
 */
    protected $_defaultCountry = 'pt';

/**
 * Input number masking.
 *
 * @var bool
 */
    protected $_mask = false;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);

        $config += [
            'country' => null,
            'defaultCountry' => null
        ];

        if ($config['country']) {
            $this->_country = $config['country'];
        }

        if ($config['defaultCountry']) {
            $this->_defaultCountry = $config['defaultCountry'];
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
            if ($this->_country === null) {
                $this->_country = $this->_defaultCountry;
            }
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
    protected function _valid($value) {
        $country = $this->country();
        $value = $this->_toNumber($value, 0);

        if (!Validation::fiscalId($value, $country)) {
            $this->_error = __('Must be a valid fiscal ID.');
            if ($this->_country !== $this->_defaultCountry) {
                $this->_error = __('Must be a valid fiscal ID for country "%s".', [strtoupper($country)]);
            }
            return false;
        }

        $this->data($value);

        return true;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $this->attributes()
            ->addData([
                'country' => $this->_country
            ]);
    }

}
