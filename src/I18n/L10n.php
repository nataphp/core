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

namespace Nata\I18n;

use Exception;
use Locale;
use Nata\I18n\Exception\LocaleException;
use Nata\I18n\Locale as NataLocale;

/**
 * Localization.
 */
class L10n {

/**
 * The language for current locale.
 *
 * @var string
 */
    public readonly string|null $language;

/**
 * The language for local name.
 *
 * @var string
 */
    public readonly string|null $localLanguage;

/**
 * Locale search paths.
 *
 * @var array
 */
    public readonly array|null $languagePath;

/**
 * ISO 639-3 for current locale.
 *
 * @var string
 */
    public readonly string|null $lang;

/**
 * Language code.
 *
 * @var string
 */
    public readonly string|null $code;

/**
 * Locale.
 *
 * @var string
 */
    public readonly string $locale;

/**
 * Encoding used for current locale.
 *
 * @var string
 */
    public readonly string|null $charset;

/**
 * Text direction for current locale.
 *
 * @var string
 */
    public readonly string $direction;

/**
 * Maps ISO 639-3 to I10n::_l10nCatalog
 * The terminological codes (first one per language) should be used if possible.
 * They are the ones building locale folder names under `resources/locales/[code]/`, `src/Locale/[code]/`, etc.
 * The bibliographic codes are aliases.
 *
 * @var array
 */
    protected $_l10nMap = [
        /* Afrikaans */ 'afr' => 'af',
        /* Albanian */ 'sqi' => 'sq',
        /* Albanian - bibliographic */ 'alb' => 'sq',
        /* Arabic */ 'ara' => 'ar',
        /* Armenian/Armenia */ 'hye' => 'hy',
        /* Basque */ 'eus' => 'eu',
        /* Basque */ 'baq' => 'eu',
        /* Tibetan */ 'bod' => 'bo',
        /* Tibetan - bibliographic */ 'tib' => 'bo',
        /* Bosnian */ 'bos' => 'bs',
        /* Bulgarian */ 'bul' => 'bg',
        /* Byelorussian */ 'bel' => 'be',
        /* Catalan */ 'cat' => 'ca',
        /* Chinese */ 'zho' => 'zh',
        /* Chinese - bibliographic */ 'chi' => 'zh',
        /* Croatian */ 'hrv' => 'hr',
        /* Czech */ 'ces' => 'cs',
        /* Czech - bibliographic */ 'cze' => 'cs',
        /* Danish */ 'dan' => 'da',
        /* Dutch (Standard) */ 'nld' => 'nl',
        /* Dutch (Standard) - bibliographic */ 'dut' => 'nl',
        /* English */ 'eng' => 'en',
        /* Estonian */ 'est' => 'et',
        /* Faeroese */ 'fao' => 'fo',
        /* Farsi/Persian */ 'fas' => 'fa',
        /* Farsi/Persian - bibliographic */ 'per' => 'fa',
        /* Finnish */ 'fin' => 'fi',
        /* French (Standard) */ 'fra' => 'fr',
        /* French (Standard)  - bibliographic */ 'fre' => 'fr',
        /* Gaelic (Scots) */ 'gla' => 'gd',
        /* Galician */ 'glg' => 'gl',
        /* German (Standard) */ 'deu' => 'de',
        /* German (Standard) - bibliographic */ 'ger' => 'de',
        /* Greek */ 'gre' => 'el',
        /* Greek */ 'ell' => 'el',
        /* Hebrew */ 'heb' => 'he',
        /* Hindi */ 'hin' => 'hi',
        /* Hungarian */ 'hun' => 'hu',
        /* Icelandic */ 'isl' => 'is',
        /* Icelandic - bibliographic */ 'ice' => 'is',
        /* Indonesian */ 'ind' => 'id',
        /* Irish */ 'gle' => 'ga',
        /* Italian */ 'ita' => 'it',
        /* Japanese */ 'jpn' => 'ja',
        /* Kazakh */ 'kaz' => 'kk',
        /* Kalaallisut (Greenlandic) */ 'kal' => 'kl',
        /* Korean */ 'kor' => 'ko',
        /* Latvian */ 'lav' => 'lv',
        /* Limburgish */ 'lim' => 'li',
        /* Lithuanian */ 'lit' => 'lt',
        /* Macedonian */ 'mkd' => 'mk',
        /* Macedonian - bibliographic */ 'mac' => 'mk',
        /* Malaysian */ 'msa' => 'ms',
        /* Malaysian - bibliographic */ 'may' => 'ms',
        /* Maltese */ 'mlt' => 'mt',
        /* Norwegian */ 'nor' => 'no',
        /* Norwegian Bokmal */ 'nob' => 'nb',
        /* Norwegian Nynorsk */ 'nno' => 'nn',
        /* Polish */ 'pol' => 'pl',
        /* Portuguese (Portugal) */ 'por' => 'pt',
        /* Rhaeto-Romanic */ 'roh' => 'rm',
        /* Romanian */ 'ron' => 'ro',
        /* Romanian - bibliographic */ 'rum' => 'ro',
        /* Russian */ 'rus' => 'ru',
        /* Sami */ 'sme' => 'se',
        /* Serbian */ 'srp' => 'sr',
        /* Slovak */ 'slk' => 'sk',
        /* Slovak - bibliographic */ 'slo' => 'sk',
        /* Slovenian */ 'slv' => 'sl',
        /* Sorbian */ 'wen' => 'sb',
        /* Spanish (Spain - Traditional) */ 'spa' => 'es',
        /* Swedish */ 'swe' => 'sv',
        /* Thai */ 'tha' => 'th',
        /* Tsonga */ 'tso' => 'ts',
        /* Tswana */ 'tsn' => 'tn',
        /* Turkish */ 'tur' => 'tr',
        /* Ukrainian */ 'ukr' => 'uk',
        /* Urdu */ 'urd' => 'ur',
        /* Venda */ 'ven' => 've',
        /* Vietnamese */ 'vie' => 'vi',
        /* Welsh */ 'cym' => 'cy',
        /* Welsh - bibliographic */ 'wel' => 'cy',
        /* Xhosa */ 'xho' => 'xh',
        /* Yiddish */ 'yid' => 'yi',
        /* Zulu */ 'zul' => 'zu'
    ];

/**
 * HTTP_ACCEPT_LANGUAGE catalog.
 *
 * Holds all information related to a language.
 *
 * @var array
 */
    protected $_l10nCatalog = [
        'af' => ['locale' => 'afr', 'localeFallback' => 'afr', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ar' => ['locale' => 'ara', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-ae' => ['locale' => 'ar_ae', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-bh' => ['locale' => 'ar_bh', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-dz' => ['locale' => 'ar_dz', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-eg' => ['locale' => 'ar_eg', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-iq' => ['locale' => 'ar_iq', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-jo' => ['locale' => 'ar_jo', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-kw' => ['locale' => 'ar_kw', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-lb' => ['locale' => 'ar_lb', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-ly' => ['locale' => 'ar_ly', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-ma' => ['locale' => 'ar_ma', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-om' => ['locale' => 'ar_om', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-qa' => ['locale' => 'ar_qa', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-sa' => ['locale' => 'ar_sa', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-sy' => ['locale' => 'ar_sy', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-tn' => ['locale' => 'ar_tn', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'ar-ye' => ['locale' => 'ar_ye', 'localeFallback' => 'ara', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'be' => ['locale' => 'bel', 'localeFallback' => 'bel', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'bg' => ['locale' => 'bul', 'localeFallback' => 'bul', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'bo' => ['locale' => 'bod', 'localeFallback' => 'bod', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'bo-cn' => ['locale' => 'bo_cn', 'localeFallback' => 'bod', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'bo-in' => ['locale' => 'bo_in', 'localeFallback' => 'bod', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'bs' => ['locale' => 'bos', 'localeFallback' => 'bos', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ca' => ['locale' => 'cat', 'localeFallback' => 'cat', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'cs' => ['locale' => 'ces', 'localeFallback' => 'ces', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'da' => ['locale' => 'dan', 'localeFallback' => 'dan', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de' => ['locale' => 'deu', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de-at' => ['locale' => 'de_at', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de-ch' => ['locale' => 'de_ch', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de-de' => ['locale' => 'de_de', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de-li' => ['locale' => 'de_li', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'de-lu' => ['locale' => 'de_lu', 'localeFallback' => 'deu', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'el' => ['locale' => 'ell', 'localeFallback' => 'ell', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en' => ['locale' => 'eng', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-au' => ['locale' => 'en_au', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-bz' => ['locale' => 'en_bz', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-ca' => ['locale' => 'en_ca', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-gb' => ['locale' => 'en_gb', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-ie' => ['locale' => 'en_ie', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-jm' => ['locale' => 'en_jm', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-nz' => ['locale' => 'en_nz', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-tt' => ['locale' => 'en_tt', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-us' => ['locale' => 'en_us', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-za' => ['locale' => 'en_za', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'en-ae' => ['locale' => 'en_ae', 'localeFallback' => 'eng', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es' => ['locale' => 'spa', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-ar' => ['locale' => 'es_ar', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-bo' => ['locale' => 'es_bo', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-cl' => ['locale' => 'es_cl', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-co' => ['locale' => 'es_co', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-cr' => ['locale' => 'es_cr', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-do' => ['locale' => 'es_do', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-ec' => ['locale' => 'es_ec', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-es' => ['locale' => 'es_es', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-gt' => ['locale' => 'es_gt', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-hn' => ['locale' => 'es_hn', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-mx' => ['locale' => 'es_mx', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-ni' => ['locale' => 'es_ni', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-pa' => ['locale' => 'es_pa', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-pe' => ['locale' => 'es_pe', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-pr' => ['locale' => 'es_pr', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-py' => ['locale' => 'es_py', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-sv' => ['locale' => 'es_sv', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-uy' => ['locale' => 'es_uy', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'es-ve' => ['locale' => 'es_ve', 'localeFallback' => 'spa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'et' => ['locale' => 'est', 'localeFallback' => 'est', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'eu' => ['locale' => 'eus', 'localeFallback' => 'eus', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fa' => ['locale' => 'fas', 'localeFallback' => 'fas', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'fi' => ['locale' => 'fin', 'localeFallback' => 'fin', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fo' => ['locale' => 'fao', 'localeFallback' => 'fao', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr' => ['locale' => 'fra', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr-be' => ['locale' => 'fr_be', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr-ca' => ['locale' => 'fr_ca', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr-ch' => ['locale' => 'fr_ch', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr-fr' => ['locale' => 'fr_fr', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'fr-lu' => ['locale' => 'fr_lu', 'localeFallback' => 'fra', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ga' => ['locale' => 'gle', 'localeFallback' => 'gle', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'gd' => ['locale' => 'gla', 'localeFallback' => 'gla', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'gd-ie' => ['locale' => 'gd_ie', 'localeFallback' => 'gla', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'gl' => ['locale' => 'glg', 'localeFallback' => 'glg', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'he' => ['locale' => 'heb', 'localeFallback' => 'heb', 'charset' => 'utf-8', 'direction' => 'rtl'],
        'hi' => ['locale' => 'hin', 'localeFallback' => 'hin', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'hr' => ['locale' => 'hrv', 'localeFallback' => 'hrv', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'hu' => ['locale' => 'hun', 'localeFallback' => 'hun', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'hy' => ['locale' => 'hye', 'localeFallback' => 'hye', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'id' => ['locale' => 'ind', 'localeFallback' => 'ind', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'is' => ['locale' => 'isl', 'localeFallback' => 'isl', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'it' => ['locale' => 'ita', 'localeFallback' => 'ita', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'it-ch' => ['locale' => 'it_ch', 'localeFallback' => 'ita', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ja' => ['locale' => 'jpn', 'localeFallback' => 'jpn', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'kk' => ['locale' => 'kaz', 'localeFallback' => 'kaz', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'kl' => ['locale' => 'kal', 'localeFallback' => 'kal', 'charset' => 'kl', 'direction' => 'ltr'],
        'ko' => ['locale' => 'kor', 'localeFallback' => 'kor', 'charset' => 'kr', 'direction' => 'ltr'],
        'ko-kp' => ['locale' => 'ko_kp', 'localeFallback' => 'kor', 'charset' => 'kr', 'direction' => 'ltr'],
        'ko-kr' => ['locale' => 'ko_kr', 'localeFallback' => 'kor', 'charset' => 'kr', 'direction' => 'ltr'],
        'koi8-r' => ['locale' => 'koi8_r', 'localeFallback' => 'rus', 'charset' => 'koi8-r', 'direction' => 'ltr'],
        'li' => ['locale' => 'lim', 'localeFallback' => 'nld', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'lt' => ['locale' => 'lit', 'localeFallback' => 'lit', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'lv' => ['locale' => 'lav', 'localeFallback' => 'lav', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'mk' => ['locale' => 'mkd', 'localeFallback' => 'mkd', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'mk-mk' => ['locale' => 'mk_mk', 'localeFallback' => 'mkd', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ms' => ['locale' => 'msa', 'localeFallback' => 'msa', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'mt' => ['locale' => 'mlt', 'localeFallback' => 'mlt', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'nb' => ['locale' => 'nob', 'localeFallback' => 'nor', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'nl' => ['locale' => 'nld', 'localeFallback' => 'nld', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'nl-be' => ['locale' => 'nl_be', 'localeFallback' => 'nld', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'nn' => ['locale' => 'nno', 'localeFallback' => 'nor', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'no' => ['locale' => 'nor', 'localeFallback' => 'nor', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'pl' => ['locale' => 'pol', 'localeFallback' => 'pol', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'pt' => ['locale' => 'por', 'localeFallback' => 'pt-pt', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'pt-br' => ['locale' => 'pt_br', 'localeFallback' => 'por', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'rm' => ['locale' => 'roh', 'localeFallback' => 'roh', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ro' => ['locale' => 'ron', 'localeFallback' => 'ron', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ro-mo' => ['locale' => 'ro_mo', 'localeFallback' => 'ron', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ru' => ['locale' => 'rus', 'localeFallback' => 'rus', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ru-mo' => ['locale' => 'ru_mo', 'localeFallback' => 'rus', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sb' => ['locale' => 'wen', 'localeFallback' => 'wen', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sk' => ['locale' => 'slk', 'localeFallback' => 'slk', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sl' => ['locale' => 'slv', 'localeFallback' => 'slv', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sq' => ['locale' => 'sqi', 'localeFallback' => 'sqi', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sr' => ['locale' => 'srp', 'localeFallback' => 'srp', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sv' => ['locale' => 'swe', 'localeFallback' => 'swe', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'sv-fi' => ['locale' => 'sv_fi', 'localeFallback' => 'swe', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'se' => ['locale' => 'sme', 'localeFallback' => 'sme', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'th' => ['locale' => 'tha', 'localeFallback' => 'tha', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'tn' => ['locale' => 'tsn', 'localeFallback' => 'tsn', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'tr' => ['locale' => 'tur', 'localeFallback' => 'tur', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ts' => ['locale' => 'tso', 'localeFallback' => 'tso', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'uk' => ['locale' => 'ukr', 'localeFallback' => 'ukr', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'ur' => ['locale' => 'urd', 'localeFallback' => 'urd', 'charset' => 'utf-8', 'direction' => 'rtl'],
        've' => ['locale' => 'ven', 'localeFallback' => 'ven', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'vi' => ['locale' => 'vie', 'localeFallback' => 'vie', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'cy' => ['locale' => 'cym', 'localeFallback' => 'cym', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'xh' => ['locale' => 'xho', 'localeFallback' => 'xho', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'yi' => ['locale' => 'yid', 'localeFallback' => 'yid', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'zh' => ['locale' => 'zho', 'localeFallback' => 'zho', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'zh-cn' => ['locale' => 'zh_cn', 'localeFallback' => 'zho', 'charset' => 'GB2312', 'direction' => 'ltr'],
        'zh-hk' => ['locale' => 'zh_hk', 'localeFallback' => 'zho', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'zh-sg' => ['locale' => 'zh_sg', 'localeFallback' => 'zho', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'zh-tw' => ['locale' => 'zh_tw', 'localeFallback' => 'zho', 'charset' => 'utf-8', 'direction' => 'ltr'],
        'zu' => ['locale' => 'zul', 'localeFallback' => 'zul', 'charset' => 'utf-8', 'direction' => 'ltr']
    ];

/**
 * HTTP_ACCEPT_LANGUAGE catalog.
 *
 * Holds all information related to a language.
 *
 * @var array
 */
    public static $_localizedCatalog = [];


/**
 * Constructor.
 *
 * @param string $language Language
 * @return void
 */
    public function __construct(string $language = 'en') {
        if (empty($language)) {
            throw new Exception('Empty language code.');
        }
        $this->_setLanguage($language);
    }

/**
 * Sets the class vars to correct values for $language.
 * If $language is null it will use the L10n::$default if defined
 *
 * @param string $language Language (if null will use L10n::$default if defined)
 * @return mixed
 */
    protected function _setLanguage($language) {
        $locale = $this->toLocale($language);
        $language = $this->toLanguage($language);
        $catalog = $this->catalog($language);
        if (!$catalog || !isset($catalog['locale'])) {
            return $this->_setLanguage('en');
        }

        $this->locale = $locale;
        $this->language = $catalog['language'];
        $this->localLanguage = $catalog['localLanguage'];
        $this->languagePath = array_unique([
            $catalog['locale'],
            $catalog['localeFallback']
        ]);
        $this->lang = $language;
        $this->code = $catalog['code'];
        if (!$this->locale) {
            $this->locale = $catalog['locale'];
        }
        $this->charset = $catalog['charset'];
        $this->direction = $catalog['direction'];
    }

/**
 * Attempts to find locale for language.
 *
 * @param string $lang 2 letter language string
 * @return string locale,
 *    or given language when locale doesn't exist
 */
    public function toLocale($lang) {
        if (in_array($lang, $this->_l10nMap)) {
            return array_search($lang, $this->_l10nMap);
        }
        return $lang;
    }

/**
 * Attempts to find 2 letter language for given 3 letter locale.
 *
 * @param string $locale 3 letter locale string
 * @return string language,
 *    or given locale when locale doesn't exist
 */
    public function toLanguage($locale): string {
        $locale = str_replace('_', '-', $locale);
        if (isset($this->_l10nMap[$locale])) {
            return $this->_l10nMap[$locale];
        }
        if (isset($this->_l10nCatalog[$locale])) {
            return $locale;
        }
        return $locale;
    }

/**
 * Attempts to find locale for language, or language for locale.
 *
 * @param string|array $mixed 2/3 char string (language/locale), array of those strings, or null
 * @return string|array|bool string language/locale, array of those values, whole map as an array,
 *    or false when language/locale doesn't exist
 */
    public function isValid() {
        return $this->lang !== null;
    }

/**
 * Attempts to find locale for language, or language for locale.
 *
 * @param string|array $mixed 2/3 char string (language/locale), array of those strings, or null
 * @return string|array|bool string language/locale, array of those values, whole map as an array,
 *    or false when language/locale doesn't exist
 */
    public function map($mixed = null) {
        if (is_array($mixed)) {
            $result = [];
            foreach ($mixed as $_mixed) {
                if ($_result = $this->map($_mixed)) {
                    $result[$_mixed] = $_result;
                }
            }
            return $result;
        }
        if (is_string($mixed)) {
            if (strlen($mixed) === 2 && in_array($mixed, $this->_l10nMap)) {
                return array_search($mixed, $this->_l10nMap);
            }
            if (isset($this->_l10nMap[$mixed])) {
                return $this->_l10nMap[$mixed];
            }
            return false;
        }
        return $this->_l10nMap;
    }

/**
 * Attempts to find catalog record for requested language.
 *
 * @param string|array $language string requested language, array of requested languages, or null for whole catalog
 * @return array|bool array catalog record for requested language, array of catalog records, whole catalog,
 *    or false when language doesn't exist
 */
    public function catalog($language = null) {
        if (is_array($language)) {
            $result = [];
            foreach ($language as $_language) {
                if ($_result = $this->catalog($_language)) {
                    $result[$_language] = $_result;
                }
            }
            return $result;
        }

        $catalog = $this->_localizedCatalog($language);
        if (is_string($language)) {
            if (isset($catalog[$language])) {
                return $catalog[$language];
            }
            if (isset($this->_l10nMap[$language]) && isset($catalog[$this->_l10nMap[$language]])) {
                return $catalog[$this->_l10nMap[$language]];
            }
            return null;
        }
        return $catalog;
    }

/**
 * Attempts to find the locale settings based on the HTTP_ACCEPT_LANGUAGE variable.
 *
 * @return bool Success
 */
    protected function _localizedCatalog($locale) {
        if (isset(static::$_localizedCatalog[$locale])) {
            return static::$_localizedCatalog[$locale];
        }

        foreach ($this->_l10nCatalog as $langCode => $langInfo) {
            $this->_localizeCatalogItem($langInfo, $locale);
            static::$_localizedCatalog[$locale][$langCode] = $langInfo;
        }

        return static::$_localizedCatalog[$locale];
    }

/**
 * Attempts to find the locale settings based on the HTTP_ACCEPT_LANGUAGE variable.
 *
 * @return bool Success
 */
    protected function _localizeCatalogItem(&$langInfo, $language) {
        $langInfo += [
            'language' => $this->_translateLanguageName($langInfo['locale'], $language),
            'localLanguage' => $this->_translateLanguageName($langInfo['locale'], $langInfo['locale']),
            'code' => $this->toLanguage($langInfo['locale'])
        ];
    }

/**
 * Translate the language name to selected language.
 *
 * @param string $catalog Catalog to translate language name
 * @return array Catalog with language name translated
 */
    protected function _translateLanguageName(string $locale, string $lang) {
        if (class_exists('\Locale')) {
            return ucwords(\Locale::getDisplayName($locale, $lang));
        }
        // Fallback when intl extension is not available - use our namespace Locale class
        return ucwords(NataLocale::getDisplayName($locale, $lang));
    }

/**
 * __set.
 *
 * @param string $name Property
 * @param mixed $language string requested language, array of requested languages, or null for whole catalog
 * @return array|bool array catalog record for requested language, array of catalog records, whole catalog,
 *    or false when language doesn't exist
 */
    public function __set(string $name, $v) {
        throw new LocaleException(sprintf('Cannot modify readonly property L10n::$%s', $name));
    }

/**
 * toArray.
 *
 * @return array
 */
    public function toArray(): array {
        return [
            'language' => $this->language,
            'localLanguage' => $this->localLanguage,
            'language' => $this->language,
            'languagePath' => $this->languagePath,
            'lang' => $this->lang,
            'code' => $this->code,
            'locale' => $this->locale,
            'charset' => $this->charset,
            'direction' => $this->direction,
        ];
    }

/**
 * __set.
 *
 * @param string $name Property
 * @param mixed $language string requested language, array of requested languages, or null for whole catalog
 * @return array|bool array catalog record for requested language, array of catalog records, whole catalog,
 *    or false when language doesn't exist
 */
    public function __debugInfo(): array {
        return $this->toArray();
    }

/**
 * __toString.
 *
 * @return string
 */
    public function __toString(): string {
        return $this->lang ?? '';
    }

}
