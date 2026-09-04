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

use Nata\I18n\Time\LocalizedDateParser;
use Nata\Utility\Validation;

/**
 * Time element.
 */
class Time extends BaseTime {

/**
 * Default time format.
 *
 * @var string
 */
    protected $_format = 'H:i';


/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!Validation::time($data)) {
            $this->_error = __('Must be a valid time in the format %s.', [$this->_format]);
        }
        return $this->_error === null;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        $this->_getView()->extend(array('input'));
		/*
		 * An empty value stays empty. strtotime('') is false and date() reads
		 * that as the epoch, so coercing it produced 00:00 -- which means an
		 * optional time field could never actually be left blank, and "no time"
		 * was indistinguishable from midnight.
		 */
		if ($this->value() !== null && trim((string)$this->_value) !== '') {
			$this->_value = date($this->_format, strtotime($this->_value));
		}
        parent::beforeRender($event);
        $this->attributes()
            ->set(array(
                'autocomplete' => 'off',
                'step' => $this->step(),
                'min' => $this->_min,
                'max' => $this->_max,
                'format' => $this->_format,
                'data' => array(
                    'autoclose' => true,
                    'format' => $this->_format,
                    'font-awesome' => true,
					'allowTimes' => $this->_allowTimes(),
                    //'min-time' => $this->_getMinTime(),
                    //'max-time' => $this->_getMaxTime()
                )
            ))
            ->addClass('form-datetimepicker');

        if ($this->_prepend === null) {
            $this->prepend('<i class="fa fa-clock-o"></i>');
        }

    }

}
