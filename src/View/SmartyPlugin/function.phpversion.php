<?php
/**
 * NataPHP Framework
 *
 * Smarty function plugin: output PHP version (e.g. debug footer).
 * Use in templates: {phpversion}
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * @param array<string, mixed> $params
 * @param \Smarty\Template $template
 */
function smarty_function_phpversion(array $params, $template): string {
    return PHP_VERSION;
}
