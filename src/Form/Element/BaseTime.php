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

/**
 * Base class for time elements (date, datetime, etc).
 */
class BaseTime extends Input {

/**
 * Default date & time format.
 *
 * @var string
 */
    protected $_format = 'Y-m-d H:i';

/**
 * Local timezone.
 *
 * @var string
 */
    protected $_timezone;

/**
 * Time step (in minutes).
 *
 * @var int
 */
    protected $_step;

/**
 * Regular expression.
 *
 * @var string
 */
    protected $_regex;

/**
 * Disable past time/dates.
 *
 * @var boolean
 */
    protected $_disabledPast;

/**
 * Disable future time/dates.
 *
 * @var boolean
 */
    protected $_disabledFuture;

/**
 * Minimum date & time.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_min;

/**
 * Maximum date & time.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_max;

/**
 * Minimum date.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_minDate;

/**
 * Maximum date.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_maxDate;

/**
 * Minimum date.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_minTime;

/**
 * Maximum date.
 *
 * @var \Nata\I18n\Time|string
 */
    protected $_maxTime;


/**
 * Pseudo-constructor.
 */
    public function initialize($config) {
        parent::initialize($config);
        $config += array(
            'regex' => null,
            'timezone' => null,
            'format' => null,
            'min' => null,
            'max' => null,
            'minDate' => null,
            'maxDate' => null,
            'minTime' => null,
            'maxTime' => null,
            'disabledPast' => null,
            'disabledFuture' => null,
            'step' => null
        );

        if ($config['min'] !== null) {
            $this->_min = $config['min'];
        }

        if ($config['max'] !== null) {
            $this->_max = $config['max'];
        }

        if ($config['minDate'] !== null) {
            $this->_minDate = $config['minDate'];
        }

        if ($config['maxDate'] !== null) {
            $this->_maxDate = $config['maxDate'];
        }

        if ($config['minTime'] !== null) {
            $this->_minTime = $config['minTime'];
        }

        if ($config['maxTime'] !== null) {
            $this->_maxTime = $config['maxTime'];
        }

        if ($config['regex']) {
            $this->_regex = $config['regex'];
        }

        if ($config['timezone']) {
            $this->_timezone = $config['timezone'];
        }

        if ($config['format']) {
            $this->_format = $config['format'];
        }

        if ($config['step']) {
            $this->step($config['step']);
        }

        if ($config['disabledPast'] !== null) {
            $this->_disabledPast = $config['disabledPast'];
        }

        if ($config['disabledFuture'] !== null) {
            $this->_disabledFuture = $config['disabledFuture'];
        }

    }

/**
 * Get/Set user timezone for local time conversion.
 *
 * @param string $timezone Timezone
 * @return $this|string
 */
    public function timezone($timezone = null) {
        return $this->_property('_timezone', $timezone);
    }

/**
 * Get/Set time step in seconds.
 *
 * @param int $step Minute interval in allowed times
 * @return $this|int
 */
    public function step($step = null) {
        if (is_numeric($step)) {
            $step = (int)$step;
            if ($step <= 30) {
                $step = $step * 60;
            }
        }
        return $this->_property('_step', $step);
    }

/**
 * Get/Set minimum date.
 *
 * @param \Nata\I18n\Time $minDate Minimum date
 * @return \Nata\I18n\Time|$this|string
 */
    public function minDate($minDate = null) {
        if ($minDate === null) {
            return $this->_property('_minDate');
        }
        return $this->_property('_minDate', $minDate);
    }

/**
 * Get/Set maximum date.
 *
 * @param \Nata\I18n\Time $maxDate Maximum date
 * @return \Nata\I18n\Time|$this|string
 */
    public function maxDate($maxDate = null) {
        if ($maxDate === null) {
            return $this->_property('_maxDate');
        }
        return $this->_property('_maxDate', $maxDate);
    }

/**
 * Get/Set minimum time.
 *
 * @param \Nata\I18n\Time $minDate Minimum time
 * @return \Nata\I18n\Time|$this|string
 */
    public function minTime($minTime = null) {
        if ($minTime === null) {
            return $this->_property('_minTime');
        }
        return $this->_property('_minTime', $minTime);
    }

/**
 * Get/Set maximum time.
 *
 * @param \Nata\I18n\Time $maxDate Maximum time
 * @return \Nata\I18n\Time|$this|string
 */
    public function maxTime($maxTime = null) {
        if ($maxTime === null) {
            return $this->_property('_maxTime');
        }
        return $this->_property('_maxTime', $maxTime);
    }

/**
 * Generate list of allowed times.
 *
 * @return array
 */
    protected function _allowTimes() {
        $times = [];
        $stepInMinutes = $this->_stepInMinutes();
        $minTime = (int)$this->_getMinTime('H');
        $maxTime = (int)$this->_getMaxTime('H');

        for ($i = 0; $i <= 23; $i++) {
            if (($this->_maxTime && $i > $maxTime) || ($this->_minTime && $i < $minTime)) {
                continue;
            }

            if ($this->_step && (!$this->_maxTime || $i < $maxTime)) {
                for ($j = 0; $j < 60; $j += $stepInMinutes) {
                    if ($j === 0) {
                        $j = '00';
                    } elseif ($j < 10) {
                        $j = '0' . $j;
                    }
                    $times[] = $i . ':' . $j;
                }
            } else {
                $times[] = $i . ':00';
            }

        }

        return $times;
    }

/**
 * Get step in minutes.
 *
 * @return int
 */
    protected function _stepInMinutes() {
        $step = (int)$this->_step;
        if ($step > 30) {
            $step = $step / 60;
        }
        return $step;
    }

/**
 * Get minimum date.
 *
 * @param string $format Time format
 * @return string Date/Time formatted
 */
    protected function _getMinDate($format) {
        if ($this->_disabledPast === null && $this->_min === null && $this->_minDate === null) {
            return;
        }

        if ($this->_isHtmlElement($this->_min)) {
            return $this->_min;
        } elseif ($this->_isHtmlElement($this->_minDate)) {
            return $this->_minDate;
        }

		if ($this->_disabledPast) {
			$this->_min = (new Time('now', 'UTC'))->modify('00:00:00');
		}

        $now = $this->_getTimeInstance($this->_min);

		return $now->format($format);
    }

/**
 * Get maximum date.
 *
 * @param string $format Time format
 * @return string Date/Time formatted
 */
    protected function _getMaxDate($format) {
        if ($this->_disabledFuture === null && $this->_max === null && $this->_maxDate === null) {
            return;
        }

        if ($this->_isHtmlElement($this->_max)) {
            return $this->_max;
        } elseif ($this->_isHtmlElement($this->_maxDate)) {
            return $this->_maxDate;
        }

		if ($this->_disabledFuture) {
			$this->_max = 'now';
		}

        $now = $this->_getTimeInstance($this->_max);

		return $now->format($format);
    }

/**
 * Get minimum time.
 *
 * @param string $format Time format
 * @return string Date/Time formatted
 */
    protected function _getMinTime($format) {
        $minTime = $this->_minTime;

        if ($minTime instanceof Time) {
            $minTime = $minTime->format('H:i');
        }

		return $minTime;
    }

/**
 * Get maximum time.
 *
 * @param string $format Time format
 * @return string Date/Time formatted
 */
    protected function _getMaxTime($format) {
        $maxTime = $this->_maxTime;
        if ($maxTime === null) {
            return;
        }

        if ($maxTime instanceof Time) {
            $maxTime = $maxTime->format('H:i');
        }

        $maxTime = new Time($maxTime, 'UTC');

		return $maxTime->modify('1 minute')->format($format);
    }

/**
 * Get Time instance.
 *
 * @param string $time Time/Date
 * @return \Nata\I18n\Time
 */
    protected function _getTimeInstance($time = 'now', $timezone = 'UTC') {
		if (!($time instanceof Time)) {
			$time = new Time($time, $timezone);
		}

        if ($timezone !== $this->_timezone) {
            $time->timezone($this->_timezone);
        }

        return $time;
    }

/**
 * Check if given string is an HTML selector.
 *
 * @param string $string HTML elem selector
 * @return bool True if it is, false otherwise
 */
    protected function _isHtmlElement($string) {
        return is_string($string) && in_array(substr($string, 0, 1), ['.', '#']);
    }

}
