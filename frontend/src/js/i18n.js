/**
 * NataI18n — Internationalization module.
 *
 * Reads from the global `i18n` object injected by the server (Smarty/PHP).
 * Supports namespaced structure: i18n[language][domain][category]
 * Falls back to old flat structure: i18n[domain][category]
 *
 * If the language dictionary is not yet loaded, fetches /i18n/{lang}.js
 * synchronously on first use and caches failed attempts.
 */
export default class NataI18n {
    /**
     * Build base URL from <html data-base-url="">.
     *
     * @return {string}
     */
    _getBaseUrl() {
        let base = document.documentElement.dataset.baseUrl || '';
        if (base === '/') {
            base = '';
        }
        return base;
    }

    /**
     * Get the language file URL.
     *
     * @param {string} language
     * @return {string}
     */
    _getLanguageScriptUrl(language) {
        return this._getBaseUrl() + '/i18n/' + language + '.js';
    }

    /**
     * Ensure the language dictionary exists without blocking the main thread.
     *
     * Notes:
     * - `translate()` is synchronous; it will kick off a load and return the
     *   fallback string for that call.
     * - Use `preload()` / `translateAsync()` when you need deterministic results.
     *
     * @param {string} language
     * @return {Promise<void>}
     */
    ensureLanguageLoaded(language) {
        // Initialize failed load cache
        if (typeof document.nataI18nFailedLoads === 'undefined') {
            document.nataI18nFailedLoads = {};
        }

        // Initialize in-flight loads cache
        if (typeof document.nataI18nLoadPromises === 'undefined') {
            document.nataI18nLoadPromises = {};
        }

        if (typeof i18n !== 'undefined' && typeof i18n[language] !== 'undefined') {
            return Promise.resolve();
        }

        if (document.nataI18nFailedLoads[language]) {
            return Promise.reject(new Error('NataI18n: previous load failed for ' + language));
        }

        if (document.nataI18nLoadPromises[language]) {
            return document.nataI18nLoadPromises[language];
        }

        const scriptUrl = this._getLanguageScriptUrl(language);

        document.nataI18nLoadPromises[language] = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = scriptUrl;
            script.async = true;
            script.defer = true;

            script.onload = () => {
                if (typeof i18n === 'undefined' || typeof i18n[language] === 'undefined') {
                    document.nataI18nFailedLoads[language] = true;
                    reject(new Error('NataI18n: loaded script but i18n[' + language + '] is missing'));
                    return;
                }
                resolve();
            };

            script.onerror = (e) => {
                document.nataI18nFailedLoads[language] = true;
                reject(new Error('NataI18n: failed to load ' + scriptUrl));
            };

            document.head.appendChild(script);
        });

        return document.nataI18nLoadPromises[language];
    }

    /**
     * Preload language dictionary (alias of ensureLanguageLoaded).
     *
     * @param {string} [language]
     * @return {Promise<void>}
     */
    preload(language) {
        if (!language) {
            language = this.getLanguage();
        }
        return this.ensureLanguageLoaded(language);
    }

    /**
     * Async translate that waits for the language file when needed.
     *
     * @return {Promise<string>}
     */
    async translateAsync(singular, plural, context, domain, category, count, language) {
        if (!language) {
            language = this.getLanguage();
        }

        if (typeof i18n === 'undefined' || typeof i18n[language] === 'undefined') {
            try {
                await this.ensureLanguageLoaded(language);
            } catch (e) {
                // ignore; translate() will fall back to the original strings
            }
        }

        return this.translate(singular, plural, context, domain, category, count, language);
    }

    /**
     * Get the current locale from the <html lang=""> attribute.
     *
     * @return {string}
     */
    locale() {
        return document.documentElement.lang;
    }

    /**
     * Alias for locale().
     *
     * @return {string}
     */
    getLanguage() {
        return this.locale();
    }

    /**
     * Translate a string.
     *
     * @param {string} singular
     * @param {string|null} plural
     * @param {string|null} context
     * @param {string|null} domain
     * @param {string|null} category
     * @param {number|null} count
     * @param {string|null} language
     * @return {string}
     */
    translate(singular, plural, context, domain, category, count, language) {
        let translation = singular;

        if (!domain) {
            domain = 'default';
        }

        if (!category) {
            category = 'LC_MESSAGES';
        }

        if (count === null || count === undefined) {
            count = null;
        }

        if (!plural || typeof count !== 'number') {
            plural = null;
        }

        if (!language) {
            language = this.getLanguage();
        }

        // Kick off an async load if the language dictionary is missing.
        // translate() remains synchronous, so we return the fallback string for now.
        if (typeof i18n === 'undefined' || typeof i18n[language] === 'undefined') {
            this.ensureLanguageLoaded(language).catch(() => {});
            return translation;
        }

        if (typeof i18n === 'undefined') {
            return translation;
        }

        // Namespaced structure: i18n[language][domain][category]
        // Fallback to flat structure: i18n[domain][category]
        const i18nData = i18n[language] || i18n;

        if (typeof i18nData[domain] === 'undefined' || typeof i18nData[domain][category] === 'undefined') {
            return translation;
        }

        let messages = i18nData[domain][category];

        // Apply context
        if (context && typeof messages['msgctxt_' + context] !== 'undefined') {
            messages = messages['msgctxt_' + context];
        }

        // Plural translation
        if (plural !== null && typeof count === 'number') {
            if (typeof messages[plural] !== 'undefined') {
                translation = messages[plural];
            } else if (typeof messages[singular] !== 'undefined') {
                translation = messages[singular];
            } else {
                return count === 1 ? singular : plural;
            }

            if (Array.isArray(translation)) {
                let pluralIndex = count === 1 ? 0 : 1;
                if (pluralIndex >= translation.length) {
                    pluralIndex = translation.length - 1;
                }
                translation = translation[pluralIndex];
            }
        } else {
            // Singular translation
            if (typeof messages[singular] !== 'undefined') {
                translation = messages[singular];

                if (Array.isArray(translation)) {
                    translation = translation[0];
                }
            }
        }

        return translation;
    }
}
