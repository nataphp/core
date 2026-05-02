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

use DOMDocument;
use DOMXPath;
use Nata\Core\Configure;
use Nata\Core\Registry;
use Nata\Utility\Inflector;

/**
 * HTML markup generator.
 */
class Html {

/**
 * Create HTML element markup by specifiing the name
 * and attributes for given element.
 *
 * Examples:
 *
 *    // <a> element that content is empty and tag get's closed
 *    echo HTML::elem('<a>', array('href' => '/some/page')); <a href="/some/page"></a>
 *
 *    // <a> element where content is false, opening tag only
 *    echo HTML::elem('<a>', false, array('href' => '/some/page')); <a href="/some/page">
 *
 *    // <a> element that content is true, meaning content is already in the attributes
 *    echo HTML::elem('<img/>', array('src' => '/some/page')); <img src="/some/page" />
 *
 * @param string $name Element name
 * @param string $contents Contents of the element.
 * If bool false, will return an unclosed tag
 * @param array $attrs Element attributes
 * @return string
 * @uses \Nata\Utility\Html::attrs()
 */
    public static function elem($name, $contents = '', $attrs = array()) {
        $xmlTag = strpos($name, '/>') !== false;

        if (is_array($contents)) {
            $attrs = $contents;
            $contents = false;
        }

        $elem = '<' . $close = trim(strtolower(str_replace(array('<', '>','/'), '', $name)));
        $elem .= ' ' . static::attrs($attrs);

        if ($xmlTag === true) {
            $elem .= ' />';
        } elseif ($contents === false) {
            $elem .= '>';
        } else {
            $elem .= '>' . $contents . '</' . $close . '>';
        }

        return $elem;
    }

/**
 * Create unordered list.
 *
 * @param array $items List of items
 * @param array $attrs Element attributes
 * @return string <ul> HTML string
 */
    public static function ul($items, $attrs = array()) {
        return static::_list('<ul>', $items, $attrs);
    }

/**
 * Create ordered list.
 *
 * @param array $items List of items
 * @param array $attrs Element attributes
 * @return string <ul> HTML string
 */
    public static function ol($items, $attrs = array()) {
        return static::_list('<ol>', $items, $attrs);
    }

/**
 * Generate HTML ul/ol list.
 *
 * @param string $name Element name
 * @param array|string $items Contents of the element.
 * If bool false, will return an unclosed tag
 * @param array $attrs Element attributes
 * @return string
 */
    protected static function _list($elem, $items, $attrs) {
        $list = '';

        $default = [
            'text' => '',
            'attrs' => []
        ];

        foreach ((array)$items as $item) {
            if (is_string($item)) {
                $item = ['text' => $item];
            }

            $item += $default;

            $list .= static::elem('<li>', $item['text'], $item['attrs']);
        }

        return static::elem($elem, $list, $attrs);
    }

/**
 * Check if given string has HTML markup in it.
 *
 * Examples:
 *    // HTML in string
 *    echo HTML::in('Just a simple <strong>string</strong>'); // true
 *
 *    // No HTML in string
 *    echo HTML::in('Just a simple string'); // false
 *
 * @param string $string String to check
 * @return bool Returns true if string contains HTML
 */
    public static function in($string) {
        return preg_match("/\/[a-z]*>/i", $string, $m) !== 0;
    }

/**
 * Create element's attributes from an array.
 *
 * @param array|string $attr Attributes array or Attr
 * @param mixed $value Attribute value
 * @param array $options Attribute options
 * @return string HTML's element attributes
 */
    public static function attrs($attr, $value = null, array $options = []) {
        if (is_object($attr)) {
            return $attr;
        }

        $html = '';
        if (empty($attr)) {
            return $html;
        }

        if (is_array($attr) && is_array($value)) {
            $options = $value;
            $value = null;
        }

        $options += [
            'dasherize' => true,
            'delimiter' => '"'
        ];

        if (!is_array($attr)) {
            $attr = [$attr => $value];
        }

        foreach ($attr as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (is_numeric($name)) {
                $name = $value;
            // If attribute is the same as value (e.g. multiple="multiple")
            } elseif ($value === true && strpos($name, 'data') === false) {
                $value = $name;
            }

            if ($name === 'data' || is_array($value)) {
                if ($name === 'class') {
                    $value = implode(' ', $value);
                } elseif ($name === 'id') {
                    $value = implode('-', $value);
                } elseif ($name === 'data') {
                    foreach ($value as $_attr => $_value) {
                        if (!empty($html)) {
                            $html .= ' ';
                        }
                        $html .= static::attrs('data-' . $_attr, $_value);
                    }
                    continue;
                }
            }

            if (!empty($html)) {
                $html .= ' ';
            }

            if (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if ($options['dasherize']) {
                $name = Inflector::dasherize($name);
            }

            $delimiter = $options['delimiter'];
            if (str_contains($value, $options['delimiter']) && $options['delimiter'] === '"') {
                $delimiter = "'";
            }

            $html .= $name . "=" . $delimiter . $value . $delimiter;
        }

        return $html;
    }

/**
 * Create array of attributes from string.
 *
 * @param string $attr Attributes string
 * @return array HTML's attributes
 */
    public static function attrsToArray($attr) {
        $attrs = [];

        if (preg_match_all("/\s?([a-z-]+)=\"(.*?)\"\s?/i", trim($attr), $matches) > 0) {
            foreach ($matches[1] as $index => $match) {
                $attrs[$match] = $matches[2][$index];
            }
        }

        return $attrs;
    }

/**
 * Create meta (<meta>) HTML element.
 *
 * @param string $name Value for name attribute
 * @param string $content Value for content attribute
 * @return string
 */
    public static function meta($name, $content) {
        return static::elem('<meta>', array(
            'name' => $name,
            'content' => $content
        ));
    }

/**
 * Convert special characters to HTML entities. All untrusted content
 * should be passed through this method to prevent XSS injections.
 *
 * @param string $value String to encode
 * @param int $flag Quotes handling
 * @param bool $doubleEncode If false will not encode existing html entities
 * @return string HTML encoded string
 * @see http://www.php.net/htmlspecialchars
 */
    public static function chars($value, $flag = ENT_QUOTES, $doubleEncode = true) {
        return htmlspecialchars(
            (string) $value,
            $flag,
            Configure::read('App.encoding'),
            $doubleEncode
        );
    }

/**
 * Convert special characters to HTML entities. All untrusted content
 * should be passed through this method to prevent XSS injections.
 *
 * @param string $value String to encode
 * @param int $flag Quotes handling
 * @param bool $doubleEncode If false will not encode existing html entities
 * @return string HTML encoded string
 * @see http://www.php.net/htmlspecialchars
 */
    public static function entities($value, $flag = ENT_QUOTES, $doubleEncode = true) {
        return htmlentities(
            (string) $value,
            $flag,
            Configure::read('App.encoding'),
            $doubleEncode
        );
    }

/**
 * Linkify URL's in string.
 *
 * @param string $string String to linkify
 * @param array $options <a> Options
 * @return string String with linkified url's
 */
    public static function linkify($string, array $options = []) {
        $options += [
            'target' => '_blank',
            'attrs' => []
        ];

        $pattern = '~(?xi)
            (\s|^)                                   # prevent matching already linkified URLs
            (?:
                ((ht|f)tps?:\/\/)                    # scheme://
                |                                    #   or
                www\d{0,3}\.                         # "www.", "www1.", "www2." ... "www999."
                |                                    #   or
                www\-                                # "www-"
                |                                    #   or
                [a-z0-9.\-]+\.[a-z]{2,4}(?=\/)       # looks like domain name followed by a slash
            )
            (?:                                      # Zero or more:
                [^\s()<>]+                           #  Run of non-space, non-()<>
                |                                    #  or
                \((?>[^\s()<>]+|(\([^\s()<>]+\)))*\) #  balanced parens, up to 2 levels
            )*
            (?:                                      # End with:
                \((?>[^\s()<>]+|(\([^\s()<>]+\)))*\) #  balanced parens, up to 2 levels
                |                                    #  or
                [^\s`!\-()\[\]{};:\'".,<>?«»“”‘’]    #  not a space or one of these punct chars
            )
        ~u';

        if (!isset($options['attrs']['target'])) {
            $options['attrs']['target'] = $options['target'];
        }

        $string = str_replace('>http', '> http', $string);

        return preg_replace_callback($pattern, function ($match) use ($options) {
            $caption = $match[0];
            $match[0] = trim($match[0]);

            if (preg_match("/^(ht|f)tps?:\/\//", $match[0]) === 0) {
                $match[0] = 'http://' . $match[0];
            }

            return static::elem('a', $caption, $options['attrs'] + ['href' => $match[0]]);
        }, $string);
    }

}
