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
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\I18n;

use Nata\Core\App;
use Nata\Core\Plugin;
use Nata\Core\Configure;
use Nata\I18n\L10n;
use Nata\Cache\Cache;
use Nata\Routing\Router;
use Nata\Utility\Inflector;
use Nata\Filesystem\Folder;
use Nata\Filesystem\File;
use Nata\I18n\Exception\LocaleException;
use Nata\Utility\Text;
use MessageFormatter;
use NataException;
use Exception;

/**
 * Internationalization.
 *
 * I18n handles translation of Text and time format strings.
 */
class I18n {

/**
 * Instance of the L10n class for localization.
 *
 * @var L10n
 */
    public $l10n;

/**
 * Instances of the L10n class for localization.
 *
 * @var array
 */
    protected $_l10n = [];

/**
 * Default domain of translation.
 *
 * @var string
 */
    public static $defaultDomain = 'default';

/**
 * Language used in the strings for translation in the code.
 *
 * @var string
 */
    protected $_sourceLocale = 'en';

/**
 * Current locale used for translations.
 *
 * @var string
 */
    protected $_locale;

/**
 * Language used if for some reason set language/locale it's invalid
 * (e.g. when 'auto' won't match any).
 *
 * @var string
 */
    protected $_defaultLocale = 'en';

/**
 * Translation strings for a specific domain read from the .mo or .po files.
 *
 * @var array
 */
    protected $_domains;

/**
 * Available locales in application
 *
 * @var array
 */
    protected $_available;

/**
 * Set to true when I18N::_bindTextDomain() is called for the first time.
 * If a translation file is found it is set to false again
 *
 * @var bool
 */
    protected $_noLocale = false;

/**
 * Cache enabled.
 *
 * @var bool
 */
    protected $_cacheEnabled = true;

/**
 * Translation categories
 *
 * @var array
 */
    protected $_categories = [
        'LC_ALL', 'LC_COLLATE', 'LC_CTYPE', 'LC_MONETARY', 'LC_NUMERIC', 'LC_TIME', 'LC_MESSAGES'
    ];

/**
 * Constants for the translation categories.
 *
 * The constants may be used in translation fetching
 * instead of hardcoded integers.
 * Example:
 * {{{
 *    I18n::translate('NataPHP is awesome.', null, null, I18n::LC_MESSAGES)
 * }}}
 *
 * To keep the code more readable, I18n constants are preferred over
 * hardcoded integers.
 */
/**
 * Constant for LC_ALL.
 *
 * @var int
 */
    const LC_ALL = 0;

/**
 * Constant for LC_COLLATE.
 *
 * @var int
 */
    const LC_COLLATE = 1;

/**
 * Constant for LC_CTYPE.
 *
 * @var int
 */
    const LC_CTYPE = 2;

/**
 * Constant for LC_MONETARY.
 *
 * @var int
 */
    const LC_MONETARY = 3;

/**
 * Constant for LC_NUMERIC.
 *
 * @var int
 */
    const LC_NUMERIC = 4;

/**
 * Constant for LC_TIME.
 *
 * @var int
 */
    const LC_TIME = 5;

/**
 * Constant for LC_MESSAGES.
 *
 * @var int
 */
    const LC_MESSAGES = 6;

/**
 * Escape string
 *
 * @var string
 */
    protected $_escape;

/**
 * Message formatter instances.
 *
 * @var array
 */
    protected $_formatter;

/**
 * Missing messages that will be added.
 *
 * @var array
 */
    protected $_missingMessages = [];

/**
 * Instance of the I18n class.
 *
 * @var array
 */
    private static $_instance = null;

/**
 * Plural guess cache.
 *
 * @var array
 */
    private static $_pluralGuessCache = [];

/**
 * Search paths for the locale definition file.
 *
 * @var array
 */
    private static array $_searchPaths = [];

/**
 * Constructor.
 *
 * Use I18n::getInstance() to get the i18n translation object.
 */
    public function __construct() {}

/**
 * Return a static instance of the I18n class
 *
 * @return I18n
 */
    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new static();
        }
        return self::$_instance;
    }

/**
 * Get the list locale's folders with information for each.
 *
 * @param array|null $available Available locales
 * @return array|I18n Locales in Locale folders when getting, $this when setting
 */
    public static function available($available = null) {
        $_this = static::getInstance();
        if (func_num_args() === 0) {
            if ($_this->_available === null) {
                $current = static::locale();
                $_this->_available = [(string)$current => new L10n($current)];

                $baseLocale = $_this->_sourceLocale;
                if (!empty($baseLocale) && $baseLocale !== $current) {
                    $_this->_available[$baseLocale] = new L10n($baseLocale);
                }

                foreach (static::_localeSearchRoots() as $localeRoot) {
                    $base = rtrim($localeRoot, DS);
                    if ($base === '' || !is_dir($base)) {
                        continue;
                    }
                    $folder = new Folder($base);
                    [$dir] = $folder->read();
                    foreach ($dir as $locale) {
                        if (strpos($locale, '.') !== false) {
                            continue;
                        }

                        $lang = new L10n($locale);
                        if (empty($lang)) {
                            continue;
                        }

                        $_this->_available[(string)$lang] = $lang;
                    }
                }
            }
            return $_this->_available;
        }

        $_this->_available = (array)$available;

        return $_this;
    }

/**
 * Check if a specific locale is available.
 *
 * @param string $locale Locale to check
 * @return bool
 */
    public static function isAvailable(string $locale): bool {
        return isset(static::available()[$locale]);
    }

/**
 * Language catalog information (alias to L10n::catalog()).
 *
 * @param string $locale Optionally pass the locale to get (defaults to current locale)
 * @return array Data about the catalog language
 */
    public static function catalog($locale = null) {
        if ($locale === null) {
            $locale = static::locale();
        }
        return static::getInstance()->l10n->catalog($locale);
    }

/**
 * Set/Get source locale.
 * This is the source locale that's being used in the strings to be translated in the code.
 *
 * @param string $locale Source locale
 * @return $this|string Current source locale when getting, $this when setting
 */
    public static function sourceLocale(string $locale = null) {
        $_this = static::getInstance();

        if ($locale === null) {
            return $_this->_sourceLocale;
        }

        $default = $_this->_getL10n($locale)->catalog($locale);
        if (empty($default)) {
            throw new LocaleException(sprintf('Source locale "%s" value not found in catalog', $locale));
        }

        $_this->_sourceLocale = $default['code'];

        return $_this;
    }

/**
 * Set/Get default locale (if no locale found, fallsback to default locale).
 *
 * @param string $locale Default locale
 * @return $this|string Current default language when getting, $this when setting
 */
    public static function defaultLocale($locale = null) {
        $_this = static::getInstance();

        if ($locale === null) {
            return $_this->_defaultLocale;
        }

        $default = $_this->_getL10n($locale)->catalog($locale);
        if (empty($default)) {
            throw new LocaleException(sprintf('Default language "%s" value not found in catalog', $locale));
        }

        $_this->_defaultLocale = $default['code'];

        return $_this;
    }

/**
 * Set/Get locale.
 * If set to 'http', 'browser' or 'auto' it will negotiate accepted languages HTTP header.
 *
 * @param string|null $locale Locale to set, or null to get current
 * @return $this|string Current locale string when getting, $this when setting
 */
    public static function locale(string $locale = null) {
        $_this = static::getInstance();

        if ($locale === null) {
            if ($_this->_locale === null) {
                $httpLang = static::httpLocale();
                $_this->l10n = $_this->_getL10n($httpLang);
                $_this->_locale = $_this->l10n->code;
            }
            return $_this->_locale;
        }

        if ($locale === 'http' || $locale === 'browser' || $locale === 'auto') {
            $locale = static::httpLocale();
        }

        $_this->l10n = $_this->_getL10n($locale);
        $_this->_locale = $_this->l10n->code;

        return $_this;
    }

/**
 * Attempts to find the locale settings based on the HTTP_ACCEPT_LANGUAGE variable.
 *
 * @param string $fallback Fallback locale
 * @return string Locale
 */
    public static function httpLocale(string $fallback = null) {
        if ($fallback === null) {
            $fallback = static::defaultLocale();
        }

        $request = Router::getRequest(true);
        if (!$request) {
            return $fallback;
        }

        $acceptLanguage = $request->acceptLanguage();
        if (!$acceptLanguage) {
            return $fallback;
        }

        $available = static::available();
        foreach ($acceptLanguage as $language) {
            $language = strtolower($language);
            if (isset($available[$language])) {
                return $available[$language]->code;
            }

            [$language] = explode('-', $language);
            if (isset($available[$language])) {
                return $available[$language]->code;
            }
        }

        return $fallback;
    }

/**
 * Used by the translation functions in basics.inc.php
 * Returns a translated string based on current language and translation files stored in locale folder
 *
 * @param string $singular String to translate
 * @param string $plural Plural string (if any)
 * @param string $context String Context (if any)
 * @param string $domain Domain The domain of the translation. Domains are often used by plugin translations.
 *    If null, the default domain will be used.
 * @param string $category Category The integer value of the category to use.
 * @param int $count Count Count is used with $plural to choose the correct plural form.
 * @param string $language Language to translate string to.
 *    If null it checks for language in session followed by Config.language configuration variable.
 * @return string translated string.
 * @throws \Exception When '' is provided as a domain.
 */
    public static function translate($singular, $plural = null, $context = null, $domain = null, int $category = self::LC_MESSAGES, $count = null, $language = null, $appendMissing = false) {
        $_this = I18n::getInstance();

        if (strpos($singular, "\r\n") !== false) {
            $singular = str_replace("\r\n", "\n", $singular);
        }
        if ($plural !== null && strpos($plural, "\r\n") !== false) {
            $plural = str_replace("\r\n", "\n", $plural);
        }

        if (is_numeric($category)) {
            $category = $_this->_categories[$category];
        }

        if ($language === null) {
            $language = static::locale();
        }

        $l10n = $_this->l10n;
        if ($language !== null) {
            $l10n = $_this->_getL10n($language);
        }

        if ($domain === null) {
            $domain = static::$defaultDomain;
        }
        if ($domain === '') {
            throw new Exception('You cannot use "" as a domain.');
        }

        $cacheKey = $domain . '_' . $l10n->lang;
        if (!isset($_this->_domains[$domain][$l10n->lang]) && $_this->_cacheEnabled === true) {
            $_this->_domains[$domain][$l10n->lang] = Cache::read($cacheKey, '__nata_core__');
        }

        if (!isset($_this->_domains[$domain][$l10n->lang][$category])) {
            $_this->_bindTextDomain($l10n, $domain, $category);
            if ($_this->_cacheEnabled === true) {
                Cache::write($cacheKey, $_this->_domains[$domain][$l10n->lang], '__nata_core__');
            }
        }

        // Plurals
        if ($count === null || $plural === null) {
            $plurals = 0;
        } elseif (!empty($_this->_domains[$domain][$l10n->lang][$category]["%plural-c"]) && $_this->_noLocale === false) {
            $header = $_this->_domains[$domain][$l10n->lang][$category]["%plural-c"];
            $plurals = $_this->_pluralGuess($header, $count);
        } elseif ($count != 1) {
            $plurals = 1;
        } else {
            $plurals = 0;
        }

        $messages = $_this->_domains[$domain][$l10n->lang][$category];
        if (isset($messages['msgctxt_' . $context][$singular]) || isset($messages['msgctxt_' . $context][$plural])) {
            $messages = $messages['msgctxt_' . $context];
        }

        if (isset($messages[$singular])) {
            if ($plurals && ($trans = $messages[$plural]) || ($trans = $messages[$singular])) {
                if (is_array($trans)) {
                    if (isset($trans[$plurals])) {
                        $trans = $trans[$plurals];
                    } else {
                        trigger_error(
                            sprintf(
                                'Missing plural form translation for "%s" in "%s" domain, "%s" locale.' .
                                ' Check your po file for correct plurals and valid Plural-Forms header.',
                                $singular,
                                $domain,
                                $l10n->lang
                            ),
                            E_USER_WARNING
                        );
                        $trans = $trans[0];
                    }
                }

                if (strlen($trans)) {
                    return $trans;
                }

            }
        }

        /*
        if ($appendMissing && !empty($singular)) {
            $_this->_appendMissingMessage($l10n, $domain, $category, $singular, $plural, $context);
        }
        */

        return !empty($plurals) ? $plural : $singular;
    }

/**
 * Used by the translation functions in basics.inc.php.
 * Reverses a translated string to the default language.
 *
 * @param string $singular String to translate
 * @param string $context String Context (if any)
 * @param string $domain Domain The domain of the translation. Domains are often used by plugin translations.
 *    If null, the default domain will be used.
 * @param string $category Category The integer value of the category to use.
 * @param string $language Language to translate string to.
 *    If null it checks for language in session followed by Config.language configuration variable.
 * @return string translated string.
 * @throws \NataException When '' is provided as a domain.
 */
    public static function reverse($singular, $context = null, $domain = null, $category = self::LC_MESSAGES, string $language = null) {
        $_this = I18n::getInstance();

        if (str_contains($singular, "\r\n")) {
            $singular = str_replace("\r\n", "\n", $singular);
        }

        if (is_numeric($category)) {
            $category = $_this->_categories[$category];
        }

        $l10n = $_this->l10n;
        if ($language !== null) {
            $l10n = $_this->_getL10n($language);
        }

        if ($domain === null) {
            $domain = static::$defaultDomain;
        }

        if ($domain === '') {
            throw new Exception('You cannot use "" as a domain.');
        }

        $cacheKey = $domain . '_' . $l10n->lang;
        if (!isset($_this->_domains[$domain][$l10n->lang]) && $_this->_cacheEnabled === true) {
            $_this->_domains[$domain][$l10n->lang] = Cache::read($cacheKey, '__nata_core__');
        }

        if (!isset($_this->_domains[$domain][$l10n->lang][$category]['_reversei18n'])) {
            $_this->_bindTextDomain($l10n, $domain, $category);
            Cache::write($cacheKey, $_this->_domains[$domain][$l10n->lang], '__nata_core__');
        }

        $messages = $_this->_domains[$domain][$l10n->lang][$category]['_reversei18n'] ?? [];
        if (isset($messages['msgctxt_' . $context])) {
            $messages = $messages['msgctxt_' . $context];
        }

        if (!isset($messages[$singular])) {
            return $singular;
        }

        return $messages[$singular];
    }

/**
 * Adds missing message to .po file.
 *
 * @param \Nata\I18n\L10n $l10n Language instance
 * @param string $domain Domain to bind
 * @param string $singular Singular message
 * @param string $plural Plural message
 * @param string $context Context
 * @return void
 */
    protected function _appendMissingMessage(L10n $l10n, string $domain, string $category, string $singular, ?string $plural, ?string $context): void {
        if (!Configure::read('debug')) {
            return;
        }

        [$file, $line] = $this->_getCodeOccurrence();
        $line = sprintf('## %s - %s:%s', $l10n->lang, $file, $line);
        $func = '';

        // Plural
        if ($plural) {
            $func = sprintf('__n("%s", "%s", 0, 0);', $singular, $plural);
            if ($context) {
                $func = sprintf('__nx("%s", "%s", "%s", 0, 0);', $context, $singular, $plural);
            }

            $array = [$singular, $plural];
            $dict[$plural] = $context ? ['_context' => [$context => $array]] : $array;
            $dict[$singular] = $context ? ['_context' => $singular] : $singular;
        // Singular
        } elseif ($singular) {
            $func = sprintf('__("%s");', $singular);
            if ($context) {
                $func = sprintf('__x("%s", "%s");', $context, $singular);
            }
            $dict[$singular] = $context ? ['_context' => $singular, 'msgstr' => $singular] : $singular;
        }

        $this->_domains[$domain][$l10n->lang][$category] = array_merge(
            $this->_domains[$domain][$l10n->lang][$category],
            $dict
        );

        Cache::write($domain . '_' . $l10n->lang, $this->_domains[$domain][$l10n->lang], '__nata_core__');

        if (!isset($this->_missingMessages[$domain])) {
            $this->_missingMessages[$domain] = [];
        }

        $this->_missingMessages[$domain][] = [
            'message' => $line . PHP_EOL . $func,
            'lang' => $l10n->lang,
        ];
    }

/**
 * Get the code occurrence.
 * This is used to add missing messages to the .po file.
 *
 * @todo Move to a utility class
 * @return array File and line number
 */
    protected function _getCodeOccurrence(): array {
        $backTrace = debug_backtrace();
        $runtimeReference = $backTrace[2];
        if (str_contains($runtimeReference['file'], 'basics.inc')) {
            $runtimeReference = $backTrace[3];
        }

        $file = str_replace('\\', '/', str_replace(ROOT, '', $runtimeReference['file']));
        return [$file, $runtimeReference['line']];
    }

/**
 * Get the L10n instance.
 * This is used to get the L10n instance for a given locale.
 *
 * @param string|L10n $locale Language/locale
 * @return L10n L10n instance
 */
    protected function _getL10n(string $locale): L10n {
        if ($locale instanceof L10n) {
            return $this->_l10n[$locale->code] = $locale;
        } elseif (!isset($this->_l10n[$locale])) {
            $this->_l10n[$locale] = new L10n($locale);
        }
        return $this->_l10n[$locale];
    }

/**
 * Format string according to pattern.
 * Accepts intl's \MessageFormatter or vprintf arguments.
 *
 * ...
 * // Locale as 'pt'
 * I18n::format('Hoje é {0, date, full}', [new \DateTime()]);
 * 'Hoje é domingo, 28 de fevereiro de 2016'
 * ...
 *
 * ...
 * I18n::format('%d monkeys', array(12)));
 * '12 monkeys'
 * ...
 *
 * @param string $pattern Pattern
 * @param mixed $args Format arguments
 * @return string Formatted message
 */
    public static function format(string $pattern, $args) {
        if (!is_array($args)) {
            $args = array_slice(func_get_args(), 1);
        }

        $formatter = static::getInstance()->formatter();
        if ($formatter && strpos($pattern, '{0') !== false) {
            $formatter->setPattern($pattern);
            return $formatter->format($args);
        }

        return vsprintf($pattern, $args);
    }

/**
 * Initialize MessageFormatter instance.
 *
 * @return \MessageFormatter Message formatter instance
 */
    public function formatter() {
        $locale = static::locale();
        if (!isset($this->_formatter[$locale])) {
            $formatter = false;
            if (class_exists('\MessageFormatter')) {
                $formatter = new MessageFormatter($locale, ' ');
            }
            $this->_formatter[$locale] = $formatter;
        }
        return $this->_formatter[$locale];
    }

/**
 * Clears the domains internal data array. Useful for testing i18n.
 *
 * @return void
 */
    public static function clear() {
        $self = I18n::getInstance();
        $self->_domains = [];
        $self->_available = [];
        $self->_locale = null;
        self::$_pluralGuessCache = [];
        self::$_searchPaths = [];
    }

/**
 * Get the loaded domains cache.
 *
 * @param string $domain Domain name
 * @return array
 */
    public static function domains($domain = null) {
        $self = I18n::getInstance();
        if ($domain === null) {
            return $self->_domains;
        }
        return (isset($self->_domains[$domain]) ? $self->_domains : null);
    }

/**
 * Attempts to find the plural form of a string.
 *
 * @param string $header Type
 * @param int $n Number
 * @return int plural match
 */
    protected function _pluralGuess($header, $n) {
        $cacheKey = $header . '_' . $n;
        if (isset(self::$_pluralGuessCache[$cacheKey])) {
            return self::$_pluralGuessCache[$cacheKey];
        }

        if (!is_string($header) || $header === "nplurals=1;plural=0;" || !isset($header[0])) {
            return self::$_pluralGuessCache[$cacheKey] = 0;
        }

        if ($header === "nplurals=2;plural=n!=1;") {
            return self::$_pluralGuessCache[$cacheKey] = $n != 1 ? 1 : 0;
        } elseif ($header === "nplurals=2;plural=n>1;") {
            return self::$_pluralGuessCache[$cacheKey] = $n > 1 ? 1 : 0;
        }

        if (strpos($header, "plurals=3")) {
            if (strpos($header, "100!=11")) {
                if (strpos($header, "10<=4")) {
                    return self::$_pluralGuessCache[$cacheKey] = $n % 10 == 1 && $n % 100 != 11 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
                } elseif (strpos($header, "100<10")) {
                    return self::$_pluralGuessCache[$cacheKey] = $n % 10 == 1 && $n % 100 != 11 ? 0 : ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
                }
                return self::$_pluralGuessCache[$cacheKey] = $n % 10 == 1 && $n % 100 != 11 ? 0 : ($n != 0 ? 1 : 2);
            } elseif (strpos($header, "n==2")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n == 2 ? 1 : 2);
            } elseif (strpos($header, "n==0")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n == 0 || ($n % 100 > 0 && $n % 100 < 20) ? 1 : 2);
            } elseif (strpos($header, "n>=2")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n >= 2 && $n <= 4 ? 1 : 2);
            } elseif (strpos($header, "10>=2")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
            }
            return self::$_pluralGuessCache[$cacheKey] = $n % 10 == 1 ? 0 : ($n % 10 == 2 ? 1 : 2);
        } elseif (strpos($header, "plurals=4")) {
            if (strpos($header, "100==2")) {
                return self::$_pluralGuessCache[$cacheKey] = $n % 100 == 1 ? 0 : ($n % 100 == 2 ? 1 : ($n % 100 == 3 || $n % 100 == 4 ? 2 : 3));
            } elseif (strpos($header, "n>=3")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n == 2 ? 1 : ($n == 0 || ($n >= 3 && $n <= 10) ? 2 : 3));
            } elseif (strpos($header, "100>=1")) {
                return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n == 0 || ($n % 100 >= 1 && $n % 100 <= 10) ? 1 : ($n % 100 >= 11 && $n % 100 <= 20 ? 2 : 3));
            }
        } elseif (strpos($header, "plurals=5")) {
            return self::$_pluralGuessCache[$cacheKey] = $n == 1 ? 0 : ($n == 2 ? 1 : ($n >= 3 && $n <= 6 ? 2 : ($n >= 7 && $n <= 10 ? 3 : 4)));
        }
        return self::$_pluralGuessCache[$cacheKey] = 0;
    }

/**
 * Loads the locale definition file and returns array of translations
 *
 * @param string $locale Locale to load
 * @param string $domain Domain to load
 * @param string $category Category to load
 * @return array Array of translations
 */
    public static function loadDomains(string $locale = null, string $domain = null, string $category = self::LC_MESSAGES) {
        if ($locale === null) {
            $locale = static::locale();
        }

        if ($domain === null) {
            $domain = static::$defaultDomain;
        }

        $_this = static::getInstance();
        $l10n = $_this->_getL10n($locale);

        $_this->_bindTextDomain($l10n, $domain, $category);
        return $_this->_domains[$domain][$l10n->lang][$category];
    }

/**
 * Binds the given domain to a file in the specified directory.
 *
 * @param \Nata\I18n\L10n $l10n Language instance
 * @param string $domain Domain to bind
 * @param string $category Category
 * @return string Domain binded
 */
    protected function _bindTextDomain(L10n $l10n, string $domain, string $category): string {
        foreach (static::_searchPaths($domain) as $directory) {
            foreach ($l10n->languagePath as $lang) {
                $localeDef = $directory . $lang . DS . $category;
                if (is_file($localeDef)) {
                    $definitions = static::loadLocaleDefinition($localeDef);
                    if ($definitions !== false) {
                        $this->_domains[$domain][$l10n->lang][$category] = $definitions;
                        $this->_noLocale = false;
                        return $domain;
                    }
                }

                $file = $directory . $lang;
                if (is_dir($file . DS . $category)) {
                    $file .= DS . $category;
                }
                $file .= DS . $domain;
                $translations = $this->_loadTranslations($file);
                if ($translations !== null) {
                    $this->_domains[$domain][$l10n->lang][$category] = $translations;
                    $this->_noLocale = false;
                    break 2;
                }
            }
        }

        if (empty($this->_domains[$domain][$l10n->lang][$category])) {
            $this->_domains[$domain][$l10n->lang][$category] = [];
            return $domain;
        }

        if (isset($this->_domains[$domain][$l10n->lang][$category][""])) {
            $head = $this->_domains[$domain][$l10n->lang][$category][""];

            foreach (explode("\n", $head) as $line) {
                $header = strtok($line, ':');
                $line = trim(strtok("\n"));
                $this->_domains[$domain][$l10n->lang][$category]["%po-header"][strtolower($header)] = $line;
            }

            if (isset($this->_domains[$domain][$l10n->lang][$category]["%po-header"]["plural-forms"])) {
                $switch = preg_replace("/(?:[() {}\\[\\]^\\s*\\]]+)/", "", $this->_domains[$domain][$l10n->lang][$category]["%po-header"]["plural-forms"]);
                $this->_domains[$domain][$l10n->lang][$category]["%plural-c"] = $switch;
            }

            // Set last revision date
            if (isset($this->_domains[$domain][$l10n->lang][$category]["%po-header"]["po-revision-date"])) {
                $this->_domains[$domain][$l10n->lang][$category]["%po-revision-date"] = $this->_domains[$domain][$l10n->lang][$category]["%po-header"]["po-revision-date"];
                unset($this->_domains[$domain][$l10n->lang][$category]["%po-header"]);
            }

            if (isset($this->_domains[$domain][$l10n->lang][$category][null])) {
                unset($this->_domains[$domain][$l10n->lang][$category][null]);
            }
        }

        return $domain;
    }

/**
 * Search paths for the locale definition file.
 *
 * @param string $domain Domain to search for
 * @return array Search paths
 */
    protected function _searchPaths(string $domain): array {
        if (!isset(static::$_searchPaths[$domain])) {
            $paths = [];
            $pluginLocalePath = null;
            $plugins = Plugin::loaded();
            if (!empty($plugins)) {
                foreach ($plugins as $plugin) {
                    $pluginDomain = Inflector::underscore($plugin);
                    if ($pluginDomain === $domain) {
                        $pluginLocalePath = Plugin::path($plugin) . 'resources' . DS . 'locales' . DS;
                        break;
                    }
                }
            }
            if ($pluginLocalePath !== null) {
                $paths[] = $pluginLocalePath;
            }
            foreach (static::_localeSearchRoots() as $root) {
                $paths[] = $root;
            }
            static::$_searchPaths[$domain] = $paths;
        }
        return static::$_searchPaths[$domain];
    }

/**
 * Locale directories for the application (Configure `I18n.localeRoots` + `src/Locale`).
 *
 * @return list<string> Absolute paths with trailing DS
 */
    private static function _localeSearchRoots(): array {
        $roots = [];
        $configured = Configure::read('I18n.localeRoots');
        if (!is_array($configured)) {
            $configured = [];
        }
        foreach ($configured as $root) {
            $normalized = App::ds(rtrim((string)$root, '/\\'));
            if ($normalized !== '' && is_dir($normalized)) {
                $roots[] = $normalized . DS;
            }
        }
        $appLocale = App::path('Locale/');
        $appBase = rtrim($appLocale, DS);
        if ($appBase !== '' && is_dir($appBase)) {
            $roots[] = App::ds($appBase) . DS;
        }

        return array_values(array_unique($roots));
    }

/**
 * Loads the binary .mo file and returns array of translations
 *
 * @param string $filename Binary .mo file to load
 * @return mixed Array of translations on success or false on failure
 */
    protected function _loadTranslations($file) {
        $filename = $file . '.mo';
        $translations = null;
        $class = null;
        if (is_file($filename)) {
            $class = 'MoFile';
        } elseif (is_file($file . '.po')) {
            $class = 'PoFile';
            $filename = $file . '.po';
        }
        $className = App::className($class, 'I18n/Parser');
        if ($className) {
            $translations = (new $className($filename))->getMessages();
            $translations['__lmf__'] = filemtime($filename);
        }
        return $translations;
    }

/**
 * Parses a locale definition file following the POSIX standard.
 *
 * @param string $filename Locale definition filename
 * @return mixed Array of definitions on success or false on failure
 */
    public static function loadLocaleDefinition($filename) {
        if (!$file = fopen($filename, 'r')) {
            return false;
        }

        $definitions = [];
        $comment = '#';
        $escape = '\\';
        $currentToken = false;
        $value = '';
        $_this = I18n::getInstance();
        while ($line = fgets($file)) {
            $line = trim($line);
            if (empty($line) || $line[0] === $comment) {
                continue;
            }
            $parts = preg_split("/[[:space:]]+/", $line);
            if ($parts[0] === 'comment_char') {
                $comment = $parts[1];
                continue;
            }
            if ($parts[0] === 'escape_char') {
                $escape = $parts[1];
                continue;
            }
            $count = count($parts);
            if ($count === 2) {
                $currentToken = $parts[0];
                $value = $parts[1];
            } elseif ($count === 1) {
                $value = is_array($value) ? $parts[0] : $value . $parts[0];
            } else {
                continue;
            }

            $len = strlen($value) - 1;
            if ($value[$len] === $escape) {
                $value = substr($value, 0, $len);
                continue;
            }

            $mustEscape = array($escape . ',', $escape . ';', $escape . '<', $escape . '>', $escape . $escape);
            $replacements = array_map('crc32', $mustEscape);
            $value = str_replace($mustEscape, $replacements, $value);
            $value = explode(';', $value);
            $_this->_escape = $escape;
            foreach ($value as $i => $val) {
                $val = trim($val, '"');
                $val = preg_replace_callback('/(?:<)?(.[^>]*)(?:>)?/', array(&$_this, '_parseLiteralValue'), $val);
                $val = str_replace($replacements, $mustEscape, $val);
                $value[$i] = $val;
            }
            if (count($value) === 1) {
                $definitions[$currentToken] = array_pop($value);
            } else {
                $definitions[$currentToken] = $value;
            }
        }

        return $definitions;
    }

/**
 * Auxiliary function to parse a symbol from a locale definition file.
 *
 * @param string $string Symbol to be parsed
 * @return string parsed symbol
 */
    protected function _parseLiteralValue($string) {
        $string = $string[1];
        if (substr($string, 0, 2) === $this->_escape . 'x') {
            $delimiter = $this->_escape . 'x';
            return implode('', array_map('chr', array_map('hexdec', array_filter(explode($delimiter, $string)))));
        }
        if (substr($string, 0, 2) === $this->_escape . 'd') {
            $delimiter = $this->_escape . 'd';
            return implode('', array_map('chr', array_filter(explode($delimiter, $string))));
        }
        if ($string[0] === $this->_escape && isset($string[1]) && is_numeric($string[1])) {
            $delimiter = $this->_escape;
            return implode('', array_map('chr', array_filter(explode($delimiter, $string))));
        }
        if (substr($string, 0, 3) === 'U00') {
            $delimiter = 'U00';
            return implode('', array_map('chr', array_map('hexdec', array_filter(explode($delimiter, $string)))));
        }
        if (preg_match('/U([0-9a-fA-F]{4})/', $string, $match)) {
            return Text::ascii([hexdec($match[1])]);
        }
        return $string;
    }

/**
 * Constructor.
 *
 * Use I18n::getInstance() to get the i18n translation object.
 */
    public function __destruct() {
        $this->_saveMissingMessages();
    }

/**
 * Returns a Time format definition from corresponding domain
 *
 * @param string $format Format to be translated
 * @param string $domain Domain where format is stored
 * @return mixed translated format string if only value or array of translated strings for corresponding format.
 */
    protected function _saveMissingMessages() {
        $roots = static::_localeSearchRoots();
        $directory = !empty($roots) ? rtrim($roots[0], DS) : rtrim(App::path('Locale/'), DS);
        foreach ($this->_missingMessages as $domain => $functions) {
            $file = $directory . DS . $domain . '.c';
            $poFile = new File($file);

            $messages = '';
            foreach ($functions as $function) {
                $messages .= PHP_EOL . $function['message'];
            }

            $poFile->append($messages);
            $poFile->close();
        }
        $this->_missingMessages = [];
    }

/**
 * Enable or disable cache.
 *
 * @param bool|null $enabled Enable or disable cache
 * @return bool Cache enabled
 */
    public static function cacheEnabled(bool $enabled = null): bool {
        $_this = static::getInstance();
        if ($enabled === null) {
            return $_this->_cacheEnabled;
        }
        return $_this->_cacheEnabled = $enabled;
    }

/**
 * Returns the language code
 *
 * @return string Language code
 */
    public function __toString(): string {
        return $this->_locale ?? '';
    }

}