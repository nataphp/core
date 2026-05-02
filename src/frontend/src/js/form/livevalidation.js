/**
 * Live field validation — checks individual field values with the server in real time.
 *
 * Opt-in:
 *   • data-live-validate on the <form> — enables for all text/date inputs in that form
 *   • data-live-validate on a single <input> — enables only for that field
 *
 * Only applies to text-like inputs and date pickers (not checkboxes, radios, selects,
 * uploaders). Sends a POST to the form's action URL with just that field's value plus
 * a _validate_field=1 flag, then updates the validation state.
 *
 * The server is expected to return the same JSON shape as a regular form submit,
 * but only with the relevant element in data.form.elements.
 */
import validation from './validation.js';

const TEXT_INPUT_SELECTOR = 'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]), textarea';

const liveValidation = {
    /**
     * Initialize live validation listeners.
     */
    init() {
        document.querySelectorAll('form.nata').forEach(form => {
            const formLevel = form.hasAttribute('data-live-validate');

            // Field-level selectors
            const selector = formLevel
                ? TEXT_INPUT_SELECTOR
                : '[data-live-validate]';

            form.querySelectorAll(selector).forEach(input => {
                if (input.dataset.liveValidateBound) {
                    return;
                }
                input.dataset.liveValidateBound = '1';
                liveValidation._bindInput(form, input);
            });
        });
    },

    /**
     * Bind debounced blur/input events to a single input.
     *
     * @param {HTMLFormElement} form
     * @param {HTMLInputElement|HTMLTextAreaElement} input
     */
    _bindInput(form, input) {
        let timeout = null;

        const validate = () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => liveValidation._validate(form, input), 600);
        };

        input.addEventListener('input', validate);
        input.addEventListener('change', validate);
        // Also validate on blur without debounce
        input.addEventListener('blur', () => {
            clearTimeout(timeout);
            liveValidation._validate(form, input);
        });
    },

    /**
     * Send the field value to the server and update its validation state.
     *
     * @param {HTMLFormElement} form
     * @param {HTMLInputElement|HTMLTextAreaElement} input
     */
    _validate(form, input) {
        const name = input.getAttribute('name');
        if (!name) {
            return;
        }

        const value = input.value;
        const url = form.getAttribute('action');
        if (!url) {
            return;
        }

        // Find CSRF/timestamp token if present
        const tsField = form.querySelector('input[type="hidden"][name$="[___timestamp]"]');

        const body = {};
        body[name] = value;
        body['_validate_field'] = '1';
        if (tsField) {
            body[tsField.name] = tsField.value;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        })
            .then(r => r.json())
            .catch(() => null)
            .then(data => {
                if (!data || !data.form || !data.form.elements) {
                    return;
                }
                // Only apply validation for this specific field
                const id = input.getAttribute('id') || name;
                for (const [, element] of Object.entries(data.form.elements)) {
                    if (element.id === id) {
                        if (element.error) {
                            validation.element.clear(form, element);
                            validation.element.invalid(form, element);
                        } else {
                            validation.element.valid(form, element);
                            validation.element.clear(form, element);
                        }
                        break;
                    }
                }
            });
    }
};

export default liveValidation;
