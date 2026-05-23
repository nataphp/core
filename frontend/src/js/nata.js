/**
 * NataPHP Framework — nata.js
 *
 * jQuery-independent vanilla JS library.
 * Built via Rollup into an IIFE: public/nata/nata.js
 *
 * Exposes: window.nata, window.__(), __x(), __d(), __dx(), __n(), __xn(), __dn(), __dxn()
 */

import NataI18n from './i18n.js';
import time from './time.js';
import text from './text.js';
import number from './number.js';
import inflector from './inflector.js';
import utf8 from './utf8.js';
import hash from './hash.js';
import { router, url } from './router.js';
import { cookie, removeCookie, session, storage } from './storage.js';
import dom from './dom.js';
import dialog from './dialog.js';
import notifier from './notifier.js';

// ---------------------------------------------------------------------------
// sprintf — PHP-like string formatter
// ---------------------------------------------------------------------------

/**
 * PHP-like sprintf.
 *
 * @return {string}
 */
function sprintf() {
    if (arguments.length === 1 || typeof arguments[1] === 'undefined') {
        return arguments[0];
    }

    if (typeof arguments[1] === 'object') {
        const args = arguments[1];
        delete arguments[1];
        for (let i = 0; i < args.length; i++) {
            arguments[i + 1] = args[i];
        }
    }

    const regex = /%%|%(\d+\$)?([-+\'#0 ]*)(\*\d+\$|\*|\d+)?(\.(\*\d+\$|\*|\d+))?([scboxXuideEfFgG])/g;
    const a = arguments;
    let i = 0;
    const format = a[i++];

    const pad = (str, len, chr, leftJustify) => {
        if (!chr) { chr = ' '; }
        const padding = str.length >= len ? '' : new Array(1 + len - str.length >>> 0).join(chr);
        return leftJustify ? str + padding : padding + str;
    };

    const justify = (value, prefix, leftJustify, minWidth, zeroPad, customPadChar) => {
        const diff = minWidth - value.length;
        if (diff > 0) {
            if (leftJustify || !zeroPad) {
                value = pad(value, minWidth, customPadChar, leftJustify);
            } else {
                value = value.slice(0, prefix.length) + pad('', diff, '0', true) + value.slice(prefix.length);
            }
        }
        return value;
    };

    const formatBaseX = (value, base, prefix, leftJustify, minWidth, precision, zeroPad) => {
        const num = value >>> 0;
        prefix = prefix && num && { '2': '0b', '8': '0', '16': '0x' }[base] || '';
        value = prefix + pad(num.toString(base), precision || 0, '0', false);
        return justify(value, prefix, leftJustify, minWidth, zeroPad);
    };

    const formatString = (value, leftJustify, minWidth, precision, zeroPad, customPadChar) => {
        if (precision != null) { value = value.slice(0, precision); }
        return justify(value, '', leftJustify, minWidth, zeroPad, customPadChar);
    };

    const doFormat = (substring, valueIndex, flags, minWidth, _, precision, type) => {
        if (substring === '%%') { return '%'; }

        let leftJustify = false;
        let positivePrefix = '';
        let zeroPad = false;
        let prefixBaseX = false;
        let customPadChar = ' ';
        const flagsl = flags.length;

        for (let j = 0; flags && j < flagsl; j++) {
            switch (flags.charAt(j)) {
                case ' ': positivePrefix = ' '; break;
                case '+': positivePrefix = '+'; break;
                case '-': leftJustify = true; break;
                case "'": customPadChar = flags.charAt(j + 1); break;
                case '0': zeroPad = true; customPadChar = '0'; break;
                case '#': prefixBaseX = true; break;
            }
        }

        if (!minWidth) {
            minWidth = 0;
        } else if (minWidth === '*') {
            minWidth = +a[i++];
        } else if (minWidth.charAt(0) == '*') {
            minWidth = +a[minWidth.slice(1, -1)];
        } else {
            minWidth = +minWidth;
        }

        if (minWidth < 0) { minWidth = -minWidth; leftJustify = true; }

        if (!isFinite(minWidth)) {
            throw new Error('sprintf: (minimum-)width must be finite');
        }

        if (!precision) {
            precision = 'fFeE'.indexOf(type) > -1 ? 6 : (type === 'd') ? 0 : undefined;
        } else if (precision === '*') {
            precision = +a[i++];
        } else if (precision.charAt(0) == '*') {
            precision = +a[precision.slice(1, -1)];
        } else {
            precision = +precision;
        }

        let value = valueIndex ? a[valueIndex.slice(0, -1)] : a[i++];

        switch (type) {
            case 's': return formatString(String(value), leftJustify, minWidth, precision, zeroPad, customPadChar);
            case 'c': return formatString(String.fromCharCode(+value), leftJustify, minWidth, precision, zeroPad);
            case 'b': return formatBaseX(value, 2, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
            case 'o': return formatBaseX(value, 8, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
            case 'x': return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
            case 'X': return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad).toUpperCase();
            case 'u': return formatBaseX(value, 10, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
            case 'i':
            case 'd': {
                let num = +value || 0;
                num = Math.round(num - num % 1);
                const prefix = num < 0 ? '-' : positivePrefix;
                value = prefix + pad(String(Math.abs(num)), precision || 0, '0', false);
                return justify(value, prefix, leftJustify, minWidth, zeroPad);
            }
            case 'e':
            case 'E':
            case 'f':
            case 'F':
            case 'g':
            case 'G': {
                let num = +value;
                const prefix = num < 0 ? '-' : positivePrefix;
                const method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(type.toLowerCase())];
                const textTransform = ['toString', 'toUpperCase']['eEfFgG'.indexOf(type) % 2];
                value = prefix + Math.abs(num)[method](precision);
                return justify(value, prefix, leftJustify, minWidth, zeroPad)[textTransform]();
            }
            default: return substring;
        }
    };

    return format.replace(regex, doFormat);
}

// ---------------------------------------------------------------------------
// md5 — simple one-way hash (Locutus port)
// ---------------------------------------------------------------------------

/**
 * Compute the MD5 hash of a string (Locutus port).
 * Suitable for non-cryptographic use cases such as cache keys and change detection.
 *
 * @param {string} str
 * @return {string}  32-character lowercase hex string
 *
 * @example
 * md5('hello') // => '5d41402abc4b2a76b9719d911017c592'
 */
function md5(str) {
    let xl;

    const _rotateLeft = (lValue, iShiftBits) => (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));

    const _addUnsigned = (lX, lY) => {
        const lX8 = lX & 0x80000000, lY8 = lY & 0x80000000;
        const lX4 = lX & 0x40000000, lY4 = lY & 0x40000000;
        const lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) { return lResult ^ 0x80000000 ^ lX8 ^ lY8; }
        if (lX4 | lY4) {
            return lResult & 0x40000000
                ? lResult ^ 0xC0000000 ^ lX8 ^ lY8
                : lResult ^ 0x40000000 ^ lX8 ^ lY8;
        }
        return lResult ^ lX8 ^ lY8;
    };

    const _F = (x, y, z) => (x & y) | ((~x) & z);
    const _G = (x, y, z) => (x & z) | (y & (~z));
    const _H = (x, y, z) => x ^ y ^ z;
    const _I = (x, y, z) => y ^ (x | (~z));
    const _FF = (a, b, c, d, x, s, ac) => _addUnsigned(_rotateLeft(_addUnsigned(_addUnsigned(_addUnsigned(_F(b, c, d), x), ac), a), s), b);
    const _GG = (a, b, c, d, x, s, ac) => _addUnsigned(_rotateLeft(_addUnsigned(_addUnsigned(_addUnsigned(_G(b, c, d), x), ac), a), s), b);
    const _HH = (a, b, c, d, x, s, ac) => _addUnsigned(_rotateLeft(_addUnsigned(_addUnsigned(_addUnsigned(_H(b, c, d), x), ac), a), s), b);
    const _II = (a, b, c, d, x, s, ac) => _addUnsigned(_rotateLeft(_addUnsigned(_addUnsigned(_addUnsigned(_I(b, c, d), x), ac), a), s), b);

    const _convertToWordArray = str => {
        let lWordCount;
        const lMessageLength = str.length;
        const lNumberOfWordsTemp1 = lMessageLength + 8;
        const lNumberOfWordsTemp2 = (lNumberOfWordsTemp1 - (lNumberOfWordsTemp1 % 64)) / 64;
        const lNumberOfWords = (lNumberOfWordsTemp2 + 1) * 16;
        const lWordArray = Array(lNumberOfWords - 1);
        let lBytePosition = 0, lByteCount = 0;
        while (lByteCount < lMessageLength) {
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = (lWordArray[lWordCount] || 0) | (str.charCodeAt(lByteCount) << lBytePosition);
            lByteCount++;
        }
        lWordCount = (lByteCount - (lByteCount % 4)) / 4;
        lBytePosition = (lByteCount % 4) * 8;
        lWordArray[lWordCount] = (lWordArray[lWordCount] || 0) | (0x80 << lBytePosition);
        lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
        lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
        return lWordArray;
    };

    const _wordToHex = lValue => {
        let wordToHexValue = '', wordToHexValueTemp = '', lByte, lCount;
        for (lCount = 0; lCount <= 3; lCount++) {
            lByte = (lValue >>> (lCount * 8)) & 255;
            wordToHexValueTemp = '0' + lByte.toString(16);
            wordToHexValue += wordToHexValueTemp.substr(wordToHexValueTemp.length - 2, 2);
        }
        return wordToHexValue;
    };

    str = utf8.encode(str);
    const x = _convertToWordArray(str);
    let a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
    const S = [7,12,17,22, 5,9,14,20, 4,11,16,23, 6,10,15,21];
    xl = x.length;

    for (let k = 0; k < xl; k += 16) {
        const [AA, BB, CC, DD] = [a, b, c, d];
        a=_FF(a,b,c,d,x[k],S[0],0xD76AA478);d=_FF(d,a,b,c,x[k+1],S[1],0xE8C7B756);
        c=_FF(c,d,a,b,x[k+2],S[2],0x242070DB);b=_FF(b,c,d,a,x[k+3],S[3],0xC1BDCEEE);
        a=_FF(a,b,c,d,x[k+4],S[0],0xF57C0FAF);d=_FF(d,a,b,c,x[k+5],S[1],0x4787C62A);
        c=_FF(c,d,a,b,x[k+6],S[2],0xA8304613);b=_FF(b,c,d,a,x[k+7],S[3],0xFD469501);
        a=_FF(a,b,c,d,x[k+8],S[0],0x698098D8);d=_FF(d,a,b,c,x[k+9],S[1],0x8B44F7AF);
        c=_FF(c,d,a,b,x[k+10],S[2],0xFFFF5BB1);b=_FF(b,c,d,a,x[k+11],S[3],0x895CD7BE);
        a=_FF(a,b,c,d,x[k+12],S[0],0x6B901122);d=_FF(d,a,b,c,x[k+13],S[1],0xFD987193);
        c=_FF(c,d,a,b,x[k+14],S[2],0xA679438E);b=_FF(b,c,d,a,x[k+15],S[3],0x49B40821);
        a=_GG(a,b,c,d,x[k+1],S[4],0xF61E2562);d=_GG(d,a,b,c,x[k+6],S[5],0xC040B340);
        c=_GG(c,d,a,b,x[k+11],S[6],0x265E5A51);b=_GG(b,c,d,a,x[k],S[7],0xE9B6C7AA);
        a=_GG(a,b,c,d,x[k+5],S[4],0xD62F105D);d=_GG(d,a,b,c,x[k+10],S[5],0x2441453);
        c=_GG(c,d,a,b,x[k+15],S[6],0xD8A1E681);b=_GG(b,c,d,a,x[k+4],S[7],0xE7D3FBC8);
        a=_GG(a,b,c,d,x[k+9],S[4],0x21E1CDE6);d=_GG(d,a,b,c,x[k+14],S[5],0xC33707D6);
        c=_GG(c,d,a,b,x[k+3],S[6],0xF4D50D87);b=_GG(b,c,d,a,x[k+8],S[7],0x455A14ED);
        a=_GG(a,b,c,d,x[k+13],S[4],0xA9E3E905);d=_GG(d,a,b,c,x[k+2],S[5],0xFCEFA3F8);
        c=_GG(c,d,a,b,x[k+7],S[6],0x676F02D9);b=_GG(b,c,d,a,x[k+12],S[7],0x8D2A4C8A);
        a=_HH(a,b,c,d,x[k+5],S[8],0xFFFA3942);d=_HH(d,a,b,c,x[k+8],S[9],0x8771F681);
        c=_HH(c,d,a,b,x[k+11],S[10],0x6D9D6122);b=_HH(b,c,d,a,x[k+14],S[11],0xFDE5380C);
        a=_HH(a,b,c,d,x[k+1],S[8],0xA4BEEA44);d=_HH(d,a,b,c,x[k+4],S[9],0x4BDECFA9);
        c=_HH(c,d,a,b,x[k+7],S[10],0xF6BB4B60);b=_HH(b,c,d,a,x[k+10],S[11],0xBEBFBC70);
        a=_HH(a,b,c,d,x[k+13],S[8],0x289B7EC6);d=_HH(d,a,b,c,x[k],S[9],0xEAA127FA);
        c=_HH(c,d,a,b,x[k+3],S[10],0xD4EF3085);b=_HH(b,c,d,a,x[k+6],S[11],0x4881D05);
        a=_HH(a,b,c,d,x[k+9],S[8],0xD9D4D039);d=_HH(d,a,b,c,x[k+12],S[9],0xE6DB99E5);
        c=_HH(c,d,a,b,x[k+15],S[10],0x1FA27CF8);b=_HH(b,c,d,a,x[k+2],S[11],0xC4AC5665);
        a=_II(a,b,c,d,x[k],S[12],0xF4292244);d=_II(d,a,b,c,x[k+7],S[13],0x432AFF97);
        c=_II(c,d,a,b,x[k+14],S[14],0xAB9423A7);b=_II(b,c,d,a,x[k+5],S[15],0xFC93A039);
        a=_II(a,b,c,d,x[k+12],S[12],0x655B59C3);d=_II(d,a,b,c,x[k+3],S[13],0x8F0CCC92);
        c=_II(c,d,a,b,x[k+10],S[14],0xFFEFF47D);b=_II(b,c,d,a,x[k+1],S[15],0x85845DD1);
        a=_II(a,b,c,d,x[k+8],S[12],0x6FA87E4F);d=_II(d,a,b,c,x[k+15],S[13],0xFE2CE6E0);
        c=_II(c,d,a,b,x[k+6],S[14],0xA3014314);b=_II(b,c,d,a,x[k+13],S[15],0x4E0811A1);
        a=_II(a,b,c,d,x[k+4],S[12],0xF7537E82);d=_II(d,a,b,c,x[k+11],S[13],0xBD3AF235);
        c=_II(c,d,a,b,x[k+2],S[14],0x2AD7D2BB);b=_II(b,c,d,a,x[k+9],S[15],0xEB86D391);
        a=_addUnsigned(a,AA); b=_addUnsigned(b,BB); c=_addUnsigned(c,CC); d=_addUnsigned(d,DD);
    }

    return (_wordToHex(a) + _wordToHex(b) + _wordToHex(c) + _wordToHex(d)).toLowerCase();
}

/**
 * Return a short 10-character hash of a string (first 10 hex digits of its MD5).
 * Used internally for lightweight change-detection hashes.
 *
 * @param {string} str
 * @return {string}
 *
 * @example
 * hashCode('hello') // => '5d41402abc'
 */
function hashCode(str) {
    return md5(str).substring(0, 10);
}

// ---------------------------------------------------------------------------
// trim — trim a specific character from both ends of a string
// ---------------------------------------------------------------------------

/**
 * Trim a specific character from both ends of a string.
 * Defaults to trimming spaces when no character is given.
 *
 * @param {string} string
 * @param {string} [character=' ']
 * @return {string}
 *
 * @example
 * trim('/path/to/', '/')  // => 'path/to'
 */
function trim(string, character) {
    character = character ? character : ' ';
    while (string.charAt(0) == character) {
        string = string.substring(1);
    }
    while (string.charAt(string.length - 1) == character) {
        string = string.substring(0, string.length - 1);
    }
    return string;
}

/**
 * Parses a data-value attribute from a dataset.
 *
 * @param {string} value
 * @returns {boolean|null|number|string|object}
 */
function parseDataValue(value) {
    if (value === "true") {
        return true;
    }
    if (value === "false") {
        return false;
    }
    if (value === "null") {
        return null;
    }
    // Only convert to number if it doesn't change the string representation
    if (value === +value + "") {
        return +value;
    }
    // Try JSON parse (handles objects, arrays)
    try {
        return JSON.parse(value);
    } catch (e) {}

    return value;
}

/**
 * Parses the dataset of an element into an object.
 *
 * @param {HTMLElement} el
 * @returns {object}
 */
function parseDataset(el) {
    const result = {};
    for (const [key, value] of Object.entries(el.dataset)) {
        result[key] = parseDataValue(value);
    }
    return result;
}

// ---------------------------------------------------------------------------
// Assemble nata object
// ---------------------------------------------------------------------------

const i18n = new NataI18n();

const nata = {
    i18n,
    time,
    text,
    number,
    inflector,
    utf8,
    hash,
    router,
    url,
    cookie,
    removeCookie,
    session,
    storage,
    dom,
    dialog,
    sprintf,
    md5,
    hashCode,
    trim,
    parseDataset,
    notifier,

    /**
     * Redirect to a given URL.
     *
     * @param {string} url  Absolute or router-relative URL
     */
    redirect(url) {
        if (typeof url === 'string') {
            if (url.indexOf('http') === -1) {
                url = nata.router.url(url);
            }
            window.location.replace(url);
        }
    },

    /**
     * Display an array / map of alert objects via nata.notifier.
     * Skips alerts with handler === 'inline'.
     *
     * @param {object|Array} alerts
     * @param {Function}     [callback]
     */
    alerts(alerts, callback) {
        if (!alerts) { return; }
        if (alerts.alerts) { alerts = alerts.alerts; }
        if (alerts.type)   { alerts = { alert: alerts }; }

        const notify = (items) => {
            if (!items || items.length === 0) { return; }
            Object.values(items).forEach(alert => {
                if (alert.handler === 'inline') { return; }
                if (typeof alert === 'object' && typeof alert.id === 'undefined') {
                    notify(alert);
                } else {
                    nata.notifier(alert);
                }
            });
        };

        notify(alerts);

        if (callback) { callback(); }
    },

    /**
     * Process pending alerts stored in the natalerts cookie or sessionStorage.
     * Call once on DOMContentLoaded.
     */
    initAlerts() {
        const fromCookie = nata.cookie('natalerts');
        if (fromCookie) {
            nata.session.write('alerts', fromCookie);
            nata.removeCookie('natalerts', { path: '/' });
        }

        const pending = nata.session.read('alerts');
        if (pending) {
            nata.alerts(pending, () => {
                setTimeout(() => nata.session.delete('alerts'), 150);
            });
        }
    },

    /**
     * Intercept all XHR responses and act on Nata backend headers:
     *   nata-status   — HTTP status the backend intended (e.g. 302)
     *   nata-location — redirect URL
     *
     * JSON responses may also carry an `alerts` array and an `error` flag.
     * Call once on DOMContentLoaded.
     */
    initXhr() {
        const handleNataResponse = (redirectUrl, nataStatus, contentType, getBody) => {
            if (!contentType) {
                return;
            }

            if (contentType.indexOf('json') > -1) {
                let nataResponse;
                try {
                    nataResponse = JSON.parse(getBody());
                } catch (e) {}
                if (nataResponse) {
                    if (nataResponse.error) {
                        console.error(nataResponse.name, nataResponse.message, nataResponse.trace);
                    } else if (nataStatus > 300 && nataStatus < 400 && redirectUrl) {
                        nata.session.write('alerts', nataResponse.alerts);
                        nata.redirect(redirectUrl);
                    } else {
                        nata.alerts(nataResponse.alerts);
                    }
                }
            } else if (redirectUrl && nataStatus > 300 && nataStatus < 400) {
                nata.redirect(redirectUrl);
            }
        };

        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function () {
            this.addEventListener('load', function () {
                if (this.status === 503) { return; }
                handleNataResponse(
                    this.getResponseHeader('nata-location'),
                    +this.getResponseHeader('nata-status'),
                    this.getResponseHeader('content-type') || '',
                    () => this.responseText
                );
                document.dispatchEvent(new CustomEvent('nata:load'));
            });

            this.addEventListener('error', function () {
                console.error('XHR error:', this.status, this.statusText);
            });

            originalSend.apply(this, arguments);
        };

        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            const csrfToken = nata.cookie('csrfToken');
            if (csrfToken && (typeof args[0] === 'string' || args[0] instanceof URL)) {
                args[1] = args[1] || {};
                args[1].headers = Object.assign({ 'X-CSRF-Token': csrfToken }, args[1].headers);
            }
            return originalFetch.apply(this, args).then(response => {
                if (response.status === 503) { return response; }
                const redirectUrl = response.headers.get('nata-location');
                const nataStatus  = +response.headers.get('nata-status');
                const contentType = response.headers.get('content-type') || '';
                document.dispatchEvent(new CustomEvent('nata:load'));
                if (!redirectUrl && contentType.indexOf('json') === -1) { return response; }
                return response.clone().text().then(body => {
                    handleNataResponse(redirectUrl, nataStatus, contentType, () => body);
                    return response;
                });
            });
        };
    },

    // Convenience helpers
    /**
     * Execute a callback after a debounce delay, cancelling any previously scheduled call.
     *
     * @param {function} callback
     * @param {number}   ms  Delay in milliseconds
     * @return {number}  The setTimeout timer id
     *
     * @example
     * nata.delay(() => console.log('done'), 300);
     */
    delay(callback, ms) {
        let timer = 0;
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
        return timer;
    },

    /**
     * Return the "length" of a value: for arrays/strings uses `.length`,
     * for plain objects counts own enumerable keys.
     *
     * @param {object|Array|string} value
     * @return {number}
     *
     * @example
     * nata.length({ a: 1, b: 2 })  // => 2
     * nata.length([1, 2, 3])        // => 3
     */
    length(value) {
        if (typeof value === 'object') {
            let size = 0;
            for (const key in value) {
                if (Object.prototype.hasOwnProperty.call(value, key)) {
                    size++;
                }
            }
            return size;
        }
        return value.length;
    },

    /**
     * Return true when the device supports touch events.
     *
     * @return {boolean}
     */
    isTouchDevice() {
        return ('ontouchstart' in document.documentElement);
    }
};

// Inject sprintf and __n into number module after assembly
nata.number._init(
    sprintf,
    (singular, plural, count, args) => {
        const msg = nata.i18n.translate(singular, plural, null, null, null, count);
        return sprintf(msg, args);
    }
);

// ---------------------------------------------------------------------------
// Expose on window
// ---------------------------------------------------------------------------

window.nata = nata;

// Gettext globals — available synchronously before DOMContentLoaded

window.__ = function(singular, args) {
    return sprintf(i18n.translate(singular), args);
};

window.__x = function(context, singular, args) {
    return sprintf(i18n.translate(singular, undefined, context), args);
};

window.__d = function(domain, singular, args) {
    return sprintf(i18n.translate(singular, null, null, domain), args);
};

window.__dx = function(domain, context, singular, args) {
    return sprintf(i18n.translate(singular, null, context, domain), args);
};

window.__n = function(singular, plural, count, args) {
    const msg = i18n.translate(singular, plural, null, null, null, count);
    return sprintf(msg, args);
};

window.__xn = function(context, singular, plural, count, args) {
    const msg = i18n.translate(singular, plural, context, null, null, count);
    return sprintf(msg, args);
};

window.__dn = function(domain, singular, plural, count, args) {
    const msg = i18n.translate(singular, plural, null, domain, null, count);
    return sprintf(msg, args);
};

window.__dxn = function(domain, context, singular, plural, count, args) {
    const msg = i18n.translate(singular, plural, context, domain, null, count);
    return sprintf(msg, args);
};

// ---------------------------------------------------------------------------
// XHR intercept + pending-alert flush
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    nata.initXhr();
    nata.initAlerts();
    document.addEventListener('htmx:afterSettle', () => {
        document.dispatchEvent(new CustomEvent('nata:load'));
    });
});
