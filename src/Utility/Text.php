<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Copyright (c) 2008 Sebastián Grignoli (https://github.com/neitanod/forceutf8)
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

use Nata\Core\Configure;
use Nata\Utility\Html;
use UConverter;

/**
 * String handling methods.
 */
class Text {

/**
 * ASCII-art emoticons to Unicode emoji.
 *
 * @var array
 */
    protected static $_asciiToEmoji = [
        'o/' => '👋',
        '</3' => '💔', '<3' => '💗',
        '=-3' => '😁',
        '=-D' => '😁',
        '=3' => '😁',
        'X-D' => '😆', 'XD' => '😆', 'x-D' => '😆', 'xD' => '😆',
        'X-P' => '😝', 'XP' => '😝', 'x-p' => '😝', 'xp' => '😝',
        ':3' => '😄',
        ':\')' => '😂',
        ':\'-)' => '😂',
        ':-))' => '😃',
        '8)' => '😎',
        '8-D' => '🤓', '8D' => '🤓', 'B^D' => '🤓',
        ':)' => '🙂', ':-)' => '🙂', ':]' => '🙂', '=)' => '🙂', '=]' => '🙂', ':o)' => '🙂', ':^)' => '🙂',
        ':D' => '😀', '=D' => '😀', ':-D' => '😃',
        ';D' => '😉',
        ':c)' => '😄',
        ':}' => '😄',
        ':っ)' => '😄',
        '0:)' => '😇',
        '0:-)' => '😇',
        '0:-3' => '😇',
        '0:3' => '😇',
        '0;^)' => '😇',
        'O:-)' => '😇',
        '3:)' => '😈',
        '3:-)' => '😈',
        '}:)' => '😈',
        '}:-)' => '😈',
        '*)' => '😉',
        '*-)' => '😉',
        ':-,' => '😉',
        ';)' => '😉',';-)' => '😉',';-]' => '😉',';]' => '😉',';^)' => '😉',
        ':-|' => '😐',
        ':|' => '😐',
        ':(' => '😒',
        ':-(' => '😒',
        ':-<' => '😒',
        ':-[' => '😒',
        ':-c' => '😒',
        ':<' => '😒',
        ':[' => '😒',
        ':c' => '😒',
        ':{' => '😒',
        ':っC' => '😒',
        ':-P' => '😛',':-b' => '😛',':-p' => '😛',':-Þ' => '😛',':-þ' => '😛',':P' => '😛',':b' => '😛',':-b' => '😛',':p' => '😛',':Þ' => '😛',':þ' => '😛',
        ';-P' => '😜',';-b' => '😜',';-p' => '😜',';-Þ' => '😜',';-þ' => '😜',';P' => '😜',';b' => '😜',';-b' => '😜',';p' => '😜',';Þ' => '😜',';þ' => '😜',
        ';(' => '😜',
        '=p' => '😜',
        'd:' => '😜',
        '%)' => '😖',
        '%-)' => '😖',
        ':-||' => '😠',
        ':@' => '😠',
        ':-.' => '😡',
        ':-/' => '😡',
        ':/' => '😡',
        ':L' => '😡',
        ':S' => '😡',
        ':\\' => '😡',
        '=/' => '😡',
        '=L' => '😡',
        '=\\' => '😡',
        ':\'(' => '😢',
        ':\'-(' => '😢',
        '^5'  => '😤',
        '^<_<' => '😤',
        'o/\\o' => '😤',
        '|-O' => '😫',
        '|;-)' => '😫',
        ':###..' => '😰',
        ':-###..' => '😰',
        'D-\':' => '😱',
        'D8' => '😱',
        'D:' => '😱',
        'D:<' => '😱',
        'D;' => '😱',
        'D=' => '😱',
        'DX' => '😱',
        'v.v' => '😱',
        '8-0' => '😲',
        ':-O' => '😲',
        ':-o' => '😲',
        ':O' => '😲',
        ':o' => '😲',
        'O-O' => '😲',
        'O_O' => '😲',
        'O_o' => '😲',
        'o-o' => '😲',
        'o_O' => '😲',
        'o_o' => '😲',
        ':$' => '😳',
        '#-)' => '😵',
        ':#' => '😶',
        ':&' => '😶',
        ':-#' => '😶',
        ':-&' => '😶',
        ':-X' => '😶',
        ':X' => '😶',
        ':-J' => '😼',
        ':*' => '😽',
        ':^*' => '😽',
        'ಠ_ಠ' => '🙅',
        '*\\0/*'=> '🙆',
        '\\o/' => '🙆',
        ':>' => '😄',
        '>.<' => '😡',
        '>:(' => '😠',
        '>:)' => '😈',
        '>:-)' => '😈',
        '>:/' => '😡',
        '>:O' => '😲',
        '>:P' => '😜',
        '>:[' => '😒',
        '>:\\' => '😡',
        '>;)' => '😈',
        '>_>^' => '😤'
    ];

/**
 * ICONV options.
 *
 * @var const
 */
    public const ICONV_TRANSLIT = "TRANSLIT";
    public const ICONV_IGNORE = "IGNORE";
    public const WITHOUT_ICONV = "";

/**
 * Windows-1252 encoding map.
 *
 * @var array
 */
    protected static $_win1252ToUtf8 = [
        128 => "\xe2\x82\xac",

        130 => "\xe2\x80\x9a",
        131 => "\xc6\x92",
        132 => "\xe2\x80\x9e",
        133 => "\xe2\x80\xa6",
        134 => "\xe2\x80\xa0",
        135 => "\xe2\x80\xa1",
        136 => "\xcb\x86",
        137 => "\xe2\x80\xb0",
        138 => "\xc5\xa0",
        139 => "\xe2\x80\xb9",
        140 => "\xc5\x92",

        142 => "\xc5\xbd",


        145 => "\xe2\x80\x98",
        146 => "\xe2\x80\x99",
        147 => "\xe2\x80\x9c",
        148 => "\xe2\x80\x9d",
        149 => "\xe2\x80\xa2",
        150 => "\xe2\x80\x93",
        151 => "\xe2\x80\x94",
        152 => "\xcb\x9c",
        153 => "\xe2\x84\xa2",
        154 => "\xc5\xa1",
        155 => "\xe2\x80\xba",
        156 => "\xc5\x93",

        158 => "\xc5\xbe",
        159 => "\xc5\xb8"
    ];

/**
 * Broken UTF-8 encoding map.
 *
 * @var array
 */
    protected static $_brokenUtf8ToUtf8 = [
        "\xc2\x80" => "\xe2\x82\xac",

        "\xc2\x82" => "\xe2\x80\x9a",
        "\xc2\x83" => "\xc6\x92",
        "\xc2\x84" => "\xe2\x80\x9e",
        "\xc2\x85" => "\xe2\x80\xa6",
        "\xc2\x86" => "\xe2\x80\xa0",
        "\xc2\x87" => "\xe2\x80\xa1",
        "\xc2\x88" => "\xcb\x86",
        "\xc2\x89" => "\xe2\x80\xb0",
        "\xc2\x8a" => "\xc5\xa0",
        "\xc2\x8b" => "\xe2\x80\xb9",
        "\xc2\x8c" => "\xc5\x92",

        "\xc2\x8e" => "\xc5\xbd",


        "\xc2\x91" => "\xe2\x80\x98",
        "\xc2\x92" => "\xe2\x80\x99",
        "\xc2\x93" => "\xe2\x80\x9c",
        "\xc2\x94" => "\xe2\x80\x9d",
        "\xc2\x95" => "\xe2\x80\xa2",
        "\xc2\x96" => "\xe2\x80\x93",
        "\xc2\x97" => "\xe2\x80\x94",
        "\xc2\x98" => "\xcb\x9c",
        "\xc2\x99" => "\xe2\x84\xa2",
        "\xc2\x9a" => "\xc5\xa1",
        "\xc2\x9b" => "\xe2\x80\xba",
        "\xc2\x9c" => "\xc5\x93",

        "\xc2\x9e" => "\xc5\xbe",
        "\xc2\x9f" => "\xc5\xb8"
    ];

/**
 * UTF-8 to Windows-1252 encoding map.
 *
 * @var array
 */
    protected static $_utf8ToWin1252 = [
        "\xe2\x82\xac" => "\x80",

        "\xe2\x80\x9a" => "\x82",
        "\xc6\x92"     => "\x83",
        "\xe2\x80\x9e" => "\x84",
        "\xe2\x80\xa6" => "\x85",
        "\xe2\x80\xa0" => "\x86",
        "\xe2\x80\xa1" => "\x87",
        "\xcb\x86"     => "\x88",
        "\xe2\x80\xb0" => "\x89",
        "\xc5\xa0"     => "\x8a",
        "\xe2\x80\xb9" => "\x8b",
        "\xc5\x92"     => "\x8c",

        "\xc5\xbd"     => "\x8e",


        "\xe2\x80\x98" => "\x91",
        "\xe2\x80\x99" => "\x92",
        "\xe2\x80\x9c" => "\x93",
        "\xe2\x80\x9d" => "\x94",
        "\xe2\x80\xa2" => "\x95",
        "\xe2\x80\x93" => "\x96",
        "\xe2\x80\x94" => "\x97",
        "\xcb\x9c"     => "\x98",
        "\xe2\x84\xa2" => "\x99",
        "\xc5\xa1"     => "\x9a",
        "\xe2\x80\xba" => "\x9b",
        "\xc5\x93"     => "\x9c",

        "\xc5\xbe"     => "\x9e",
        "\xc5\xb8"     => "\x9f"
    ];


/**
 * Generate a random UUID version 4.
 *
 * Warning: This method should not be used as a random seed for any cryptographic operations.
 * Instead, you should use `Security::randomBytes()` or `Security::randomString()` instead.
 *
 * It should also not be used to create identifiers that have security implications, such as
 * 'unguessable' URL identifiers. Instead, you should use {@link \Nata\Utility\Security::randomBytes()}` for that.
 *
 * @see https://www.ietf.org/rfc/rfc4122.txt
 * @return string RFC 4122 UUID
 * @copyright Matt Farina MIT License https://github.com/lootils/uuid/blob/master/LICENSE
 */
    public static function uuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            random_int(0, 65535),
            random_int(0, 65535),
            // 16 bits for "time_mid"
            random_int(0, 65535),
            // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
            random_int(0, 4095) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535)
        );
    }

/**
 * Generate a random UUID version 7.
 *
 * @return string UUID v7
 */
    public static function uuidv7(): string {
        $time = microtime(true);
        $seconds = (int) $time;
        $milliseconds = (int) (($time - $seconds) * 1000);
        $nanoseconds = (int) (($time - $seconds) * 1e9);

        // Timestamp portion
        $timestamp = dechex($seconds);
        $timestamp = str_pad($timestamp, 12, '0', STR_PAD_LEFT);

        // Generate random bits for UUID
        $randomBits = bin2hex(random_bytes(10));

        // Assemble the UUID
        $uuid = sprintf(
            '%08s-%04s-%04x-%04x-%012s',
            substr($timestamp, 0, 8),
            substr($timestamp, 8, 4),
            0x7000 | (($milliseconds & 0xFFF000) >> 12), // Version 7
            0x8000 | ($nanoseconds & 0x3FFF), // Variant
            substr($randomBits, 0, 12)
        );

        return $uuid;
    }

/**
 * Generates random alphanumeric string.
 *
 * @param int $length Length
 * @param bool $alphanumeric True to use alphanumeric characters
 * @return string Random generated string
 */
    public static function random($length = 5, $alphanumeric = false) {
        // Hexdecimal string based on clock
        if ($alphanumeric === false) {
            return substr(str_shuffle(sha1(microtime())), 0, $length);
        }

        $dictionary = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $dictionaryLength = strlen($dictionary);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomCharacter = $dictionary[random_int(0, $dictionaryLength - 1)];
            $randomString .= $randomCharacter;
        }

        return $randomString;
    }

/**
 * Tokenizes a string using $separator, ignoring any instance of $separator that appears between
 * $leftBound and $rightBound.
 *
 * @param string $data The data to tokenize.
 * @param string $separator The token to split the data on.
 * @param string $leftBound The left boundary to ignore separators in.
 * @param string $rightBound The right boundary to ignore separators in.
 * @return mixed Array of tokens in $data or original input if empty.
 */
    public static function tokenize($data, $separator = ',', $leftBound = '(', $rightBound = ')') {
        $results = [];
        if (empty($data)) {
            return $results;
        }

        $depth = 0;
        $offset = 0;
        $buffer = '';
        $length = strlen($data);
        $open = false;

        while ($offset <= $length) {
            $tmpOffset = -1;
            $offsets = [
                strpos($data, $separator, $offset),
                strpos($data, $leftBound, $offset),
                strpos($data, $rightBound, $offset)
            ];
            for ($i = 0; $i < 3; $i++) {
                if ($offsets[$i] !== false && ($offsets[$i] < $tmpOffset || $tmpOffset == -1)) {
                    $tmpOffset = $offsets[$i];
                }
            }
            if ($tmpOffset !== -1) {
                $buffer .= substr($data, $offset, ($tmpOffset - $offset));
                if (!$depth && $data[$tmpOffset] == $separator) {
                    $results[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $data[$tmpOffset];
                }
                if ($leftBound != $rightBound) {
                    if ($data[$tmpOffset] == $leftBound) {
                        $depth++;
                    }
                    if ($data[$tmpOffset] == $rightBound) {
                        $depth--;
                    }
                } else {
                    if ($data[$tmpOffset] == $leftBound) {
                        if (!$open) {
                            $depth++;
                            $open = true;
                        } else {
                            $depth--;
                        }
                    }
                }
                $offset = ++$tmpOffset;
            } else {
                $results[] = $buffer . substr($data, $offset);
                $offset = $length + 1;
            }
        }

        if (empty($results) && !empty($buffer)) {
            $results[] = $buffer;
        }

        return array_map('trim', $results);
    }

/**
 * Replaces variable placeholders inside a $str with any given $data. Each key in the $data array
 * corresponds to a variable placeholder name in $str.
 * Example: `Text::insert(':name is :age years old.', array('name' => 'Bob', '65'));`
 * Returns: Bob is 65 years old.
 *
 * Available $options are:
 *
 * - before: The character or string in front of the name of the variable placeholder (Defaults to `:`)
 * - after: The character or string after the name of the variable placeholder (Defaults to null)
 * - escape: The character or string used to escape the before character / string (Defaults to `\`)
 * - format: A regex to use for matching variable placeholders. Default is: `/(?<!\\)\:%s/`
 *   (Overwrites before, after, breaks escape / clean)
 * - clean: A boolean or array with instructions for Text::cleanInsert
 *
 * @param string $str A string containing variable placeholders
 * @param array $data A key => val array where each key stands for a placeholder variable name
 *     to be replaced with val
 * @param array $options An array of options, see description above
 * @return string
 */
    public static function insert($str, $data, $options = []) {
        $defaults = [
            'before' => ':', 'after' => '', 'escape' => '\\', 'format' => null, 'clean' => false
        ];

        $options += $defaults;
        $format = $options['format'];
        $data = (array)$data;

        if (empty($data)) {
            return ($options['clean']) ? Text::cleanInsert($str, $options) : $str;
        }

        if (!isset($format)) {
            $format = sprintf(
                '/(?<!%s)%s%%s%s/',
                preg_quote($options['escape'], '/'),
                str_replace('%', '%%', preg_quote($options['before'], '/')),
                str_replace('%', '%%', preg_quote($options['after'], '/'))
            );
        }

        if (strpos($str, '?') !== false && is_numeric(key($data))) {
            $offset = 0;
            while (($pos = strpos($str, '?', $offset)) !== false) {
                $val = array_shift($data);
                $offset = $pos + strlen($val);
                $str = substr_replace($str, $val, $pos, 1);
            }
            return ($options['clean']) ? Text::cleanInsert($str, $options) : $str;
        }

        asort($data);

        $dataKeys = array_keys($data);
        $hashKeys = array_map('crc32', $dataKeys);
        $tempData = array_combine($dataKeys, $hashKeys);
        krsort($tempData);

        foreach ($tempData as $key => $hashVal) {
            $key = sprintf($format, preg_quote($key, '/'));
            $str = preg_replace($key, $hashVal, $str);
        }
        $dataReplacements = array_combine($hashKeys, array_values($data));
        foreach ($dataReplacements as $tmpHash => $tmpValue) {
            $tmpValue = (is_array($tmpValue)) ? '' : $tmpValue;
            $str = str_replace($tmpHash, $tmpValue, $str);
        }

        if (!isset($options['format']) && isset($options['before'])) {
            $str = str_replace($options['escape'] . $options['before'], $options['before'], $str);
        }

        return ($options['clean']) ? Text::cleanInsert($str, $options) : $str;
    }

/**
 * Cleans up a Text::insert() formatted string with given $options depending on the 'clean' key in
 * $options. The default method used is text but html is also available. The goal of this function
 * is to replace all whitespace and unneeded markup around placeholders that did not get replaced
 * by Text::insert().
 *
 * @param string $str
 * @param array $options
 * @return string
 * @see Text::insert()
 */
    public static function cleanInsert($str, $options) {
        $clean = $options['clean'];
        if (!$clean) {
            return $str;
        }
        if ($clean === true) {
            $clean = array('method' => 'text');
        }
        if (!is_array($clean)) {
            $clean = array('method' => $options['clean']);
        }
        switch ($clean['method']) {
            case 'html':
                $clean = array_merge(array(
                    'word' => '[\w,.]+',
                    'andText' => true,
                    'replacement' => '',
                ), $clean);
                $kleenex = sprintf(
                    '/[\s]*[a-z]+=(")(%s%s%s[\s]*)+\\1/i',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                if ($clean['andText']) {
                    $options['clean'] = array('method' => 'text');
                    $str = Text::cleanInsert($str, $options);
                }
                break;
            case 'text':
                $clean = array_merge(array(
                    'word' => '[\w,.]+',
                    'gap' => '[\s]*(?:(?:and|or)[\s]*)?',
                    'replacement' => '',
                ), $clean);

                $kleenex = sprintf(
                    '/(%s%s%s%s|%s%s%s%s)/',
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/'),
                    $clean['gap'],
                    $clean['gap'],
                    preg_quote($options['before'], '/'),
                    $clean['word'],
                    preg_quote($options['after'], '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                break;
        }
        return $str;
    }

/**
 * Parses placeholders/blocks with options/config, useful for components insertion.
 * Example: `Text::extractBlock('Your event: [event id=1234]. Have fun.');`
 * Returns: [['placeholder' => '[event id=1234]', 'name' => 'event', 'params' => ['id' => 1234]]]
 *
 * Available $options are:
 *
 * - before: The character or string in front of the name of the variable placeholder (Defaults to `[`)
 * - after: The character or string after the name of the variable placeholder (Defaults to `]`)
 *
 * @param string $str A string containing variable placeholders
 * @param array $options An array of options, see description above
 * @return array Extracted blocks
 */
    public static function extractBlock(string $str, array $options = []) {
        $defaults = [
            'before' => '[', 'after' => ']', 'escape' => '\\'
        ];
        $options += $defaults;

        $regex = sprintf("/\%s([a-z]+)\s*?(.*?)\%s/i", $options['before'], $options['after']);
        if (!preg_match_all($regex, $str, $matches)) {
            return null;
        }

        $blocks = [];
        foreach ($matches[0] as $index => $match) {
            $params = trim($matches[2][$index]);
            if ($params && preg_match_all("/(\w+)=[\"'](.*?)[\"']|(\w+)=([0-9]+)/i", $params, $_matches) > 0) {
                $params = [];
                foreach ($_matches[0] as $_index => $_match) {
                    $params[$_matches[1][$_index]] = $_matches[2][$_index];
                }
            }

            $blocks[] = [
                'placeholder' => $match,
                'name' => $matches[1][$index],
                'params' => $params
            ];
        }

        return $blocks;
    }

/**
 * Wraps text to a specific width, can optionally wrap at word breaks.
 *
 * ### Options
 *
 * - `width` The width to wrap to. Defaults to 72.
 * - `wordWrap` Only wrap on words breaks (spaces) Defaults to true.
 * - `indent` String to indent with. Defaults to null.
 * - `indentAt` 0 based index to start indenting at. Defaults to 0.
 *
 * @param string $text The text to format.
 * @param array|integer $options Array of options to use, or an integer to wrap the text to.
 * @return string Formatted text.
 */
    public static function wrap($text, $options = []): string {
        if (is_numeric($options)) {
            $options = array('width' => $options);
        }

        $options += array('width' => 72, 'wordWrap' => true, 'indent' => null, 'indentAt' => 0);

        if ($options['wordWrap']) {
            $wrapped = self::wordWrap($text, $options['width'], "\n");
        } else {
            $wrapped = trim(chunk_split($text, $options['width'] - 1, "\n"));
        }

        if (!empty($options['indent'])) {
            $chunks = explode("\n", $wrapped);

            for ($i = $options['indentAt'], $len = count($chunks); $i < $len; $i++) {
                $chunks[$i] = $options['indent'] . $chunks[$i];
            }

            $wrapped = implode("\n", $chunks);
        }

        return $wrapped;
    }

/**
 * Wraps a complete block of text to a specific width, can optionally wrap
 * at word breaks.
 *
 * ### Options
 *
 * - `width` The width to wrap to. Defaults to 72.
 * - `wordWrap` Only wrap on words breaks (spaces) Defaults to true.
 * - `indent` String to indent with. Defaults to null.
 * - `indentAt` 0 based index to start indenting at. Defaults to 0.
 *
 * @param string $text The text to format.
 * @param array<string, mixed>|int $options Array of options to use, or an integer to wrap the text to.
 * @return string Formatted text.
 */
    public static function wrapBlock(string $text, $options = []): string {
        if (is_numeric($options)) {
            $options = ['width' => $options];
        }
        $options += ['width' => 72, 'wordWrap' => true, 'indent' => null, 'indentAt' => 0];

        $wrapped = self::wrap($text, $options);

        if (!empty($options['indent'])) {
            $indentationLength = mb_strlen($options['indent']);
            $chunks = explode("\n", $wrapped);
            $count = count($chunks);
            if ($count < 2) {
                return $wrapped;
            }
            $toRewrap = '';
            for ($i = $options['indentAt']; $i < $count; $i++) {
                $toRewrap .= mb_substr($chunks[$i], $indentationLength) . ' ';
                unset($chunks[$i]);
            }
            $options['width'] -= $indentationLength;
            $options['indentAt'] = 0;
            $rewrapped = self::wrap($toRewrap, $options);
            $newChunks = explode("\n", $rewrapped);

            $chunks = array_merge($chunks, $newChunks);
            $wrapped = implode("\n", $chunks);
        }

        return $wrapped;
    }

/**
 * Unicode aware version of wordwrap.
 *
 * @param string $text The text to format.
 * @param integer $width The width to wrap to. Defaults to 72.
 * @param string $break The line is broken using the optional break parameter. Defaults to '\n'.
 * @param boolean $cut If the cut is set to true, the string is always wrapped at the specified width.
 * @return string Formatted text.
 */
    public static function wordWrap($text, $width = 72, $break = "\n", $cut = false) {
        if ($cut) {
            $parts = array();
            while (mb_strlen($text) > 0) {
                $part = mb_substr($text, 0, $width);
                $parts[] = trim($part);
                $text = trim(mb_substr($text, mb_strlen($part)));
            }
            return implode($break, $parts);
        }

        $parts = [];
        while (mb_strlen($text) > 0) {
            if ($width >= mb_strlen($text)) {
                $parts[] = trim($text);
                break;
            }

            $part = mb_substr($text, 0, $width);
            $nextChar = mb_substr($text, $width, 1);
            if ($nextChar !== ' ') {
                $breakAt = mb_strrpos($part, ' ');
                if ($breakAt === false) {
                    $breakAt = mb_strpos($text, ' ', $width);
                }
                if ($breakAt === false) {
                    $parts[] = trim($text);
                    break;
                }
                $part = mb_substr($text, 0, $breakAt);
            }

            $part = trim($part);
            $parts[] = $part;
            $text = trim(mb_substr($text, mb_strlen($part)));
        }

        return implode($break, $parts);
    }

/**
 * Highlights a given phrase in a text. You can specify any expression in highlighter that
 * may include the \1 expression to include the $phrase found.
 *
 * ### Options:
 *
 * - `format` The piece of html with that the phrase will be highlighted
 * - `html` If true, will ignore any HTML tags, ensuring that only the correct text is highlighted
 * - `regex` a custom regex rule that is used to match words, default is '|$tag|iu'
 *
 * @param string $text Text to search the phrase in
 * @param string $phrase The phrase that will be searched
 * @param array $options An array of html attributes and options.
 * @return string The highlighted text
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::highlight
 */
    public static function highlight($text, $phrase, $options = []) {
        if (empty($phrase)) {
            return $text;
        }

        $default = [
            'format' => '<span class="highlight">\1</span>',
            'html' => false,
            'regex' => "|%s|iu"
        ];
        $options = array_merge($default, $options);
        extract($options);

        if (is_array($phrase)) {
            $replace = [];
            $with = [];

            foreach ($phrase as $key => $segment) {
                $segment = '(' . preg_quote($segment, '|') . ')';
                if ($html) {
                    $segment = "(?![^<]+>)$segment(?![^<]+>)";
                }

                $with[] = (is_array($format)) ? $format[$key] : $format;
                $replace[] = sprintf($options['regex'], $segment);
            }

            return preg_replace($replace, $with, $text);
        }

        $phrase = '(' . preg_quote($phrase, '|') . ')';
        if ($html) {
            $phrase = "(?![^<]+>)$phrase(?![^<]+>)";
        }

        return preg_replace(sprintf($options['regex'], $phrase), $format, $text);
    }

/**
 * Strips given text of all links (<a href=....)
 *
 * @param string $text Text
 * @return string The text without links
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::stripLinks
 */
    public static function stripLinks($text) {
        return preg_replace('|<a\s+[^>]+>|im', '', preg_replace('|<\/a>|im', '', $text));
    }

/**
 * Truncates text starting from the end.
 *
 * Cuts a string to the length of $length and replaces the first characters
 * with the ellipsis if the text is longer than length.
 *
 * ### Options:
 *
 * - `ellipsis` Will be used as Beginning and prepended to the trimmed string
 * - `exact` If false, $text will not be cut mid-word
 *
 * @param string $text String to truncate.
 * @param integer $length Length of returned string, including ellipsis.
 * @param array $options An array of options.
 * @return string Trimmed string.
 */
    public static function tail($text, $length = 100, $options = []) {
        $default = [
            'ellipsis' => '...', 'exact' => true
        ];
        $options = array_merge($default, $options);
        extract($options);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncate = mb_substr($text, mb_strlen($text) - $length + mb_strlen($ellipsis));
        if (!$exact) {
            $spacepos = mb_strpos($truncate, ' ');
            $truncate = $spacepos === false ? '' : trim(mb_substr($truncate, $spacepos));
        }

        return $ellipsis . $truncate;
    }

/**
 * Truncates text.
 *
 * Cuts a string to the length of $length and replaces the last characters
 * with the ellipsis if the text is longer than length.
 *
 * ### Options:
 *
 * - `ellipsis` Will be used as Ending and appended to the trimmed string (`ending` is deprecated)
 * - `exact` If false, $text will not be cut mid-word
 * - `html` If true, HTML tags would be handled correctly
 *
 * @param string $text String to truncate.
 * @param integer $length Length of returned string, including ellipsis.
 * @param array $options An array of html attributes and options.
 * @return string Trimmed string.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::truncate
 */
    public static function truncate($text, $length = 100, array $options = []) {
        $default = [
            'ellipsis' => '...', 'exact' => true, 'html' => false
        ];

        if (isset($options['ending'])) {
            $default['ellipsis'] = $options['ending'];
        } elseif (!empty($options['html']) && Configure::read('App.encoding') === 'UTF-8') {
            $default['ellipsis'] = "\xe2\x80\xa6";
        }

        $options = array_merge($default, $options);

        extract($options);

        if ($html) {
            if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
                return $text;
            }

            $totalLength = mb_strlen(strip_tags($ellipsis));
            $openTags = array();
            $truncate = '';

            preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);

            foreach ($tags as $tag) {
                if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
                    if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
                        array_unshift($openTags, $tag[2]);
                    } elseif (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                        $pos = array_search($closeTag[1], $openTags);
                        if ($pos !== false) {
                            array_splice($openTags, $pos, 1);
                        }
                    }
                }

                $truncate .= $tag[1];

                $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));

                if ($contentLength + $totalLength > $length) {
                    $left = $length - $totalLength;
                    $entitiesLength = 0;
                    if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
                        foreach ($entities[0] as $entity) {
                            if ($entity[1] + 1 - $entitiesLength <= $left) {
                                $left--;
                                $entitiesLength += mb_strlen($entity[0]);
                            } else {
                                break;
                            }
                        }
                    }

                    $truncate .= mb_substr($tag[3], 0, $left + $entitiesLength);
                    break;
                } else {
                    $truncate .= $tag[3];
                    $totalLength += $contentLength;
                }
                if ($totalLength >= $length) {
                    break;
                }
            }
        } else {
            if (mb_strlen($text) <= $length) {
                return $text;
            }

            $truncate = mb_substr($text, 0, $length - mb_strlen($ellipsis));
        }

        if (!$exact) {
            $spacepos = mb_strrpos($truncate, ' ');
            if ($html) {
                $truncateCheck = mb_substr($truncate, 0, $spacepos);
                $lastOpenTag = mb_strrpos($truncateCheck, '<');
                $lastCloseTag = mb_strrpos($truncateCheck, '>');
                if ($lastOpenTag > $lastCloseTag) {
                    preg_match_all('/<[\w]+[^>]*>/s', $truncate, $lastTagMatches);
                    $lastTag = array_pop($lastTagMatches[0]);
                    $spacepos = mb_strrpos($truncate, $lastTag) + mb_strlen($lastTag);
                }
                $bits = mb_substr($truncate, $spacepos);
                preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                if (!empty($droppedTags)) {
                    if (!empty($openTags)) {
                        foreach ($droppedTags as $closingTag) {
                            if (!in_array($closingTag[1], $openTags)) {
                                array_unshift($openTags, $closingTag[1]);
                            }
                        }
                    } else {
                        foreach ($droppedTags as $closingTag) {
                            $openTags[] = $closingTag[1];
                        }
                    }
                }
            }
            $truncate = mb_substr($truncate, 0, $spacepos);
        }

        $truncate .= $ellipsis;

        if ($html) {
            foreach ($openTags as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }

        return $truncate;
    }

/**
 * Extracts an excerpt from the text surrounding the phrase with a number of characters on each side
 * determined by radius.
 *
 * @param string $text String to search the phrase in
 * @param string $phrase Phrase that will be searched for
 * @param integer $radius The amount of characters that will be returned on each side of the founded phrase
 * @param string $ellipsis Ending that will be appended
 * @return string Modified string
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::excerpt
 */
    public static function excerpt($text, $phrase, $radius = 100, $ellipsis = '...') {
        if (empty($text) || empty($phrase)) {
            return static::truncate($text, $radius * 2, array('ellipsis' => $ellipsis));
        }

        $append = $prepend = $ellipsis;

        $phraseLen = mb_strlen($phrase);
        $textLen = mb_strlen($text);

        $pos = mb_strpos(mb_strtolower($text), mb_strtolower($phrase));
        if ($pos === false) {
            return mb_substr($text, 0, $radius) . $ellipsis;
        }

        $startPos = $pos - $radius;
        if ($startPos <= 0) {
            $startPos = 0;
            $prepend = '';
        }

        $endPos = $pos + $phraseLen + $radius;
        if ($endPos >= $textLen) {
            $endPos = $textLen;
            $append = '';
        }

        $excerpt = mb_substr($text, $startPos, $endPos - $startPos);
        $excerpt = $prepend . $excerpt . $append;

        return $excerpt;
    }

/**
 * Creates a comma separated list where the last two items are joined with 'and', forming natural text.
 *
 * @param array $list The list to be joined
 * @param string $and The word used to join the last and second last items together with. Defaults to 'and'
 * @param string $separator The separator used to join all the other items together. Defaults to ', '
 * @return string The glued together string.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/text.html#TextHelper::toList
 */
    public static function toList(array $list, $and = 'and', $separator = ', ') {
        if (count($list) > 1) {
            return implode($separator, array_slice($list, 0, -1)) . ' ' . $and . ' ' . array_pop($list);
        }
        return array_pop($list);
    }

/**
 * Converts a hexadecimal color value to an RGB array.
 *
 * ### Usage
 * ```
 * Text::hexToRgb('#ffcc00'); // returns [255, 204, 0]
 * ```
 *
 * @param string $hexcolor Hexadecimal color value
 * @return array RGB values
 */
    public static function hexToRgb(string $hexcolor) {
        $hexcolor = str_replace('#', '', $hexcolor);
        if (strlen($hexcolor) !== 6) {
            return [0, 0, 0];
        }

        $split = str_split($hexcolor, 2);
        $red = hexdec($split[0]);
        $green = hexdec($split[1]);
        $blue = hexdec($split[2]);
        return [$red, $green, $blue];
    }

/**
 * Darken a hexadecimal color value.
 *
 * @param string $hexcolor Hexadecimal color value
 * @param int $percent Percentage to darken the color
 * @return string Darkened hexadecimal color
 */
    public static function darkenColor(string $hexcolor, int $percent): string {
        [$r, $g, $b] = static::hexToRgb($hexcolor);

        $r = round($r * (100 - $percent) / 100);
        $g = round($g * (100 - $percent) / 100);
        $b = round($b * (100 - $percent) / 100);

        $r = ($r < 0) ? 0 : (($r > 255) ? 255 : $r);
        $g = ($g < 0) ? 0 : (($g > 255) ? 255 : $g);
        $b = ($b < 0) ? 0 : (($b > 255) ? 255 : $b);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

/**
 * Lighten a hexadecimal color value.
 *
 * @param string $hexcolor Hexadecimal color value
 * @param int $percent Percentage to darken the color
 * @return string Lighten hexadecimal color
 */
    public static function lightenColor(string $hex, $percent) {
        [$r, $g, $b] = static::hexToRgb($hex);

        $r = round($r + (255 - $r) * $percent / 100);
        $g = round($g + (255 - $g) * $percent / 100);
        $b = round($b + (255 - $b) * $percent / 100);

        $r = ($r < 0) ? 0 : (($r > 255) ? 255 : $r);
        $g = ($g < 0) ? 0 : (($g > 255) ? 255 : $g);
        $b = ($b < 0) ? 0 : (($b > 255) ? 255 : $b);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

/**
 * Check if the string contain multibyte characters
 *
 * @param string $string value to test
 * @return boolean
 */
    public static function isMultibyte($string) {
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++ ) {
            $value = ord(($string[$i]));
            if ($value > 128) {
                return true;
            }
        }
        return false;
    }

/**
 * Converts a multibyte character string
 * to the decimal value of the character
 *
 * @param string $string
 * @return array
 */
    public static function utf8($string) {
        $map = [];

        $values = [];
        $find = 1;
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $value = ord($string[$i]);

            if ($value < 128) {
                $map[] = $value;
            } else {
                if (empty($values)) {
                    $find = ($value < 224) ? 2 : 3;
                }
                $values[] = $value;

                if (count($values) === $find) {
                    if ($find == 3) {
                        $map[] = (($values[0] % 16) * 4096) + (($values[1] % 64) * 64) + ($values[2] % 64);
                    } else {
                        $map[] = (($values[0] % 32) * 64) + ($values[1] % 64);
                    }
                    $values = [];
                    $find = 1;
                }
            }
        }

        return $map;
    }

/**
 * Converts the decimal value of a multibyte character string
 * to a string
 *
 * @param array $array
 * @return string
 */
    public static function ascii($array): string {
        $ascii = '';

        foreach ($array as $utf8) {
            if ($utf8 < 128) {
                $ascii .= chr($utf8);
            } elseif ($utf8 < 2048) {
                $ascii .= chr(192 + (($utf8 - ($utf8 % 64)) / 64));
                $ascii .= chr(128 + ($utf8 % 64));
            } else {
                $ascii .= chr(224 + (($utf8 - ($utf8 % 4096)) / 4096));
                $ascii .= chr(128 + ((($utf8 % 4096) - ($utf8 % 64)) / 64));
                $ascii .= chr(128 + ($utf8 % 64));
            }
        }

        return $ascii;
    }

/**
 * Replaces accented characters to ASCII ones.
 *
 * @param string $string String to normalize
 * @return string Normalized string
 */
    public static function normalize($string) {
        return transliterator_transliterate('NFKC; [:Nonspacing Mark:] Remove; NFKC; Any-Latin; Latin-ASCII', $string);
    }

/**
 * Linkify URL's in string.
 *
 * @param string $string String to linkify
 * @return string String with linkified url's
 */
    public static function linkify($string) {
        return Html::linkify($string);
    }

/**
 * Emojify ASCII emoticon in given string.
 *
 * @param string $string String to emojify
 * @return string String with emojies
 */
    public static function emojify($string) {
        return strtr($string, static::$_asciiToEmoji);
    }

/**
 * Strip emojis from given string.
 *
 * @param string $string String to strip emojis from
 * @return string String without emojis
 */
    public static function stripEmojis(string $string): string {
        // Match Emoticons
        $string = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $string);
        // Match Miscellaneous Symbols and Pictographs
        $string = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $string);
        // Match Transport And Map Symbols
        $string = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $string);
        // Match Miscellaneous Symbols
        $string = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $string);
        // Match Dingbats
        $string = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $string);
        return $string;
    }

/**
 * Compress string by given level.
 * Result will be Base64 encoded (URL-safe).
 *
 * @param string $string String to compress
 * @param int $level Level of compression
 * @return string Compressed string base64 encoded
 */
    public static function compress($string, $level = 9) {
        return base64url_encode(gzencode($string, $level));
    }

/**
 * Decompress compressed string.
 *
 * @param string $string Base64 encoded string to decompress
 * @return string Decompressed string
 */
    public static function decompress($string) {
        return gzdecode(base64url_decode($string));
    }

/**
 * Splits a string with delimiter syntax.
 *
 * The difference from explode() is that ensures that n indexes are always returned
 * placeholding the requested limit, preventing unset index.
 * If $string does not have a delimiter, then index 0 will be $string.
 *
 * Commonly used like:
 * ```
 * [$controller, $action] = Text::explode('::', 'Controller::index');
 * ```
 *
 * @param string $delimiter Set to true if you want the delimiter appended to it.
 * @param string|null $string The name you want to split.
 * @param boolean $delimiterAppended Set to true if you want the first index with the delimiter appended to it.
 * @param int $limit Limit of times it will explode the delimiter.
 * @param mixed $default Default value used if none found (defaults to null).
 * @return array Array with number of $limit indexes.
 */
    public static function explode(string $delimiter, string|null $string, bool|int $delimiterAppended = false, int $limit = 2, $default = null): array {
        if (is_int($delimiterAppended)) {
            $limit = $delimiterAppended;
            $delimiterAppended = false;
        }

        $splitted = explode($delimiter, $string, $limit);
        $array = [];

        for ($index = 0; $index < $limit; $index++) {
            $array[] = $default;
        }

        $splitted += $array;

        if ($delimiterAppended) {
            $splitted[0] .= $delimiter;
        }

        return $splitted;
    }

/**
 * Encode given string to given encoding.
 *
 * @param string $text String to encode.
 * @param string $encodingLabel Encoding label
 * @return string The same string, encoded into encoding label
 */
    public static function encode($text, $encodingLabel = 'UTF-8') {
        $encodingLabel = self::normalizeEncoding($encodingLabel);
        if ($encodingLabel === 'ISO-8859-1') {
            return self::toLatin1($text);
        }
        return static::toUtf8($text);
    }

/**
 * This function leaves UTF8 characters alone, while converting almost all non-UTF8 to UTF8.
 *
 * It assumes that the encoding of the original string is either Windows-1252 or ISO 8859-1.
 *
 * It may fail to convert characters to UTF-8 if they fall into one of these scenarios:
 *
 * 1) when any of these characters:   ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
 *    are followed by any of these:  ("group B")
 *                                    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
 * For example:   %ABREPRESENT%C9%BB. «REPRESENTÉ»
 * The "«" (%AB) character will be converted, but the "É" followed by "»" (%C9%BB)
 * is also a valid unicode character, and will be left unchanged.
 *
 * 2) when any of these: àáâãäåæçèéêëìíîï  are followed by TWO chars from group B,
 * 3) when any of these: ðñòó  are followed by THREE chars from group B.
 *
 * @param string $text Any string.
 * @return string The same string, UTF8 encoded
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function toUtf8($text) {
        if (is_array($text)) {
            foreach($text as $k => $v) {
                $text[$k] = self::toUtf8($v);
            }
            return $text;
        }

        if (!is_string($text)) {
            return $text;
        }

        $max = self::_strlen($text);
        $buf = "";
        for ($i = 0; $i < $max; $i++) {
            $c1 = $text[$i];

            // Should be converted to UTF8, if it's not UTF8 already
            if ($c1 >= "\xc0") {
                $c2 = $i+1 >= $max? "\x00" : $text[$i+1];
                $c3 = $i+2 >= $max? "\x00" : $text[$i+2];
                $c4 = $i+3 >= $max? "\x00" : $text[$i+3];

                if ($c1 >= "\xc0" & $c1 <= "\xdf") { // looks like 2 bytes UTF8
                    if ($c2 >= "\x80" && $c2 <= "\xbf") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2;
                        $i++;
                    } else { // not valid UTF8.  Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } elseif ($c1 >= "\xe0" & $c1 <= "\xef") { // looks like 3 bytes UTF8
                    // Check for illegal surrogate characters
                    if ($c1 === "\xed" && $c2 >= "\xa0" && $c2 <= "\xbf") {
                        $i += 2;
                        continue;
                    }
                    if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2 . $c3;
                        $i = $i + 2;
                    } else { // not valid UTF8. Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } elseif ($c1 >= "\xf0" & $c1 <= "\xf7") { // looks like 4 bytes UTF8
                    if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2 . $c3 . $c4;
                        $i = $i + 3;
                    } else { // not valid UTF8. Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } else { // doesn't look like UTF8, but should be converted
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = (($c1 & "\x3f") | "\x80");
                        $buf .= $cc1 . $cc2;
                }
            } elseif (($c1 & "\xc0") === "\x80") { // needs conversion
                if (isset(self::$_win1252ToUtf8[ord($c1)])) { //found in Windows-1252 special cases
                    $buf .= self::$_win1252ToUtf8[ord($c1)];
                } else {
                    $cc1 = (chr(ord($c1) / 64) | "\xc0");
                    $cc2 = (($c1 & "\x3f") | "\x80");
                    $buf .= $cc1 . $cc2;
                }
            } else { // it doesn't need conversion
                $buf .= $c1;
            }
        }
        return $buf;
    }

/**
 * Convert given string to Windows-1252 encoding.
 *
 * @param string $text Any string.
 * @param const $option With or without ICONV
 * @return string The same string, Windows-1252 encoded
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function toWin1252($text, $option = self::WITHOUT_ICONV) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = self::toWin1252($v, $option);
            }
            return $text;
        }
        return is_string($text) ? static::_utf8Decode($text, $option) : $text;
    }

/**
 * Convert given string to ISO-8859 encoding.
 *
 * @param string $text Any string.
 * @param const $option With or without ICONV
 * @return string The same string, ISO-8859 encoded
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    static function toIso8859($text, $option = self::WITHOUT_ICONV) {
        return self::toWin1252($text, $option);
    }

/**
 * Convert given string to Latin-1 encoding.
 *
 * @param string $text Any string.
 * @param const $option With or without ICONV
 * @return string The same string, Latin-1 encoded
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    static function toLatin1($text, $option = self::WITHOUT_ICONV) {
        return self::toWin1252($text, $option);
    }

/**
 * Fix bad UTF8 encoding in given string.
 *
 * @param string $text Any string.
 * @param const $option With or without ICONV
 * @return string The same string, Latin-1 encoded
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function fixUtf8($text, $option = self::WITHOUT_ICONV) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = self::fixUtf8($v, $option);
            }
            return $text;
        }

        if (!is_string($text)) {
            return $text;
        }

        $last = "";
        while ($last <> $text) {
            $last = $text;
            $text = self::toUtf8(static::_utf8Decode($text, $option));
        }
        return self::toUtf8(static::_utf8Decode($text, $option));
    }

/**
 * Fix an UTF-8 string that was converted from Windows-1252 as it was ISO8859-1
 * (ignoring Windows-1252 chars from 80 to 9F) use this function to fix it.
 *
 * @param string $text Any string.
 * @return string The same string, with fixed UTF8
 * @see http://en.wikipedia.org/wiki/Windows-1252
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function UTF8FixWin1252Chars($text) {
        return str_replace(array_keys(self::$_brokenUtf8ToUtf8), array_values(self::$_brokenUtf8ToUtf8), $text);
    }

/**
 * Remove BOM from given string.
 *
 * @param string $text Any string.
 * @return string The same string, without BOM
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function removeBom($str) {
        if (substr($str, 0, 3) === pack("CCC", 0xef, 0xbb, 0xbf)) {
            $str = substr($str, 3);
        }
        return $str;
    }

/**
 * Obtain the length of the string.
 *
 * @param string $text Any string.
 * @return int String length
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    protected static function _strlen($text) {
        return (function_exists('mb_strlen') && ((int) ini_get('mbstring.func_overload')) & 2) ?
                mb_strlen($text, '8bit') : strlen($text);
    }

/**
 * Normalize the encoding label.
 *
 * @param string $encodingLabel Encoding label.
 * @return string Normalized encoding label
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    public static function normalizeEncoding($encodingLabel) {
        $encoding = strtoupper($encodingLabel);
        $encoding = preg_replace('/[^a-zA-Z0-9\s]/', '', $encoding);
        $equivalences = [
            'ISO88591' => 'ISO-8859-1',
            'ISO8859' => 'ISO-8859-1',
            'ISO' => 'ISO-8859-1',
            'LATIN1' => 'ISO-8859-1',
            'LATIN' => 'ISO-8859-1',
            'UTF8' => 'UTF-8',
            'UTF' => 'UTF-8',
            'WIN1252' => 'ISO-8859-1',
            'WINDOWS1252' => 'ISO-8859-1'
        ];

        if (empty($equivalences[$encoding])) {
            return 'UTF-8';
        }
        return $equivalences[$encoding];
    }

/**
 * Decode UTF-8 string.
 *
 * @param string $text Any string.
 * @param const $option ICONV option
 * @return string UTF-8 decoded text
 * @see https://github.com/neitanod/forceutf8/blob/master/src/ForceUTF8/Encoding.php
 */
    protected static function _utf8Decode($text, $option = self::WITHOUT_ICONV) {
        if ($option !== self::WITHOUT_ICONV && function_exists('iconv')) {
            return iconv("UTF-8", "Windows-1252" . ($option === self::ICONV_TRANSLIT ? '//TRANSLIT' : ($option === self::ICONV_IGNORE ? '//IGNORE' : '')), $text);
        }
        return utf8_decode(
            str_replace(array_keys(self::$_utf8ToWin1252), array_values(self::$_utf8ToWin1252), self::toUtf8($text))
        );
    }

/**
 * Check the $string for multibyte characters.
 *
 * @param string $string value to test
 * @return boolean True if multibyte, false otherwise
 */
    public static function checkMultibyte($string): bool {
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++ ) {
            $value = ord(($string[$i]));
            if ($value > 128) {
                return true;
            }
        }
        return false;
    }

/**
 * Prepare a string for mail transport, using the provided encoding.
 *
 * @param string $string value to encode
 * @param string $charset charset to use for encoding. defaults to UTF-8
 * @param string $newline
 * @return string
 */
    public static function mimeEncode($string, $charset = null, $newline = "\r\n") {
        if (!static::checkMultibyte($string) && strlen($string) < 75) {
            return $string;
        }

        if (empty($charset)) {
            $charset = Configure::read('App.encoding');
        }
        $charset = strtoupper($charset);

        $start = '=?' . $charset . '?B?';
        $end = '?=';
        $spacer = $end . $newline . ' ' . $start;

        $length = 75 - strlen($start) - strlen($end);
        $length = $length - ($length % 4);
        if ($charset == 'UTF-8') {
            $parts = array();
            $maxchars = floor(($length * 3) / 4);
            $stringLength = strlen($string);
            while ($stringLength > $maxchars) {
                $i = (int)$maxchars;
                $test = ord($string[$i]);
                while ($test >= 128 && $test <= 191) {
                    $i--;
                    $test = ord($string[$i]);
                }
                $parts[] = base64_encode(substr($string, 0, $i));
                $string = substr($string, $i);
                $stringLength = strlen($string);
            }
            $parts[] = base64_encode($string);
            $string = implode($spacer, $parts);
        } else {
            $string = chunk_split(base64_encode($string), $length, $spacer);
            $string = preg_replace('/' . preg_quote($spacer) . '$/', '', $string);
        }
        return $start . $string . $end;
    }

}
