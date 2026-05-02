<?php
/**
 * NataPHP Framework
 *
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
 *
 * Utility to parse i18n date strings (with localized month/weekday names)
 * into values compatible with \Nata\I18n\Time.
 *
 * Requires: PHP intl extension.
 */

namespace Nata\I18n\Time;

use IntlDateFormatter;
use DateTimeZone;
use Nata\I18n\I18n;
use Nata\I18n\Time;

class LocalizedDateParser {

/**
 * Map of ICU pattern tokens to PHP date pattern tokens.
 *
 * @var array
 */
	protected static $map = [
		'EEEE' => 'l',  // Monday
		'EEE'  => 'D',  // Mon
		'MMMM' => 'F',  // January
		'MMM'  => 'M',  // Jan
		'MM'   => 'm',  // 01
		'M'    => 'n',  // 1
		'dd'   => 'd',  // 01
		'd'    => 'j',  // 1
		'yyyy' => 'Y',
		'yy'   => 'y',
		'y'    => 'Y',
		'HH'   => 'H',  // 00-23
		'H'    => 'G',
		'hh'   => 'h',  // 01-12
		'h'    => 'g',
		'mm'   => 'i',
		'ss'   => 's',
		'a'    => 'A',  // AM/PM
	];

/**
 * Try to parse a localized date/time string and return a Time instance.
 *
 * Examples (pt_PT):
 *  - "Terça-feira, 18 novembro 2025"
 *  - "Domingo, 16, Novembro 2025"
 *  - "16 de Novembro de 2025 14:30"
 *  - "2025-11-16 14:30"
 *
 * @param string $input Localized date string
 * @param string|null $locale ICU/CLDR locale (e.g., "pt_PT"); falls back to framework locale
 * @param string|DateTimeZone|null $timezone Timezone identifier; defaults to system/app tz
 * @return \Nata\I18n\Time|null
 */
	public static function parseToTime(string $input, string|DateTimeZone $locale = null, string|DateTimeZone $timezone = null): ?Time {
        $result = self::_parseToLocalizedTime($input, $locale, $timezone);
        // Fallback to English if no result
        if ($result === null && !str_contains(strtolower($input), 'en')) {
            $result = self::_parseToLocalizedTime($input, 'en', $timezone);
        }
        return $result;
	}

/**
 * Parse a localized date/time string into a Time instance.
 *
 * @param string $input Localized date string
 * @param string|null $locale ICU/CLDR locale (e.g., "pt_PT"); falls back to framework locale
 * @param string|DateTimeZone|null $timezone Timezone identifier; defaults to system/app timezone
 * @return \Nata\I18n\Time|null
 */
	protected static function _parseToLocalizedTime(string $input, string|DateTimeZone $locale = null, string|DateTimeZone $timezone = null): ?Time {
		// Resolve locale first (used in pre-normalization)
		if ($locale === null) {
			$locale = I18n::locale();
		}
		$input = self::_preNormalize($input, (string)$locale);

		// Resolve timezone
		if ($timezone !== null && !($timezone instanceof DateTimeZone)) {
			$timezone = new DateTimeZone($timezone);
		}

		// Fast path: numeric epoch or ISO-ish date
		if (is_numeric($input)) {
			return new Time((int)$input, $timezone);
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(:\d{2})?)?$/', $input)) {
			return new Time($input, $timezone);
		}

		// Try style-based formatters first (robust for many locales)
		$stylePairs = [
			[IntlDateFormatter::FULL,   IntlDateFormatter::NONE],
			[IntlDateFormatter::LONG,   IntlDateFormatter::NONE],
			[IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE],
			[IntlDateFormatter::FULL,   IntlDateFormatter::SHORT],
			[IntlDateFormatter::FULL,   IntlDateFormatter::MEDIUM],
			[IntlDateFormatter::LONG,   IntlDateFormatter::SHORT],
			[IntlDateFormatter::LONG,   IntlDateFormatter::MEDIUM],
			[IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT],
			[IntlDateFormatter::MEDIUM, IntlDateFormatter::MEDIUM],
		];
		foreach ($stylePairs as [$dateStyle, $timeStyle]) {
			$fmt = new IntlDateFormatter(
				(string)$locale,
				$dateStyle,
				$timeStyle,
				$timezone instanceof DateTimeZone ? $timezone : null
			);
			$ts = $fmt->parse($input);
			if ($ts !== false) {
				return new Time((int)$ts, $timezone);
			}
		}

		// Fallback: explicit patterns (covers variations like commas and "de"/"às" connectors)
		$patterns = self::_patternsForLocale((string)$locale);
		foreach ($patterns as $pattern) {
			$fmt = new IntlDateFormatter(
				(string)$locale,
				IntlDateFormatter::NONE,
				IntlDateFormatter::NONE,
				$timezone instanceof DateTimeZone ? $timezone : null,
				null,
				$pattern
			);
			$ts = $fmt->parse($input);
			if ($ts !== false) {
				return new Time((int)$ts, $timezone);
			}
		}

		return null;
	}

/**
 * Parse and return normalized ISO string (Y-m-d H:i:s).
 *
 * @param string $input
 * @param string|DateTimeZone|null $locale
 * @param string|DateTimeZone|null $timezone
 * @return string|null
 */
	public static function parseToIso(string $input, string|DateTimeZone $locale = null, string|DateTimeZone $timezone = null): ?string {
		$time = self::parseToTime($input, $locale, $timezone);
		return $time ? $time->format('Y-m-d H:i:s') : null;
	}

/**
 * Soft-clean common connectors and punctuation from localized date strings.
 *
 * @param string $input
 * @return string
 */
	protected static function _preNormalize(string $input, string $locale = ''): string {
		$clean = trim($input);
		// Remove duplicated spaces, normalize commas
		$clean = preg_replace('/\s+/', ' ', $clean);
		$clean = preg_replace('/\s*,\s*/', ', ', $clean);
		// Remove common language connectors that confuse the parser
		$removeWords = [
			// Portuguese
			' de ', ' da ', ' do ', ' das ', ' dos ', ' às ', ' às ', ' às ', ' às ',
			// Spanish/Italian/French connectors
			' del ', ' al ', ' a las ', ' a la ',
		];
		$clean = str_ireplace($removeWords, ' ', $clean);
		$clean = preg_replace('/\s+/', ' ', $clean);
		$clean = trim($clean);

		// Locale-specific normalizations
		$l = strtolower(str_replace('_', '-', $locale));
		if (str_starts_with($l, 'pt')) {
			// Normalize weekdays missing "-feira" (Mon-Fri) to full forms
			$patterns = [
				'/\b(segunda)(?!-?feira)\b/i',
				'/\b(ter[çc]a)(?!-?feira)\b/i',
				'/\b(quarta)(?!-?feira)\b/i',
				'/\b(quinta)(?!-?feira)\b/i',
				'/\b(sexta)(?!-?feira)\b/i',
			];
			$replacements = [
				'segunda-feira',
				'terça-feira',
				'quarta-feira',
				'quinta-feira',
				'sexta-feira',
			];
			$clean = preg_replace($patterns, $replacements, $clean);
		}

		return $clean;
	}

/**
 * Patterns to try per-locale (ICU pattern syntax).
 * Order matters: most specific first.
 *
 * @param string $locale
 * @return array
 */
	protected static function _patternsForLocale(string $locale): array {
		$l = strtolower(str_replace('_', '-', $locale));

		$common = [
			// With weekday, with/without comma
			"EEEE, d MMMM yyyy HH:mm",
			"EEEE, d MMMM yyyy",
			"EEEE d MMMM yyyy",
			"EEEE, d, MMMM yyyy",
			// Without weekday
			"d MMMM yyyy HH:mm",
			"d MMMM yyyy",
			// Numeric fallbacks
			"dd/MM/yyyy HH:mm",
			"dd/MM/yyyy",
			"dd-MM-yyyy HH:mm",
			"dd-MM-yyyy",
			"yyyy-MM-dd HH:mm",
			"yyyy-MM-dd",
			// 12h times
			"EEEE, d MMMM yyyy h:mm a",
			"d MMMM yyyy h:mm a",
			"dd/MM/yyyy h:mm a",
		];

		// Locale-specific variants (phrases like "16 de Novembro de 2025")
		if (str_starts_with($l, 'pt')) {
			$pt = [
				"EEEE, d 'de' MMMM 'de' yyyy HH:mm",
				"EEEE, d 'de' MMMM 'de' yyyy",
				"d 'de' MMMM 'de' yyyy HH:mm",
				"d 'de' MMMM 'de' yyyy",
				"d 'de' MMMM yyyy",
				"EEEE, d, MMMM yyyy",
			];
			return array_merge($pt, $common);
		}

		if (str_starts_with($l, 'es')) {
			$es = [
				"EEEE, d 'de' MMMM 'de' yyyy HH:mm",
				"EEEE, d 'de' MMMM 'de' yyyy",
				"d 'de' MMMM 'de' yyyy HH:mm",
				"d 'de' MMMM 'de' yyyy",
			];
			return array_merge($es, $common);
		}

		if (str_starts_with($l, 'fr')) {
			$fr = [
				"EEEE d MMMM yyyy HH:mm",
				"EEEE d MMMM yyyy",
				"d MMMM yyyy HH:mm",
				"d MMMM yyyy",
			];
			return array_merge($fr, $common);
		}

		// Default
		return $common;
	}

/**
 * Convert ICU pattern to PHP date pattern.
 *
 * ## Examples
 *
 * ```php
 * LocalizedDateParser::icuToPhpPattern('dd-MM-yyyy');
 * // Returns 'd-m-Y'
 * ```
 *
 * @param string $pattern ICU pattern
 * @return string PHP date pattern
 */
    public static function icuToPhpPattern(string $pattern) {
        // Protect quoted literals in ICU ('text') and escape them for PHP date()
        return preg_replace_callback(
            // 1) quoted literals, 2) runs of the same A–Z letter (ICU token), 3) separators (/, -, ., space)
            "/'([^']*)'|([A-Za-z])\\2*|[\\/\\-\\.\\s]+/u",
            function ($matches) {
                if (isset($matches[1]) && $matches[1] !== '') {
					// literal -> escape each char for PHP date()
                    return preg_replace('/(.)/u', '\\\\$1', $matches[1]);
                }

                // ICU token run (e.g., yyyy, MM, dd)
                if (isset($matches[2]) && $matches[2] !== '') {
                    $token = $matches[0];
                    if (isset(self::$map[$token])) {
                        return self::$map[$token];
                    }
                    // Fallback: map per-char if the exact token isn't in the map
                    $out = '';
                    foreach (preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                        $out .= self::$map[$ch] ?? $ch;
                    }
                    return $out;
                }

                // Separator run (/, -, ., space). PHP date() prints these as-is.
                return $matches[0];
            },
            $pattern
        );
    }

/**
 * Convert ICU pattern to PHP date pattern.
 *
 * ## Examples
 *
 * ```php
 * LocalizedDateParser::phpToIcuPattern('d-m-Y');
 * // Returns 'dd-MM-yyyy'
 *
 * @param string $pattern ICU pattern
 * @return string PHP date pattern
 */
	public static function phpToIcuPattern(string $pattern): string {
		// Reverse map with chosen ICU defaults
		static $reverse = [
			'l' => 'EEEE', 'D' => 'EEE',
			'F' => 'MMMM', 'M' => 'MMM', 'm' => 'MM', 'n' => 'M',
			'Y' => 'yyyy', 'y' => 'yy',
			'H' => 'HH',   'G' => 'H',
			'h' => 'hh',   'g' => 'h',
			'i' => 'mm',   's' => 'ss',
			'A' => 'a',
			'd' => 'dd',   'j' => 'd',
		];

		return preg_replace_callback('/\\\\.|./u', function ($m) use ($reverse) {
			$ch = $m[0];
			// Preserve escaped char as literal for ICU
			if (strlen($ch) === 2 && $ch[0] === '\\') {
				$lit = $ch[1];
				return "'" . str_replace("'", "''", $lit) . "'";
			}
			// Token
			if (isset($reverse[$ch])) {
				return $reverse[$ch];
			}
			// Plain text (keep as-is; ICU treats unrecognized letters as literals)
			return $ch;
		}, $pattern);
	}

}


