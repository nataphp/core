/**
 * Text utilities.
 */
const text = {
    /**
     * PHP nl2br equivalent — replace newlines with <br>.
     *
     * @param {string} string
     * @return {string}
     */
    nl2br(string) {
        return (string + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
    },

    /**
     * Replace variable placeholders in a string.
     *
     * Example:
     *   text.insert(':name is :age years old.', {name: 'Bob', age: 65});
     *   // → "Bob is 65 years old."
     *
     *   text.insert('{name} is {age} years old.', {name: 'Bob', age: 65});
     *   // → "Bob is 65 years old."
     *
     * @param {string} str
     * @param {object} data
     * @param {object} [options]
     * @return {string}
     */
    insert(str, data, options) {
        options = Object.assign({
            before: '{',
            after: '}',
            escape: '\\',
            format: null,
            clean: false
        }, options);

        let regex = '';

        if (options.before) {
            regex += '\\' + options.before;
        }

        regex += '([^';

        if (options.before) {
            regex += '\\' + options.before;
        }

        if (options.after) {
            regex += '\\' + options.after;
        }

        regex += ']+)';

        if (options.after) {
            regex += '\\' + options.after;
        }

        return str.replace(new RegExp(regex, 'g'), (_unused, varName) => data[varName]);
    },

    /**
     * Copy text to clipboard.
     *
     * @param {string} textContent
     * @return {Promise}
     */
    clipboard(textContent) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(textContent).then(() => {
                console.log('Async: Copying to clipboard was successful!');
            }, (error) => {
                console.error('Async: Could not copy text: ', error);
            });
        }

        const textArea = document.createElement('textarea');
        textArea.value = textContent;
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.position = 'fixed';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        let successful = false;
        let copyError = null;

        try {
            successful = document.execCommand('copy');
            console.log('Fallback: Copying text command was ' + (successful ? 'successful' : 'unsuccessful'));
        } catch (e) {
            copyError = e;
            console.error('Fallback: Oops, unable to copy text.', e);
        }

        document.body.removeChild(textArea);

        return new Promise((resolve, reject) => {
            resolve(textContent);
            reject(copyError, textContent);
        });
    }
};

export default text;
