/**
 * NataI18n — Internationalization module.
 *
 * Reads from the global `i18n` object injected by the server (Smarty/PHP).
 * Supports namespaced structure: i18n[language][domain][category]
 * Falls back to old flat structure: i18n[domain][category]
 *
 * The language is the page's <html lang>, as it is (e.g. "pt-PT"), but the
 * dictionaries are named after the catalogue code `generate_i18n_js` writes
 * them with (e.g. "pt"). So a language is looked for as itself, then its base
 * language ("pt-PT" → "pt", "en-GB" → "en"):
 *
 * - a dictionary already on the page (a <script src="/i18n/pt.js">) is used
 *   at once, with no request;
 * - otherwise /i18n/languages.json (the list `generate_i18n_js` writes next
 *   to the dictionaries) says which one exists, and only that one is
 *   requested: no 404 in the console;
 * - without that list (dictionaries generated before it existed) the
 *   regional file and then the base one are tried in turn.
 *
 * The dictionary found is also kept under the language asked for
 * (i18n['pt-PT'] = i18n['pt']). When none exists the page keeps the original
 * strings; nothing throws.
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
     * URL of the list of dictionaries written by `generate_i18n_js`.
     *
     * @return {string}
     */
    _getLanguagesUrl() {
        return this._getBaseUrl() + '/i18n/languages.json';
    }

    /**
     * The global dictionaries object (created when missing).
     *
     * @return {Object}
     */
    _dictionaries() {
        if (typeof globalThis.i18n === 'undefined') {
            globalThis.i18n = {};
        }
        return globalThis.i18n;
    }

    /**
     * The codes a language is looked for as, in order: as written, with "_"
     * read as "-", in lowercase, then its base language.
     *
     * @param {string} language e.g. "pt-PT", "pt_PT", "en".
     * @return {string[]} e.g. ["pt-PT", "pt-pt", "pt"].
     */
    candidates(language) {
        const written = String(language || '').trim();
        if (written === '') {
            return [];
        }
        const dashed = written.replace(/_/g, '-');
        const base = dashed.split('-')[0].toLowerCase();
        const list = [written, dashed, dashed.toLowerCase(), base];
        return list.filter((code, index) => code !== '' && list.indexOf(code) === index);
    }

    /**
     * The code under which a language's dictionary is already loaded.
     *
     * @param {string} language
     * @return {string|null} The candidate found, or null.
     */
    resolveLoaded(language) {
        if (typeof globalThis.i18n === 'undefined') {
            return null;
        }
        const found = this.candidates(language).find((code) => typeof globalThis.i18n[code] !== 'undefined');
        return found === undefined ? null : found;
    }

    /**
     * The dictionaries that exist (/i18n/languages.json), read once per page.
     *
     * @return {Promise<string[]|null>} Codes, or null when the list is missing.
     */
    _availableLanguages() {
        if (typeof document.nataI18nAvailable === 'undefined') {
            document.nataI18nAvailable = (typeof fetch === 'function'
                ? fetch(this._getLanguagesUrl(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then((response) => (response.ok ? response.json() : null))
                    .then((list) => (Array.isArray(list) ? list.map(String) : null))
                    .catch(() => null)
                : Promise.resolve(null));
        }
        return document.nataI18nAvailable;
    }

    /**
     * Load one dictionary file.
     *
     * @param {string} code Code of the file (e.g. "pt").
     * @return {Promise<boolean>} Whether it loaded and defined i18n[code].
     */
    _loadScript(code) {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = this._getLanguageScriptUrl(code);
            script.async = true;
            script.defer = true;
            script.onload = () => resolve(typeof globalThis.i18n !== 'undefined' && typeof globalThis.i18n[code] !== 'undefined');
            script.onerror = () => resolve(false);
            document.head.appendChild(script);
        });
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
     * @return {Promise<void>} Rejected when no dictionary exists for it.
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

        const loaded = this.resolveLoaded(language);
        if (loaded !== null) {
            this._alias(language, loaded);
            return Promise.resolve();
        }

        if (document.nataI18nFailedLoads[language]) {
            return Promise.reject(new Error('NataI18n: no dictionary for ' + language));
        }

        if (document.nataI18nLoadPromises[language]) {
            return document.nataI18nLoadPromises[language];
        }

        document.nataI18nLoadPromises[language] = this._availableLanguages().then(async (available) => {
            let codes = this.candidates(language);
            if (available !== null) {
                // Only what exists, spelled as the list spells it.
                const byLowercase = {};
                available.forEach((code) => { byLowercase[code.toLowerCase()] = code; });
                codes = codes.map((code) => byLowercase[code.toLowerCase()]).filter((code, index, list) => code !== undefined && list.indexOf(code) === index);
            }
            for (const code of codes) {
                const already = this.resolveLoaded(code);
                if (already !== null || await this._loadScript(code)) {
                    this._alias(language, already !== null ? already : code);
                    return;
                }
            }
            document.nataI18nFailedLoads[language] = true;
            throw new Error('NataI18n: no dictionary for ' + language);
        });

        return document.nataI18nLoadPromises[language];
    }

    /**
     * Keep a dictionary also under the language asked for.
     *
     * @param {string} language Language asked for.
     * @param {string} code Code it was found under.
     * @return {void}
     */
    _alias(language, code) {
        const dictionaries = this._dictionaries();
        if (language && typeof dictionaries[language] === 'undefined') {
            dictionaries[language] = dictionaries[code];
        }
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

        if (this.resolveLoaded(language) === null) {
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

        // The dictionary of the language or of its base language (pt-PT → pt).
        // When neither is loaded, kick off an async load: translate() remains
        // synchronous, so it returns the original string for now.
        const loaded = this.resolveLoaded(language);
        if (loaded === null) {
            this.ensureLanguageLoaded(language).catch(() => {});
            // The original strings, the plural one when the count asks for it.
            return plural !== null && count !== 1 ? plural : translation;
        }

        // Namespaced structure: i18n[language][domain][category]
        // Fallback to flat structure: i18n[domain][category]
        const i18nData = globalThis.i18n[loaded] || globalThis.i18n;

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
