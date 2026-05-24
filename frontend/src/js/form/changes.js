/**
 * Form change detection.
 *
 * Computes a hash of the form's current data and compares it to the
 * hash stored at init time. Enables/disables the submit button accordingly
 * and manages the confirmPageLeave state.
 *
 * Opt-in: form must have data-detect-changes attribute.
 */
const DETECTION_CLASS = '_natachangedetection_';

const changes = {
    /**
     * Initialize change detection for all forms with data-detect-changes.
     */
    init() {
        document.querySelectorAll('form.nata[data-detect-changes]:not([data-detect-changes="false"])').forEach(form => {
            _disableSubmit(form);
            // Store baseline hash after components have had time to initialize
            setTimeout(() => {
                form.dataset.datahash = _hash(form);
            }, 800);
            changes._bindEvents(form);
        });
    },

    /**
     * Bind change/keyup listeners to all inputs within a form.
     * Can be called again after group rows are added.
     *
     * @param {HTMLFormElement} form
     */
    _bindEvents(form) {
        // Checkboxes, radios, selects, date inputs
        const changeable = form.querySelectorAll(
            'input[type="checkbox"], input[type="radio"], select, input.form-datetimepicker'
        );
        changeable.forEach(el => {
            if (el.classList.contains(DETECTION_CLASS)) {
                return;
            }
            el.addEventListener('change', () => changes._check(form));
            el.classList.add(DETECTION_CLASS);
        });

        // Flexdatalist — listens on the custom event dispatched by flexdatalist
        const flexInputs = form.querySelectorAll('input[data-search-in]');
        flexInputs.forEach(el => {
            if (el.classList.contains(DETECTION_CLASS)) {
                return;
            }
            el.addEventListener('init:flexdatalist', () => {
                el.addEventListener('change:flexdatalist', () => changes._check(form));
            });
            el.classList.add(DETECTION_CLASS);
        });

        // Upload zones
        const uploaders = form.querySelectorAll('.form-file-uploader');
        uploaders.forEach(el => {
            if (el.classList.contains(DETECTION_CLASS)) {
                return;
            }
            el.addEventListener('nata:form:upload:change', () => changes._check(form));
            el.classList.add(DETECTION_CLASS);
        });

        // Text inputs / textareas — debounced
        let keypressTimeout = null;
        const textInputs = form.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), textarea');
        textInputs.forEach(el => {
            if (el.classList.contains(DETECTION_CLASS)) {
                return;
            }
            el.addEventListener('keyup', () => {
                clearTimeout(keypressTimeout);
                keypressTimeout = setTimeout(() => changes._check(form), 400);
            });
            el.classList.add(DETECTION_CLASS);
        });
    },

    /**
     * Check if the form data has changed from the stored baseline hash.
     *
     * @param {HTMLFormElement} form
     */
    _check(form) {
        if (!form.dataset.detectChanges || form.dataset.detectChanges === 'false') {
            return;
        }

        const changed = form.dataset.datahash !== _hash(form);

        if (form.dataset.confirmPageLeave && window.nata && window.nata.dom) {
            window.nata.dom.confirmPageLeave(changed);
        }

        if (changed) {
            _enableSubmit(form);
        } else {
            _disableSubmit(form);
        }
    },

    /**
     * Reset the stored hash (call after a successful save).
     *
     * @param {HTMLFormElement} form
     */
    resetHash(form) {
        form.dataset.datahash = _hash(form);
        _disableSubmit(form);
    }
};

/**
 * Compute a hash of the form's current serialized data.
 *
 * @param {HTMLFormElement} form
 * @return {string}
 */
function _hash(form) {
    const obj = {};
    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string') {
            obj[key] = value.trim();
        }
    });
    const str = JSON.stringify(obj);
    if (window.nata && window.nata.hashCode) {
        return window.nata.hashCode(str);
    }
    // Simple fallback hash
    let h = 0;
    for (let i = 0; i < str.length; i++) {
        h = ((h << 5) - h) + str.charCodeAt(i);
        h |= 0;
    }
    return String(h);
}

/**
 * Collect all submit buttons that belong to the form, including external ones
 * referencing it via the `form` attribute.
 *
 * @param {HTMLFormElement} form
 * @return {HTMLButtonElement[]}
 */
function _getSubmitBtns(form) {
    const btns = Array.from(form.querySelectorAll('button[type="submit"]'));
    if (form.id) {
        document.querySelectorAll('button[type="submit"][form="' + form.id + '"]').forEach(b => {
            if (!btns.includes(b)) {
                btns.push(b);
            }
        });
    }
    return btns;
}

/**
 * Add the `disabled` attribute and class to all submit buttons of the form.
 *
 * @param {HTMLFormElement} form
 */
function _disableSubmit(form) {
    _getSubmitBtns(form).forEach(btn => {
        btn.classList.add('disabled');
        btn.setAttribute('disabled', 'disabled');
    });
}

/**
 * Remove the `disabled` attribute and class from all submit buttons of the form.
 *
 * @param {HTMLFormElement} form
 */
function _enableSubmit(form) {
    _getSubmitBtns(form).forEach(btn => {
        btn.classList.remove('disabled');
        btn.removeAttribute('disabled');
    });
}

export { DETECTION_CLASS };
export default changes;
