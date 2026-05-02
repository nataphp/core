<?php
/**
 * NataPHP Framework — Smarty 5 contextual translation ({__x ...} tag).
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * @param array<string, mixed> $params context, msg, args (optional)
 * @param mixed                $template
 */
function smarty_function___x(array $params, $template): string {
    $context = isset($params['context']) ? (string) $params['context'] : '';
    $msg = isset($params['msg']) ? (string) $params['msg'] : '';
    $args = $params['args'] ?? null;

    return (string) __x($context, $msg, $args);
}
