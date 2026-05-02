<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
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

// Fallback when intl extension is not available
class Locale {

/**
 * Get display name of locale.
 *
 * @param string $locale Locale code
 * @param string $inLocale Locale code to use for display name
 * @return string Display name of locale
 */
    public static function getDisplayName($locale, $inLocale = null) {
        // Simple fallback: return locale code capitalized
        return ucfirst(str_replace('_', ' ', $locale));
    }

}
