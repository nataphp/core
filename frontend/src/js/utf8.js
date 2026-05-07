/**
 * UTF-8 encode/decode utilities.
 *
 * Based on Webtoolkit.info / Locutus implementations.
 */
const utf8 = {
    /**
     * Encode a string to UTF-8 bytes.
     *
     * @param {string} argString
     * @return {string}
     */
    encode(argString) {
        if (argString === null || typeof argString === 'undefined') {
            return '';
        }

        const string = (argString + '');
        let utftext = '';
        let start;
        let end;
        let stringl = 0;
        start = end = 0;
        stringl = string.length;

        for (let n = 0; n < stringl; n++) {
            let c1 = string.charCodeAt(n);
            let enc = null;

            if (c1 < 128) {
                end++;
            } else if (c1 > 127 && c1 < 2048) {
                enc = String.fromCharCode(
                    (c1 >> 6) | 192, (c1 & 63) | 128
                );
            } else if ((c1 & 0xF800) !== 0xD800) {
                enc = String.fromCharCode(
                    (c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
                );
            } else {
                if ((c1 & 0xFC00) !== 0xD800) {
                    throw new RangeError('Unmatched trail surrogate at ' + n);
                }
                const c2 = string.charCodeAt(++n);
                if ((c2 & 0xFC00) !== 0xDC00) {
                    throw new RangeError('Unmatched lead surrogate at ' + (n - 1));
                }
                c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
                enc = String.fromCharCode(
                    (c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
                );
            }

            if (enc !== null) {
                if (end > start) {
                    utftext += string.slice(start, end);
                }
                utftext += enc;
                start = end = n + 1;
            }
        }

        if (end > start) {
            utftext += string.slice(start, stringl);
        }

        return utftext;
    },

    /**
     * Decode UTF-8 bytes to a string.
     *
     * @param {string} strData
     * @return {string}
     */
    decode(strData) {
        const tmpArr = [];
        let i = 0;
        let c1 = 0;
        let seqlen = 0;
        strData += '';

        while (i < strData.length) {
            c1 = strData.charCodeAt(i) & 0xFF;
            seqlen = 0;

            if (c1 <= 0xBF) {
                c1 = (c1 & 0x7F);
                seqlen = 1;
            } else if (c1 <= 0xDF) {
                c1 = (c1 & 0x1F);
                seqlen = 2;
            } else if (c1 <= 0xEF) {
                c1 = (c1 & 0x0F);
                seqlen = 3;
            } else {
                c1 = (c1 & 0x07);
                seqlen = 4;
            }

            for (let ai = 1; ai < seqlen; ++ai) {
                c1 = ((c1 << 0x06) | (strData.charCodeAt(ai + i) & 0x3F));
            }

            if (seqlen === 4) {
                c1 -= 0x10000;
                tmpArr.push(String.fromCharCode(0xD800 | ((c1 >> 10) & 0x3FF)));
                tmpArr.push(String.fromCharCode(0xDC00 | (c1 & 0x3FF)));
            } else {
                tmpArr.push(String.fromCharCode(c1));
            }

            i += seqlen;
        }

        return tmpArr.join('');
    }
};

export default utf8;
