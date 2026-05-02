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

use Nata\I18n\Time;
use Nata\Utility\Validation;

/**
 * Date element.
 */
class Date extends BaseTime {

/**
 * Default date format.
 *
 * @var string
 */
    protected $_format = 'Y-m-d';


/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $value = $this->value();
        if ($value) {
            if (!($value instanceof Time)) {
                $value = new Time($value, 'UTC');
            }
            $value = $value->format($this->_format);
        }

        $this->_value = $value;

        parent::beforeRender($event);

        $this->_getView()->extend(['input']);

        $this->attributes()
            ->set(array(
                'autocomplete' => 'off',
                'data' => array(
                    'autoclose' => true,
                    'format' => $this->_format,
                    'font-awesome' => true,
                    'min-date' => $this->_getMinDate($this->_format),
                    'max-date' => $this->_getMaxDate($this->_format)
                )
            ))
            ->addClass('form-datetimepicker');

        if ($this->_prepend === null) {
             $this->prepend('<i class="fa fa-calendar"></i>');
        }

    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!Validation::date($data, $this->_format, $this->_regex)) {
            $this->_error = __('Must be a valid date in format %s.', [$this->_format]);
        }

        $time = new Time($data, $this->timezone());
        if ($this->maxDate()) {
            $maxDate = new Time($this->maxDate(), $this->timezone());
            $maxDate->timezone('UTC');
            if ($time->timestamp() > $maxDate->timestamp()) {
                $this->_error = __('Date must be until %s.', $maxDate->format('Y-m-d'));
                return false;
            }
        }
        if ($this->minDate()) {
            $minDate = new Time($this->minDate(), $this->timezone());
            $minDate->timezone('UTC');
            if ($time->timestamp() < $minDate->timestamp()) {
                $this->_error = __('Date must be from %s.', $minDate->format('Y-m-d'));
                return false;
            }
        }

        return $this->_error === null;
    }

}
