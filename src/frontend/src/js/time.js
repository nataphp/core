/**
 * Time and Date utilities.
 */
const time = {
    /**
     * Get UNIX timestamp.
     *
     * @param {string} [t] Date/time string
     * @return {number}
     */
    unixtime(t) {
        const date = t ? new Date(t) : new Date();
        return Math.round(date.getTime() / 1000);
    },

    /**
     * Get the browser timezone.
     *
     * @return {string}
     */
    timezone() {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    },

    /**
     * Get locale date order as 3-char string: 'dmy', 'mdy', or 'ymd'.
     *
     * @param {string} [locale]
     * @return {string}
     */
    dateOrder(locale) {
        if (!locale) {
            locale = document.documentElement.lang;
        }
        return new Intl.DateTimeFormat(locale, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric'
        }).formatToParts(new Date(2001, 10, 12))
            .filter(p => p.type === 'year' || p.type === 'month' || p.type === 'day')
            .map(p => p.type[0])
            .join('');
    },

    /**
     * Parse a numeric date string (e.g. "12/31/2024", "31.12.2024").
     *
     * @param {string} date
     * @param {string} [locale]
     * @return {Date|null}
     */
    parseNumericDate(date, locale) {
        if (!locale) {
            locale = document.documentElement.lang;
        }
        const timeParts = date.trim().split(/[.\-\/]/);
        if (timeParts.length !== 3 || timeParts.some(x => !/^\d+$/.test(x))) {
            return null;
        }
        let [a, b, c] = timeParts.map(Number);
        let year, month, day;
        const order = time.dateOrder(locale);

        if (String(timeParts[0]).length === 4) {
            if (b > 12 && c <= 12) {
                year = a; day = b; month = c;
            } else {
                year = a; month = b; day = c;
            }
        } else if (order === 'mdy') {
            month = a; day = b; year = c;
        } else if (order === 'dmy') {
            day = a; month = b; year = c;
        } else if (order === 'ymd') {
            year = a; month = b; day = c;
        } else {
            return null;
        }

        const dt = new Date(Date.UTC(year, month - 1, day));
        const ok = dt.getUTCFullYear() === year && dt.getUTCMonth() === month - 1 && dt.getUTCDate() === day;
        return ok ? dt : null;
    },

    /**
     * Get the weekday name of a date.
     *
     * @param {string|Date} date
     * @param {string} [style='long']
     * @param {string} [locale]
     * @param {string} [timeZone]
     * @return {string|null}
     */
    weekdayOf(date, style = 'long', locale = undefined, timeZone = undefined) {
        if (!locale) {
            locale = document.documentElement.lang;
        }
        if (!style) {
            style = 'long';
        }
        if (!timeZone) {
            timeZone = time.timezone();
        }
        const datePattern = typeof date === 'string' ? time.parseNumericDate(date, locale) : date;
        if (!datePattern) {
            return null;
        }
        return new Intl.DateTimeFormat(locale, { weekday: style, timeZone }).format(datePattern);
    }
};

export default time;
