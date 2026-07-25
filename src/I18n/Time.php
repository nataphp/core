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

namespace Nata\I18n;

use Nata\Utility\Validation;
use Nata\Utility\Text;
use DateTimeZone;
use DateTime;
use IntlDateFormatter;
use Nata\I18n\Time\Interval;
use Nata\Core\Configure;
use Nata\I18n\Time\LocalizedDateParser;
use RuntimeException;

/**
 * Manipulation of time data.
 */
class Time extends DateTime {

/**
 * Location aware date/time format.
 * Completely specified style (Tuesday, April 12, 1952 AD or 3:30:42pm PST).
 *
 * @const string
 */
    const FULL = 'full';

/**
 * Location aware date/time format.
 * Long style (January 12, 1952 or 3:30:32pm).
 *
 * @const string
 */
    const LONG = 'long';

/**
 * Location aware date/time format.
 * Medium style (Jan 12, 1952).
 *
 * @const string
 */
    const MEDIUM = 'medium';

/**
 * Location aware date/time format.
 * Most abbreviated style, only essential data (12/13/52 or 3:30pm)
 *
 * @const string
 */
    const SHORT = 'short';

/**
 * Calendar.
 *
 * @var string
 */
    protected $_calendar = 'traditional';

/**
 * Locale.
 * If none set, will get locale from \Nata\I18n\I18n.
 *
 * @var string
 */
    protected $_locale;

/**
 * Date passed on constructor.
 *
 * @var string|int
 */
    protected $_time;

/**
 * List of existing/allowed format types for IntlDateFormatter.
 *
 * @var array
 */
    protected $_formatTypes = [
        'none',
        'short',
        'medium',
        'long',
        'full'
    ];

/**
 * List of additional modifiers for relative date formatting.
 *
 * @var array
 */
    protected $_modifierMap = [
        'last week' => 'last week monday midnight',
        'this week' => 'this week monday midnight',
        'this month' => 'first day of this month midnight',
        'this year' => 'first day of January this year midnight',
        'start of day' => 'today midnight',
        'end of day' => 'today 23:59:59',
        'start of this week' => 'this week monday midnight',
        'end of this week' => 'this week sunday 23:59:59',
        'start of week' => 'this week monday midnight',
        'end of week' => 'this week sunday 23:59:59',
        'start of next week' => 'next week monday midnight',
        'end of next week' => 'next week sunday 23:59:59',
        'start of month' => 'first day of this month midnight',
        'end of month' => 'last day of this month 23:59:59',
        'start of year' => 'first day of January this year midnight',
        'end of year' => 'last day of December this year 23:59:59',
    ];

/**
 * Holds IntlDateFormatter error code.
 *
 * @var int
 */
    protected $_formatterErrorCode;

/**
 * Holds IntlDateFormatter error message.
 *
 * @var string
 */
    protected $_formatterErrorMessage;

/**
 * Whether this instance is immutable.
 *
 * @var bool
 */
    protected $_immutable = false;

/**
 * Constructor.
 *
 * @param string|int|null $time A date/time string.
 *    Valid formats are explained in link reference below
 * @param string|DateTimeZone $timezone Timezone.
 *    If $timezone is omitted, the current timezone will be used.
 * @param array $options Additional options:
 *    - immutable: (bool) Whether to make this instance immutable
 * @link https://secure.php.net/manual/en/datetime.formats.php
 */
    public function __construct(string|int|null $time = 'now', string|DateTimeZone|null $timezone = null, array $options = []) {
        $options += [
            'locale' => null,
            'calendar' => 'traditional',
            'immutable' => false
        ];
        $this->_immutable = $options['immutable'];
        $this->_locale = $options['locale'];
        $this->_calendar = $options['calendar'];

        $time = $this->_normalizeDateTime($time);
        if (is_int($time)) {
            $time = '@' . $time;
        }

        $this->_time = $time;

        $modifier = $this->_modifierMap[$time] ?? null;
        if ($modifier) {
            $time = 'now';
        }

        if (is_string($timezone)) {
            $timezone = new DateTimeZone($timezone);
        }

        // If the time is 'now' and the now time is configured, use the configured now time
        if ($time === 'now' && Configure::read('Time.now') !== null) {
            $time = Configure::read('Time.now');
        }

        parent::__construct($time, $timezone);

        if ($modifier) {
            parent::modify($modifier);
        }
    }

/**
 * Normalize formats for DateTime.
 *
 * @param string $time Relative format
 * @return string \DateTime's supported/valid format
 */
    protected function _normalizeDateTime($time) {
        // Remove useless(?) timezone human-readable references like: '(+03)' or '(UTC)', etc
        if (is_string($time)) {
            $time = preg_replace("/([\(])(.*)([\)])/", '', $time);
        } elseif (is_null($time)) {
            $time = 'now';
        }
        return $time;
    }

/**
 * Get/set locale.
 *
 * @param string $locale Locale
 * @return string|$this Locale
 */
    public function locale($locale = null) {
        if ($locale === null) {
            if ($this->_locale === null) {
                $this->_locale = I18n::locale();
            }
            return $this->_locale;
        }
        $this->_locale = $locale;
        return $this;
    }

/**
 * Get/set immutable.
 *
 * @param string $immutable Immutable
 * @return string|$this Immutable
 */
    public function immutable($immutable = null) {
        if ($immutable === null) {
            return $this->_immutable;
        }
        $this->_immutable = $immutable;
        return $this;
    }

/**
 * Get/set calendar to use.
 *
 * @param string $calendar Calendar
 * @return string|$this Calendar
 */
    public function calendar($calendar = null) {
        if ($calendar === null) {
            return $this->_calendar;
        }
        $this->_calendar = $calendar;
        return $this;
    }

/**
 * Get/Set timezone from a string, a DateTimeZone or another Time instance.
 *
 * The Time case has to be tested first: Time extends DateTime, not DateTimeZone,
 * so it would otherwise fall into the string branch and hit new DateTimeZone(Time).
 *
 * @param string|DateTimeZone|Time $timezone Timezone string, DateTimeZone object,
 *     or a Time instance to borrow the timezone from.
 *     If null the current timezone is returned instead.
 * @return \Nata\I18n\Time|DateTimeZone Timezone object when reading, instance when setting
 */
    public function timezone($timezone = null) {
        if ($timezone === null) {
            return $this->getTimezone();
        }
        if ($timezone instanceof Time) {
            $timezone = $timezone->getTimezone();
        } elseif (!($timezone instanceof DateTimeZone)) {
            $timezone = new DateTimeZone($timezone);
        }
        return $this->setTimezone($timezone);
    }

/**
 * Modify the current instance datetime.
 *
 * This method is a wrapper for DateTime::modify() that allows for additional
 * modifiers to be used. The following modifiers are supported:
 * - last week
 * - this week
 * - this month
 * - this year
 * - start of day
 * - end of day
 * - start of this week
 * - end of this week
 * - start of week
 * - end of week
 * - start of next week
 * - end of next week
 * - start of month
 * - end of month
 * - start of year
 * - end of year
 *
 * When this instance is immutable the modification is applied to a clone, which
 * is returned still immutable so chained calls keep copying instead of silently
 * mutating the returned instance.
 *
 * @param string $modifier The modifier string
 * @return \Nata\I18n\Time|false Modified instance, or false if the modifier is invalid
 */
    public function modify(string $modifier): DateTime|false {
        if ($this->_immutable === true) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            if ($clonedInstance->modify($modifier) === false) {
                return false;
            }
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        $modifier = $this->_modifierMap[$modifier] ?? $modifier;
        return parent::modify($modifier);
    }

/**
 * Sets the date.
 * If immutable, returns a new immutable instance with the date set.
 *
 * @param int $year Year.
 * @param int $month Month.
 * @param int $day Day.
 * @return static
 */
    public function setDate(int $year, int $month, int $day): static {
        if ($this->_immutable) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            $clonedInstance->setDate($year, $month, $day);
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        return parent::setDate($year, $month, $day);
    }

/**
 * Sets the time.
 * If immutable, returns a new immutable instance with the time set.
 *
 * @param int $hour Hour.
 * @param int $minute Minute.
 * @param int $second Second.
 * @param int $microsecond Microsecond.
 * @return static
 */
    public function setTime(int $hour, int $minute, int $second = 0, int $microsecond = 0): static {
        if ($this->_immutable) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            $clonedInstance->setTime($hour, $minute, $second, $microsecond);
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        return parent::setTime($hour, $minute, $second, $microsecond);
    }

/**
 * Sets the Unix timestamp.
 * If immutable, returns a new immutable instance with the timestamp set.
 *
 * @param int $timestamp Unix timestamp.
 * @return static
 */
    public function setTimestamp(int $timestamp): static {
        if ($this->_immutable) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            $clonedInstance->setTimestamp($timestamp);
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        return parent::setTimestamp($timestamp);
    }

/**
 * Sets the timezone.
 * If immutable, returns a new immutable instance with the timezone set.
 *
 * @param \DateTimeZone $timezone Timezone.
 * @return static
 */
    public function setTimezone(DateTimeZone $timezone): static {
        if ($this->_immutable) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            $clonedInstance->setTimezone($timezone);
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        return parent::setTimezone($timezone);
    }

/**
 * Sets the ISO date.
 * If immutable, returns a new immutable instance with the ISO date set.
 *
 * @param int $year Year.
 * @param int $week ISO week number.
 * @param int $dayOfWeek Day of the week (1 = Monday, 7 = Sunday).
 * @return static
 */
    public function setISODate(int $year, int $week, int $dayOfWeek = 1): static {
        if ($this->_immutable) {
            $clonedInstance = clone $this;
            $clonedInstance->_immutable = false;
            $clonedInstance->setISODate($year, $week, $dayOfWeek);
            $clonedInstance->_immutable = true;
            return $clonedInstance;
        }
        return parent::setISODate($year, $week, $dayOfWeek);
    }

/**
 * Get/Set timestamp.
 *
 * @param int $timestamp Timestamp.
 * @return int|static Timestamp when reading, instance when setting
 */
    public function timestamp($timestamp = null) {
        if ($timestamp === null) {
            return $this->getTimestamp();
        }
        return $this->setTimestamp($timestamp);
    }

/**
 * Returns a formatted date string, given either a UNIX timestamp or a valid strtotime() date string.
 * This function also accepts a time string and a format string as first and second parameters.
 * In that case this function behaves as a wrapper for Time::i18nFormat()
 *
 * Only an empty result falls back to $default. A falsy but meaningful result
 * such as the '0' of format('L') on a non-leap year is returned as-is.
 *
 * @param string $format date format string
 * @param boolean|string $default if an invalid date is passed it will output supplied default value.
 *      Pass false if you want raw conversion value
 * @param string|null $locale Unused, kept for signature compatibility.
 * @return string Formatted date string
 * @link https://secure.php.net/manual/en/datetime.format.php
 * @link https://secure.php.net/manual/en/function.date.php
 */
    public function format($format, $default = false, ?string $locale = null): string {
        $date = parent::format($format);
        if ($date === '') {
            return (string) $default;
        }
        return $date;
    }

/**
 * Returns a localized formatted date string.
 *
 * @param string $format Date format/type supported in \IntlDateFormatter
 * @param string $default Default value to be returned if format fails
 * @return string Localized formatted date string
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
    public function intlFormat($format = 'long,short', $default = false) {
        return $this->_format($format, $default);
    }

/**
 * Alias of Time::intlFormat().
 *
 * @see Time::intlFormat()
 */
    public function i18nFormat($format = 'long,short', $default = false) {
        return $this->_format($format, $default);
    }

/**
 * Format i18n date/time.
 *
 * @param string $format Date format supported in \IntlDateFormatter
 * @param string $default Default value to be returned if format fails
 * @return string Date/time formatted
 */
    protected function _format($format, $default) {
        $format = $this->_normalizeIntlFormat($format);

        $formatter = new IntlDateFormatter(
            $this->locale(),
            $format['dateType'],
            $format['timeType'],
            $this->timezone(),
            $this->_formatterConstant($this->calendar()),
            $format['pattern']
        );

        $this->_formatterErrorCode = $formatter->getErrorCode();
        $this->_formatterErrorMessage = $formatter->getErrorMessage();
        $date = $formatter->format($this);
        return $date ? $date : $default;
    }

/**
 * Create IntlDateFormatter instance.
 *
 * @param string|array $format Date format supported in \IntlDateFormatter
 * @return \IntlDateFormatter Instance
 */
    public function _normalizeIntlFormat($format) {
        if (!is_array($format)) {
            $types = $format;
            $format = [];
            [$dateType, $timeType] = splitter($types, ',');
            if (!empty($dateType)) {
                // $dateType = LocalizedDateParser::phpToIcuPattern($dateType);
                if (!empty($timeType)) {
                    // $timeType = LocalizedDateParser::phpToIcuPattern($timeType);
                }
                if (in_array($dateType, $this->_formatTypes)) {
                    $format['dateType'] = $this->_formatterConstant($dateType);
                    if (in_array($timeType, $this->_formatTypes)) {
                        $format['timeType'] = $this->_formatterConstant($timeType);
                    }
                } else {
                    $format['pattern'] = $types;
                }
            }
        }
        $format += [
            'dateType' => IntlDateFormatter::LONG,
            'timeType' => IntlDateFormatter::SHORT,
            'pattern' => null
        ];
        return $format;
    }
/**
 * Get IntlDateFormatter constant value from given name.
 *
 * @param string $const Constant name
 * @return int Constant value
 */
    protected function _formatterConstant($const) {
        if (!is_int($const)) {
            $const = constant('\IntlDateFormatter::' . strtoupper($const));
        }
        return $const;
    }

/**
 * Returns a formatted descriptive date string for given datetime string.
 *
 * If the given date is today, the returned string could be "Today, 16:54".
 * If the given date is tomorrow, the returned string could be "Tomorrow, 16:54".
 * If the given date was yesterday, the returned string could be "Yesterday, 16:54".
 * If the given date is within next or last week, the returned string could be "On Thursday, 16:54".
 * If $dateString's year is the current year, the returned string does not
 * include mention of the year.
 *
 * @return string Described, relative date string
 * @link https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
 */
    public function niceShort($options = []) {
        $options += [
            'hourSeparator' => ', ',
            'hourFormat' => 'H:mm',
            'weekFormat' => 'eeee, H:mm',
            'thisYearFormat' => 'long',
            'longFormat' => 'long'
        ];

        if ($this->isToday()) {
            if (!$options['hourFormat']) {
                return __('Today');
            }
            return __('Today') . $options['hourSeparator'] . $this->intlFormat($options['hourFormat']);
        }

        if ($this->wasYesterday()) {
            if (!$options['hourFormat']) {
                return __('Yesterday');
            }
            return __('Yesterday') . $options['hourSeparator'] . $this->intlFormat($options['hourFormat']);
        }

        if ($this->isTomorrow()) {
            if (!$options['hourFormat']) {
                return __('Tomorrow');
            }
            return __('Tomorrow') . $options['hourSeparator'] . $this->intlFormat($options['hourFormat']);
        }

        if ($this->wasWithinLast('7 days')) {
            return $this->intlFormat($options['weekFormat']);
        }

        if ($this->isWithinNext('7 days')) {
            return __('This %s', $this->intlFormat($options['weekFormat']));
        }

        if ($this->isThisYear()) {
            return __('This %s', $this->intlFormat($options['thisYearFormat']));
        }

        return $this->intlFormat($options['longFormat']);
    }

/**
 * Nice date format.
 *
 * @return string Formatted date string
 */
    public function nice() {
        return $this->intlFormat();
    }

/**
 * Convenience method for date formatted for Atom RSS feeds.
 *
 * @return string Formatted date string
 */
    public function toAtom() {
        return $this->format(DateTime::ATOM);
    }

/**
 * Convenience method for date formatted for W3C.
 *
 * @return string Formatted date string
 */
    public function toW3c() {
        return $this->format(DateTime::W3C);
    }

/**
 * Convenience method for date formatted for RSS feeds.
 *
 * @return string Formatted date string
 */
    public function toRss() {
        return $this->format(DateTime::RSS);
    }

/**
 * Convenience method for \Nata\I18n\Time::timestamp().
 *
 * @return int UNIX timestamp
 */
    public function unix() {
        return $this->timestamp();
    }

/**
 * Return current instance datetime in UTC timestamp.
 *
 * @return int UNIX timestamp
 */
    public function utc() {
        return $this->getTimestamp();
    }

/**
 * Returns a partial SQL string to search for all records between two dates.
 *
 * @param string $fieldName Name of database field to compare with
 * @param integer|string|DateTime $begin UNIX timestamp, strtotime() valid string or DateTime object
 * @param integer|string|DateTime $end UNIX timestamp, strtotime() valid string or DateTime object
 * @param string|DateTimeZone $timezone Timezone string or DateTimeZone object
 * @return string Partial SQL string.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/time.html#TimeHelper::daysAsSql
 */
    public function daysAsSql($fieldName, $end = 'today') {
        $end = new self($end);
        $begin = $this->format('Y-m-d') . ' 00:00:00';
        $end = $end->format('Y-m-d') . ' 23:59:59';
        return "({$fieldName} >= '{$begin}') AND ({$fieldName} <= '{$end}')";
    }

/**
 * Returns true if date/time is empty.
 *
 * @return boolean True if is empty
 */
    public function isEmpty(): bool {
        if (empty($this->_time)) {
            return true;
        }
        return in_array($this->_time, ['0000-00-00', '0000-00-00 00:00:00', '0000']);
    }

/**
 * Returns true if is today.
 *
 * @return boolean True if is today
 */
    public function isToday(): bool {
        return $this->_isWithinTimeSpan('Y-m-d');
    }

/**
 * Returns true if is morning.
 *
 * @return boolean True if is morning
 */
    public function isMorning(): bool {
        return $this->_isWithinHourSpan(5, 12);
    }

/**
 * Returns true if is early morning.
 *
 * @return boolean True if is early morning
 */
    public function isEarlyMorning(): bool {
        return $this->_isWithinHourSpan(5, 8);
    }

/**
 * Returns true if is late morning.
 *
 * @return boolean True if is late morning
 */
    public function isLateMorning(): bool {
        return $this->_isWithinHourSpan(11, 12);
    }

/**
 * Returns true if is afternoon.
 *
 * @return boolean True if is afternoon
 */
    public function isAfternoon(): bool {
        return $this->_isWithinHourSpan(12, 17);
    }

/**
 * Returns true if is early afternoon.
 *
 * @return boolean True if is early afternoon
 */
    public function isEarlyAfternoon(): bool {
        return $this->_isWithinHourSpan(13, 15);
    }

/**
 * Returns true if is late afternoon.
 *
 * @return boolean True if is late afternoon
 */
    public function isLateAfternoon(): bool {
        return $this->_isWithinHourSpan(16, 17);
    }

/**
 * Returns true if is evening.
 *
 * @return boolean True if is evening
 */
    public function isEvening(): bool {
        return $this->_isWithinHourSpan(17, 21);
    }

/**
 * Returns true if is night.
 *
 * @return boolean True if is night
 */
    public function isNight(): bool {
        return $this->_isWithinHourSpan(21, 5);
    }

/**
 * Returns true if is same day as given date.
 *
 * @param string $anchor The date and time used for comparison
 * @return boolean True if is within given date's day
 */
    public function isSameDay($anchor): bool {
        return $this->_isWithinTimeSpan('Y-m-d', $anchor);
    }

/**
 * Returns true if time is in the future.
 *
 * @return boolean True if is in the future
 */
    public function isFuture(): bool {
        $timestamp = $this->timestamp();
        $dateTime = new self('now', $this->timezone());
        return $timestamp > $dateTime->getTimestamp();
    }

/**
 * Returns true if time is in the past.
 *
 * @return boolean True if is in the past
 */
    public function isPast(): bool {
        $timestamp = $this->timestamp();
        $dateTime = new self('now', $this->timezone());
        return $timestamp < $dateTime->getTimestamp();
    }

/**
 * Returns true if is date's anniversary.
 *
 * @return boolean True if today is date's anniversary
 */
    public function isAnniversary(): bool {
        return $this->_isWithinTimeSpan('m-d');
    }

/**
 * Returns true if date's week day is monday.
 *
 * @return boolean True if monday
 */
    public function isMonday(): bool {
        return $this->format('N') === '1';
    }

/**
 * Returns true if date's week day is tuesday.
 *
 * @return boolean True if tuesday
 */
    public function isTuesday(): bool {
        return $this->format('N') === '2';
    }

/**
 * Returns true if date's week day is wednesday.
 *
 * @return boolean True if wednesday
 */
    public function isWednesday(): bool {
        return $this->format('N') === '3';
    }

/**
 * Returns true if date's week day is thursday.
 *
 * @return boolean True if thursday
 */
    public function isThursday(): bool {
        return $this->format('N') === '4';
    }

/**
 * Returns true if date's week day is friday.
 *
 * @return boolean True if friday
 */
    public function isFriday(): bool {
        return $this->format('N') === '5';
    }

/**
 * Returns true if date's week day is saturday.
 *
 * @return boolean True if saturday
 */
    public function isSaturday(): bool {
        return $this->format('N') === '6';
    }

/**
 * Returns true if date's week day is sunday.
 *
 * @return boolean True if sunday
 */
    public function isSunday(): bool {
        return $this->format('N') === '7';
    }

/**
 * Returns true if time is within this week.
 *
 * @return boolean True if is within this week
 */
    public function isThisWeek(): bool {
        return $this->_isWithinTimeSpan('W o');
    }

/**
 * Returns true if time is within this week.
 *
 * @return boolean True if is within this week
 */
    public function isNextWeek(): bool {
        return $this->_isWithinTimeSpan('W o', 'next week');
    }

/**
 * Returns true if time is within this week.
 *
 * @return boolean True if is within this week
 */
    public function wasLastWeek(): bool {
        return $this->_isWithinTimeSpan('W o', 'previous week');
    }

/**
 * Returns true if is same week as given date.
 *
 * @param string $anchor The date and time used for comparison
 * @return boolean True if is within given date's week
 */
    public function isSameWeek($anchor): bool {
        return $this->_isWithinTimeSpan('W o', $anchor);
    }

/**
 * Returns true if is within this month.
 *
 * @return boolean True if is within current month
 */
    public function isThisMonth(): bool {
        return $this->_isWithinTimeSpan('m Y');
    }

/**
 * Returns true if was last month.
 *
 * @return boolean True if was last month
 */
    public function isLastMonth(): bool {
        return $this->isSameMonth(Time::now()->modify('last month'));
    }

/**
 * Returns true if is next month.
 *
 * @return boolean True if is next month
 */
    public function isNextMonth(): bool {
        return $this->isSameMonth(Time::now()->modify('next month'));
    }

/**
 * Returns true if is same month as given date/year.
 *
 * @param string $anchor The date and time used for comparison
 * @return boolean True if is within given date's month
 */
    public function isSameMonth($anchor): bool {
        return $this->_isWithinTimeSpan('m Y', $anchor);
    }

/**
 * Returns true if is within current year.
 *
 * @return boolean True if is within current year
 */
    public function isThisYear(): bool {
        return $this->_isWithinTimeSpan('Y');
    }

/**
 * Returns true if is same year as given date/year.
 *
 * @param string $anchor The date and time used for comparison
 * @return boolean True if is within given date's year
 */
    public function isSameYear($anchor): bool {
        return $this->_isWithinTimeSpan('Y', $anchor);
    }

/**
 * Returns true if was yesterday.
 *
 * @return boolean True if was yesterday
 */
    public function wasYesterday(): bool {
        return $this->_isWithinTimeSpan('Y-m-d', 'now', 'yesterday');
    }

/**
 * Returns true if is tomorrow.
 *
 * @return boolean True if was yesterday
 */
    public function isTomorrow(): bool {
        return $this->_isWithinTimeSpan('Y-m-d', 'now', 'tomorrow');
    }

/**
 * Returns true if is string passed is date.
 *
 * @return boolean True if date
 */
    public function isDate() : bool {
        if (is_numeric($this->_time)) {
            return false;
        }
        return Validation::date($this->_time, '*');
    }

/**
 * Returns true if is string passed is date and time.
 *
 * @return boolean True if date and time
 */
    public function isDatetime(): bool {
        if (is_numeric($this->_time)) {
            return true;
        }
        return Validation::datetime($this->_time, '*');
    }

/**
 * Returns true if is string passed is time.
 *
 * @return boolean True if time
 */
    public function isTime(): bool {
        if (is_numeric($this->_time)) {
            return false;
        }
        return Validation::time($this->_time, '*');
    }

/**
 * Returns true if is year.
 *
 * @return boolean True if year
 */
    public function isYear(): bool {
        if (!is_numeric($this->_time)) {
            return false;
        }
        return Validation::date($this->_time, 'Y');
    }

/**
 * Returns true if this instance falls between the given start and end dates.
 *
 * The range is half-open by default: the start boundary is included and the
 * end boundary is excluded, so consecutive ranges tile without gaps or
 * overlaps. Pass $inclusiveEnd to include the end boundary instead, which is
 * what date-only ranges usually want (an end date parses to midnight, so the
 * end day would otherwise be excluded entirely).
 *
 * Integer arguments are accepted as UNIX timestamps. They must not be left to
 * the string coercion of the parameter type, since the constructor only
 * prefixes '@' for real integers and would otherwise misparse the digits as a
 * date.
 *
 * Returns false when $start is after $end.
 *
 * @param \Nata\I18n\Time|string|int $start Start of the range.
 * @param \Nata\I18n\Time|string|int $end End of the range.
 * @param bool $inclusiveEnd Whether the end boundary counts as inside the range.
 * @return bool True if this instance is within the range
 */
    public function isBetween(Time|string|int $start, Time|string|int $end, bool $inclusiveEnd = false): bool {
        if (!($start instanceof Time)) {
            $start = new Time($start, $this->timezone());
        }

        if (!($end instanceof Time)) {
            $end = new Time($end, $this->timezone());
        }

        $startTimestamp = $start->timestamp();
        $endTimestamp = $end->timestamp();
        $timestamp = $this->timestamp();

        if ($timestamp < $startTimestamp) {
            return false;
        }

        if ($inclusiveEnd) {
            return $timestamp <= $endTimestamp;
        }
        return $timestamp < $endTimestamp;
    }

/**
 * @deprecated Use Time::isBetween() instead.
 */
    public function between(Time|string|int $start, Time|string|int $end, bool $inclusiveEnd = false): bool {
        return $this->isBetween($start, $end, $inclusiveEnd);
    }

/**
 * Evaluates if the provided date and time is within a time span.
 *
 * @param string $format The date and time format used for comparison
 * @param string $anchor The date and time used for comparison (defaults to 'now')
 * @param string $modify Modify date and time being compared
 * @return boolean True if datetime string is within the time span
 */
    protected function _isWithinTimeSpan($format, $anchor = 'now', $modify = null): bool {
        $timestamp = $this->format($format);

        $dateTime = new Time($anchor, $this->timezone());
        if ($modify) {
            $dateTime->modify($modify);
        }

        $now = $dateTime->format($format);
        return $timestamp === $now;
    }

/**
 * Evaluates if the provided hour is within a time span.
 *
 * The span is half-open: $after is included, $before is not. When $after is
 * greater than $before the span wraps past midnight (21 to 5 means 21:00-04:59),
 * so the two ends are tested separately.
 *
 * @param int $after After hour used for comparison
 * @param int $before Before hour used for comparison
 * @return boolean True if given datetime is after hour is before the hour
 */
    protected function _isWithinHourSpan($after, $before): bool {
        $hour = (int) $this->format('G');
        if ($after <= $before) {
            return $hour >= $after && $hour < $before;
        }
        return $hour >= $after || $hour < $before;
    }

/**
 * Number of years since given datetime string.
 *
 * @return int Number of years
 */
    public function ageInYears() {
        $now = new Time('now', $this->timezone());
        $interval = $now->diff($this);
        return $interval->y;
    }

/**
 * Returns number of seconds of difference between
 * now and current time instance.
 *
 * @return integer Difference in number of seconds
 */
    public function inSeconds() {
        $now = (new Time)->timestamp();
        $time = $this->timestamp();
        return $this->isPast() ? ($now - $time) : ($time - $now);
    }

/**
 * Returns number of days of difference between
 * now and current time instance.
 *
 * @return integer Difference in number of days
 */
    public function inDays() {
        return $this->diff(new Time)->format("%r%a");
    }

/**
 * Returns whether the date is a weekend day
 *
 * @return bool True if date is Saturday or Sunday
 */
    public function isWeekend(): bool {
        return in_array($this->format('N'), ['6', '7']);
    }

/**
 * Returns whether the date is a workday/business day (Mon-Fri)
 *
 * @return bool True if date is Monday through Friday
 */
    public function isWorkday(): bool {
        return !$this->isWeekend();
    }

/**
 * Add business days to the date, skipping weekends
 *
 * @param int $days Number of business days to add
 * @return \Nata\I18n\Time Modified time instance
 */
    public function addBusinessDays(int $days): Time {
        $time = clone $this;
        $time->_immutable = false;
        while ($days > 0) {
            $time->modify('+1 day');
            if ($time->isWorkday()) {
                $days--;
            }
        }
        $time->_immutable = $this->_immutable;
        return $time;
    }

/**
 * Get the quarter number and start/end dates
 *
 * @param bool $withDates Whether to include start/end dates
 * @return int|array Quarter number (1-4) or array with dates
 */
    public function quarter(bool $withDates = false) {
        // Cast is required: ceil() returns a float and match() compares with ===,
        // so a float would miss every arm below.
        $quarter = (int) ceil($this->format('m') / 3);

        if (!$withDates) {
            return $quarter;
        }

        $year = $this->format('Y');
        return match($quarter) {
            1 => ['quarter' => 1, 'start' => "{$year}-01-01", 'end' => "{$year}-03-31"],
            2 => ['quarter' => 2, 'start' => "{$year}-04-01", 'end' => "{$year}-06-30"],
            3 => ['quarter' => 3, 'start' => "{$year}-07-01", 'end' => "{$year}-09-30"],
            4 => ['quarter' => 4, 'start' => "{$year}-10-01", 'end' => "{$year}-12-31"]
        };
    }

/**
 * Returns the quarter.
 *
 * @deprecated Use Time::quarter() instead.
 */
    public function toQuarter($range = false) {
        return $this->quarter($range);
    }

/**
 * Get date/time interval between 2 dates.
 *
 * @param int|string|Time $datetime Format options
 * @return Interval
 */
    public function getInterval($datetime = 'now', $timezone = null) {
        if (is_string($datetime) && strpos($datetime, 'P') === 0) {
            return new Interval($datetime);
        }

        if (!($datetime instanceof Time)) {
            $datetime = new Time($datetime, $timezone ?? $this->timezone());
        }

        $dateInterval = parent::diff($datetime);

        $now = $datetime->timestamp();
        $time = $this->timestamp();
        $diffInSeconds = $this->isPast() ? ($now - $time) : ($time - $now);

        $duration = sprintf(
            'P%dY%dM%dDT%dH%dM%dS-%d-%d',
            $dateInterval->y,
            $dateInterval->m,
            $dateInterval->d,
            $dateInterval->h,
            $dateInterval->i,
            $dateInterval->s,
            $diffInSeconds,
            $dateInterval->days
        );

        return new Interval($duration);
    }

/**
 * Get age.
 *
 * @param array $options Format options
 * @return string Age in
 */
    public function age(array $options = []) {
        return $this->getInterval()->getText($options);
    }

/**
 * Returns either a relative or a formatted absolute date depending
 * on the difference between the current time and given datetime.
 * $datetime should be in a *strtotime* - parsable format, like MySQL's datetime datatype.
 *
 * ### Options:
 *
 * - `format` => a fall back format if the relative time is longer than the duration specified by end
 * - `accuracy` => Specifies how accurate the date should be described (array)
 *    - year =>   The format if years > 0 (default "day")
 *    - month =>  The format if months > 0 (default "day")
 *    - week =>   The format if weeks > 0 (default "day")
 *    - day =>    The format if weeks > 0 (default "hour")
 *    - hour =>   The format if hours > 0 (default "minute")
 *    - minute => The format if minutes > 0 (default "minute")
 *    - second => The format if seconds > 0 (default "second")
 * - `end` => The end of relative time telling
 * - `relativeString` => The printf compatible string when outputting relative time
 * - `absoluteString` => The printf compatible string when outputting absolute time
 *
 * Relative dates look something like this:
 *
 * - 3 weeks, 4 days ago
 * - 15 seconds ago
 *
 * Default date formatting is d/m/yy e.g: on 18/2/09
 *
 * The returned string includes 'ago' or 'on' and assumes you'll properly add a word
 * like 'Posted ' before the function output.
 *
 * NOTE: If the difference is one week or more, the lowest level of accuracy is day
 *
 * @param array $options Default format if timestamp is used in $dateString
 * @return string Relative time string.
 */
    public function timeAgoInWords(array $options = []) {
        $options += [
            'from' => 'now',
            'accuracy' => [
                'year' => "day",
                'month' => "day",
                'week' => "day",
                'day' => "hour",
                'hour' => "minute",
                'minute' => "minute",
                'second' => "second",
            ],
            'end' => '+1 month',
            'shortFormat' => false,
            'relativeStringPrefix' => null,
            'relativeString' => __('%s ago'),
            'absoluteString' => __('on %s')
        ];

        extract($options);

        $timezone = $this->timezone();

        $now = new Time($options['from'], $timezone);
        $now = $now->timestamp();

        $inSeconds = $this->timestamp();

        $backwards = ($inSeconds > $now);

        $end = new self($end, $timezone);
        $end = $end->timestamp();

        $futureTime = $now;
        $pastTime = $inSeconds;
        if ($backwards) {
            $futureTime = $inSeconds;
            $pastTime = $now;
        }

        $diff = $futureTime - $pastTime;

        if (!$diff) {
            return __('just now');
        }

        if ($diff > abs($now - $end)) {
            return sprintf($absoluteString, $this->intlFormat());
        }

        // If more than a week, then take into account the length of months
        if ($diff >= 604800) {
            list($future['H'], $future['i'], $future['s'], $future['d'], $future['m'], $future['Y']) = explode('/', date('H/i/s/d/m/Y', $futureTime));

            list($past['H'], $past['i'], $past['s'], $past['d'], $past['m'], $past['Y']) = explode('/', date('H/i/s/d/m/Y', $pastTime));
            $years = $months = $weeks = $days = $hours = $minutes = $seconds = 0;

            $years = $future['Y'] - $past['Y'];
            $months = $future['m'] + ((12 * $years) - $past['m']);

            if ($months >= 12) {
                $years = floor($months / 12);
                $months = $months - ($years * 12);
            }

            if ($future['m'] < $past['m'] && $future['Y'] - $past['Y'] === 1) {
                $years--;
            }

            if ($future['d'] >= $past['d']) {
                $days = $future['d'] - $past['d'];
            } else {
                $daysInPastMonth = date('t', $pastTime);
                $daysInFutureMonth = date('t', mktime(0, 0, 0, $future['m'] - 1, 1, $future['Y']));

                if (!$backwards) {
                    $days = ($daysInPastMonth - $past['d']) + $future['d'];
                } else {
                    $days = ($daysInFutureMonth - $past['d']) + $future['d'];
                }

                if ($future['m'] != $past['m']) {
                    $months--;
                }
            }

            if (!$months && $years >= 1 && $diff < ($years * 31536000)) {
                $months = 11;
                $years--;
            }

            if ($months >= 12) {
                $years = $years + 1;
                $months = $months - 12;
            }

            if ($days >= 7) {
                $weeks = floor($days / 7);
                $days = $days - ($weeks * 7);
            }
        } else {
            $years = $months = $weeks = 0;
            $days = floor($diff / 86400);

            $diff = $diff - ($days * 86400);

            $hours = floor($diff / 3600);
            $diff = $diff - ($hours * 3600);

            $minutes = floor($diff / 60);
            $diff = $diff - ($minutes * 60);
            $seconds = $diff;
        }

        $fWord = $accuracy['second'];
        if ($years > 0) {
            $fWord = $accuracy['year'];
        } elseif (abs($months) > 0) {
            $fWord = $accuracy['month'];
        } elseif (abs($weeks) > 0) {
            $fWord = $accuracy['week'];
        } elseif (abs($days) > 0) {
            $fWord = $accuracy['day'];
        } elseif (abs($hours) > 0) {
            $fWord = $accuracy['hour'];
        } elseif (abs($minutes) > 0) {
            $fWord = $accuracy['minute'];
        }

        $fNum = str_replace([
            'year', 'month', 'week', 'day', 'hour', 'minute', 'second'
        ], [
            1, 2, 3, 4, 5, 6, 7
        ], $fWord);

        $relativeDate = '';
        if ($fNum >= 1 && $years > 0) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d year', '%d years', $years, $years);
        }
        if ($fNum >= 2 && $months > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d month', '%d months', $months, $months);
        }
        if ($fNum >= 3 && $weeks > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d week', '%d weeks', $weeks, $weeks);
        }
        if ($fNum >= 4 && $days > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d day', '%d days', $days, $days);
        }
        if ($fNum >= 5 && $hours > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d hour', '%d hours', $hours, $hours);
        }
        if ($fNum >= 6 && $minutes > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d minute', '%d minutes', $minutes, $minutes);
        }
        if ($fNum >= 7 && $seconds > 0 && (!$shortFormat || empty($relativeDate))) {
            $relativeDate .= ($relativeDate ? ', ' : '') . __n('%d second', '%d seconds', $seconds, $seconds);
        }

        // When time has passed
        if (!$backwards && $relativeDate) {
            $relativeTime = '';
            if ($relativeStringPrefix) {
                $relativeTime = $relativeStringPrefix . ' ';
            }
            $relativeTime .= sprintf($relativeString, $relativeDate);
            return $relativeTime;
        }

        if (!$backwards) {
            $aboutAgo = [
                'second' => __('about a second ago'),
                'minute' => __('about a minute ago'),
                'hour' => __('about an hour ago'),
                'day' => __('about a day ago'),
                'week' => __('about a week ago'),
                'year' => __('about a year ago')
            ];
            return $aboutAgo[$fWord];
        }

        // When time is to come
        if (!$relativeDate) {
            $aboutIn = [
                'second' => __('in about a second'),
                'minute' => __('in about a minute'),
                'hour' => __('in about an hour'),
                'day' => __('in about a day'),
                'week' => __('in about a week'),
                'year' => __('in about a year')
            ];
            return $aboutIn[$fWord];
        }

        return $relativeDate;
    }

/**
 * Returns true if is was within the interval specified, else false.
 *
 * @param string|integer $timeInterval the numeric value with space then time type.
 *    Example of valid types: 6 hours, 2 days, 1 minute.
 * @return boolean
 * @uses \Nata\I18n\Time::_within()
 */
    public function wasWithinLast($timeInterval) {
        return $this->_within('-' . $timeInterval, function ($date, $interval, $now) {
            return $date >= $interval && $date <= $now;
        });
    }

/**
 * Returns true if specified datetime is within the interval specified, else false.
 *
 * @param string|integer $timeInterval the numeric value with space then time type.
 *    Example of valid types: 6 hours, 2 days, 1 minute.
 * @return boolean
 * @uses \Nata\I18n\Time::_within()
 */
    public function isWithinNext($timeInterval) {
        return $this->_within('+' . $timeInterval, function ($date, $interval, $now) {
            return $date <= $interval && $date >= $now;
        });
    }

/**
 * Normalize data for within comparison.
 *
 * @param string|integer $timeInterval the numeric value with space then time type.
 *    Example of valid types: 6 hours, 2 days, 1 minute.
 * @param callable $callable Callable to handle the comparison
 * @return boolean
 */
    protected function _within($timeInterval, $callable) {
        $tmp = str_replace(' ', '', $timeInterval);
        if (is_numeric($tmp)) {
            $timeInterval = $tmp . ' days';
        }
        $timezone = $this->timezone();
        $date = $this->timestamp();
        $interval = new Time($timeInterval, $timezone);
        $now = new self('now', $timezone);
        return $callable($date, $interval->timestamp(), $now->timestamp());
    }

/**
 * Get list of timezone identifiers.
 *
 * @param integer|string $filter A regex to filter identifer
 *     Or one of DateTimeZone class constants
 * @param string $country A two-letter ISO 3166-1 compatible country code.
 *     This option is only used when $filter is set to DateTimeZone::PER_COUNTRY
 * @param boolean $group If true (default value) groups the identifiers list by primary region
 * @return array List of timezone identifiers
 */
    public function listTimezones($filter = null, $country = null) {
        $regex = null;

        if (is_string($filter)) {
            $regex = $filter;
            $filter = null;
        }

        if ($filter === null) {
            $filter = DateTimeZone::ALL;
        }

        $identifiers = DateTimeZone::listIdentifiers($filter, $country);
        if ($regex) {
            foreach ($identifiers as $key => $tz) {
                if (!preg_match($regex, $tz)) {
                    unset($identifiers[$key]);
                }
            }
        }
        return array_combine($identifiers, $identifiers);
    }

/**
 * Get list of timezone identifiers grouped by primary region.
 *
 * @see Time::listTimezones()
 * @return array List of timezone identifiers grouped
 */
    public function listTimezonesGrouped($filter = null, $country = null) {
        $identifiers = $this->listTimezones($filter, $country);
        $groupedIdentifiers = [];
        foreach ($identifiers as $key => $tz) {
            [$region, $city] = splitter($tz, '/');

            if ($city) {
                $groupedIdentifiers[$region][$tz] = $city;
            } else {
                $groupedIdentifiers[$region] = [$tz => $region];
            }
        }
        return $groupedIdentifiers;
    }

/**
 * Get list of timezone identifiers with offset and current time on each.
 *
 * @param integer|string $filter A regex to filter identifer
 *     Or one of DateTimeZone class constants
 * @param string $country A two-letter ISO 3166-1 compatible country code.
 *     This option is only used when $filter is set to DateTimeZone::PER_COUNTRY
 * @param string $format String with placeholders to format
 * @see Time::listTimezones()
 * @return array List of timezone identifiers with offset
 */
    public function listTimezonesWithOffset($filter = null, $country = null, $format = ':identifier - (UTC :offset) - :time') {
        $identifiers = $this->listTimezones($filter, $country);

        $offsets = [];
        foreach ($identifiers as $timezone) {
            $tz = new DateTimeZone($timezone);
            $offsets[$timezone] = $tz->getOffset(new DateTime);
        }

        // Sort timezone by offset
        arsort($offsets);

        $identifiers = [];
        foreach ($offsets as $timezone => $offset) {
            $formatted = gmdate('H:i', abs($offset));

            $prefix = '';
            if ($offset !== 0) {
                $prefix = $offset > 0 ? '+' : '-';
            }

            $formattedOffset = sprintf('%s%s', $prefix, $formatted);

            $now = (new Time('now', $timezone))->format('G:i');

            $identifiers[$timezone] = Text::insert($format, [
                'identifier' => $timezone,
                'offset' => $formattedOffset,
                'time' => $now
            ]);
        }

        unset($offsets);

        return $identifiers;
    }

/**
 * __toString.
 *
 * @return string ISO 8601 date
 */
    public function __toString() {
        return $this->format('c');
    }

/**
 * __debugInfo.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'calendar' => $this->_calendar,
            'locale' => $this->_locale,
            'time' => $this->format('c'),
            'timezone' => $this->timezone()
        ];
    }

/**
 * Create \Nata\I18n\Time instance.
 *
 * @param string $time A date/time string.
 *    Valid formats are explained in link reference below
 * @param string|DateTimeZone $timezone Timezone.
 *    If $timezone is omitted, the current timezone will be used.
 * @link https://secure.php.net/manual/en/datetime.formats.php
 */
    public static function create($time = 'now', $timezone = null) {
        return new Time($time, $timezone);
    }

/**
 * Create \Nata\I18n\Time instance.
 *
 * @param string $time A date/time string.
 *    Valid formats are explained in link reference below
 * @param string|DateTimeZone $timezone Timezone.
 *    If $timezone is omitted, the current timezone will be used.
 * @link https://secure.php.net/manual/en/datetime.formats.php
 */
    public static function now($timezone = null) {
        return new Time('now', $timezone);
    }

/**
 * List each calendar date from $startDate through $endDate inclusive as Y-m-d strings.
 *
 * When $startDate is after $endDate, returns an empty list.
 *
 * @param string $startDate Start date (Y-m-d).
 * @param string $endDate End date (Y-m-d).
 * @return list<string>
 */
    public static function datesInRange(string $startDate, string $endDate): array {
        $dates = [];
        $cursor = (new self($startDate))->immutable(true);
        $end = (new self($endDate))->immutable(true);
        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

}
