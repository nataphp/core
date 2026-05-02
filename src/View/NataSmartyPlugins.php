<?php
/**
 * NataPHP Framework
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View;

use Nata\View\CellBuilder;
use Smarty\Smarty;
use Smarty\Template;

final class NataSmartyPlugins {

    public static function register(Smarty $smarty): void {
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__', function (string $string, ...$args): string {
            return (string) __($string, $args ?: null);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__n', function (array $params): string {
            return (string) __n(
                $params['singular'] ?? '',
                $params['plural'] ?? '',
                $params['count'] ?? 0,
                $params['args'] ?? null
            );
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__x', function (array $params): string {
            return (string) __x(
                $params['context'] ?? '',
                $params['msg'] ?? '',
                $params['args'] ?? null
            );
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'phpversion', function (): string {
            return PHP_VERSION;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'Cell', function (array $params, Template $_template): string {
            $cell = $params['cell'] ?? null;
            $data = $params['data'] ?? [];
            $options = $params['options'] ?? [];
            unset($params['cell'], $params['data'], $params['options']);

            if (!empty($params)) {
                $data['namedArgs'] = $params + ($data['namedArgs'] ?? []);
            }

            return CellBuilder::build($cell, $data, $options)->render();
        });
    }
}
