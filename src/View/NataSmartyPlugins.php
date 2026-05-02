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
        // -- simple: {__('msg')} or {'msg'|__}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__', function (string $string, ...$args): string {
            return (string) __($string, $args ?: null);
        });

        // -- verbose/add-missing: {__v('msg')} or {'msg'|__v}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__v', function (string $msg, ...$formatArgs): string {
            return (string) __v($msg, $formatArgs ?: null);
        });

        // -- reverse: {__r('msg')} or {'msg'|__r}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__r', function (string $msg, ...$formatArgs): string {
            return (string) __r($msg, $formatArgs ?: null);
        });

        // -- plural: {__n('singular', 'plural', $count)} or {'singular'|__n:'plural':$count}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__n', function (array $params): string {
            return (string) __n(
                $params['singular'] ?? '',
                $params['plural'] ?? '',
                $params['count'] ?? 0,
                $params['args'] ?? null
            );
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__n', function (string $singular, string $plural, int $count, ...$formatArgs): string {
            return (string) __n($singular, $plural, $count, $formatArgs ?: null);
        });

        // -- context: {__x('context', 'msg')} or {'msg'|__x:'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__x', function (array $params): string {
            return (string) __x(
                $params['context'] ?? '',
                $params['msg'] ?? '',
                $params['args'] ?? null
            );
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__x', function (string $msg, string $context = '', ...$formatArgs): string {
            return (string) __x($context, $msg, $formatArgs ?: null);
        });

        // -- context + reverse: {'msg'|__rx:'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__rx', function (string $msg, string $context = '', ...$formatArgs): string {
            return (string) __rx($context, $msg, $formatArgs ?: null);
        });

        // -- context plural: {__xn('context', 'singular', 'plural', $count)}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__xn', function (string $context, string $singular, string $plural, int $count, ...$formatArgs): string {
            return (string) __xn($context, $singular, $plural, $count, $formatArgs ?: null);
        });

        // -- domain: {__d('msg', 'domain')} or {'msg'|__d:'domain'} or {__d domain='...' msg='...'}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__d', function (array $params): string {
            return (string) __d(
                $params['domain'] ?? '',
                $params['msg'] ?? '',
                $params['args'] ?? null
            );
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__d', function (string $msg, string $domain = '', ...$formatArgs): string {
            return (string) __d($domain, $msg, $formatArgs ?: null);
        });

        // -- domain + verbose: {'msg'|__vd:'domain'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__vd', function (string $msg, string $domain = '', ...$formatArgs): string {
            return (string) __vd($domain, $msg, $formatArgs ?: null);
        });

        // -- domain + reverse: {'msg'|__rd:'domain'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__rd', function (string $msg, string $domain = '', ...$formatArgs): string {
            return (string) __rd($domain, $msg, $formatArgs ?: null);
        });

        // -- domain plural: {__dn('domain', 'singular', 'plural', $count)}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dn', function (string $domain, string $singular, string $plural, int $count, ...$formatArgs): string {
            return (string) __dn($domain, $singular, $plural, $count, $formatArgs ?: null);
        });

        // -- domain + context: {'msg'|__dx:'domain':'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dx', function (string $msg, string $domain = '', string $context = '', ...$formatArgs): string {
            return (string) __dx($domain, $context, $msg, $formatArgs ?: null);
        });

        // -- domain + context plural: {__dxn('domain', 'context', 'singular', 'plural', $count)}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dxn', function (string $domain, string $context, string $singular, string $plural, int $count, ...$formatArgs): string {
            return (string) __dxn($domain, $context, $singular, $plural, $count, $formatArgs ?: null);
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
