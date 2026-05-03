<?php
/**
 * NataPHP Framework
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View\Smarty;

use Nata\View\CellBuilder;
use Nata\I18n\I18n;
use Smarty\Smarty;
use Smarty\Template;

final class Plugins {

    public static function register(Smarty $smarty): void {
        // -- simple: {'msg'|__}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__', function (string $string, ...$args): string {
            if (!$string) return '';
            $result = I18n::translate($string);
            return (string) ($args ? I18n::format($result, $args) : $result);
        });

        // -- verbose/add-missing: {'msg'|__v}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__v', function (string $msg, ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::translate($msg, null, null, null, I18n::LC_MESSAGES, null, null, true);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });


        // -- plural: {__n singular='' plural='' count=$n} or {'singular'|__n:'plural':$count}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__n', function (array $params): string {
            $singular = $params['singular'] ?? '';
            if (!$singular) return '';
            $result = I18n::translate($singular, $params['plural'] ?? null, null, null, I18n::LC_MESSAGES, $params['count'] ?? 0);
            $args = $params['args'] ?? null;
            return (string) ($args !== null ? I18n::format($result, (array) $args) : $result);
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__n', function (string $singular, string $plural, int $count, ...$formatArgs): string {
            if (!$singular) return '';
            $result = I18n::translate($singular, $plural, null, null, I18n::LC_MESSAGES, $count);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- context: {__x context='' msg=''} or {'msg'|__x:'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__x', function (array $params): string {
            $msg = $params['msg'] ?? '';
            if (!$msg) return '';
            $result = I18n::translate($msg, null, $params['context'] ?? null);
            $args = $params['args'] ?? null;
            return (string) ($args !== null ? I18n::format($result, (array) $args) : $result);
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__x', function (string $msg, string $context = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::translate($msg, null, $context ?: null);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- context + reverse: {'msg'|__rx:'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__rx', function (string $msg, string $context = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::reverse($msg, $context ?: null);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- context plural: {'context'|__xn:'singular':'plural':$count}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__xn', function (string $context, string $singular, string $plural, int $count, ...$formatArgs): string {
            if (!$singular) return '';
            $result = I18n::translate($singular, $plural, $context ?: null, null, I18n::LC_MESSAGES, $count);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain: {__d domain='' msg=''} or {'msg'|__d:'domain'}
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, '__d', function (array $params): string {
            $msg = $params['msg'] ?? '';
            if (!$msg) return '';
            $result = I18n::translate($msg, null, null, $params['domain'] ?? null);
            $args = $params['args'] ?? null;
            return (string) ($args !== null ? I18n::format($result, (array) $args) : $result);
        });
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__d', function (string $msg, string $domain = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::translate($msg, null, null, $domain ?: null);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain + verbose: {'msg'|__vd:'domain'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__vd', function (string $msg, string $domain = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::translate($msg, null, null, $domain ?: null, I18n::LC_MESSAGES, null, null, true);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain + reverse: {'msg'|__rd:'domain'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__rd', function (string $msg, string $domain = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::reverse($msg, null, $domain ?: null);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain plural: {'domain'|__dn:'singular':'plural':$count}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dn', function (string $domain, string $singular, string $plural, int $count, ...$formatArgs): string {
            if (!$singular) return '';
            $result = I18n::translate($singular, $plural, null, $domain ?: null, I18n::LC_MESSAGES, $count);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain + context: {'msg'|__dx:'domain':'context'}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dx', function (string $msg, string $domain = '', string $context = '', ...$formatArgs): string {
            if (!$msg) return '';
            $result = I18n::translate($msg, null, $context ?: null, $domain ?: null);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        // -- domain + context plural: {'domain'|__dxn:'context':'singular':'plural':$count}
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, '__dxn', function (string $domain, string $context, string $singular, string $plural, int $count, ...$formatArgs): string {
            if (!$singular) return '';
            $result = I18n::translate($singular, $plural, $context ?: null, $domain ?: null, I18n::LC_MESSAGES, $count);
            return (string) ($formatArgs ? I18n::format($result, $formatArgs) : $result);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 't', function (array $params): string {
            $singular = $params['msg'] ?? $params['singular'] ?? '';
            $plural = $params['plural'] ?? null;
            $context = $params['context'] ?? null;
            $domain = $params['domain'] ?? null;
            $count = isset($params['count']) ? (int) $params['count'] : null;
            $language = $params['language'] ?? null;
            $vars = $params['vars'] ?? null;
            $appendMissing = (bool) ($params['appendMissing'] ?? false);

            if (!$singular) {
                return '';
            }

            $result = I18n::translate($singular, $plural, $context, $domain, I18n::LC_MESSAGES, $count, $language, $appendMissing);

            if ($vars !== null) {
                $result = I18n::format($result, is_array($vars) ? $vars : [$vars]);
            }

            return $result;
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
