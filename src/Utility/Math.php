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
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Utility;

use Nata\Error;
use Nata\I18n\Time;

/**
 * Math helper library.
 *
 * Methods with some common algorithms.
 */
class Math {


/**
 * Sum percentage of value to itself.
 *
 * @param float $value A floating point number
 * @param float $percentage Percentage
 * @param integer $precision The precision of the returned number
 * @return float|int Value with percentage value of itself added
 */
    public static function addPercentageOf($value, $percentage, $precision = 2) {
        return static::_percentageOf('+', $value, $percentage, $precision);
    }

/**
 * Sub percentage of value to itself.
 *
 * @param float $value A floating point number
 * @param float $percentage Percentage
 * @param integer $precision The precision of the returned number.
 * @return float|int Value with percentage value of itself added
 */
    public static function subtractPercentageOf($value, $percentage, $precision = 2) {
        return static::_percentageOf('-', $value, $percentage, $precision);
    }

/**
 * Sub/add percentage of value to itself.
 *
 * @param string $operator Operator
 * @param float $value A floating point number
 * @param float $percentage Percentage
 * @param integer $precision The precision of the returned number.
 * @return float|int Value with percentage value of itself added
 */
    private static function _percentageOf($operator, $value, $percentage, $precision = 2) {
        $value = (float)$value;
        $per = (float)$percentage / 100;
        $result = $operator === '+' ? (1 + $per) : (1 - $per);
        return Number::precision($value *= $result, $precision);
    }

/**
 * Get respective percentage that given value is to given total.
 *
 * @param float|int $value A floating point number
 * @param float|int $number Relative number
 * @param integer $precision The precision of the returned number.
 * @param integer|float $base Percentage calculation base
 * @return float|int Percent of one number out of a second number
 */
    public static function percentageOf($value, $total, $precision = 2, int $base = 100) {
        $value = (float)$value;
        $total = (float)$total;

        if (empty($value) || empty($total)) {
            return 0;
        }

        $percent = (($value / $total) * $base);

        return Number::precision($percent, $precision);
    }

/**
 * Obtain the value that the given percentage of given value represents.
 *
 * @param float|int $percent A floating point percentage
 * @param float|int $number Relative number
 * @param integer $precision The precision of the returned number.
 * @param integer|float $base Percentage calculation base
 * @return float|int Value that represents the given percentage
 */
    public static function percentOf($percent, $value, $precision = 2, int $base = 100) {
        $percent = (float)$percent;
        $value = (float)$value;

        if (empty($percent) || empty($value)) {
            return 0;
        }

        $value = (($value * $percent) / $base);

        return Number::precision($value, $precision);
    }

/**
 * Get the original value that was taken from given percentage.
 *
 * @param float|int $value Current value
 * @param float|int $percent Percentage that was taken from value
 * @param integer $precision The precision of the returned number.
 * @param integer|float $base Percentage calculation base
 * @return float|int Original value
 */
    public static function reversePercentOf($value, $percent, $precision = 2, int $base = 100) {
        $percent = (float)$percent;
        $value = (float)$value;

        if (empty($percent) || empty($value)) {
            return 0;
        }

        $value = (($value / $percent) * $base);
        return Number::precision($value, $precision);
    }

/**
 * Calculate average value.
 *
 * @param array $values Array of values
 * @param integer $precision The precision of the returned number.
 * @return float|int Average value
 */
    public static function average(array $values, $precision = 2) {
        return Number::precision(array_sum($values) / count($values), $precision);
    }

/**
 * Calculate greatest common divisor of a and b.
 * The result is always positive even if either of, or both, input operands are negative.
 *
 * @param int $a Number
 * @param int $b Number
 * @return int Greatest common divisor of a and b
 */
    public static function gcd($a, $b) {
        return gmp_gcd($a, $b);
    }

/**
 * Calculates aspect ratio of given dimensions.
 *
 * @param int $width New Image Width
 * @param int $height New Image Height
 * @param float $tolerance Tolerance
 * @return string Aspect ratio of given dimensions
 */
    public static function calcAspectRatio(int $width, int $height, float $tolerance = 0.02): ?string {
        $aspectRatio = null;

        if ($width <= 0 || $height <= 0) {
            return $aspectRatio;
        } elseif ($width === $height) {
            return '1:1';
        }

        $total = $width + $height;
        for ($i = 1; $i <= 40; $i++) {
            $widthrx = $i * 1.0 * $width / $total;
            $heightrx = $i * 1.0 * $height / $total;

            // Accept aspect ratios within a given tolerance
            if ($i == 40 || (abs($widthrx - round($widthrx)) <= $tolerance && abs($heightrx - round($heightrx)) <= $tolerance)) {
                $aspectRatio = round($widthrx) . ':' . round($heightrx);
                break;
            }

        }

        return $aspectRatio;
    }

/**
 * Cartesian product algorithm.
 *
 * ## Example
 *  $input = [
 *      'arm' => ['A', 'B', 'C'],
 *      'gender' => ['Female', 'Male'],
 *      'location' => ['Vancouver', 'Calgary']
 *  ];
 *
 *  print_a(Math::cartesianProduct($input));
 *
 * @param array $data Array of values to sort
 * @return array Permutation
 */
    public static function cartesianProduct($data) {
        $result = [];

        foreach ($data as $key => $values) {
            // If a sub-array is empty, it doesn't affect the cartesian product
            if (empty($values)) {
                continue;
            }

            // Seeding the product array with the values from the first sub-array
            if (empty($result)) {
                foreach ($values as $value) {
                    $result[] = [$key => $value];
                }
            } else {
                // Second and subsequent input sub-arrays work like this:
                //   1. In each existing array inside $product, add an item with
                //      key == $key and value == first item in input sub-array
                //   2. Then, for each remaining item in current input sub-array,
                //      add a copy of each existing array inside $product with
                //      key == $key and value == first item of input sub-array

                // Store all items to be added to $product here; adding them
                // inside the foreach will result in an infinite loop
                $append = [];

                foreach ($result as &$product) {
                    // Do step 1 above. array_shift is not the most efficient, but
                    // it allows us to iterate over the rest of the items with a
                    // simple foreach, making the code short and easy to read.
                    $product[$key] = array_shift($values);

                    // $product is by reference (that's why the key we added above
                    // will appear in the end result), so make a copy of it here
                    $copy = $product;

                    // Do step 2 above.
                    foreach ($values as $item) {
                        $copy[$key] = $item;
                        $append[] = $copy;
                    }

                    // Undo the side effecst of array_shift
                    array_unshift($values, $product[$key]);
                }

                // Out of the foreach, we can add to $results now
                $result = array_merge($result, $append);
            }
        }
        return $result;
    }

/**
 * Calculates the prorated amount.
 *
 * ## Example
 * ```php
 * Math::prorata(100, new Time, 1, 2);
 * ```
 *
 * @param float $amount Amount
 * @param Time $now Time object
 * @param int $renewalDayOfTheMonth Renewal day of the month
 * @param DateTime|string $now Current date (todo)
 * @return float Prorated amount
 */
    public static function prorata(float $amount, Time $now = new Time, int $renewalDayOfTheMonth = 1, int $precision = 2): float {
        $today = $now->modify('00:00:00')->format('d');
        if ($today == $renewalDayOfTheMonth) {
            return $amount;
        }

        $startDate = new Time(sprintf('%s-%s-%s', $renewalDayOfTheMonth, $now->format('m'), $now->format('Y')), $now->timezone());
        $endDate = (clone $startDate)->modify('+1 month');
        $totalDays = $startDate->diff($endDate)->days;

        $month = 'this';
        if ($today > $renewalDayOfTheMonth) {
            $month = 'next';
        }
        $relativeDate = sprintf('first day of %s month', $month);
        $renewalDate = (clone $now)
            ->modify($relativeDate)
            ->modify('00:00:00')
            ->modify(($renewalDayOfTheMonth - 2) . ' days');

        return Number::precision(($amount / $totalDays) * $now->diff($renewalDate)->days, $precision);
    }

}
