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
use Nata\Utility\Number as NumberUtility;

/**
 * Number element.
 */
class Number extends Input {

/**
 * Minimum number.
 *
 * @var int|float
 */
    protected $_min;

/**
 * Maximum number.
 *
 * @var int|float
 */
    protected $_max;

/**
 * Step.
 *
 * @var string|int|float
 */
    protected $_step = 'any';

/**
 * Decimals.
 *
 * @var int
 */
    protected $_decimals = 0;

/**
 * Decimal point.
 *
 * @var string
 */
    protected $_decimalPoint = '.';

/**
 * Thousands separator.
 *
 * @var string
 */
    protected $_thousandsSeparator = ' ';

/**
 * Remove trailing zeros.
 *
 * @var bool
 */
    protected $_removeTrailingZeros = false;

/**
 * Input number masking.
 *
 * @var bool
 */
    protected $_mask = true;

/**
 * Input spinner.
 *
 * @var bool
 */
    protected $_spinner = false;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $format = NumberUtility::format();

        $config += array(
            'min' => null,
            'max' => null,
            'step' => null,
            'decimals' => null,
            'decimalPoint' => $format['decimals'],
            'thousandsSeparator' => $format['thousands'],
            'removeTrailingZeros' => null,
            'mask' => null,
            'spinner' => null
        );

        if ($config['mask'] !== null) {
            $this->_mask = $config['mask'];
        }
        if ($config['min'] !== null) {
            $this->_min = $config['min'];
        }
        if ($config['max'] !== null) {
            $this->_max = $config['max'];
        }
        if ($config['step']) {
            $this->_step = $config['step'];
        }
        if ($config['decimals']) {
            $this->_decimals = $config['decimals'];
        }
        if ($config['decimalPoint'] !== null) {
            $this->_decimalPoint = $config['decimalPoint'];
        }
        if ($config['thousandsSeparator'] !== null) {
            $this->_thousandsSeparator = $config['thousandsSeparator'];
        }
        if ($config['removeTrailingZeros'] !== null) {
            $this->_removeTrailingZeros = $config['removeTrailingZeros'];
        }
        if ($config['spinner'] !== null) {
            $this->_spinner = $config['spinner'];
        }

        // Normalize marked value
        if ($value = $this->data()->request()) {
            $this->data()->request($this->_toNumber($value, $this->_decimals));
        }

        parent::initialize($config);
    }

/**
 * Get data to be validaded.
 *
 * @param mixed $data Data to validate
 * @return mixed
 */
    protected function _getData($data) {
        if ($data === null) {
            $data = $this->value();
        }
        return $this->_toNumber($data, $this->_decimals);
    }

/**
 * Calculate the input's number allowed length, taking into
 * account the desired number formatting.
 *
 * @param int $length Intended length
 * @return int Real accepted length in input
 */
    protected function _realLength($length) {
        if ($this->_mask === true) {
            // Add 1 character per thousand separator
            $length += (round(($length - 2) / 3) * strlen($this->_thousandsSeparator));
        }

        if ($this->_decimals > 0) {
            $length += strlen($this->_decimalPoint);
        }

        return $length;
    }

/**
 * Get/Set min attribute.
 *
 * @param int $min min attribute value
 * @return $this|int
 */
    public function min($min = null) {
        return $this->_property('_min', $min);
    }

/**
 * Get/Set max attribute.
 *
 * @param int $max max attribute value
 * @return $this|int
 */
    public function max($max = null) {
        return $this->_property('_max', $max);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $show Allow show password
 * @return $this|bool
 */
    public function step($step = null) {
        return $this->_property('_step', $step);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $decimals Allow show password
 * @return $this|bool
 */
    public function decimals($decimals = null) {
        return $this->_property('_decimals', $decimals);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $decimals Allow show password
 * @return $this|bool
 */
    public function decimalPoint($decimalPoint = null) {
        return $this->_property('_decimalPoint', $decimalPoint);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $thousandsSeparator Allow show password
 * @return $this|bool
 */
    public function thousandsSeparator($thousandsSeparator = null) {
        return $this->_property('_thousandsSeparator', $thousandsSeparator);
    }

/**
 * Get/Set option to remove insignificant trailing zeros.
 *
 * @param bool $removeTrailingZeros Allow show password
 * @return $this|bool
 */
    public function removeTrailingZeros($removeTrailingZeros = null) {
        return $this->_property('_removeTrailingZeros', $removeTrailingZeros);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $decimals Allow show password
 * @return $this|bool
 */
    public function mask($mask = null) {
        return $this->_property('_mask', $mask);
    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $decimals Allow show password
 * @return $this|bool
 */
    public function spinner($spinner = null) {
        return $this->_property('_spinner', $spinner);
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($value) {

        $value = $this->_toNumber($value, $this->_decimals);

        if (!Validation::numeric($value)) {
            $this->_error = __('Must be numeric.');
            return false;
        }

        if ($this->min() && $value < $this->_min) {
            $this->_error = __('Must be equal or greater than %s.', $this->_numberFormat($this->_min));
            return false;
        } elseif ($this->max() && $value > $this->_max) {
            $this->_error = __('Must be equal or lower than %s.', $this->_numberFormat($this->_max));
            return false;
        }

        $this->data($value);

        return true;
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _toNumber($value) {
        if ($value === null) {
            return $value;
        }

        $value = str_replace($this->_thousandsSeparator, '', $value);
        $value = str_replace($this->_decimalPoint, '.', $value);

        if (is_numeric($value) && is_string($value)) {
            $value = ($this->_decimals > 0 ? (float)$value : (int)$value);
        }

        return $value;
    }

/**
 * Format given number.
 * Useful when presenting the number in specified format.
 *
 * @param string|float|int $value Number to format
 * @return string Formated number
 */
    protected function _numberFormat($value) {
        return number_format($value, $this->_decimals, $this->_decimalPoint, $this->_thousandsSeparator);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $this->_getView()->extend(['input']);

        if ($this->_length) {
            $this->attributes()->set('maxlength', $this->_realLength($this->_length));
        }

        $this->attributes()
            ->set('type', ($this->mask() || $this->_spinner ? 'text' : 'number'))
            ->set('min', $this->min())
            ->set('max', $this->max())
            ->set('step', $this->step())
            ->addData([
                'spinner' => $this->_spinner,
                'mask' => $this->mask(),
                'decimals' => $this->_decimals,
                'decimal-point' => $this->_decimalPoint,
                'thousands-separator' => $this->_thousandsSeparator,
                'remove-trailing-zeros' => $this->_removeTrailingZeros
            ]);
    }

}
