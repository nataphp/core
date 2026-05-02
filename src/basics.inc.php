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

use Nata\I18n\I18n;
use Nata\Core\App;
use Nata\Core\Configure;
use Nata\I18n\Multibyte;

/**
 * NataPHP version.
 */
    define('VERSION', '1.0.1');

/**
 * Basic defines for timing functions.
 */
    define('SECOND', 1);
    define('MINUTE', 60);
    define('HOUR', 3600);
    define('DAY', 86400);
    define('WEEK', 604800);
    define('MONTH', 2592000);
    define('YEAR', 31536000);

/**
 * Script execution timer.
 *
 * @param string $profileName Profile name
 * @param boolean $print True to print the result, false to return it
 * @param boolean $clear True to clear counter, false otherwise
 * @param int $precision Rounding precision
 * @return true or string
 */
function gentime($profileName = 'App', $print = true, $clear = true, $precision = 5) {
    static $profiles;

    if (isset($profiles[$profileName])) {
        $time = (float)(microtime(true) - $profiles[$profileName]);
        $time = round($time, $precision);

        if ($clear === true) {
            unset($profiles[$profileName]);
        }

        if ($print) {
            echo ($profileName . ' - ' . $time . ' seconds' . PHP_EOL);
            return;
        }

        return $time;
    }

    $profiles[$profileName] = microtime(true);
}

/**
 * Simple debug using print_r.
 *
 * @param mixed $array The array to print
 * @param boolean $print True to print the result, false to return it
 * @return string|void
 */
function print_a($data, $print = true) {
    $out = print_r($data, true);
    if (!$print) {
        return $out;
    }

    if (PHP_SAPI === 'cli') {
        echo $out . PHP_EOL;
        return;
    }

    echo '<pre>' . htmlspecialchars($out, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>' . PHP_EOL;
}

/**
 * Console-like debug logger (similar to JavaScript's console.log).
 * - Accepts multiple arguments
 * - In CLI prints readable text
 * - In web context logs to the browser console via a <script> tag
 *
 * @return void
 */
function console_log() {
    $args = func_get_args();

    // CLI output
    if (PHP_SAPI === 'cli') {
        $parts = array_map(function ($value) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if ($value === null) {
                return 'null';
            }
            if (is_scalar($value)) {
                return (string)$value;
            }
            if (is_resource($value)) {
                return '(resource:' . get_resource_type($value) . ')';
            }
            return print_r($value, true);
        }, $args);

        echo implode(' ', $parts) . PHP_EOL;
        return;
    }

    // Browser console output
    $jsonArgs = [];
    foreach ($args as $arg) {
        // Normalize values that JSON can't encode
        if (is_resource($arg)) {
            $arg = '(resource:' . get_resource_type($arg) . ')';
        } elseif ($arg instanceof \Throwable) {
            $arg = get_class($arg) . ': ' . $arg->getMessage();
        }

        $encoded = json_encode($arg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($encoded === false) {
            // Fallback to stringified representation
            $encoded = json_encode(print_r($arg, true), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
        $jsonArgs[] = $encoded;
    }

    echo '<script>(function(){try{if(window&&window.console&&console.log){console.log(' . implode(', ', $jsonArgs) . ');}}catch(e){}})();</script>' . PHP_EOL;
}

/**
 * Console-like debug logger (similar to JavaScript's console.log).
 * - Accepts multiple arguments
 * - In CLI prints readable text
 * - In web context logs to the browser console via a <script> tag
 *
 * @return void
 */
function print_value(mixed $value, string $labelPrefix = '') {
    $out = '';
    if ($labelPrefix) {
        if (PHP_SAPI === 'cli') {
            $labelPrefix = '[' . $labelPrefix . '] ';
        } else {
            $labelPrefix = '<span style="color: red;">' . $labelPrefix . '</span>';
        }
        $out .= $labelPrefix . ': ';
    }

    if ($value === null) {
        $out .= 'null';
    } elseif (is_bool($value)) {
        $out .= $value ? 'true' : 'false';
    } elseif (is_scalar($value)) {
        $out .= $value;
    } elseif (is_resource($value)) {
        $out .= '(resource:' . get_resource_type($value) . ')';
    } else {
        $out .= print_r($value, true);
    }
    if (PHP_SAPI === 'cli') {
        echo $out . PHP_EOL;
        return;
    }

    echo '<pre>' . $out . '</pre>' . PHP_EOL;
}

/**
 * Print to file string returned from print_a().
 *
 * @param string $filename Filename to use.
 * @param mixed $data Data to print to file.
 * @return boolean True if successful
 */
function print_to_file($filename, $data = array(), $replace = false) {
    if (is_array($filename)) {
        $data = $filename;
        $filename = null;
    }

    if (empty($filename)) {
        $filename = 'print';
    }

    if (strpos($filename, DS) === false && strpos($filename, '/') === false) {
        $filename .= '.log';
        $filename = LOGS . $filename;
    }

    $contents = '---- ' . gmdate('Y-m-d H:i:s')
        . ' ----------------------------------------------------------------------'
        . PHP_EOL . PHP_EOL;

    $contents .= "\t" . print_a($data, false);

    return file_put_contents($filename, $contents, (!$replace ? FILE_APPEND : 0));
}

/**
 * Uses splitter() to split a dot syntax plugin name into its plugin and classname.
 *
 * Commonly used like `list($plugin, $name) = pluginSplit($name);`
 *
 * @param string $name The name you want to plugin split.
 * @param boolean $dotAppend Set to true if you want the plugin to have a '.' appended to it.
 * @param string $plugin Optional default plugin to use if no plugin is found. Defaults to null.
 * @return array Array with 2 indexes.  0 => plugin name, 1 => classname
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#pluginSplit
 */
function pluginSplit(?string $name, $dotAppend = false, $plugin = null) {
    if ($name && strpos($name, '.') !== false) {
        return splitter($name, '.', $dotAppend);
    }
    return [$plugin, $name];
}

/**
 * Splits a string with delimiter syntax. This function is useful when you need to
 * ensure that n indexes are always returned, preventing unset index.
 * If $string does not have a delimiter, then index 0 will be $string.
 *
 * Commonly used like `list($controller, $action) = splitter('Controller::index', '::');`
 *
 * @param string $string The name you want to plugin split.
 * @param string $delimiter Set to true if you want the plugin to have a '.' appended to it.
 * @param boolean $delimiterAppended Set to true if you want the first index with the delimiter appended to it.
 * @param int $limit Limit of times it will explode the delimiter.
 * @param mixed $default Default value used if none found.
 * @return array Array with number of $limit indexes.
 */
function splitter(string $string, $delimiter = '.', $delimiterAppended = false, $limit = 2, $default = null) {
    if (is_int($delimiterAppended)) {
        $limit = $delimiterAppended;
        $delimiterAppended = false;
    }

    $splitted = explode($delimiter, $string, $limit);
    $array = [];

    for ($index = 0; $index < $limit; $index++) {
        $array[] = $default;
    }

    $splitted += $array;

    if ($delimiterAppended) {
        $splitted[0] .= $delimiter;
    }

    return $splitted;
}

/**
 * Split the namespace from the classname.
 *
 * Commonly used like `list($namespace, $className) = namespaceSplit($class);`.
 *
 * @param string $class The full class name, ie `Nata\Core\App`.
 * @return array Array with 2 indexes. 0 => namespace, 1 => classname.
 */
function namespaceSplit($class) {
    $pos = strrpos($class, '\\');
    if ($pos === false) {
        return array('', $class);
    }
    return array(substr($class, 0, $pos), substr($class, $pos + 1));
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__
 */
function __($singular, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::translate($singular);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Returns correct plural form of message identified by $singular and $plural for count $count.
 * Some languages have more than one form for plural messages dependent on the count.
 *
 * @param string $singular Singular text to translate
 * @param string $plural Plural text
 * @param integer $count Count
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__n
 */
function __n($singular, $plural, $count, $args = null) {
    if (!$singular) {
        return;
    }

    $translated = I18n::translate($singular, $plural, null, null, I18n::LC_MESSAGES, $count);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Returns a translated string in given context.
 *
 * @param string $context String context
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @tutorial Add __x:1c,2 to PoEdit to parse strings correctly
 * @return string Translated string
 */
function __x($context, $msg, $args = null) {
    if (!$msg) {
        return;
    }

    $translated = I18n::translate($msg, null, $context);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Returns correct plural form of message identified by $singular and $plural for count $count
 * in given context.
 * Some languages have more than one form for plural messages dependent on the count.
 *
 * @param string $context String context
 * @param string $singular Singular string to translate
 * @param string $plural Plural
 * @param integer $count Count
 * @param mixed $args Array with arguments or multiple arguments in function
 * @tutorial Add __x:1c,2,3 to PoEdit to parse strings correctly
 * @return string Translated string
 */
function __xn($context, $singular, $plural, $count, $args = null) {
    if (!$singular) {
        return;
    }

    $translated = I18n::translate($singular, $plural, $context, null, I18n::LC_MESSAGES, $count);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup.
 *
 * @param string $domain Domain
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__d
 */
function __d($domain, $msg, $args = null) {
    if (!$msg) {
        return;
    }

    $translated = I18n::translate($msg, null, null, $domain);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single plural message lookup.
 * Returns correct plural form of message identified by $singular and $plural for count $count
 * from domain $domain.
 *
 * @param string $domain Domain
 * @param string $singular Singular string to translate
 * @param string $plural Plural
 * @param integer $count Count
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Plural form of translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__dn
 */
function __dn($domain, $singular, $plural, $count, $args = null) {
    if (!$singular) {
        return;
    }

    $translated = I18n::translate($singular, $plural, null, $domain, I18n::LC_MESSAGES, $count);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup in given context.
 *
 * @param string $domain Domain
 * @param string $context String context
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @tutorial Add __x:1c,2 to PoEdit to parse strings correctly
 * @return string Translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__x
 */
function __dx($domain, $context, $msg, $args = null) {
    if (!$msg) {
        return;
    }
    $translated = I18n::translate($msg, null, $context, $domain);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single plural message lookup in given context.
 * Returns correct plural form of message identified by $singular and $plural for count $count
 * from domain $domain.
 *
 * @param string $domain Domain
 * @param string $context String context
 * @param string $singular Singular string to translate
 * @param string $plural Plural
 * @param integer $count Count
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Plural form of translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__dn
 */
function __dxn($domain, $context, $singular, $plural, $count, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::translate($singular, $plural, $context, $domain, I18n::LC_MESSAGES, $count);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup.
 * It also allows you to specify a category.
 *
 * The category argument allows a specific category of the locale settings to be used for fetching a message.
 * Valid categories are: LC_CTYPE, LC_NUMERIC, LC_TIME, LC_COLLATE, LC_MONETARY, LC_MESSAGES and LC_ALL.
 *
 * Note that the category must be specified with a numeric value, instead of the constant name. The values are:
 *
 * - LC_ALL       0
 * - LC_COLLATE   1
 * - LC_CTYPE     2
 * - LC_MONETARY  3
 * - LC_NUMERIC   4
 * - LC_TIME      5
 * - LC_MESSAGES  6
 *
 * @param string $domain Domain
 * @param string $msg Message to translate
 * @param integer $category Category
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__dc
 */
function __dc($domain, $msg, $category, $args = null) {
    if (!$msg) {
        return;
    }
    $translated = I18n::translate($msg, null, null, $domain, $category);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single plural message lookup.
 * It also allows you to specify a category.
 * Returns correct plural form of message identified by $singular and $plural for count $count
 * from domain $domain.
 *
 * The category argument allows a specific category of the locale settings to be used for fetching a message.
 * Valid categories are: LC_CTYPE, LC_NUMERIC, LC_TIME, LC_COLLATE, LC_MONETARY, LC_MESSAGES and LC_ALL.
 *
 * Note that the category must be specified with a numeric value, instead of the constant name.  The values are:
 *
 * - LC_ALL       0
 * - LC_COLLATE   1
 * - LC_CTYPE     2
 * - LC_MONETARY  3
 * - LC_NUMERIC   4
 * - LC_TIME      5
 * - LC_MESSAGES  6
 *
 * @param string $domain Domain
 * @param string $singular Singular string to translate
 * @param string $plural Plural
 * @param integer $count Count
 * @param integer $category Category
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Plural form of translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__dcn
 */
function __dcn($domain, $singular, $plural, $count, $category, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::translate($singular, $plural, null, $domain, $category, $count);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * It will add message if it's missing.
 * It's useful for passing variable's strings
 *
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __v($singular, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::translate($singular, null, null, null, I18n::LC_MESSAGES, null, null, true);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Returns a translated string in given context.
 *
 * @param string $context String context
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @tutorial Add __x:1c,2 to PoEdit to parse strings correctly
 * @return string Translated string
 */
function __vx($context, $msg, $args = null) {
    if (!$msg) {
        return;
    }

    $translated = I18n::translate($msg, null, $context, null, I18n::LC_MESSAGES, null, null, true);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup.
 *
 * @param string $domain Domain
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __vd($domain, $msg, $args = null) {
    if (!$msg) {
        return;
    }

    $translated = I18n::translate($msg, null, null, $domain, I18n::LC_MESSAGES, null, null, true);

    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup in given context.
 *
 * @param string $domain Domain
 * @param string $context String context
 * @param string $msg String to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @tutorial Add __x:1c,2 to PoEdit to parse strings correctly
 * @return string Translated string
 */
function __vdx($domain, $context, $msg, $args = null) {
    if (!$msg) {
        return;
    }
    $translated = I18n::translate($msg, null, $context, $domain, I18n::LC_MESSAGES, null, null, true);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single message lookup.
 * It also allows you to specify a category.
 *
 * The category argument allows a specific category of the locale settings to be used for fetching a message.
 * Valid categories are: LC_CTYPE, LC_NUMERIC, LC_TIME, LC_COLLATE, LC_MONETARY, LC_MESSAGES and LC_ALL.
 *
 * Note that the category must be specified with a numeric value, instead of the constant name. The values are:
 *
 * - LC_ALL       0
 * - LC_COLLATE   1
 * - LC_CTYPE     2
 * - LC_MONETARY  3
 * - LC_NUMERIC   4
 * - LC_TIME      5
 * - LC_MESSAGES  6
 *
 * @param string $domain Domain
 * @param string $msg Message to translate
 * @param integer $category Category
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __vdc($domain, $msg, $category, $args = null) {
    if (!$msg) {
        return;
    }
    $translated = I18n::translate($msg, null, null, $domain, $category, null, null, true);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Allows you to override the current domain for a single plural message lookup.
 * It also allows you to specify a category.
 * Returns correct plural form of message identified by $singular and $plural for count $count
 * from domain $domain.
 *
 * The category argument allows a specific category of the locale settings to be used for fetching a message.
 * Valid categories are: LC_CTYPE, LC_NUMERIC, LC_TIME, LC_COLLATE, LC_MONETARY, LC_MESSAGES and LC_ALL.
 *
 * Note that the category must be specified with a numeric value, instead of the constant name.  The values are:
 *
 * - LC_ALL       0
 * - LC_COLLATE   1
 * - LC_CTYPE     2
 * - LC_MONETARY  3
 * - LC_NUMERIC   4
 * - LC_TIME      5
 * - LC_MESSAGES  6
 *
 * @param string $domain Domain
 * @param string $singular Singular string to translate
 * @param string $plural Plural
 * @param integer $count Count
 * @param integer $category Category
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Plural form of translated string
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#__dcn
 */
function __vdcn($domain, $singular, $plural, $count, $category, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::translate($singular, $plural, null, $domain, $category, $count, null, true);
    if ($args === null) {
        return $translated;
    }
    return I18n::format($translated, $args);
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __r($singular, $args = null) {
    if (!$singular) {
        return;
    }

    $translated = I18n::reverse($singular);
    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * @param string $context Context
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __rx($context, $singular, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::reverse($singular, $context);
    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * @param string $domain Context
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __rd($domain, $singular, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::reverse($singular, null, $domain);
    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Returns a translated string if one is found; Otherwise, the submitted message.
 *
 * @param string $domain Context
 * @param string $singular Text to translate
 * @param mixed $args Array with arguments or multiple arguments in function
 * @return string Translated string
 */
function __rdx($domain, $context, $singular, $args = null) {
    if (!$singular) {
        return;
    }
    $translated = I18n::reverse($singular, $context, $domain);
    if ($args === null) {
        return $translated;
    }

    return I18n::format($translated, $args);
}

/**
 * Gets an environment variable from available sources, and provides emulation
 * for unsupported or inconsistent environment variables (i.e. DOCUMENT_ROOT on
 * IIS, or SCRIPT_NAME in CGI mode).  Also exposes some additional custom
 * environment information.
 *
 * @param  string $key Environment variable name.
 * @return string Environment variable setting.
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#env
 */
function env($key) {
    if ($key === 'HTTPS') {
        if (isset($_SERVER['HTTPS'])) {
            return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        }
        return (env('SCRIPT_URI') && strpos(env('SCRIPT_URI'), 'https://') === 0);
    }

    if ($key === 'SCRIPT_NAME') {
        if (env('CGI_MODE') && isset($_ENV['SCRIPT_URL'])) {
            $key = 'SCRIPT_URL';
        }
    }

    $val = null;
    if (isset($_SERVER[$key])) {
        $val = $_SERVER[$key];
    } elseif (isset($_ENV[$key])) {
        $val = $_ENV[$key];
    } elseif (getenv($key) !== false) {
        $val = getenv($key);
    }

    if ($key === 'REMOTE_ADDR' && $val === env('SERVER_ADDR')) {
        $addr = env('HTTP_PC_REMOTE_ADDR');
        if ($addr !== null) {
            $val = $addr;
        }
    }

    if ($val !== null) {
        return $val;
    }

    switch ($key) {
        case 'SCRIPT_FILENAME':
            if (defined('SERVER_IIS') && SERVER_IIS === true) {
                return str_replace('\\\\', '\\', env('PATH_TRANSLATED'));
            }
            break;
        case 'DOCUMENT_ROOT':
            $name = env('SCRIPT_NAME');
            $filename = env('SCRIPT_FILENAME');
            $offset = 0;
            if (!strpos($name, '.php')) {
                $offset = 4;
            }
            return substr($filename, 0, -(strlen($name) + $offset));
        case 'PHP_SELF':
            return str_replace(env('DOCUMENT_ROOT'), '', env('SCRIPT_FILENAME'));
        case 'CGI_MODE':
            return (PHP_SAPI === 'cgi');
        case 'CRON':
        case 'CLI_MODE':
            return (PHP_SAPI === 'cli');
        case 'HTTP_BASE':
            $host = env('HTTP_HOST');
            $parts = explode('.', $host);
            $count = count($parts);

            if ($count === 1) {
                return '.' . $host;
            } elseif ($count === 2) {
                return '.' . $host;
            } elseif ($count === 3) {
                $gTLD = array(
                    'aero',
                    'asia',
                    'biz',
                    'cat',
                    'com',
                    'coop',
                    'edu',
                    'gov',
                    'info',
                    'int',
                    'jobs',
                    'mil',
                    'mobi',
                    'museum',
                    'name',
                    'net',
                    'org',
                    'pro',
                    'tel',
                    'travel',
                    'xxx'
                );
                if (in_array($parts[1], $gTLD)) {
                    return '.' . $host;
                }
            }
            array_shift($parts);
            return '.' . implode('.', $parts);
    }
    return null;
}

/**
 * Convenience method for htmlspecialchars.
 *
 * @param string|array|object $text Text to wrap through htmlspecialchars.  Also works with arrays, and objects.
 *    Arrays will be mapped and have all their elements escaped.  Objects will be string cast if they
 *    implement a `__toString` method.  Otherwise the class name will be used.
 * @param boolean $double Encode existing html entities
 * @param string $charset Character set to use when escaping.  Defaults to config value in 'App.encoding' or 'UTF-8'
 * @return string Wrapped text
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#h
 */
function h($text, $double = true, $charset = null) {
    if (is_array($text)) {
        $texts = array();
        foreach ($text as $k => $t) {
            $texts[$k] = h($t, $double, $charset);
        }
        return $texts;
    } elseif (is_object($text)) {
        if (method_exists($text, '__toString')) {
            $text = (string)$text;
        } else {
            $text = '(object)' . get_class($text);
        }
    } elseif (is_bool($text)) {
        return $text;
    }

    static $defaultCharset = false;
    if ($defaultCharset === false) {
        $defaultCharset = Configure::read('App.encoding');
        if ($defaultCharset === null) {
            $defaultCharset = 'UTF-8';
        }
    }
    if (is_string($double)) {
        $charset = $double;
    }
    return htmlspecialchars($text, ENT_QUOTES, ($charset) ? $charset : $defaultCharset, $double);
}

/**
 * Recursively strips slashes from all values in an array.
 *
 * @param array $values Array of values to strip slashes
 * @return mixed What is returned from calling stripslashes
 * @link http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#stripslashes_deep
 */
function stripslashes_deep($values) {
    if (is_array($values)) {
        foreach ($values as $key => $value) {
            $values[$key] = stripslashes_deep($value);
        }
    } else {
        $values = stripslashes($values);
    }
    return $values;
}

/**
 * Convert <br> tags to nl.
 *
 * @param string The string to convert
 * @return string The converted string
 */
function br2nl($string) {
    $breaks = ["<br />", "<br>", "<br/>", "<br />", "&lt;br /&gt;", "&lt;br/&gt;", "&lt;br&gt;"];
    return str_ireplace($breaks, "\r\n", $string);
    return preg_replace('/\<br(\s*)?\/?\>/i', "\r\n", $string);
}

/**
 * Base encode string to be used in URL.
 *
 * @param string $data String to be encoded
 * @return string URL safe Base64 encoded string
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode URL safe Base64 encoded string.
 *
 * @param string $string String to be decoded
 * @return string URL safe Base64 decoded string
 */
function base64url_decode($data) {
    return base64_decode(
        str_pad(
            strtr($data, '-_', '+/'),
            strlen($data) % 4,
            '=',
            STR_PAD_RIGHT
        )
    );
}

/**
 * Compatibility with the password_* functions that ship with PHP 5.5
 */
if (!function_exists('password_hash')) {
    include NATA . 'vendor' . DS . 'password_compat' . DS . 'lib' . DS . 'password.php';
}

if (!function_exists('mb_stripos')) {

/**
 * Find position of first occurrence of a case-insensitive string.
 *
 * @param string $haystack The string from which to get the position of the first occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param integer $offset The position in $haystack to start searching.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer|boolean The numeric position of the first occurrence of $needle in the $haystack string, or false
 *    if $needle is not found.
 */
    function mb_stripos($haystack, $needle, $offset = 0, $encoding = null) {
        return Multibyte::stripos($haystack, $needle, $offset);
    }

}

if (!function_exists('mb_stristr')) {

/**
 * Finds first occurrence of a string within another, case insensitive.
 *
 * @param string $haystack The string from which to get the first occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param boolean $part Determines which portion of $haystack this function returns.
 *    If set to true, it returns all of $haystack from the beginning to the first occurrence of $needle.
 *    If set to false, it returns all of $haystack from the first occurrence of $needle to the end,
 *    Default value is false.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string|boolean The portion of $haystack, or false if $needle is not found.
 */
    function mb_stristr($haystack, $needle, $part = false, $encoding = null) {
        return Multibyte::stristr($haystack, $needle, $part);
    }

}

if (!function_exists('mb_strlen')) {

/**
 * Get string length.
 *
 * @param string $string The string being checked for length.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer The number of characters in string $string having character encoding encoding.
 *    A multi-byte character is counted as 1.
 */
    function mb_strlen($string, $encoding = null) {
        return Multibyte::strlen($string);
    }

}

if (!function_exists('mb_strpos')) {

/**
 * Find position of first occurrence of a string.
 *
 * @param string $haystack The string being checked.
 * @param string $needle The position counted from the beginning of haystack.
 * @param integer $offset The search offset. If it is not specified, 0 is used.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer|boolean The numeric position of the first occurrence of $needle in the $haystack string.
 *    If $needle is not found, it returns false.
 */
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return Multibyte::strpos($haystack, $needle, $offset);
    }

}

if (!function_exists('mb_strrchr')) {

/**
 * Finds the last occurrence of a character in a string within another.
 *
 * @param string $haystack The string from which to get the last occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param boolean $part Determines which portion of $haystack this function returns.
 *    If set to true, it returns all of $haystack from the beginning to the last occurrence of $needle.
 *    If set to false, it returns all of $haystack from the last occurrence of $needle to the end,
 *    Default value is false.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string|boolean The portion of $haystack. or false if $needle is not found.
 */
    function mb_strrchr($haystack, $needle, $part = false, $encoding = null) {
        return Multibyte::strrchr($haystack, $needle, $part);
    }

}

if (!function_exists('mb_strrichr')) {

/**
 * Finds the last occurrence of a character in a string within another, case insensitive.
 *
 * @param string $haystack The string from which to get the last occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param boolean $part Determines which portion of $haystack this function returns.
 *    If set to true, it returns all of $haystack from the beginning to the last occurrence of $needle.
 *    If set to false, it returns all of $haystack from the last occurrence of $needle to the end,
 *    Default value is false.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string|boolean The portion of $haystack. or false if $needle is not found.
 */
    function mb_strrichr($haystack, $needle, $part = false, $encoding = null) {
        return Multibyte::strrichr($haystack, $needle, $part);
    }

}

if (!function_exists('mb_strripos')) {

/**
 * Finds position of last occurrence of a string within another, case insensitive
 *
 * @param string $haystack The string from which to get the position of the last occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param integer $offset The position in $haystack to start searching.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer|boolean The numeric position of the last occurrence of $needle in the $haystack string,
 *    or false if $needle is not found.
 */
    function mb_strripos($haystack, $needle, $offset = 0, $encoding = null) {
        return Multibyte::strripos($haystack, $needle, $offset);
    }

}

if (!function_exists('mb_strrpos')) {

/**
 * Find position of last occurrence of a string in a string.
 *
 * @param string $haystack The string being checked, for the last occurrence of $needle.
 * @param string $needle The string to find in $haystack.
 * @param integer $offset May be specified to begin searching an arbitrary number of characters into the string.
 *    Negative values will stop searching at an arbitrary point prior to the end of the string.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer|boolean The numeric position of the last occurrence of $needle in the $haystack string.
 *    If $needle is not found, it returns false.
 */
    function mb_strrpos($haystack, $needle, $offset = 0, $encoding = null) {
        return Multibyte::strrpos($haystack, $needle, $offset);
    }

}

if (!function_exists('mb_strstr')) {

/**
 * Finds first occurrence of a string within another
 *
 * @param string $haystack The string from which to get the first occurrence of $needle.
 * @param string $needle The string to find in $haystack
 * @param boolean $part Determines which portion of $haystack this function returns.
 *    If set to true, it returns all of $haystack from the beginning to the first occurrence of $needle.
 *    If set to false, it returns all of $haystack from the first occurrence of $needle to the end,
 *    Default value is FALSE.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string|boolean The portion of $haystack, or true if $needle is not found.
 */
    function mb_strstr($haystack, $needle, $part = false, $encoding = null) {
        return Multibyte::strstr($haystack, $needle, $part);
    }

}

if (!function_exists('mb_strtolower')) {

/**
 * Make a string lowercase
 *
 * @param string $string The string being lowercased.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string with all alphabetic characters converted to lowercase.
 */
    function mb_strtolower($string, $encoding = null) {
        return Multibyte::strtolower($string);
    }

}

if (!function_exists('mb_strtoupper')) {

/**
 * Make a string uppercase
 *
 * @param string $string The string being uppercased.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string with all alphabetic characters converted to uppercase.
 */
    function mb_strtoupper($string, $encoding = null) {
        return Multibyte::strtoupper($string);
    }

}

if (!function_exists('mb_substr_count')) {

/**
 * Count the number of substring occurrences
 *
 * @param string $haystack The string being checked.
 * @param string $needle The string being found.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return integer The number of times the $needle substring occurs in the $haystack string.
 */
    function mb_substr_count($haystack, $needle, $encoding = null) {
        return Multibyte::substrCount($haystack, $needle);
    }

}

if (!function_exists('mb_substr')) {

/**
 * Get part of string
 *
 * @param string $string The string being checked.
 * @param integer $start The first position used in $string.
 * @param integer $length The maximum length of the returned string.
 * @param string $encoding Character encoding name to use. If it is omitted, internal character encoding is used.
 * @return string The portion of $string specified by the $string and $length parameters.
 */
    function mb_substr($string, $start, $length = null, $encoding = null) {
        return Multibyte::substr($string, $start, $length);
    }

}

if (!function_exists('mb_encode_mimeheader')) {

/**
 * Encode string for MIME header.
 *
 * @param string $str The string being encoded
 * @param string $charset specifies the name of the character set in which str is represented in.
 *    The default value is determined by the current NLS setting (mbstring.language).
 * @param string $transfer_encoding specifies the scheme of MIME encoding.
 *    It should be either "B" (Base64) or "Q" (Quoted-Printable). Falls back to "B" if not given.
 * @param string $linefeed specifies the EOL (end-of-line) marker with which
 *    mb_encode_mimeheader() performs line-folding
 *    (a » RFC term, the act of breaking a line longer than a certain length into multiple lines.
 *    The length is currently hard-coded to 74 characters). Falls back to "\r\n" (CRLF) if not given.
 * @param integer $indent [definition unknown and appears to have no affect]
 * @return string A converted version of the string represented in ASCII.
 */
    function mb_encode_mimeheader($str, $charset = 'UTF-8', $transferEncoding = 'B', $linefeed = "\r\n", $indent = 1) {
        return Multibyte::mimeEncode($str, $charset, $linefeed);
    }

}

if (!function_exists('str_contains')) {

/**
 * Determine if a string contains a given substring.
 * Performs a case-sensitive check indicating if needle is contained in haystack.
 *
 * @param string $haystack The string to search in.
 * @param string $needle The substring to search for in the haystack.
 * @return boolean true if needle is in haystack, false otherwise.
 */
    function str_contains(?string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }

/**
 * Checks if a string starts with a given substring.
 *
 * @param string $haystack The string from which to check existence of $needle.
 * @param string $needle The string to check in $haystack.
 * @return boolean true if haystack starts with needle, false otherwise.
 */
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

/**
 * Checks if a string ends with a given substring.
 *
 * @param string $haystack The string from which to check existence of $needle.
 * @param string $needle The string to check in $haystack.
 * @return boolean true if haystack ends with needle, false otherwise.
 */
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }

}

/**
 * Generates cryptographically secure pseudo-random integers.
 * This function is only for PHP <= 5.6
 *
 * @param int $min The lowest value to be returned, which must be PHP_INT_MIN or higher
 * @param int $max The highest value to be returned, which must be less than or equal to PHP_INT_MAX
 * @return int Returns a cryptographically secure random integer in the range min to max, inclusive.
 */
if (!function_exists('random_int')) {
    function random_int($min, $max) {

        if (!function_exists('mcrypt_create_iv')) {
            trigger_error(
                'mcrypt must be loaded for random_int to work',
                E_USER_WARNING
            );
            return null;
        }

        if (!is_int($min) || !is_int($max)) {
            trigger_error('$min and $max must be integer values', E_USER_NOTICE);
            $min = (int)$min;
            $max = (int)$max;
        }

        if ($min > $max) {
            trigger_error('$max can\'t be lesser than $min', E_USER_WARNING);
            return null;
        }

        $range = $counter = $max - $min;
        $bits = 1;

        while ($counter >>= 1) {
            ++$bits;
        }

        $bytes = (int)max(ceil($bits/8), 1);
        $bitmask = pow(2, $bits) - 1;

        if ($bitmask >= PHP_INT_MAX) {
            $bitmask = PHP_INT_MAX;
        }

        do {
            $result = hexdec(
                bin2hex(
                    mcrypt_create_iv($bytes, MCRYPT_DEV_URANDOM)
                )
            ) & $bitmask;
        } while ($result > $range);

        return $result + $min;
    }

}