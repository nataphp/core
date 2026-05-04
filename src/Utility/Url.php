<?php

declare(strict_types=1);

/**
 * URL utility class implementing RFC3986 algorithms.
 *
 * splitUrl/joinUrl/removeDotSegments based on work by David R. Nadeau (NadeauSoftware.com),
 * licensed under BSD (http://www.opensource.org/licenses/bsd-license.php).
 */

namespace Nata\Utility;

use Nata\Routing\Router;

class Url {

    /**
     * Combine a base URL and a relative URL into a new absolute URL (RFC3986 "absolutize").
     *
     * @return string|false  False if either URL is unparseable or base is not absolute.
     */
    public static function absolute(string $relativeUrl, ?string $baseUrl = null): string|false {
        $relativeParts = self::splitUrl($relativeUrl);
        if ($relativeParts === false) {
            return false;
        }

        if (!empty($relativeParts['scheme'])) {
            if (!empty($relativeParts['path']) && $relativeParts['path'][0] === '/') {
                $relativeParts['path'] = self::removeDotSegments($relativeParts['path']);
            }
            return self::joinUrl($relativeParts);
        }

        if (empty($baseUrl)) {
            $baseUrl = Router::url('/', true);
        }

        $baseParts = self::splitUrl($baseUrl);
        if ($baseParts === false || empty($baseParts['scheme']) || empty($baseParts['host'])) {
            return false;
        }

        $relativeParts['scheme'] = $baseParts['scheme'];

        if (isset($relativeParts['host'])) {
            if (!empty($relativeParts['path'])) {
                $relativeParts['path'] = self::removeDotSegments($relativeParts['path']);
            }
            return self::joinUrl($relativeParts);
        }

        unset($relativeParts['port'], $relativeParts['user'], $relativeParts['pass']);

        $relativeParts['host'] = $baseParts['host'];
        if (isset($baseParts['port'])) $relativeParts['port'] = $baseParts['port'];
        if (isset($baseParts['user'])) $relativeParts['user'] = $baseParts['user'];
        if (isset($baseParts['pass'])) $relativeParts['pass'] = $baseParts['pass'];

        if (empty($relativeParts['path'])) {
            if (!empty($baseParts['path'])) {
                $relativeParts['path'] = $baseParts['path'];
            }
            if (!isset($relativeParts['query']) && isset($baseParts['query'])) {
                $relativeParts['query'] = $baseParts['query'];
            }
            return self::joinUrl($relativeParts);
        }

        if ($relativeParts['path'][0] !== '/') {
            $basePath = mb_strrchr($baseParts['path'], '/', true, 'UTF-8');
            if ($basePath === false) $basePath = '';
            $relativeParts['path'] = $basePath . '/' . $relativeParts['path'];
        }

        $relativeParts['path'] = self::removeDotSegments($relativeParts['path']);
        return self::joinUrl($relativeParts);
    }

    /**
     * Remove "." and ".." segments from a URL path (RFC3986 "remove_dot_segments").
     */
    public static function removeDotSegments(string $path): string {
        $inputSegments  = preg_split('!/!u', $path);
        $outputSegments = [];

        foreach ($inputSegments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($outputSegments);
            } else {
                $outputSegments[] = $segment;
            }
        }

        $outputPath = implode('/', $outputSegments);
        if ($path[0] === '/') {
            $outputPath = '/' . $outputPath;
        }

        // Preserve trailing slash
        if ($outputPath !== '/' && (mb_strlen($path) - 1) === mb_strrpos($path, '/', 0, 'UTF-8')) {
            $outputPath .= '/';
        }

        return $outputPath;
    }

    /**
     * Parse an absolute or relative URL into its RFC3986 components.
     *
     * Returns an associative array with any present keys from:
     * scheme, host, port, user, pass, path, query, fragment.
     *
     * @return array<string, string>|false  False if the URL is too malformed to parse.
     */
    public static function splitUrl(string $url, bool $decode = false): array|false {
        $xunressub  = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
        $xpchar     = $xunressub . ':@% ';
        $xscheme    = '([a-zA-Z][a-zA-Z\d+-.]*)';
        $xuserinfo  = '(([' . $xunressub . '%]*)(:([' . $xunressub . ':%]*))?)';
        $xipv4      = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';
        $xipv6      = '(\[([a-fA-F\d.:]+)\])';
        $xhost_name = '([a-zA-Z\d-.%]+)';
        $xhost      = '(' . $xhost_name . '|' . $xipv4 . '|' . $xipv6 . ')';
        $xport      = '(\d*)';
        $xauthority = '((' . $xuserinfo . '@)?' . $xhost . '?(:' . $xport . ')?)';

        $xslash_seg    = '(/[' . $xpchar . ']*)';
        $xpath_authabs = '((//' . $xauthority . ')((/[' . $xpchar . ']*)*))';
        $xpath_rel     = '([' . $xpchar . ']+' . $xslash_seg . '*)';
        $xpath_abs     = '(/(' . $xpath_rel . ')?)';
        $xapath        = '(' . $xpath_authabs . '|' . $xpath_abs . '|' . $xpath_rel . ')';
        $xqueryfrag    = '([' . $xpchar . '/?]*)';
        $xurl          = '^(' . $xscheme . ':)?' . $xapath . '?(\?' . $xqueryfrag . ')?(#' . $xqueryfrag . ')?$';

        if (!preg_match('!' . $xurl . '!', $url, $matches)) {
            return false;
        }

        $parts = [];
        $hostName = null;

        if (!empty($matches[2]))       $parts['scheme']   = strtolower($matches[2]);
        if (!empty($matches[7]))       $parts['user']     = isset($matches[9]) ? $matches[9] : '';
        if (!empty($matches[10]))      $parts['pass']     = $matches[11];

        if (!empty($matches[13]))      { $hostName = $matches[13]; $parts['host'] = $matches[13]; }
        elseif (!empty($matches[14]))  $parts['host'] = $matches[14];
        elseif (!empty($matches[16]))  $parts['host'] = $matches[16];
        elseif (!empty($matches[5]))   $parts['host'] = '';

        if (!empty($matches[17]))      $parts['port']     = $matches[18];
        if (!empty($matches[19]))      $parts['path']     = $matches[19];
        elseif (!empty($matches[21]))  $parts['path']     = $matches[21];
        elseif (!empty($matches[25]))  $parts['path']     = $matches[25];

        if (!empty($matches[27]))      $parts['query']    = $matches[28];
        if (!empty($matches[29]))      $parts['fragment'] = $matches[30];

        if (!$decode) {
            return $parts;
        }

        if (!empty($parts['user']))     $parts['user']     = rawurldecode($parts['user']);
        if (!empty($parts['pass']))     $parts['pass']     = rawurldecode($parts['pass']);
        if (!empty($parts['path']))     $parts['path']     = rawurldecode($parts['path']);
        if ($hostName !== null)         $parts['host']     = rawurldecode($parts['host']);
        if (!empty($parts['query']))    $parts['query']    = rawurldecode($parts['query']);
        if (!empty($parts['fragment'])) $parts['fragment'] = rawurldecode($parts['fragment']);

        return $parts;
    }

    /**
     * Assemble RFC3986 URL components into a URL string (RFC3986 "component recomposition").
     *
     * @param array<string, string> $parts
     */
    public static function joinUrl(array $parts, bool $encode = false): string {
        if ($encode) {
            if (isset($parts['user']))     $parts['user']     = rawurlencode($parts['user']);
            if (isset($parts['pass']))     $parts['pass']     = rawurlencode($parts['pass']);
            if (
                isset($parts['host']) &&
                !preg_match('!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host'])
            ) {
                $parts['host'] = rawurlencode($parts['host']);
            }
            if (!empty($parts['path'])) {
                $parts['path'] = preg_replace('!%2F!ui', '/', rawurlencode($parts['path']));
            }
            if (isset($parts['query']))    $parts['query']    = rawurlencode($parts['query']);
            if (isset($parts['fragment'])) $parts['fragment'] = rawurlencode($parts['fragment']);
        }

        $result = '';

        if (!empty($parts['scheme'])) {
            $result .= $parts['scheme'] . ':';
        }

        if (isset($parts['host'])) {
            $result .= '//';
            if (isset($parts['user'])) {
                $result .= $parts['user'];
                if (isset($parts['pass'])) {
                    $result .= ':' . $parts['pass'];
                }
                $result .= '@';
            }
            if (preg_match('!^[\da-f]*:[\da-f.:]+$!ui', $parts['host'])) {
                $result .= '[' . $parts['host'] . ']'; // IPv6
            } else {
                $result .= $parts['host'];
            }
            if (isset($parts['port'])) {
                $result .= ':' . $parts['port'];
            }
            if (!empty($parts['path']) && $parts['path'][0] !== '/') {
                $result .= '/';
            }
        }

        if (!empty($parts['path']))     $result .= $parts['path'];
        if (isset($parts['query']))     $result .= '?' . $parts['query'];
        if (isset($parts['fragment']))  $result .= '#' . $parts['fragment'];

        return $result;
    }

    /**
     * Percent-encode a URL, preserving already-encoded sequences and RFC3986 reserved characters.
     */
    public static function encodeUrl(string $url): string {
        $reserved = [
            ':' => '!%3A!ui', '/' => '!%2F!ui', '?' => '!%3F!ui',
            '#' => '!%23!ui', '[' => '!%5B!ui', ']' => '!%5D!ui',
            '@' => '!%40!ui', '!' => '!%21!ui', '$' => '!%24!ui',
            '&' => '!%26!ui', "'" => '!%27!ui', '(' => '!%28!ui',
            ')' => '!%29!ui', '*' => '!%2A!ui', '+' => '!%2B!ui',
            ',' => '!%2C!ui', ';' => '!%3B!ui', '=' => '!%3D!ui',
            '%' => '!%25!ui',
        ];

        $url = rawurlencode($url);
        return preg_replace(array_values($reserved), array_keys($reserved), $url);
    }

    /**
     * Base64-encode a string for safe use in a URL (uses `.`, `-`, `~` instead of `+`, `=`, `/`).
     */
    public static function base64Encode(string $string): string {
        return strtr(base64_encode($string), ['+' => '.', '=' => '-', '/' => '~']);
    }

    /**
     * Decode a URL-safe base64 string encoded with {@see base64Encode()}.
     */
    public static function base64Decode(string $string): string {
        return base64_decode(strtr($string, ['.' => '+', '-' => '=', '~' => '/']));
    }

    /**
     * Ensure a URL path ends with a trailing slash.
     */
    public static function addTrailingSlash(string $url): string {
        return str_ends_with($url, '/') ? $url : $url . '/';
    }
}
