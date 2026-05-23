/**
 * Datetime picker component — uses flatpickr served from /nata/vendor/flatpickr/.
 *
 * Lazily loads flatpickr JS + CSS on first use. On mobile with native date
 * support, normalizes the value to ISO format and skips flatpickr entirely.
 *
 * HTML: <input class="form-datetimepicker" data-format="Y-m-d H:i" ... />
 */

const datetime = {
    /**
     * Initialize flatpickr on all uninitialized .form-datetimepicker inputs.
     */
    init() {
        if (_browserNativeDateSupport()) {
            _normalizeNativeDates();
            return;
        }

        const inputs = document.querySelectorAll('input.form-datetimepicker:not(.set)');
        if (inputs.length === 0) {
            return;
        }

        _loadFlatpickr(() => {
            const flatpickr = window.flatpickr;
            const locale = _locale();

            _loadLocale(locale, flatpickr, () => {
                inputs.forEach(input => {
                    const data = input.dataset;
                    const fmt = data.format || 'Y-m-d H:i';
                    const fmtLower = fmt.toLowerCase();
                    const hasTime = fmtLower.includes('h') || fmtLower.includes('i');
                    const hasDate = fmtLower.includes('y') || fmtLower.includes('m') || fmtLower.includes('d');
                    const is24hr = fmt.includes('H');

                    // Normalize stored value — replace T separator with space
                    input.value = (input.value || '').replace('T', ' ');

                    const useLocale = locale !== 'en' && flatpickr.l10ns[locale] ? { locale } : {};
                    const dialogAncestor = input.closest('dialog');
                    const fp = flatpickr(input, {
                        ...useLocale,
                        appendTo: dialogAncestor || document.body,
                        allowInput: true,
                        dateFormat: fmt,
                        enableTime: hasTime,
                        noCalendar: !hasDate,
                        time_24hr: is24hr,
                        closeOnSelect: true,
                        minDate: _dateElement(data.minDate) || '1900-01-01',
                        maxDate: _dateElement(data.maxDate) || undefined,
                        onChange(selectedDates, dateStr) {
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        },
                        onOpen() {
                            fp.set('minDate', _dateElement(data.minDate) || '1900-01-01');
                            fp.set('maxDate', _dateElement(data.maxDate) || undefined);
                        }
                    });

                    input.classList.add('set');
                    input._flatpickr = fp;

                    const trigger = input.previousElementSibling;
                    if (trigger) {
                        trigger.addEventListener('click', () => fp.open());
                    }
                });

                document.dispatchEvent(new CustomEvent('nata:form:datetime:init'));
            });
        });
    },

    /**
     * Destroy flatpickr on an element (needed when a group row is cloned).
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('input.form-datetimepicker.set').forEach(input => {
            if (input._flatpickr) {
                input._flatpickr.destroy();
                delete input._flatpickr;
            }
            input.classList.remove('set');
        });
    }
};

/**
 * Lazily load flatpickr JS and CSS from the vendor directory.
 * Calls cb immediately if flatpickr is already available.
 *
 * @param {function} cb  Called once flatpickr is ready
 */
function _loadFlatpickr(cb) {
    if (window.flatpickr) {
        cb();
        return;
    }

    const base = document.documentElement.dataset.baseUrl || '';
    const prefix = base === '/' ? '' : base;

    if (!document.querySelector('link[data-flatpickr-css]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.dataset.flatpickrCss = '';
        link.href = prefix + '/nata/vendor/flatpickr/flatpickr.min.css';
        document.head.appendChild(link);
    }

    const existing = document.querySelector('script[data-flatpickr-js]');
    if (existing) {
        existing.addEventListener('load', cb);
        return;
    }

    const script = document.createElement('script');
    script.dataset.flatpickrJs = '';
    script.src = prefix + '/nata/vendor/flatpickr/flatpickr.min.js';
    script.onload = cb;
    document.head.appendChild(script);
}

/**
 * Return the two-letter locale code from the <html lang> attribute (e.g. "pt" from "pt-BR").
 *
 * @return {string}
 */
function _locale() {
    const lang = document.documentElement.lang || 'en';
    return lang.split('-')[0];
}

/**
 * Resolve a minDate/maxDate value — if it starts with # or . treat it as a selector.
 */
function _dateElement(date) {
    if (!date) {
        return null;
    }
    if (date.startsWith('#') || date.startsWith('.')) {
        const el = document.querySelector(date);
        return el ? el.value || false : false;
    }
    return date;
}

/**
 * Detect whether the current browser is a mobile device with native <input type="date">
 * support. When true, flatpickr is skipped and native inputs are used instead.
 *
 * @return {boolean}
 */
function _browserNativeDateSupport() {
    const el = document.createElement('input');
    const testVal = 'not-a-date';
    el.setAttribute('type', 'date');
    el.setAttribute('value', testVal);
    const ua = navigator.userAgent;
    return el.value !== testVal && (
        /Android|webOS|iPhone|iPad|iPod|BlackBerry|Windows Phone/i.test(ua)
    );
}

/**
 * On mobile devices using native date inputs, normalize pre-filled date values
 * to the ISO 8601 format (YYYY-MM-DD) expected by <input type="date">.
 * Handles both ISO and local (DD/MM/YYYY or DD-MM-YYYY) input formats.
 */
function _normalizeNativeDates() {
    document.querySelectorAll('input[type="date"]').forEach(input => {
        const v = input.value || input.getAttribute('value');
        if (!v) {
            return;
        }
        const mIso = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        const mLocal = v.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);

        let y, mo, d;
        if (mIso) {
            [, y, mo, d] = mIso;
        } else if (mLocal) {
            [, d, mo, y] = mLocal;
        } else {
            return;
        }

        mo = String(mo).padStart(2, '0');
        d = String(d).padStart(2, '0');
        input.value = y + '-' + mo + '-' + d;
    });
}

/**
 * Inject a flatpickr locale script from the vendor directory and register it.
 * Calls cb immediately for English or if the locale is already loaded.
 * Falls back to English silently when the locale file is not found.
 *
 * @param {string}   locale     Two-letter locale code (e.g. "pt", "fr")
 * @param {object}   flatpickr  The flatpickr global
 * @param {function} cb         Called once the locale is ready
 */
function _loadLocale(locale, flatpickr, cb) {
    if (locale === 'en' || typeof flatpickr.l10ns[locale] !== 'undefined') {
        cb();
        return;
    }
    const base = document.documentElement.dataset.baseUrl || '';
    const src = (base === '/' ? '' : base) + '/nata/vendor/flatpickr/l10n/' + locale + '.js';
    const script = document.createElement('script');
    script.src = src;
    script.onload = cb;
    script.onerror = cb;
    document.head.appendChild(script);
}

export default datetime;
