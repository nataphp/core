<?php
/**
 * NataPHP Framework — Rewrites legacy Cake/Nata-style i18n tags for Smarty 5.
 *
 * Smarty is not PHP: {__('msg')} is parsed incorrectly (modifier "__"). This prefilter
 * converts common patterns to {"msg"|__}, {__n ...}, and {__x ...} function tags.
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View;

use Smarty\Template;

class I18nSmartyPrefilter {

/**
 * @param string $source Template source
 */
    public static function filter(string $source, Template $template): string {
        $source = self::_replaceUnderscoreFn($source);
        $source = self::_replaceUnderscoreN($source);
        $source = self::_replaceUnderscoreX($source);
        $source = self::_replaceDefaultUnderscoreFn($source);

        return $source;
    }

/**
 * Decode a PHP single- or double-quoted string literal (including quotes).
 */
    protected static function _decodePhpStringLiteral(string $literal): string {
        $q = $literal[0];
        $inner = substr($literal, 1, -1);
        if ($q === "'") {
            return str_replace(["\\\\", "\\'"], ['\\', "'"], $inner);
        }

        return stripcslashes($inner);
    }

/**
 * Escape for use inside Smarty double-quoted strings: {"..."|__}
 */
    protected static function _smartyDoubleQuoted(string $decoded): string {
        return str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $decoded);
    }

/**
 * {__('...')|modifiers} → {"..."|__|modifiers}
 */
    protected static function _replaceUnderscoreFn(string $source): string {
        return preg_replace_callback(
            '/\{\s*__\s*\(\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*\)(\s*\|[^}]*)?\s*\}/',
            static function (array $m): string {
                $decoded = self::_decodePhpStringLiteral($m[1]);
                $body = self::_smartyDoubleQuoted($decoded);
                $mods = $m[2] ?? '';

                return '{"' . $body . '"|__' . $mods . '}';
            },
            $source
        ) ?? $source;
    }

/**
 * |default:__('...')
 */
    protected static function _replaceDefaultUnderscoreFn(string $source): string {
        return preg_replace_callback(
            '/\|default:\s*__\s*\(\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*\)/',
            static function (array $m): string {
                $decoded = self::_decodePhpStringLiteral($m[1]);
                $body = self::_smartyDoubleQuoted($decoded);

                return '|default:{"' . $body . '"|__}';
            },
            $source
        ) ?? $source;
    }

/**
 * {__n('sg','pl', $count, $args)|mods}
 */
    protected static function _replaceUnderscoreN(string $source): string {
        return preg_replace_callback(
            '/\{\s*__n\s*\(\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*,\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*,\s*([^,)]+)\s*,\s*([^)]+)\)(\s*\|[^}]*)?\s*\}/',
            static function (array $m): string {
                $sg = self::_smartyDoubleQuoted(self::_decodePhpStringLiteral($m[1]));
                $pl = self::_smartyDoubleQuoted(self::_decodePhpStringLiteral($m[2]));
                $count = trim($m[3]);
                $args = trim($m[4]);
                $mods = $m[5] ?? '';

                return '{__n singular="' . $sg . '" plural="' . $pl . '" count=' . $count . ' args=' . $args . ($mods ?? '') . '}';
            },
            $source
        ) ?? $source;
    }

/**
 * {__x('context', 'msg')|mods}
 */
    protected static function _replaceUnderscoreX(string $source): string {
        return preg_replace_callback(
            '/\{\s*__x\s*\(\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*,\s*((?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"))\s*\)(\s*\|[^}]*)?\s*\}/',
            static function (array $m): string {
                $ctx = self::_smartyDoubleQuoted(self::_decodePhpStringLiteral($m[1]));
                $msg = self::_smartyDoubleQuoted(self::_decodePhpStringLiteral($m[2]));
                $mods = $m[3] ?? '';

                return '{__x context="' . $ctx . '" msg="' . $msg . '"' . $mods . '}';
            },
            $source
        ) ?? $source;
    }
}
