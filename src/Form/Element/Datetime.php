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
use Nata\I18n\Time;
use Nata\I18n\Time\LocalizedDateParser;

/**
 * Datetimelocal element.
 */
class Datetime extends BaseTime {


/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        $time = LocalizedDateParser::parseToTime($data, null, $this->timezone());
        if ($time === null) {
            $this->_error = __('Must be a valid date and time in the format %s.', [$this->_format]);
            return false;
        }
        if (!Validation::datetime($data, $this->_format, $this->_match)) {
            $this->_error = __('Must be a valid date and time format.');
        } else {
            $modelValue = $this->_data->model();

            $time = new Time($data, $this->timezone());
            $time->timezone('UTC');

            $data = (is_string($modelValue) ? $time->format('Y-m-d H:i:s') : $time);
            $this->data($data);
        }
        $this->data($time);
        return $this->_error === null;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        $value = $this->value();
        if ($value) {
            if (!($value instanceof Time)) {
                $value = new Time($value, 'UTC');
            }
            if ($this->_timezone) {
                $value->timezone($this->_timezone);
            }
            $value = $value->format($this->_format);
        }

        // Check if is empty/format to datepicker plugin
        $this->_value = $value ?: '';

        parent::beforeRender($event);

        $this->attributes()
            ->set([
                'autocomplete' => 'off',
                'step' => $this->step(),
                'data' => [
                    'autoclose' => true,
                    'format' => $this->_format,
                    'font-awesome' => true,
                    'allowTimes' => $this->_allowTimes(),
                    'min-date' => $this->_getMinDate('Y-m-d'),
                    'max-date' => $this->_getMaxDate('Y-m-d'),
                    'min-time' => $this->_getMinTime('H:i'),
                    'max-time' => $this->_getMaxTime('H:i')
                ]
            ])
            ->addClass('form-datetimepicker');

        if ($this->_prepend === null) {
            $this->prepend('<i class="fa fa-calendar"></i>');
        }

        $this->template('datetimelocal');
        $this->_getView()->extend(['input']);
    }

}
