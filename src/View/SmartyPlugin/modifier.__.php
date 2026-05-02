<?php
/**
 * NataPHP Framework — Smarty 5 translation modifier (calls PHP __()).
 *
 * Usage: {"Source string"|__} or {$var|__}
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * @param string|null $string Message to translate
 * @param mixed       ...$args Optional sprintf-style arguments for I18n::format()
 */
function smarty_modifier___($string, ...$args): string {
    $string = $string === null ? '' : (string) $string;
    if ($string === '') {
        return '';
    }

    return (string) __($string, $args === [] ? null : $args);
}
