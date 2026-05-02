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
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\I18n\Time;

use Nata\Utility\Text;
use DateInterval;

/**
 * Represents a date interval.
 */
class Interval extends DateInterval {

/**
 * Total years.
 *
 * @var int
 */
    protected $_totalYears;

/**
 * Total months.
 *
 * @var int
 */
    protected $_totalWeeks;

/**
 * Total months.
 *
 * @var int
 */
    protected $_totalMonths;

/**
 * Total days.
 *
 * @var int
 */
    protected $_totalDays;

/**
 * Total hours.
 *
 * @var int
 */
    protected $_totalHours;

/**
 * Total seconds.
 *
 * @var int
 */
    protected $_totalSeconds;

/**
 * Get years.
 *
 * @return int Years
 */
    public function __construct(string $duration) {
        if (str_contains($duration, '-')) {
            [$duration, $diffSeconds, $totalDays] = explode('-', $duration, 3);
            $this->_totalSeconds = $diffSeconds;
            $this->_totalDays = $totalDays;
        }
        parent::__construct($duration);
    }

/**
 * Get years.
 *
 * @return int Years
 */
    public function getYears() {
        return $this->y;
    }

/**
 * Get months.
 *
 * @return int Months
 */
    public function getMonths() {
        return $this->m;
    }

/**
 * Get days.
 *
 * @return int Days
 */
    public function getDays() {
        return $this->d;
    }

/**
 * Get hours.
 *
 * @return int Hours
 */
    public function getHours() {
        return $this->h;
    }

/**
 * Get minutes.
 *
 * @return int Minutes
 */
    public function getMinutes() {
        return $this->i;
    }

/**
 * Get seconds.
 *
 * @return int Seconds
 */
    public function getSeconds() {
        return $this->s;
    }

/**
 * Get number of microseconds, as a fraction of a second.
 *
 * @return int Microseconds
 */
    public function getMicroseconds() {
        return $this->f;
    }

/**
 * Get the total number of full years between the start and end dates.
 *
 * @return float Total years
 */
    public function getInYears() {
        if ($this->_totalYears === null) {
            $this->_totalYears = $this->getInDays() / 365;
        }
        return $this->_totalYears;
    }

/**
 * Get the total number of full months between the start and end dates.
 *
 * @return float Total months
 */
    public function getInMonths() {
        if ($this->_totalMonths === null) {
            $this->_totalMonths = $this->getInDays() / 30;
        }
        return $this->_totalMonths;
    }

/**
 * Get the total number of full weeks between the start and end dates.
 *
 * @return float Total weeks
 */
    public function getInWeeks() {
        if ($this->_totalWeeks === null) {
            $this->_totalWeeks = $this->getInDays() / 7;
        }
        return $this->_totalWeeks;
    }

/**
 * Get the total number of full days between the start and end dates.
 *
 * @return int Total days
 */
    public function getInDays() {
        return $this->_totalDays;
    }

/**
 * Get the total number of full hours between the start and end dates.
 *
 * @return int Total hours
 */
    public function getInHours() {
        if ($this->_totalHours === null) {
            $this->_totalHours = round($this->getInSeconds() / 60, 0);
        }
        return $this->_totalHours;
    }

/**
 * Get the total number of seconds.
 *
 * @return int Total seconds
 */
    public function getInSeconds() {
        return $this->_totalSeconds;
    }

/**
 * Get interval as text.
 *
 * @param array $options Formating options
 * @return string Total days
 */
    public function getText(array $options = []) {
        $options += [
            'shorterText' => false,
            'format' => ':years :months :days :hours :minutes :seconds'
        ];

        $age = [
            'years' => '',
            'months' => '',
            'days' => '',
            'hours' => '',
            'minutes' => '',
            'seconds' => ''
        ];

        $shorterText = $options['shorterText'];
        if ($this->y) {
            $age['years'] = $shorterText ?
                __x('nata_time_interval_years', '%dy', $this->y)
                : __xn('nata_time_interval', '%d year', '%d years', $this->y, $this->y);
        }

        if ($this->m) {
            $age['months'] = $shorterText ?
                __x('nata_time_interval_months', '%dm', $this->m)
                 : __xn('nata_time_interval', '%d month', '%d months', $this->m, $this->m);
        }

        if ($this->d) {
            $age['days'] = $shorterText ?
                __x('nata_time_interval_days', '%dd', $this->d)
                 : __xn('nata_time_interval', '%d day', '%d days', $this->d, $this->d);
        }

        if ($this->h) {
            $age['hours'] = $shorterText ?
                __xn('nata_time_interval_hours', '%dhr', '%dhrs', $this->h, $this->h)
                 : __xn('nata_time_interval', '%d hour', '%d hours', $this->h, $this->h);
        }

        if ($this->i) {
            $age['minutes'] = $shorterText ?
                __xn('nata_time_interval_minutes', '%dmin', '%dmins', $this->i, $this->i)
                 : __xn('nata_time_interval', '%d minute', '%d minutes', $this->i, $this->i);
        }

        $age['seconds'] = $shorterText ?
        __xn('nata_time_interval_seconds', '%dseg', '%dsegs', $this->s, $this->s)
             : __xn('nata_time_interval', '%d second', '%d seconds', $this->s, $this->s);

        return trim(Text::insert($options['format'], $age));
    }

}
