/**
 * Cookie, session (sessionStorage) and localStorage utilities.
 */
import time from './time.js';

const cookieDefaults = { path: '/' };

/**
 * Get or set a cookie.
 *
 * @param {string} key
 * @param {*} [value]
 * @param {object} [options]
 * @return {string|object|null}
 */
function cookie(key, value, options) {
    const encode = s => encodeURIComponent(s);
    const decode = s => decodeURIComponent(s);

    const parseCookieValue = s => {
        if (s.indexOf('"') === 0) {
            s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
        }
        try {
            s = decodeURIComponent(s.replace(/\+/g, ' '));
            if (s.indexOf('{') === 0) {
                return JSON.parse(s);
            }
            return s;
        } catch (e) {
            return undefined;
        }
    };

    // Write
    if (value !== undefined) {
        options = Object.assign({}, cookieDefaults, options);

        if (typeof options.expires === 'number') {
            const t = new Date();
            t.setTime(+t + options.expires * 864e+5);
            options.expires = t;
        }

        if (typeof value === 'object') {
            value = JSON.stringify(value);
        }

        return (document.cookie = [
            encode(key), '=', encode(String(value)),
            options.expires ? '; expires=' + options.expires.toUTCString() : '',
            options.path ? '; path=' + options.path : '',
            options.domain ? '; domain=' + options.domain : '',
            options.secure || options.sameSite === 'none' ? '; secure' : '',
            '; samesite=' + (options.sameSite || 'strict')
        ].join(''));
    }

    // Read
    let result = key ? undefined : {};
    const cookies = document.cookie ? document.cookie.split('; ') : [];

    for (const cookieStr of cookies) {
        const eqPos = cookieStr.indexOf('=');
        const name = decode(cookieStr.slice(0, eqPos));
        const val = cookieStr.slice(eqPos + 1);

        if (key && key === name) {
            return parseCookieValue(val);
        }

        if (!key) {
            const parsed = parseCookieValue(val);
            if (parsed !== undefined) {
                result[name] = parsed;
            }
        }
    }

    return result;
}

/**
 * Remove a cookie.
 *
 * @param {string} key
 * @param {object} [options]
 * @return {boolean|null}
 */
function removeCookie(key, options) {
    if (cookie(key) === undefined) {
        return null;
    }
    cookie(key, '', Object.assign({}, options, { expires: -1 }));
    return cookie(key) === undefined;
}

/**
 * Simple sessionStorage interface with optional TTL.
 */
const session = {
    /**
     * Write a value to sessionStorage with an optional TTL.
     *
     * @param {string} key
     * @param {*}      value
     * @param {number} [lifetime]  TTL in seconds; omit for no expiry
     * @return {void|null}  null when sessionStorage is unavailable
     */
    write(key, value, lifetime) {
        if (!this.isSupported()) {
            return null;
        }
        const object = {
            value,
            timestamp: time.unixtime(),
            lifetime: lifetime ? lifetime : false
        };
        return sessionStorage.setItem(key, JSON.stringify(object));
    },

    /**
     * Read a value from sessionStorage, respecting its TTL.
     * Returns null when the key is missing, expired, or sessionStorage is unavailable.
     *
     * @param {string} key
     * @return {*|null}
     */
    read(key) {
        if (!this.isSupported()) {
            return null;
        }
        const data = sessionStorage.getItem(key);
        if (data) {
            const object = JSON.parse(data);
            if (object.lifetime) {
                const diff = time.unixtime() - object.timestamp;
                if (object.lifetime > diff) {
                    return object.value;
                }
                this.delete(key);
                return null;
            }
            return object.value;
        }
        return null;
    },

    /**
     * Remove an entry from sessionStorage.
     *
     * @param {string} key
     * @return {void|null}
     */
    delete(key) {
        if (!this.isSupported()) {
            return null;
        }
        return sessionStorage.removeItem(key);
    },

    /**
     * Clear all entries from sessionStorage.
     *
     * @return {boolean|false}
     */
    clear() {
        if (!this.isSupported()) {
            return false;
        }
        return sessionStorage.clear();
    },

    /**
     * Return true when sessionStorage is accessible in the current browser context.
     *
     * @return {boolean}
     */
    isSupported() {
        try {
            return 'sessionStorage' in window && window['sessionStorage'] !== null;
        } catch (e) {
            return false;
        }
    }
};

/**
 * Simple localStorage interface with optional TTL and garbage collection.
 */
const storage = {
    /**
     * Writes a value to localStorage with an optional lifetime.
     *
     * @param {string} key
     * @param {*} value
     * @param {number} [lifetime]
     * @return {string|null}
     */
    write(key, value, lifetime) {
        if (!this.isSupported()) {
            return null;
        }
        const object = {
            value,
            timestamp: time.unixtime(),
            lifetime: lifetime ? lifetime : false
        };
        this.gc();
        return localStorage.setItem(key, JSON.stringify(object));
    },

    /**
     * Reads a value from localStorage with an optional lifetime.
     *
     * @param {string} key
     * @return {string|null}
     */
    read(key) {
        if (!this.isSupported()) {
            return null;
        }
        const data = localStorage.getItem(key);
        if (data) {
            if (data.indexOf('{') !== 0 && data.indexOf('[') !== 0) {
                return data;
            }

            const object = JSON.parse(data);
            if (object.lifetime) {
                const diff = time.unixtime() - object.timestamp;
                if (object.lifetime > diff) {
                    return object.value;
                }
                this.delete(key);
                return null;
            }
            return object.value;
        }
        return null;
    },

    /**
     * Removes a value from localStorage.
     *
     * @param {string} key
     * @return {boolean|null}
     */
    delete(key) {
        if (!this.isSupported()) {
            return null;
        }
        return localStorage.removeItem(key);
    },

    /**
     * Clears all values from localStorage.
     *
     * @return {boolean|null}
     */
    clear() {
        if (!this.isSupported()) {
            return false;
        }
        return localStorage.clear();
    },

    /**
     * Garbage collects expired values from localStorage.
     *
     * @return {void}
     */
    gc() {
        const keys = [];
        for (let i = 0; i < localStorage.length; i++) {
            keys.push(localStorage.key(i));
        }

        for (const key of keys) {
            const data = localStorage.getItem(key);

            if (typeof data !== 'string' || data.indexOf('{') !== 0) {
                continue;
            }

            const object = JSON.parse(data);
            if (typeof object !== 'object' || !object.lifetime) {
                continue;
            }

            const diff = time.unixtime() - object.timestamp;
            if (object.lifetime > diff) {
                continue;
            }

            localStorage.removeItem(key);
        }
    },

    /**
     * Checks if localStorage is supported.
     *
     * @return {boolean}
     */
    isSupported() {
        try {
            return 'localStorage' in window && window['localStorage'] !== null;
        } catch (e) {
            return false;
        }
    }
};

export { cookie, removeCookie, session, storage };
