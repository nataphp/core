/**
 * Form submission — replaces $.nata.ajax() + $.fn.serializeArray().
 *
 * Uses fetch() for AJAX and FormData for serialization.
 * Handles the full server JSON response: validation, redirect, clear, disable.
 */
import validation from './validation.js';

const submit = {
    /**
     * Bind submit events to all form.nata elements.
     */
    init() {
        document.querySelectorAll('form.nata').forEach(form => {
            if (form.dataset.submitBound) {
                return;
            }
            form.dataset.submitBound = '1';

            // Track which submit button was clicked
            form.querySelectorAll('button[type="submit"]').forEach(btn => {
                btn.addEventListener('click', function () {
                    form.querySelectorAll('button[type="submit"]').forEach(b => b.classList.remove('nata-form-submitted'));
                    this.classList.add('nata-form-submitted');
                });
            });
            // Buttons outside the form (form="id")
            if (form.id) {
                document.querySelectorAll('button[type="submit"][form="' + form.id + '"]').forEach(btn => {
                    btn.addEventListener('click', function () {
                        form.querySelectorAll('button[type="submit"]').forEach(b => b.classList.remove('nata-form-submitted'));
                        this.classList.add('nata-form-submitted');
                    });
                });
            }

            form.addEventListener('submit', function (event) {
                if (this.classList.contains('form-ajax')) {
                    event.preventDefault();
                    submit.send(this);
                } else {
                    // Non-AJAX: just show loading state on submit button
                    const btn = _getSubmitButton(this);
                    btn.forEach(b => _setLoading(b));
                }
            });
        });
    },

    /**
     * Send the form via fetch() and process the JSON response.
     *
     * @param {HTMLFormElement} form
     * @param {object} [options]
     */
    send(form, options) {
        options = options || {};

        const allSubmitBtns = _getSubmitButton(form);
        const submittedBtn = form.querySelector('button[type="submit"].nata-form-submitted')
            || (form.id && document.querySelector('button[type="submit"][form="' + form.id + '"].nata-form-submitted'));
        const disabledClass = 'disabled';

        // Abort if already disabled
        if (allSubmitBtns.some(b => b.classList.contains(disabledClass))) {
            return;
        }

        // Disable all buttons; show loading on the one that was clicked
        allSubmitBtns.forEach(btn => {
            if (btn !== submittedBtn) {
                btn.classList.add(disabledClass);
                btn.setAttribute('disabled', 'disabled');
            }
        });
        if (submittedBtn) {
            _setLoading(submittedBtn);
        }

        form.classList.add('processing');
        form.dispatchEvent(new CustomEvent('nata:form:before:submit', { bubbles: true, detail: { options } }));

        const url = options.url || form.getAttribute('action');
        const method = (options.method || form.getAttribute('method') || 'POST').toUpperCase();
        const contentType = form.dataset.contentType || 'json';

        // Build request body
        let body;
        let headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        const formData = new FormData(form);

        // Append submitted button value if it has a name
        if (submittedBtn && submittedBtn.getAttribute('name') && submittedBtn.getAttribute('value')) {
            formData.append(submittedBtn.getAttribute('name'), submittedBtn.getAttribute('value'));
        }

        if (contentType === 'json') {
            body = JSON.stringify(_formDataToJson(formData));
            headers['Content-Type'] = 'application/json';
        } else {
            body = new URLSearchParams(formData).toString();
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        fetch(url, {method, headers, body})
            .then(response => {
                if (!response.ok && response.status === 0) {
                    console.warn('Nata form: empty response from server');
                    return null;
                }
                return response.text().then(text => {
                    if (!text || text.length === 0) {
                        console.warn('Nata form: empty response from server');
                        return null;
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.warn('Nata form: non-JSON response', text);
                        return null;
                    }
                });
            })
            .then(data => {
                if (!data) {
                    return;
                }

                // Redirect takes priority — don't process validation
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }

                form.classList.remove('processing');
                form.dispatchEvent(new CustomEvent('nata:form:after:submit', { bubbles: true, detail: { data } }));

                if (!data.form) {
                    console.warn('Nata form: form object missing from server response');
                    return;
                }

                // Update CSRF/timestamp hidden field
                if (data.form.isValid && data.form.timestamp) {
                    const tsField = form.querySelector('input[type="hidden"][name$="[___timestamp]"]');
                    if (tsField) {
                        tsField.value = data.form.timestamp;
                    }
                }

                // Clear confirmPageLeave state if valid
                if (data.form.isValid && window.nata && window.nata.dom && window.nata.dom.confirmPageLeave) {
                    window.nata.dom.confirmPageLeave(false);
                }

                // Run validation display
                validation.check(form, data.form);

                // Scroll to first error
                const firstError = form.querySelector('.has-error, .text-error');
                if (firstError && window.nata && window.nata.dom && window.nata.dom.scrollTo) {
                    window.nata.dom.scrollTo({ target: firstError });
                } else if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                // Clear form if server says so
                if (data.form.clear) {
                    form.reset();
                    form.querySelectorAll('.dz-preview').forEach(el => el.remove());
                }

                // Disable form if server says so
                if (data.form.disable) {
                    allSubmitBtns.forEach(b => b.classList.add(disabledClass));
                    form.querySelectorAll('input, select, textarea').forEach(el => {
                        el.setAttribute('disabled', 'disabled');
                    });
                }
            })
            .catch(err => {
                console.error('Nata form submit error:', err);
            })
            .finally(() => {
                // Restore button states
                if (submittedBtn) {
                    _resetLoading(submittedBtn);
                    submittedBtn.classList.remove('nata-form-submitted');
                }
                allSubmitBtns.forEach(btn => {
                    if (btn !== submittedBtn) {
                        btn.classList.remove(disabledClass);
                        btn.removeAttribute('disabled');
                    }
                });
            });
    }
};

/**
 * Get all submit buttons associated with a form.
 *
 * @param {HTMLFormElement} form
 * @return {HTMLElement[]}
 */
function _getSubmitButton(form) {
    const btns = Array.from(form.querySelectorAll('button[type="submit"]'));
    if (form.id) {
        document.querySelectorAll('button[type="submit"][form="' + form.id + '"]').forEach(btn => {
            if (!btns.includes(btn)) {
                btns.push(btn);
            }
        });
    }
    return btns;
}

/**
 * Set a button to loading state.
 * Stores original HTML so it can be restored later.
 *
 * @param {HTMLElement} btn
 */
function _setLoading(btn) {
    if (btn.dataset.originalHtml !== undefined) {
        return; // already loading
    }
    btn.dataset.originalHtml = btn.innerHTML;
    const loadingText = btn.dataset.loadingText
        || '<span class="loading loading-spinner loading-xs"></span> ' + (window.__ ? window.__('Please wait...') : 'Please wait...');
    btn.innerHTML = loadingText;
    btn.setAttribute('disabled', 'disabled');
}

/**
 * Restore a button from loading state.
 *
 * @param {HTMLElement} btn
 */
function _resetLoading(btn) {
    if (btn.dataset.originalHtml !== undefined) {
        btn.innerHTML = btn.dataset.originalHtml;
        delete btn.dataset.originalHtml;
    }
    btn.removeAttribute('disabled');
}

/**
 * Convert FormData to nested object using bracket notation keys.
 * Example:
 *   admin_venue[address][town][id] => { admin_venue: { address: { town: { id: ... } } } }
 *
 * @param {FormData} formData
 * @return {object}
 */
function _formDataToJson(formData) {
    const obj = {};

    formData.forEach((value, key) => {
        _appendNestedValue(obj, _parseFormKey(key), value);
    });

    return obj;
}

/**
 * Parse form field names that use bracket notation.
 * Example:
 *   "admin_venue[address][town][id]" => ["admin_venue", "address", "town", "id"]
 *   "tags[]" => ["tags", ""]
 *
 * @param {string} key
 * @return {string[]}
 */
function _parseFormKey(key) {
    const tokens = [];
    const pattern = /([^[\]]+)|\[(.*?)\]/g;
    let match = pattern.exec(key);

    while (match) {
        tokens.push(match[1] !== undefined ? match[1] : match[2]);
        match = pattern.exec(key);
    }

    return tokens;
}

/**
 * Append a value into an object using parsed form key tokens.
 *
 * @param {object} target
 * @param {string[]} tokens
 * @param {*} value
 */
function _appendNestedValue(target, tokens, value) {
    if (!tokens || tokens.length === 0) {
        return;
    }

    let cursor = target;

    for (let i = 0; i < tokens.length; i++) {
        const token = tokens[i];
        const isLast = i === tokens.length - 1;
        const nextToken = tokens[i + 1];

        if (token === '') {
            if (!Array.isArray(cursor)) {
                return;
            }

            if (isLast) {
                cursor.push(value);
                return;
            }

            const newNode = nextToken === '' ? [] : {};
            cursor.push(newNode);
            cursor = newNode;
            continue;
        }

        if (isLast) {
            if (Object.prototype.hasOwnProperty.call(cursor, token)) {
                if (!Array.isArray(cursor[token])) {
                    cursor[token] = [cursor[token]];
                }
                cursor[token].push(value);
            } else {
                cursor[token] = value;
            }
            return;
        }

        const shouldBeArray = nextToken === '';

        if (!Object.prototype.hasOwnProperty.call(cursor, token) || cursor[token] === null) {
            cursor[token] = shouldBeArray ? [] : {};
        } else if (shouldBeArray && !Array.isArray(cursor[token])) {
            cursor[token] = [cursor[token]];
        } else if (!shouldBeArray && (typeof cursor[token] !== 'object' || Array.isArray(cursor[token]))) {
            cursor[token] = {};
        }

        cursor = cursor[token];
    }
}

export default submit;
