<?php
/**
 * NataPHP Framework — Smarty 5 plural translation ({__n ...} tag).
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * @param array<string, mixed> $params singular, plural, count, args (optional)
 * @param mixed                $template
 */
function smarty_function___n(array $params, $template): string {
    $singular = isset($params['singular']) ? (string) $params['singular'] : '';
    $plural = isset($params['plural']) ? (string) $params['plural'] : '';
    $count = $params['count'] ?? 0;
    $args = $params['args'] ?? null;

    return (string) __n($singular, $plural, $count, $args);
}
