<?php
/**
 * Validation Class.  Used for validation of model data
 *
 * PHP Version 5.x
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 1.2.0.3830
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Utility;

use Exception;
use InvalidArgumentException;
use Nata\Core\App;
use Nata\Filesystem\File;
use Nata\Http\Client;
use Nata\I18n\Time\LocalizedDateParser;
use SoapClient;

/**
 * Offers different validation methods.
 */
class Validation {

/**
 * Some complex patterns needed in multiple places
 *
 * @var array
 */
    protected static $_pattern = [
        'hostname' => '(?:[-_a-z0-9][-_a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,})'
    ];

/**
 * Postal code placeholders.
 *
 * @var array
 */
    public static $postalPlaceholders = [
        'uk' => 'SW1A 1AA',
        'ca' => 'K1A 0B1',
        'it' => '00118',
        'de' => '10115',
        'be' => '1000',
        'us' => '12345 or 12345-6789',
        'br' => '12345-678',
        'pt' => '1234-567',
        'mz' => '1100',
        'ao' => '9100-001',
        'bw' => '10000',
        'za' => '1000',
        'ng' => '100000',
        'ke' => '00100',
        'tz' => '10000',
        'ug' => '10000',
        'zm' => '10000',
        'zw' => '10000',
        'es' => '28001',
        'fr' => '75001',
        'nl' => '1000',
        'at' => '1010',
        'ch' => '1000',
        'se' => '10000',
        'no' => '1000',
        'dk' => '1000',
        'is' => '100',
        'fi' => '00100',
        'sv' => '10000',
    ];

/**
 * Holds an array of errors messages set in this class.
 * These are used for debugging purposes
 *
 * @var array
 */
    public static $errors = [];

/**
 * Checks that a string contains something other than whitespace
 *
 * Returns true if string contains something other than whitespace
 *
 * $check can be passed as an array:
 * array('check' => 'valueToCheck');
 *
 * @param string|array $check Value to check
 * @return boolean Success
 */
    public static function notEmpty($check) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (empty($check) && $check != '0') {
            return false;
        }
        return static::_check($check, '/[^\s]+/m');
    }

/**
 * Checks that a string contains only integer or letters
 *
 * Returns true if string contains only integer or letters
 *
 * $check can be passed as an array:
 * array('check' => 'valueToCheck');
 *
 * @param string|array $check Value to check
 * @return boolean Success
 */
    public static function alphaNumeric($check) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (empty($check) && $check != '0') {
            return false;
        }

        return static::_check($check, '/^[\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]+$/mu');
    }

/**
 * Checks that a string is valid as a human name.
 *
 * Numbers are not allowed.
 *
 * Returns true if string contains only (accentted) letters and hyphen.
 *
 * @param string|array $check Value to check
 * @return boolean Success
 */
    public static function humanName($check) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (empty($check) && $check != '0') {
            return false;
        }

        return static::_check($check, '/^[\p{L}\040\-]+$/iu');
    }

/**
 * Checks that a string length is within s specified range.
 * Spaces are included in the character count.
 * Returns true is string matches value min, max, or between min and max,
 *
 * @param string $check Value to check for length
 * @param integer $min Minimum value in range (inclusive)
 * @param integer $max Maximum value in range (inclusive)
 * @return boolean Success
 */
    public static function between($check, $min, $max) {
        $length = mb_strlen($check);
        return ($length >= $min && $length <= $max);
    }

/**
 * Returns true if field is left blank -OR- only whitespace characters are present in it's value
 * Whitespace characters include Space, Tab, Carriage Return, Newline
 *
 * $check can be passed as an array:
 * array('check' => 'valueToCheck');
 *
 * @param string|array $check Value to check
 * @return boolean Success
 */
    public static function blank($check) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }
        return !static::_check($check, '/[^\\s]/');
    }

/**
 * Validation of credit card numbers.
 * Returns true if $check is in the proper credit card format.
 *
 * @param string|array $check credit card number to validate
 * @param string|array $type 'all' may be passed as a sting, defaults to fast which checks format of most major credit cards
 *    if an array is used only the values of the array are checked.
 *    Example: array('amex', 'bankcard', 'maestro')
 * @param boolean $deep set to true this will check the Luhn algorithm of the credit card.
 * @param string $regex A custom regex can also be passed, this will be used instead of the defined regex values
 * @return boolean Success
 * @see Validation::luhn()
 */
    public static function cc($check, $type = 'fast', $deep = false, $regex = null) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        $check = str_replace(array('-', ' '), '', $check);
        if (mb_strlen($check) < 13) {
            return false;
        }

        if (!is_null($regex)) {
            if (static::_check($check, $regex)) {
                return static::luhn($check, $deep);
            }
        }

        $cards = array(
            'all' => array(
                'amex' => '/^3[4|7]\\d{13}$/',
                'bankcard' => '/^56(10\\d\\d|022[1-5])\\d{10}$/',
                'diners' => '/^(?:3(0[0-5]|[68]\\d)\\d{11})|(?:5[1-5]\\d{14})$/',
                'disc' => '/^(?:6011|650\\d)\\d{12}$/',
                'electron' => '/^(?:417500|4917\\d{2}|4913\\d{2})\\d{10}$/',
                'enroute' => '/^2(?:014|149)\\d{11}$/',
                'jcb' => '/^(3\\d{4}|2100|1800)\\d{11}$/',
                'maestro' => '/^(?:5020|6\\d{3})\\d{12}$/',
                'mc' => '/^5[1-5]\\d{14}$/',
                'solo' => '/^(6334[5-9][0-9]|6767[0-9]{2})\\d{10}(\\d{2,3})?$/',
                'switch' => '/^(?:49(03(0[2-9]|3[5-9])|11(0[1-2]|7[4-9]|8[1-2])|36[0-9]{2})\\d{10}(\\d{2,3})?)|(?:564182\\d{10}(\\d{2,3})?)|(6(3(33[0-4][0-9])|759[0-9]{2})\\d{10}(\\d{2,3})?)$/',
                'visa' => '/^4\\d{12}(\\d{3})?$/',
                'voyager' => '/^8699[0-9]{11}$/'
            ),
            'fast' => '/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/'
        );

        if (is_array($type)) {
            foreach ($type as $value) {
                $regex = $cards['all'][strtolower($value)];

                if (static::_check($check, $regex)) {
                    return static::luhn($check, $deep);
                }
            }
        } elseif ($type == 'all') {
            foreach ($cards['all'] as $value) {
                $regex = $value;

                if (static::_check($check, $regex)) {
                    return static::luhn($check, $deep);
                }
            }
        } else {
            $regex = $cards['fast'];

            if (static::_check($check, $regex)) {
                return static::luhn($check, $deep);
            }
        }

        return false;
    }

/**
 * Used to compare 2 numeric values.
 *
 * @param string|array $check1 if string is passed for a string must also be passed for $check2
 *    used as an array it must be passed as array('check1' => value, 'operator' => 'value', 'check2' -> value)
 * @param string $operator Can be either a word or operand
 *    is greater >, is less <, greater or equal >=
 *    less or equal <=, is less <, equal to ==, not equal !=
 * @param integer $check2 only needed if $check1 is a string
 * @return boolean Success
 */
    public static function comparison($check1, $operator = null, $check2 = null) {
        if (is_array($check1)) {
            extract($check1, EXTR_OVERWRITE);
        }
        $operator = str_replace(array(' ', "\t", "\n", "\r", "\0", "\x0B"), '', strtolower($operator));

        switch ($operator) {
            case 'isgreater':
            case '>':
                if ($check1 > $check2) {
                    return true;
                }
                break;
            case 'isless':
            case '<':
                if ($check1 < $check2) {
                    return true;
                }
                break;
            case 'greaterorequal':
            case '>=':
                if ($check1 >= $check2) {
                    return true;
                }
                break;
            case 'lessorequal':
            case '<=':
                if ($check1 <= $check2) {
                    return true;
                }
                break;
            case 'equalto':
            case '==':
                if ($check1 == $check2) {
                    return true;
                }
                break;
            case 'notequal':
            case '!=':
                if ($check1 != $check2) {
                    return true;
                }
                break;
            default:
                static::$errors[] = 'You must define the $operator parameter for Validation::comparison()';
                break;
        }
        return false;
    }

/**
 * Used when a custom regular expression is needed.
 *
 * @param string|array $check When used as a string, $regex must also be a valid regular expression.
 *                                As and array: array('check' => value, 'regex' => 'valid regular expression')
 * @param string $regex If $check is passed as a string, $regex must also be set to valid regular expression
 * @return boolean Success
 */
    public static function custom($check, $regex = null) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if ($regex === null) {
            static::$errors[] = 'You must define a regular expression for Validation::custom()';
            return false;
        }

        return static::_check($check, $regex);
    }

/**
 * Date validation, determines if the string passed is a valid date.
 * keys that expect full month, day and year will validate leap years
 *
 * @param string $check a valid date string
 * @param string|array $format Use a string or an array of the keys below. Arrays should be passed as array('dmy', 'mdy', etc)
 *           Keys: dmy 27-12-2006 or 27-12-06 separators can be a space, period, dash, forward slash
 *                 mdy 12-27-2006 or 12-27-06 separators can be a space, period, dash, forward slash
 *                 ymd 2006-12-27 or 06-12-27 separators can be a space, period, dash, forward slash
 *                 dMy 27 December 2006 or 27 Dec 2006
 *                 Mdy December 27, 2006 or Dec 27, 2006 comma is optional
 *                 My December 2006 or Dec 2006
 *                 my 12/2006 separators can be a space, period, dash, forward slash
 * @param string $regex If a custom regular expression is used this is the only validation that will occur.
 * @return boolean Success
 */
    public static function date($check, $format = 'ymd', $regex = null) {
        if ($regex !== null) {
            return static::_check($check, $regex);
        }

        if (str_contains($check, ',') || str_contains($check, '/')) {
            $parsed = LocalizedDateParser::parseToTime($check)?->format($format);
            if ($parsed !== null) {
                $check = $parsed;
            }
        }

        // Allow ISO-8601 format
        $format = str_replace(['-', '/', '.', ' '], '', $format);

        $regex['dmy'] = $regex['dmY'] = '%^(?:(?:31(\\/|-|\\.|\\x20)(?:0?[13578]|1[02]))\\1|(?:(?:29|30)(\\/|-|\\.|\\x20)(?:0?[1,3-9]|1[0-2])\\2))(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$|^(?:29(\\/|-|\\.|\\x20)0?2\\3(?:(?:(?:1[6-9]|[2-9]\\d)?(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])00))))$|^(?:0?[1-9]|1\\d|2[0-8])(\\/|-|\\.|\\x20)(?:(?:0?[1-9])|(?:1[0-2]))\\4(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$%';
        $regex['mdy'] = '%^(?:(?:(?:0?[13578]|1[02])(\\/|-|\\.|\\x20)31)\\1|(?:(?:0?[13-9]|1[0-2])(\\/|-|\\.|\\x20)(?:29|30)\\2))(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$|^(?:0?2(\\/|-|\\.|\\x20)29\\3(?:(?:(?:1[6-9]|[2-9]\\d)?(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])00))))$|^(?:(?:0?[1-9])|(?:1[0-2]))(\\/|-|\\.|\\x20)(?:0?[1-9]|1\\d|2[0-8])\\4(?:(?:1[6-9]|[2-9]\\d)?\\d{2})$%';
        $regex['Ymd'] = $regex['ymd'] = $regex['Ymj'] = '%^(?:(?:(?:(?:(?:1[6-9]|[2-9]\\d)?(?:0[48]|[2468][048]|[13579][26])|(?:(?:16|[2468][048]|[3579][26])00)))(\\/|-|\\.|\\x20)(?:0?2\\1(?:29)))|(?:(?:(?:1[6-9]|[2-9]\\d)?\\d{2})(\\/|-|\\.|\\x20)(?:(?:(?:0?[13578]|1[02])\\2(?:31))|(?:(?:0?[1,3-9]|1[0-2])\\2(29|30))|(?:(?:0?[1-9])|(?:1[0-2]))\\2(?:0?[1-9]|1\\d|2[0-8]))))$%';
        $regex['dMy'] = $regex['dMY'] = '/^((31(?!\\ (Feb(ruary)?|Apr(il)?|June?|(Sep(?=\\b|t)t?|Nov)(ember)?)))|((30|29)(?!\\ Feb(ruary)?))|(29(?=\\ Feb(ruary)?\\ (((1[6-9]|[2-9]\\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00)))))|(0?[1-9])|1\\d|2[0-8])\\ (Jan(uary)?|Feb(ruary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)|(ne?))|Aug(ust)?|Oct(ober)?|(Sep(?=\\b|t)t?|Nov|Dec)(ember)?)\\ ((1[6-9]|[2-9]\\d)\\d{2})$/';
        $regex['Mdy'] = '/^(?:(((Jan(uary)?|Ma(r(ch)?|y)|Jul(y)?|Aug(ust)?|Oct(ober)?|Dec(ember)?)\\ 31)|((Jan(uary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)|(ne?))|Aug(ust)?|Oct(ober)?|(Sep)(tember)?|(Nov|Dec)(ember)?)\\ (0?[1-9]|([12]\\d)|30))|(Feb(ruary)?\\ (0?[1-9]|1\\d|2[0-8]|(29(?=,?\\ ((1[6-9]|[2-9]\\d)(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00)))))))\\,?\\ ((1[6-9]|[2-9]\\d)\\d{2}))$/';
        $regex['My'] = '%^(Jan(uary)?|Feb(ruary)?|Ma(r(ch)?|y)|Apr(il)?|Ju((ly?)|(ne?))|Aug(ust)?|Oct(ober)?|(Sep(?=\\b|t)t?|Nov|Dec)(ember)?)[ /]((1[6-9]|[2-9]\\d)\\d{2})$%';
        $regex['my'] = '%^(((0[123456789]|10|11|12)([- /.])(([1][9][0-9][0-9])|([2][0-9][0-9][0-9]))))$%';
        $regex['Ym'] = $regex['ym'] = '%^(([1][9][0-9][0-9])|([2][0-9][0-9][0-9])([- /.])(((0[123456789]|10|11|12))))$%';
        $regex['Y'] = $regex['y'] = '%^(([1][9][0-9][0-9])|([2][0-9][0-9][0-9]))$%';
        // l,j,FY -> "Tuesday, 18, November 2025" (weekday, day, monthName year)
        // Simpler validation (does not enforce month/day compatibility, only ranges)
        $regex['l,j,FY'] = '/^(?:Mon(?:day)?|Tue(?:sday)?|Wed(?:nesday)?|Thu(?:rsday)?|Fri(?:day)?|Sat(?:urday)?|Sun(?:day)?),\\s*(?:0?[1-9]|[12]\\d|3[01])\\s*,\\s*(?:Jan(?:uary)?|Feb(?:ruary)?|Ma(?:r(?:ch)?|y)|Apr(?:il)?|Ju(?:ne|ly)|Aug(?:ust)?|Oct(?:ober)?|Sep(?:tember)?|Nov(?:ember)?|Dec(?:ember)?)\\s+(?:1[6-9]|[2-9]\\d)\\d{2}$/';

        if ($format === '*') {
            $format = array_keys($regex);
        }
        $format = (is_array($format)) ? array_values($format) : [$format];
        foreach ($format as $key) {
            if (static::_check($check, $regex[$key]) === true) {
                return true;
            }
        }
        return false;
    }

/**
 * Validates a datetime value.
 * All values matching the "date" core validation rule, and the "time" one will be valid.
 *
 * @param array $check Value to check
 * @param string|array $dateFormat Format of the date part
 * Use a string or an array of the keys below. Arrays should be passed as array('dmy', 'mdy', etc)
 * ## Keys: *
 *    - dmy 27-12-2006 or 27-12-06 separators can be a space, period, dash, forward slash
 *    - mdy 12-27-2006 or 12-27-06 separators can be a space, period, dash, forward slash
 *    - ymd 2006-12-27 or 06-12-27 separators can be a space, period, dash, forward slash
 *  - dMy 27 December 2006 or 27 Dec 2006
 *    - Mdy December 27, 2006 or Dec 27, 2006 comma is optional
 *    - My December 2006 or Dec 2006
 *     - my 12/2006 separators can be a space, period, dash, forward slash
 * @param string $regex Regex for the date part. If a custom regular expression is used this is the only validation that will occur.
 * @return boolean True if the value is valid, false otherwise
 * @see Validation::date
 * @see Validation::time
 */
    public static function datetime($check, $dateFormat = 'ymd', $regex = null) {
        $valid = false;
        $check = str_replace('T', ' ', $check);
        $parts = explode(' ', $check);
        if (!empty($parts) && count($parts) > 1) {
            $time = array_pop($parts);
            $date = implode(' ', $parts);
            $dateFormat = str_replace(array('\T', 'H', 'h', ':', 'i', 's'), '', $dateFormat);
            $valid = static::date($date, $dateFormat, $regex) && static::time($time);
        }
        return $valid;
    }

/**
 * Time validation, determines if the string passed is a valid time.
 * Validates time as 24hr (HH:MM) or am/pm ([H]H:MM[a|p]m)
 * Does not allow/validate seconds.
 *
 * @param string $check a valid time string
 * @return boolean Success
 */
    public static function time($check) {
        return static::_check($check, '%^((0?[1-9]|1[012])(:[0-5]\d){0,2} ?([AP]M|[ap]m))$|^([01]\d|2[0-3])(:[0-5]\d){0,2}$%');
    }

/**
 * Boolean validation, determines if value passed is a boolean integer or true/false.
 *
 * @param string $check a valid boolean
 * @return boolean Success
 */
    public static function boolean($check) {
        $booleanList = array(0, 1, '0', '1', true, false);
        return in_array($check, $booleanList, true);
    }

/**
 * Checks that a value is a valid decimal. Both the sign and exponent are optional.
 *
 * Valid Places:
 *
 * - null => Any number of decimal places, including none. The '.' is not required.
 * - true => Any number of decimal places greater than 0, or a float|double. The '.' is required.
 * - 1..N => Exactly that many number of decimal places. The '.' is required.
 *
 * @param integer $check The value the test for decimal
 * @param integer $places
 * @param string $regex If a custom regular expression is used, this is the only validation that will occur.
 * @return boolean Success
 */
    public static function decimal($check, $places = null, $regex = null) {
        if (is_null($regex)) {
            $lnum = '[0-9]+';
            $dnum = "[0-9]*[\.]{$lnum}";
            $sign = '[+-]?';
            $exp = "(?:[eE]{$sign}{$lnum})?";

            if ($places === null) {
                $regex = "/^{$sign}(?:{$lnum}|{$dnum}){$exp}$/";

            } elseif ($places === true) {
                if (is_float($check) && floor($check) === $check) {
                    $check = sprintf("%.1f", $check);
                }
                $regex = "/^{$sign}{$dnum}{$exp}$/";

            } elseif (is_numeric($places)) {
                $places = '[0-9]{' . $places . '}';
                $dnum = "(?:[0-9]*[\.]{$places}|{$lnum}[\.]{$places})";
                $regex = "/^{$sign}{$dnum}{$exp}$/";
            }
        }
        return static::_check($check, $regex);
    }

/**
 * Validates for an email address.
 *
 * Only uses getmxrr() checking for deep validation if PHP 5.3.0+ is used, or
 * any PHP version on a non-windows distribution
 *
 * @param string $check Value to check
 * @param boolean $deep Perform a deeper validation (if true), by also checking availability of host
 * @param string $regex Regex to use (if none it will use built in regex)
 * @return boolean Success
 */
    public static function email($check, $deep = false, $regex = null) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (is_null($regex)) {
            $regex = '/^[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[\p{L}0-9!#$%&\'*+\/=?^_`{|}~-]+)*@' . self::$_pattern['hostname'] . '$/ui';
        }

        $return = static::_check($check, $regex);
        if ($deep === false || $deep === null) {
            return $return;
        }

        if ($return === true && preg_match('/@(' . static::$_pattern['hostname'] . ')$/i', $check, $regs)) {
            if (function_exists('getmxrr') && getmxrr($regs[1], $mxhosts)) {
                return true;
            }
            if (function_exists('checkdnsrr') && checkdnsrr($regs[1], 'MX')) {
                return true;
            }
            return is_array(gethostbynamel($regs[1]));
        }

        return false;
    }

/**
 * Check that value is exactly $comparedTo.
 *
 * @param mixed $check Value to check
 * @param mixed $comparedTo Value to compare
 * @return boolean Success
 */
    public static function equalTo($check, $comparedTo) {
        return ($check === $comparedTo);
    }

/**
 * @todo
 *
 * Check if color code given is valid.
 *
 * @param string $check Valid color
 * @param string $type Type of code
 * @return boolean Success
 */
    public static function color($check, $type = 'any') {
        $regex = [
            'any' => '^(\#[\da-f]{3}|\#[\da-f]{6}|rgba\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)(,\s*(0\.\d+|1))\)|hsla\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)(,\s*(0\.\d+|1))\)|rgb\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)|hsl\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)\))$',
            'hex' => '^#(?:[0-9a-fA-F]{3}){1,2}$',
            'rgb' => ''
        ];

        return static::_check($check, sprintf('/%s/', $regex));
    }

/**
 * Check that value has a valid file extension.
 *
 * @param string|array $check Value to check
 * @param array $extensions file extensions to allow. By default extensions are 'gif', 'jpeg', 'png', 'jpg'
 * @return boolean Success
 */
    public static function extension($check, $extensions = array('gif', 'jpeg', 'png', 'jpg')) {
        if (is_array($check)) {
            return static::extension(array_shift($check), $extensions);
        }

        $extension = strtolower(pathinfo($check, PATHINFO_EXTENSION));

        foreach ($extensions as $value) {
            if ($extension === strtolower($value)) {
                return true;
            }
        }

        return false;
    }

/**
 * Checks whether the length of a string is greater or equal to a minimal length.
 *
 * @param string $check The string to test
 * @param integer $min The minimal string length
 * @return boolean Success
 */
    public static function minLength($check, $min) {
        return mb_strlen($check) >= $min;
    }

/**
 * Checks whether the length of a string is smaller or equal to a maximal length..
 *
 * @param string $check The string to test
 * @param integer $max The maximal string length
 * @return boolean Success
 */
    public static function maxLength($check, $max) {
        return mb_strlen($check) <= $max;
    }

/**
 * Checks that a value is a monetary amount.
 *
 * @param string $check Value to check
 * @param string $symbolPosition Where symbol is located (left/right)
 * @return boolean Success
 */
    public static function money($check, $symbolPosition = 'left') {
        $money = '(?!0,?\d)(?:\d{1,3}(?:([, .])\d{3})?(?:\1\d{3})*|(?:\d+))((?!\1)[,.]\d{2})?';
        if ($symbolPosition == 'right') {
            $regex = '/^' . $money . '(?<!\x{00a2})\p{Sc}?$/u';
        } else {
            $regex = '/^(?!\x{00a2})\p{Sc}?' . $money . '$/u';
        }
        return static::_check($check, $regex);
    }

/**
 * @TODO
 * Checks if VAT number is valid.
 *
 * @param string $check Value to check
 * @param string $symbolPosition Where symbol is located (left/right)
 * @return boolean Success
 */
    public static function vatNumber($check, $country = 'PT') {
        if (!static::numeric($check)) {
            return false;
        }

        $response = Client::get([
            'url' => 'http://www.nif.pt/',
            'data' => [
                'json' => 1,
                'q' => $check,
                'key' => 'ba4d4b3977de57d41234afbdd5d00225'
            ]
        ])->send();

        print_a($response);die;

        $client = new SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl", array('trace' => true) );

        print_a($client->checkVat(array('countryCode' => $country, 'vatNumber' => $check)));
        die;

    }

/**
 * Validate a multiple select.
 *
 * Valid Options
 *
 * - in => provide a list of choices that selections must be made from
 * - max => maximum number of non-zero choices that can be made
 * - min => minimum number of non-zero choices that can be made
 *
 * @param array $check Value to check
 * @param array $options Options for the check.
 * @param boolean $strict Defaults to true, set to false to disable strict type check
 * @return boolean Success
 */
    public static function multiple($check, $options = [], $strict = true) {
        $defaults = ['in' => null, 'max' => null, 'min' => null];

        $options = array_merge($defaults, $options);

        $check = array_filter((array)$check);

        if (empty($check)) {
            return false;
        }

        if ($options['max'] && count($check) > $options['max']) {
            return false;
        }

        if ($options['min'] && count($check) < $options['min']) {
            return false;
        }

        if ($options['in'] && is_array($options['in'])) {
            foreach ($check as $val) {
                if (!in_array($val, $options['in'], $strict)) {
                    return false;
                }
            }
        }

        return true;
    }

/**
 * Checks if a value is numeric.
 *
 * @param string $check Value to check
 * @return boolean Success
 */
    public static function numeric($check) {
        return is_numeric($check);
    }

/**
 * Checks if a value is a natural number.
 *
 * @param string $check Value to check
 * @param boolean $allowZero Set true to allow zero, defaults to false
 * @return boolean Success
 * @see http://en.wikipedia.org/wiki/Natural_number
 */
    public static function naturalNumber($check, $allowZero = false) {
        $regex = $allowZero ? '/^(?:0|[1-9][0-9]*)$/' : '/^[1-9][0-9]*$/';
        return static::_check($check, $regex);
    }

/**
 * Check that a value is a valid phone number.
 *
 * @param string|array $check Value to check (string or array)
 * @param string $regex Regular expression to use
 * @param string $country Country code (defaults to 'all')
 * @return boolean Success
 */
    public static function phone($check, $regex = null, $country = 'all') {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (is_null($regex)) {
            switch ($country) {
                case 'us':
                case 'all':
                case 'can':
                    // includes all NANPA members.
                    // see http://en.wikipedia.org/wiki/North_American_Numbering_Plan#List_of_NANPA_countries_and_territories
                    $regex = '/^(?:\+?1)?[-. ]?\\(?[2-9][0-8][0-9]\\)?[-. ]?[2-9][0-9]{2}[-. ]?[0-9]{4}$/';
                    break;
                case 'pt':
                    // portuguese phone numbers format.
                    // https://pt.coredump.biz/questions/34292278/validate-mobile-and-phone-number-outsystems
                    // https://regex101.com/r/qV2jV9/2
                    $regex = '/^(?:(?:(2)([013-9])|(9)([1236]))(?!(?:\1|\2){7})(?!(?:\3|\4){7})\d{7}|(?=2{2,7}([^2])(?!(?:2|\5)+\b))22\d{7})\b/';
                    break;
                case 'br':
                    // brasilian phone numbers format.
                    // https://regex101.com/r/lHbA3X/3
                    $regex = '/\(?\b([0-9]{2,3}|0((x|[0-9]){2,3}[0-9]{2}))\)?\s*[0-9]{4,5}[- ]*[0-9]{4}\b/';
                    break;
                case 'no':
                    // Norwegian phone numbers format.
                    // https://regex101.com/r/4LGYGj/1
                    $regex = '/(0047|\+47|47)?\d{8}/';
                    break;
                case 'mz':
                    // mozambican phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?258)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'ao':
                    // angolan phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?244)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'zw':
                    // zimbabwean phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?263)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'bw':
                    // botswanan phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?267)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'za':
                    // south african phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?27)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'ng':
                    // nigerian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?234)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'ke':
                    // kenyan phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?254)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'tz':
                    // tanzanian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?255)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'ug':
                    // ugandan phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?256)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'zm':
                    // zambian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?260)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'zw':
                    // zimbabwean phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?263)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'es':
                    // spanish phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?34)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'fr':
                    // french phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?33)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'it':
                    // italian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?39)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'de':
                    // german phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?49)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'nl':
                    // dutch phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?31)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'be':
                    // belgian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?32)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'at':
                    // austrian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?43)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'ch':
                    // swiss phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?41)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'se':
                    // swedish phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?46)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'no':
                    // norwegian phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?47)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
                case 'dk':
                    // danish phone numbers format.
                    // https://regex101.com/r/0Lv8G1/1
                    $regex = '/^(?:\+?45)?(?:9[1-9][0-9]{7}|[28][0-9]{7,8})$/';
                    break;
            }
        }

        if (empty($regex)) {
            return static::_pass('phone', $check, $country);
        }

        return static::_check($check, $regex);
    }

/**
 * Checks that a given value is a valid postal code.
 *
 * @param string|array $check Value to check
 * @param string $regex Regular expression to use
 * @param string $country Country to use for formatting
 * @return boolean|null Success, null if country is not found or class does not exist
 */
    public static function postal($check, $regex = null, $country = 'us'): ?bool {
        $country = strtolower($country);
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (is_null($regex)) {
            switch ($country) {
                case 'uk':
                    $regex = '/\\A\\b[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}\\b\\z/i';
                    break;
                case 'ca':
                    $regex = '/\\A\\b[ABCEGHJKLMNPRSTVXY][0-9][A-Z] [0-9][A-Z][0-9]\\b\\z/i';
                    break;
                case 'it':
                case 'de':
                    $regex = '/^[0-9]{5}$/i';
                    break;
                case 'be':
                    $regex = '/^[1-9]{1}[0-9]{3}$/i';
                    break;
                case 'us':
                    $regex = '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i';
                    break;
                case 'br':
                    $regex = '/\d{5}-\d{3}/';
                    break;
                case 'pt':
                    $regex = '/\d{4}-\d{3}/';
                    break;
                case 'mz':
                    $regex = '/\d{4}/';
                    break;
            }
        }

        if (empty($regex)) {
            if (static::_regionClassExists($country)) {
                return static::_pass('postal', $check, $country);
            }
            return null;
        }

        return static::_check($check, $regex);
    }

/**
 * Get postal code placeholder/example format for a given country.
 *
 * @param string $country Country code (ISO 2-letter code)
 * @return string|null Placeholder/example format for the postal code, null if not found
 */
    public static function postalPlaceholder($country = 'us'): ?string {
        return self::$postalPlaceholders[strtolower($country)] ?? null;
    }

/**
 * Validate that a number is in specified range.
 * if $lower and $upper are not set, will return true if
 * $check is a legal finite on this platform
 *
 * @param string $check Value to check
 * @param integer $lower Lower limit
 * @param integer $upper Upper limit
 * @return boolean Success
 */
    public static function range($check, $lower = null, $upper = null) {
        if (!is_numeric($check)) {
            return false;
        }

        if (isset($lower) && isset($upper)) {
            return ($check > $lower && $check < $upper);
        }

        return is_finite($check);
    }

/**
 * Check if given number/string is in range given the pattern.
 *
 * #### Usage:
 *
 * ```
 * Validation::rangePattern(4535, '45*'); // true
 * Validation::rangePattern(4535, '4300...5000'); // true
 * Validation::rangePattern('CBDU', 'CBD*'); // true
 * Validation::rangePattern('CBDU', 'C[BZ]DU'); // true
 * ```
 *
 * @param string $number Number to check/match
 * @param string $pattern Pattern/range to check
 * @param string $rangeSeparator Range separator
 * @return boolean True if in range, false otherwise
 * @throws InvalidArgumentException
 */
    public static function rangePattern($check, $pattern, $rangeSeparator = '...') {
        list($lower, $upper) = splitter($pattern, $rangeSeparator);
        if (is_numeric($lower)) {
            $check = (int)$check;
        }
        if ($lower && $upper) {
            return static::range($check, $lower, $upper);
        }
        return fnmatch($pattern, $check);
    }

/**
 * Checks that a value is a valid Social Security Number.
 *
 * @param string|array $check Value to check
 * @param string $regex Regular expression to use
 * @param string $country Country
 * @return boolean Success
 */
    public static function ssn($check, $regex = null, $country = null) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }

        if (is_null($regex)) {
            switch ($country) {
                case 'dk':
                    $regex  = '/\\A\\b[0-9]{6}-[0-9]{4}\\b\\z/i';
                    break;
                case 'nl':
                    $regex  = '/\\A\\b[0-9]{9}\\b\\z/i';
                    break;
                case 'us':
                    $regex  = '/\\A\\b[0-9]{3}-[0-9]{2}-[0-9]{4}\\b\\z/i';
                    break;
            }
        }
        if (empty($regex)) {
            return static::_pass('ssn', $check, $country);
        }
        return static::_check($check, $regex);
    }

/**
 * Validation of an IP address.
 *
 * @param string $check The string to test.
 * @param string $type The IP Protocol version to validate against
 * @return boolean Success
 */
    public static function ip($check, $type = 'both') {
        $type = strtolower($type);
        $flags = 0;
        if ($type === 'ipv4') {
            $flags = FILTER_FLAG_IPV4;
        }
        if ($type === 'ipv6') {
            $flags = FILTER_FLAG_IPV6;
        }
        return (boolean)filter_var($check, FILTER_VALIDATE_IP, array('flags' => $flags));
    }

/**
 * Check if given IPv4 or IPv6 address is within given range.
 *
 * @param string $ip IPv4 or IPv6 address to check if its within the range.
 * @param string $rangIp IP range to check
 * @return bool True if in within the range, false otherwise
 */
    public static function ipInRange(string $ip, string $rangeIp): bool {
        return !static::ip($ip, 'ipv6')
         ? static::ipv4InRange($ip, $rangeIp) : static::ipv6InRange($ip, $rangeIp);
    }

/**
 * This function takes 2 arguments, an IPv4 address and a "range" in several different formats.
 *
 *  ## Network ranges can be specified as:
 *  - 1. Wildcard format:     1.2.3.*
 *  - 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 *  - 3. Start-End IP format: 1.2.3.0-1.2.3.255
 *
 * Note that little validation is done on the range inputs - it expects you to
 * use one of the above 3 formats.
 *
 * @param string $ip IPv4 address to check if its within the givenIP.
 * @param string $rangIp IP range to check
 * @return bool True if in within the range, false otherwise
 * @link https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php
 */
    public static function ipv4InRange(string $ip, string $range): bool {
        if (!static::ip($ip, 'ipv4')) {
            throw new InvalidArgumentException(sprintf('IPv4 address "%s" is invalid.', $ip));
        }

        // $range is in IP/NETMASK format
        if (strpos($range, '/') !== false) {
            list($range, $netmask) = explode('/', $range, 2);
            // $netmask is a 255.255.0.0 format
            if (strpos($netmask, '.') !== false) {
                $netmask = str_replace('*', '0', $netmask);
                $netmaskDec = ip2long($netmask);
                return ((ip2long($ip) & $netmaskDec) == (ip2long($range) & $netmaskDec));
            }

            // $netmask is a CIDR size block
            // fix the range argument
            $parts = explode('.', $range);
            while (count($parts) < 4) {
                $parts[] = '0';
            }
            list($a, $b, $c, $d) = $parts;

            $range = sprintf(
                "%u.%u.%u.%u",
                empty($a) ? '0' : $a,
                empty($b) ? '0' : $b,
                empty($c) ? '0' : $c,
                empty($d) ? '0' : $d
            );

            $rangeDec = ip2long($range);
            $ipDec = ip2long($ip);

            $wildcardDec = pow(2, (32 - $netmask)) - 1;
            $netmaskDec = ~$wildcardDec;

            return (($ipDec & $netmaskDec) == ($rangeDec & $netmaskDec));
        }

        // Range might be 255.255.*.* or 1.2.3.0-1.2.3.255

        // For a.b.*.* format
        // Just convert to A-B format by setting * to 0 for A and 255 for B
        if (strpos($range, '*') !== false) {
            $lower = str_replace('*', '0', $range);
            $upper = str_replace('*', '255', $range);
            $range = "$lower-$upper";
        }

        // For A-B format
        if (strpos($range, '-') !== false) {
            list($lower, $upper) = explode('-', $range, 2);
            $lowerDec = (float)sprintf("%u", ip2long($lower));
            $upperDec = (float)sprintf("%u", ip2long($upper));
            $ipDec = (float)sprintf("%u", ip2long($ip));
            return (($ipDec >= $lowerDec) && ($ipDec <= $upperDec));
        }

        return false;
    }

/**
 * @todo Needs more testing with IPv6 addresses.
 *
 * Determine whether the IPv6 address is within given IP range.
 *
 * @param string $ip IPv6 address to check if its within the givenIP.
 * @param string $rangIp IP range to check
 * @return bool True if in within the range, false otherwise
 * @link https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php
 */
    public static function ipv6InRange($ip, $rangeIp) {
        $pieces = explode ('/', $rangeIp, 2);
        $leftPiece = $pieces[0];
        $rightPiece = $pieces[1];

        // Extract out the main IP pieces
        $ipPieces = explode('::', $leftPiece, 2);
        $mainIpPiece = $ipPieces[0];
        $lastIpPiece = $ipPieces[1];

        // Pad out the shorthand entries.
        $mainIpPieces = explode(':', $mainIpPiece);
        foreach ($mainIpPieces as $key => $val) {
            $mainIpPieces[$key] = trim(str_pad($mainIpPieces[$key], 4, '0', STR_PAD_LEFT));
        }

        // Create the first and last pieces that will denote the IPV6 range.
        $first = $mainIpPieces;
        $last = $mainIpPieces;

        // Check to see if the last IP block (part after ::) is set
        $lastPiece = '';
        $size = count($mainIpPieces);
        if (trim($lastIpPiece) !== '') {
            $lastPiece = str_pad($lastIpPiece, 4, '0', STR_PAD_LEFT);

            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }

            $mainIpPieces[7] = $lastPiece;
        } else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
        }

        // Rebuild the final long form IPV6 address
        $first = static::_ip2Long6(implode(':', $first));
        $last = static::_ip2Long6(implode(':', $last));

        if (!is_numeric($ip)) {
            $ip = static::_ip2Long6($ip);
        }

        $inRange = ($ip >= $first && $ip <= $last);

        return $inRange;
    }

/**
 * Get IPv6 as integer.
 *
 * @param string $ip IPv6
 * @return int IPv6 as integer
 * @link https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php
 */
    protected static function _ip2Long6($ip) {
        if (substr_count($ip, '::')) {
            $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
        }

        $ip = explode(':', $ip);
        $rIp = '';
        foreach ($ip as $v) {
            $rIp .= str_pad(base_convert($v, 16, 2), 16, 0, STR_PAD_LEFT);
        }

        return base_convert($rIp, 2, 10);
    }

/**
 * Check if Fiscal ID is valid.
 *
 * @param string|int $fiscalId Fiscal ID to check
 * @param string $country Country of origin
 * @retrun boolean True if valid, false otherwise
 */
    public static function fiscalId($fiscalId, $country = 'pt') {
        $fiscalId = (string)trim(str_replace(' ', '', $fiscalId));

        if (empty($fiscalId)) {
            return false;
        }

        $methodName = sprintf('_validate%sFiscalId', ucfirst(strtolower($country)));
        if (!method_exists(static::class, $methodName)) {
            throw new InvalidArgumentException(sprintf('Missing fiscal ID validation for country "%s"', strtoupper($country)));
        }

        return static::$methodName($fiscalId);
    }

/**
 * Checks that a value is a valid URL according to http://www.w3.org/Addressing/URL/url-spec.txt
 *
 * The regex checks for the following component parts:
 *
 * - a valid, optional, scheme
 * - a valid ip address OR
 *   a valid domain name as defined by section 2.3.1 of http://www.ietf.org/rfc/rfc1035.txt
 *   with an optional port number
 * - an optional valid path
 * - an optional query string (get parameters)
 * - an optional fragment (anchor tag)
 *
 * @param string $check Value to check
 * @param boolean $strict Require URL to be prefixed by a valid scheme (one of http(s)/ftp(s)/file/news/gopher)
 * @return boolean Success
 */
    public static function url($check, $strict = false) {
        static::_populateIp();

        $validChars = '([' . preg_quote('!"$&\'()*+,-.@_:;=~[]') . '\/0-9a-z\p{L}\p{N}]|(%[0-9a-f]{2}))';

        $regex = '/^(?:(?:https?|ftps?|sftp|file|news|gopher):\/\/)' . (!empty($strict) ? '' : '?') .
            '(?:' . static::$_pattern['IPv4'] . '|\[' . static::$_pattern['IPv6'] . '\]|' . static::$_pattern['hostname'] . ')(?::[1-9][0-9]{0,4})?' .
            '(?:\/?|\/' . $validChars . '*)?' .
            '(?:\?' . $validChars . '*)?' .
            '(?:#' . $validChars . '*)?$/iu';

        return static::_check($check, $regex);
    }

/**
 * Checks that a value contains a URL.
 * Basic verification if string contains a human readable
 * URL.
 *
 * @param string $check Value to check
 * @return boolean Success
 */
    public static function containsUrl($check) {
        if (static::url($check)) {
            return true;
        }

        $check = str_replace([" ", "\n\r", PHP_EOL], '', $check);
        $check = str_replace(['(dot)', '[dot]', 'dot'], '.', $check);
        $check = str_replace(['(slash)', '[slash]', 'slash'], '/', $check);

        return strpos($check, 'http') !== false
            || strpos($check, 'www') !== false
            || strpos($check, '<a') !== false
            || strpos($check, '/a>') !== false
            || strpos($check, 'href') !== false;
    }

/**
 * Checks if a value is in a given list.
 *
 * @param string $check Value to check
 * @param array $list List to check against
 * @param boolean $strict Defaults to true, set to false to disable strict type check
 * @return boolean Success
 */
    public static function inList($check, $list, $strict = true) {
        return in_array($check, $list, $strict);
    }

/**
 * Runs an user-defined validation.
 *
 * @param string|array $check value that will be validated in user-defined methods.
 * @param object $object class that holds validation method
 * @param string $method class method name for validation to run
 * @param array $args arguments to send to method
 * @return mixed user-defined class class method returns
 */
    public static function userDefined($check, $object, $method, $args = null) {
        return call_user_func_array(array($object, $method), array($check, $args));
    }

/**
 * Checks that a value is a valid uuid - http://tools.ietf.org/html/rfc4122
 *
 * @param string $check Value to check
 * @return boolean Success
 */
    public static function uuid($check) {
        $regex = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        return static::_check($check, $regex);
    }

/**
 * Check if region class exists.
 *
 * @param string $class Region class name
 * @return boolean True if class exists, false otherwise
 */
    protected static function _regionClassExists($class) {
        $class = Inflector::camelize($class);
        $className = App::className($class, 'Utility/Validation');
        return class_exists($className);
    }

/**
 * Attempts to pass unhandled Validation locales to a class starting with $class
 * and ending with Validation. For example $class = 'nl', the class would be
 * `Utility\Validation\Nl`.
 *
 * @param string $method The method to call on the other class.
 * @param mixed $check The value to check or an array of parameters for the method to be called.
 * @param string $class The class to do the validation.
 * @return mixed Return of Passed method, false on failure
 */
    protected static function _pass($method, $check, $class) {
        $class = Inflector::camelize($class);
        $className = App::className($class, 'Utility/Validation');
        if (!class_exists($className)) {
            trigger_error(sprintf(
                'Could not find %s class, unable to complete validation.',
                'Utility\Validation\\' . $class
            ), E_USER_WARNING);
            return false;
        }
        if (!method_exists($className, $method)) {
            trigger_error(sprintf(
                'Method %s does not exist on %s unable to complete validation.',
                $method,
                $className
            ), E_USER_WARNING);
            return false;
        }
        $check = (array)$check;
        return $className::{$method}(...$check);
    }

/**
 * Runs a regular expression match.
 *
 * @param string $check Value to check against the $regex expression
 * @param string $regex Regular expression
 * @return boolean Success of match
 */
    protected static function _check($check, $regex) {
        if (is_string($regex) && preg_match($regex, $check)) {
            static::$errors[] = false;
            return true;
        } else {
            static::$errors[] = true;
            return false;
        }
    }

/**
 * Get the values to use when value sent to validation method is
 * an array.
 *
 * @param array $params Parameters sent to validation method
 * @return array Default params
 */
    protected static function _defaults($params) {
        static::_reset();
        $defaults = array(
            'check' => null,
            'regex' => null,
            'country' => null,
            'deep' => false,
            'type' => null
        );
        $params = array_merge($defaults, $params);

        if ($params['country'] !== null) {
            $params['country'] = mb_strtolower($params['country']);
        }

        return $params;
    }

/**
 * Luhn algorithm.
 *
 * @param string|array $check
 * @param boolean $deep
 * @return boolean Success
 * @see http://en.wikipedia.org/wiki/Luhn_algorithm
 */
    public static function luhn($check, $deep = false) {
        if (is_array($check)) {
            extract(static::_defaults($check));
        }
        if ($deep !== true) {
            return true;
        }
        if ($check == 0) {
            return false;
        }
        $sum = 0;
        $length = strlen($check);

        for ($position = 1 - ($length % 2); $position < $length; $position += 2) {
            $sum += $check[$position];
        }

        for ($position = ($length % 2); $position < $length; $position += 2) {
            $number = $check[$position] * 2;
            $sum += ($number < 10) ? $number : $number - 9;
        }

        return ($sum % 10 == 0);
    }

/**
 * Checks the mime type of a file.
 *
 * @param string|array $check
 * @param array $mimeTypes to check for
 * @return boolean Success
 * @throws NataException when mime type can not be determined.
 */
    public static function mimeType($check, $mimeTypes = array()) {
        if (is_array($check) && isset($check['tmp_name'])) {
            $check = $check['tmp_name'];
        }

        $File = new File($check);
        $mime = $File->mime();

        if ($mime === false) {
            throw new Exception('Can not determine the mimetype.');
        }
        return in_array($mime, $mimeTypes);
    }

/**
 * Checking for upload errors.
 *
 * @param string|array $check
 * @retrun boolean
 * @see http://www.php.net/manual/en/features.file-upload.errors.php
 */
    public static function uploadError($check) {
        if (is_array($check) && isset($check['error'])) {
            $check = $check['error'];
        }

        return $check === UPLOAD_ERR_OK;
    }

/**
 * Lazily populate the IP address patterns used for validations.
 *
 * @return void
 */
    protected static function _populateIp() {
        if (!isset(static::$_pattern['IPv6'])) {
            $pattern  = '((([0-9A-Fa-f]{1,4}:){7}(([0-9A-Fa-f]{1,4})|:))|(([0-9A-Fa-f]{1,4}:){6}';
            $pattern .= '(:|((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})';
            $pattern .= '|(:[0-9A-Fa-f]{1,4})))|(([0-9A-Fa-f]{1,4}:){5}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})';
            $pattern .= '(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:)';
            $pattern .= '{4}(:[0-9A-Fa-f]{1,4}){0,1}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2}))';
            $pattern .= '{3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:){3}(:[0-9A-Fa-f]{1,4}){0,2}';
            $pattern .= '((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|';
            $pattern .= '((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:){2}(:[0-9A-Fa-f]{1,4}){0,3}';
            $pattern .= '((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2}))';
            $pattern .= '{3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:)(:[0-9A-Fa-f]{1,4})';
            $pattern .= '{0,4}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)';
            $pattern .= '|((:[0-9A-Fa-f]{1,4}){1,2})))|(:(:[0-9A-Fa-f]{1,4}){0,5}((:((25[0-5]|2[0-4]';
            $pattern .= '\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|((:[0-9A-Fa-f]{1,4})';
            $pattern .= '{1,2})))|(((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})))(%.+)?';

            static::$_pattern['IPv6'] = $pattern;
        }
        if (!isset(static::$_pattern['IPv4'])) {
            $pattern = '(?:(?:25[0-5]|2[0-4][0-9]|(?:(?:1[0-9])?|[1-9]?)[0-9])\.){3}(?:25[0-5]|2[0-4][0-9]|(?:(?:1[0-9])?|[1-9]?)[0-9])';
            static::$_pattern['IPv4'] = $pattern;
        }
    }

/**
 * Reset internal variables for another validation run.
 *
 * @return void
 */
    protected static function _reset() {
        static::$errors = array();
    }

/**
 * Portuguese Fiscal ID validation.
 *
 * @param string|int $fiscalId Portuguese Fiscal ID to check
 * @retrun boolean True if valid, false otherwise
 */
    protected static function _validatePtFiscalId($fiscalId) {
        if (empty($fiscalId) || strlen($fiscalId) !== 9) {
            return false;
        }

        if (!in_array(substr($fiscalId, 0, 1), ['1', '2', '3', '5', '6', '8'])
            && !in_array(substr((int)$fiscalId, 0, 2), ['45', '70', '71', '72', '74', '75', '77', '79', '90', '91', '98', '99'])) {
            return false;
        }

        $total = $fiscalId[0] * 9
            + $fiscalId[1] * 8
            + $fiscalId[2] * 7
            + $fiscalId[3] * 6
            + $fiscalId[4] * 5
            + $fiscalId[5] * 4
            + $fiscalId[6] * 3
            + $fiscalId[7] * 2;

        $module11 = $total - (int)($total / 11) * 11;
        $comparator = $module11 == 1 || $module11 == 0 ? 0 : 11 - $module11;

        return $fiscalId[8] == $comparator;
    }

/**
 * Brasilian Fiscal ID validation.
 *
 * @param string|int $fiscalId Brasilian Fiscal ID to check
 * @retrun boolean True if valid, false otherwise
 */
    protected static function _validateBrFiscalId($fiscalId) {
        if (empty($fiscalId) || strlen($fiscalId) !== 9) {
            return false;
        }
        return preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$|^\d{11}$/i', $fiscalId) > 0;
    }

}
