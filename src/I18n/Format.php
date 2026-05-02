<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
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

use BadMethodCallException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use Nata\Core\Configure;
use NumberFormatter;

/**
 * Format.
 *
 * Thin wrapper around PHP's intl extension. Locale is implicit (from I18n::locale());
 * callers pass only the value to format and optional config.
 */
class Format {

/**
 * Get locale for intl (converts I18n format to intl format).
 *
 * @return string Locale string for intl
 */
    protected static function locale(): string {
        $locale = I18n::locale();
        return str_replace('-', '_', (string)$locale);
    }

/**
 * Format a number.
 *
 * ## Examples
 *
 * ```php
 * Format::number(1234567890);
 * // 1,234,567,890
 * ```
 *
 * ```php
 * Format::number(1234567890, ['decimals' => 2]);
 * // 1,234,567,890.00
 * ```
 *
 * ```php
 * Format::number(1234567890, ['pattern' => '###,###.##']);
 * // 1234567890.00
 * ```
 *
 * ```php
 * Format::number(1234567890, ['pattern' => '###,###.##', 'decimals' => 2]);
 * // 1,234,567,890.00
 * ```
 *
 * @param float $value Number to format
 * @param array $config Options: decimals (int), pattern (string ICU pattern)
 * @return string Formatted number
 * @throws BadMethodCallException When intl extension is not available
 */
    public static function number(float $value, array $config = []): string {
        if (!class_exists(NumberFormatter::class)) {
            throw new BadMethodCallException('Format::number() requires intl extension.');
        }

        $locale = static::locale();
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        if (isset($config['decimals'])) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, (int)$config['decimals']);
        }
        if (isset($config['pattern'])) {
            $formatter->setPattern($config['pattern']);
        }

        $result = $formatter->format($value);
        return $result !== false ? $result : (string)$value;
    }

/**
 * Format a number as currency.
 *
 * ## Examples
 *
 * ```php
 * Format::currency(1234567890);
 * // 1,234,567,890 EUR
 * ```
 *
 * ```php
 * Format::currency(1234567890, 'USD');
 * // 1,234,567,890 USD
 * ```
 *
 * ```php
 * Format::currency(1234567890, 'USD', ['decimals' => 2]);
 * // 1,234,567,890.00 USD
 * ```
 *
 * ```php
 * Format::currency(1234567890, 'USD', ['pattern' => '###,###.##']);
 * // 1,234,567,890.00 USD
 * ```
 * @param float $value Amount to format
 * @param string $currency Currency code (e.g. EUR, USD)
 * @param array $config Options: decimals (int)
 * @return string Formatted currency
 * @throws BadMethodCallException When intl extension is not available
 */
    public static function currency(float $value, string $currency = 'EUR', array $config = []): string {
        if (!class_exists(NumberFormatter::class)) {
            throw new BadMethodCallException('Format::currency() requires intl extension.');
        }

        $locale = static::locale();
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($config['decimals'])) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, (int)$config['decimals']);
        }

        $result = $formatter->formatCurrency($value, $currency);
        return $result !== false ? $result : (string)$value;
    }

/**
 * Normalize value to DateTimeInterface.
 *
 * @param DateTimeInterface|string|int $value
 * @param DateTimeZone|string|null $timezone
 * @return DateTimeInterface|null
 */
    protected static function _toDateTime($value, $timezone = null): ?DateTimeInterface {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if (is_int($value)) {
            $dt = new DateTime('@' . $value);
            if ($timezone) {
                $tz = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
                $dt->setTimezone($tz);
            }
            return $dt;
        }
        if (is_string($value)) {
            try {
                $dt = $timezone
                    ? new DateTime($value, $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone))
                    : new DateTime($value);
                return $dt;
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

/**
 * Get IntlDateFormatter constant from preset string.
 *
 * @param string $preset short, medium, long, full, or none
 * @return int
 */
    protected static function _dateFormatterConstant(string $preset): int {
        $map = [
            'none' => IntlDateFormatter::NONE,
            'short' => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
        ];
        $key = strtolower($preset);
        return $map[$key] ?? IntlDateFormatter::MEDIUM;
    }

/**
 * Format a date.
 *
 * ## Examples
 *
 * ```php
 * Format::date(1234567890);
 * // 2009-02-13
 * ```
 *
 * ```php
 * Format::date(1234567890, 'Europe/Lisbon');
 * // 2009-02-13 Europe/Lisbon
 * ```
 *
 * ```php
 * Format::date(1234567890, ['pattern' => 'd/M/Y']);
 * // 13/2/2009
 * ```
 *
 * ```php
 * Format::date(1234567890, ['pattern' => 'd/M/Y', 'dateType' => 'short']);
 * // 13/2/2009
 * ```
 *
 * ```php
 * Format::date(1234567890, ['pattern' => 'd/M/Y', 'dateType' => 'short', 'timezone' => 'Europe/Lisbon']);
 * // 13/2/2009 Europe/Lisbon
 * ```
 *
 * @param DateTimeInterface|string|int $value Date to format
 * @param array $config Options: pattern (string), dateType (short/medium/long/full), timezone (string|DateTimeZone)
 * @return string Formatted date, or empty string on failure
 * @throws BadMethodCallException When intl extension is not available
 */
    public static function date($value, array $config = []): string {
        if (!class_exists(IntlDateFormatter::class)) {
            throw new BadMethodCallException('Format::date() requires intl extension.');
        }

        $timezone = $config['timezone'] ?? Configure::read('App.timezone');
        $dt = static::_toDateTime($value, $timezone);
        if (!$dt) {
            return '';
        }

        $dateType = static::_dateFormatterConstant($config['dateType'] ?? 'long');
        $timeType = IntlDateFormatter::NONE;
        $pattern = $config['pattern'] ?? null;

        $tz = $timezone instanceof DateTimeZone ? $timezone : ($timezone ? new DateTimeZone($timezone) : $dt->getTimezone());
        $formatter = new IntlDateFormatter(
            static::locale(),
            $dateType,
            $timeType,
            $tz,
            IntlDateFormatter::GREGORIAN,
            $pattern ?? null
        );

        $result = $formatter->format($dt);
        return $result !== false ? $result : '';
    }

/**
 * Format a time.
 *
 * ## Examples
 *
 * ```php
 * Format::time(1234567890);
 * // 23:31:30
 * ```
 *
 * ```php
 * Format::time(1234567890, 'Europe/Lisbon');
 * // 23:31:30 Europe/Lisbon
 * ```
 *
 * ```php
 * Format::time(1234567890, ['pattern' => 'H:i:s']);
 * // 23:31:30
 * ```
 *
 * ```php
 * Format::time(1234567890, ['pattern' => 'H:i:s', 'timeType' => 'short']);
 * // 23:31:30
 * ```
 *
 * ```php
 * Format::time(1234567890, ['pattern' => 'H:i:s', 'timeType' => 'short', 'timezone' => 'Europe/Lisbon']);
 * // 23:31:30 Europe/Lisbon
 * ```
 *
 * @param DateTimeInterface|string|int $value Time to format
 * @param array $config Options: pattern (string), timeType (short/medium/long/full), timezone (string|DateTimeZone)
 * @return string Formatted time, or empty string on failure
 * @throws BadMethodCallException When intl extension is not available
 */
    public static function time($value, array $config = []): string {
        if (!class_exists(IntlDateFormatter::class)) {
            throw new BadMethodCallException('Format::time() requires intl extension.');
        }

        $timezone = $config['timezone'] ?? Configure::read('App.timezone');
        $dt = static::_toDateTime($value, $timezone);
        if (!$dt) {
            return '';
        }

        $dateType = IntlDateFormatter::NONE;
        $timeType = static::_dateFormatterConstant($config['timeType'] ?? 'short');
        $pattern = $config['pattern'] ?? null;

        $tz = $timezone instanceof DateTimeZone ? $timezone : ($timezone ? new DateTimeZone($timezone) : $dt->getTimezone());
        $formatter = new IntlDateFormatter(
            static::locale(),
            $dateType,
            $timeType,
            $tz,
            IntlDateFormatter::GREGORIAN,
            $pattern ?? null
        );

        $result = $formatter->format($dt);
        return $result !== false ? $result : '';
    }

/**
 * Format a date and time.
 *
 * ## Examples
 *
 * ```php
 * Format::datetime(1234567890);
 * // 2009-02-13 23:31:30
 * ```
 *
 * ```php
 * Format::datetime(1234567890, 'Europe/Lisbon');
 * // 2009-02-13 23:31:30 Europe/Lisbon
 * ```
 *
 * ```php
 * Format::datetime(1234567890, ['pattern' => 'd/M/Y H:i:s', 'dateType' => 'short', 'timeType' => 'short', 'timezone' => 'Europe/Lisbon']);
 * // 13/2/2009 23:31:30 Europe/Lisbon
 * ```
 *
 * ```php
 * Format::datetime(1234567890, ['pattern' => 'd/M/Y H:i:s', 'dateType' => 'short', 'timeType' => 'short', 'timezone' => 'Europe/Lisbon']);
 * // 13/2/2009 23:31:30 Europe/Lisbon
 * ```
 *
 * @param DateTimeInterface|string|int $value Date/time to format
 * @param array $config Options: pattern (string), dateType (short/medium/long/full), timeType (short/medium/long/full), timezone (string|DateTimeZone)
 * @return string Formatted date and time, or empty string on failure
 * @throws BadMethodCallException When intl extension is not available
 */
    public static function datetime($value, array $config = []): string {
        if (!class_exists(IntlDateFormatter::class)) {
            throw new BadMethodCallException('Format::datetime() requires intl extension.');
        }

        $timezone = $config['timezone'] ?? Configure::read('App.timezone');
        $dt = static::_toDateTime($value, $timezone);
        if (!$dt) {
            return '';
        }

        $dateType = static::_dateFormatterConstant($config['dateType'] ?? 'long');
        $timeType = static::_dateFormatterConstant($config['timeType'] ?? 'short');
        $pattern = $config['pattern'] ?? null;

        $tz = $timezone instanceof DateTimeZone ? $timezone : ($timezone ? new DateTimeZone($timezone) : $dt->getTimezone());
        $formatter = new IntlDateFormatter(
            static::locale(),
            $dateType,
            $timeType,
            $tz,
            IntlDateFormatter::GREGORIAN,
            $pattern ?? null
        );

        $result = $formatter->format($dt);
        return $result !== false ? $result : '';
    }

}
