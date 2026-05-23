/**
 * Number formatting utilities.
 *
 * Note: `precision` and `toReadableSize` require `sprintf` and `__n` to be
 * injected at assembly time via `number._init(sprintf, __n)`.
 */
const number = {
    _sprintf: null,
    _n: null,

    /**
     * Inject dependencies (called from nata.js entry after all modules load).
     *
     * @param {Function} sprintf
     * @param {Function} __n
     */
    _init(sprintf, __n) {
        this._sprintf = sprintf;
        this._n = __n;
    },

    /**
     * Generate a random integer between min and max (inclusive).
     *
     * @param {number} min
     * @param {number} max
     * @return {number}
     */
    rand(min, max) {
        const argc = arguments.length;
        if (argc === 0) {
            min = 0;
            max = 2147483647;
        } else if (argc === 1) {
            throw new Error('Warning: rand() expects exactly 2 parameters, 1 given');
        }
        return Math.floor(Math.random() * (max - min + 1)) + min;
    },

    /**
     * Format a number (PHP number_format equivalent).
     *
     * @param {number} num
     * @param {number} [decimals=0]
     * @param {string} [decPoint='.']
     * @param {string} [thousandsSep=',']
     * @param {boolean} [removeTrailingZeros=false]
     * @return {string}
     */
    format(num, decimals, decPoint, thousandsSep, removeTrailingZeros) {
        num = (num + '').replace(/[^0-9+\-Ee.]/g, '');
        const n = !isFinite(+num) ? 0 : +num;
        const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
        const sep = typeof thousandsSep === 'undefined' ? ',' : thousandsSep;
        const dec = typeof decPoint === 'undefined' ? '.' : decPoint;

        const toFixedFix = (n, prec) => {
            if (('' + n).indexOf('e') === -1) {
                return +(Math.round(n + 'e+' + prec) + 'e-' + prec);
            } else {
                const arr = ('' + n).split('e');
                let sig = '';
                if (+arr[1] + prec > 0) {
                    sig = '+';
                }
                return (+(Math.round(+arr[0] + 'e' + sig + (+arr[1] + prec)) + 'e-' + prec)).toFixed(prec);
            }
        };

        let s = (prec ? toFixedFix(n, prec).toString() : '' + Math.round(n)).split('.');

        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }

        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }

        if (removeTrailingZeros && s[1]) {
            while (s[1].slice(-1) == '0') {
                s[1] = s[1].slice(0, -1);
            }
            if (s[1].length === 0) {
                s = [s[0]];
            }
        }

        return s.join(dec);
    },

    /**
     * Format a float with a given number of decimal places.
     *
     * @param {number} value
     * @param {number} [precision=3]
     * @return {string}
     */
    precision(value, precision) {
        precision = precision ?? 3;
        return this._sprintf('%01.' + precision + 'f', value);
    },

    /**
     * Format a file size in human-readable form (Bytes / KB / MB / GB / TB).
     *
     * @param {number} size Size in bytes
     * @return {string}
     */
    toReadableSize(size) {
        const __n = this._n;
        const self = this;
        switch (true) {
            case size < 1024:
                return __n('%d Byte', '%d Bytes', size, size);
            case Math.round(size / 1024) < 1024:
                return window.__('%s KB', self.precision(size / 1024, 0));
            case (size / 1024 / 1024) < 1024:
                return window.__('%s MB', self.precision(size / 1024 / 1024, 2));
            case (size / 1024 / 1024 / 1024) < 1024:
                return window.__('%s GB', self.precision(size / 1024 / 1024 / 1024, 2));
            default:
                return window.__('%s TB', self.precision(size / 1024 / 1024 / 1024 / 1024, 2));
        }
    }
};

export default number;
