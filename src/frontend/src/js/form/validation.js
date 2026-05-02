/**
 * Form validation — processes the server JSON response and updates error/valid states.
 *
 * Template convention (all themes must follow):
 *   Field wrapper:  <div class="... {id}[ nata-field--invalid]">
 *   Error container: <div id="{id}-error" class="nata-field-error[ nata-field-error--visible]">
 *                        <span data-nata-form-error-message></span>
 *                    </div>
 *
 * JS toggles:
 *   .is-invalid             on the input control
 *   .nata-field--invalid    on the field wrapper  (.{id})
 *   .nata-field-error--visible  on the error container (#id-error)
 *   .has-error              on the closest .form-group (tab/wizard indicators)
 */
const validation = {
    /**
     * Process the full form validation response.
     *
     * @param {HTMLFormElement} form
     * @param {object} data  form.* from server JSON response
     */
    check(form, data) {
        if (!data) {
            return;
        }

        // Wizard forms: per-fieldset validation
        if (data.fieldsets) {
            const stepsList = form.querySelectorAll('ul.form-wizard-steps-list li');
            const fieldsets = form.querySelectorAll('fieldset');

            for (const [id, fieldset] of Object.entries(data.fieldsets)) {
                validation.element.check(form, fieldset.elements);
                validation.group.check(form, fieldset.groups);

                const fieldsetEl = form.querySelector('#' + id);
                if (fieldsetEl && stepsList.length > 0) {
                    const index = Array.from(fieldsets).indexOf(fieldsetEl);
                    const step = stepsList[index];
                    if (step) {
                        fieldset.isValid === false
                            ? step.classList.add('has-error')
                            : step.classList.remove('has-error');
                    }
                }
            }
        }

        validation.element.check(form, data.elements);
        validation.group.check(form, data.groups);
    },

    /**
     * Clear all validation messages from a form.
     *
     * @param {HTMLFormElement} form
     */
    clear(form) {
        validation.element.clearAll(form);
        validation.group.clearAll(form);
    },

    /**
     * Element validation methods.
     */
    element: {
        /**
         * Iterate elements map and mark each as valid/invalid.
         *
         * @param {HTMLFormElement} form
         * @param {object|null} elements  keyed by field name
         */
        check(form, elements) {
            if (!elements) {
                return;
            }

            for (const [, element] of Object.entries(elements)) {
                const ctrl = document.getElementById(element.id);
                if (ctrl) {
                    ctrl.dispatchEvent(new CustomEvent('nata:form:element:before:validation', {
                        bubbles: true,
                        detail: { element }
                    }));
                }

                validation.element.clear(form, element);
                if (element.error) {
                    validation.element.invalid(form, element);
                } else {
                    validation.element.valid(form, element);
                }

                if (ctrl) {
                    ctrl.dispatchEvent(new CustomEvent('nata:form:element:after:validation', {
                        bubbles: true,
                        detail: { element }
                    }));
                }
            }
        },

        /**
         * Mark a single element as valid (clear() already ran; nothing additional needed).
         */
        valid() {},

        /**
         * Mark a single element as invalid and display error message.
         *
         * @param {HTMLFormElement} form
         * @param {object} element  { id, error }
         */
        invalid(form, element) {
            const ctrl = document.getElementById(element.id);

            const wrapper = form.querySelector('.' + element.id);
            if (wrapper) {
                wrapper.classList.add('nata-field--invalid');
            }

            if (ctrl) {
                ctrl.classList.add('is-invalid');
            }

            const errorContainer = document.getElementById(element.id + '-error');
            if (errorContainer) {
                const errors = Array.isArray(element.error) ? element.error : [element.error];
                const msgEl = errorContainer.querySelector('[data-nata-form-error-message]');
                if (msgEl) {
                    msgEl.textContent = errors[0];
                }
                errorContainer.classList.add('nata-field-error--visible');

                // Mark parent .form-group (tab/wizard indicators)
                const formGroup = errorContainer.closest('.form-group') || errorContainer.closest('.input-group');
                if (formGroup) {
                    formGroup.classList.add('has-error');
                }

                // Tab pane errors
                const tabPane = errorContainer.closest('.tab-pane');
                if (tabPane && tabPane.id) {
                    const tabTrigger = document.querySelector('[data-toggle="tab"][href="#' + tabPane.id + '"]');
                    if (tabTrigger) {
                        tabTrigger.closest('li')?.classList.add('has-error');
                    }
                }
            }
        },

        /**
         * Clear error state from a single element.
         *
         * @param {HTMLFormElement} form
         * @param {object} element  { id }
         */
        clear(form, element) {
            const ctrl = document.getElementById(element.id);
            if (ctrl) {
                ctrl.classList.remove('is-invalid');
            }

            const wrapper = form.querySelector('.' + element.id);
            if (wrapper) {
                wrapper.classList.remove('nata-field--invalid');
            }

            const errorContainer = document.getElementById(element.id + '-error');
            if (errorContainer) {
                const msgEl = errorContainer.querySelector('[data-nata-form-error-message]');
                if (msgEl) {
                    msgEl.textContent = '';
                }
                errorContainer.classList.remove('nata-field-error--visible');

                const formGroup = errorContainer.closest('.form-group');
                if (formGroup) {
                    formGroup.classList.remove('has-error');
                }
            }
        },

        /**
         * Clear all element errors in the form.
         *
         * @param {HTMLFormElement} form
         */
        clearAll(form) {
            form.querySelectorAll('[data-nata-form-error-message]').forEach(el => {
                el.textContent = '';
            });
            form.querySelectorAll('.nata-field-error--visible').forEach(el => {
                el.classList.remove('nata-field-error--visible');
            });
            form.querySelectorAll('.nata-field--invalid').forEach(el => {
                el.classList.remove('nata-field--invalid');
            });
            form.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            form.querySelectorAll('.has-error').forEach(el => {
                el.classList.remove('has-error');
            });
        }
    },

    /**
     * Group validation methods.
     */
    group: {
        /**
         * Mark group rows as valid/invalid.
         *
         * @param {HTMLFormElement} form
         * @param {object|null} groups
         */
        check(form, groups) {
            if (!groups) {
                return;
            }

            for (const [, group] of Object.entries(groups)) {
                if (!group.rows) {
                    continue;
                }
                group.rows.forEach(row => {
                    const rowEl = document.getElementById(row.id);
                    if (!rowEl) {
                        return;
                    }
                    if (row.isValid === false) {
                        validation.group.error(rowEl);
                    } else {
                        validation.group.clear(rowEl);
                    }
                    // Recurse into row-level elements
                    validation.check(form, row);
                });
            }
        },

        /**
         * Mark a group row as errored.
         *
         * @param {HTMLElement} rowEl
         */
        error(rowEl) {
            rowEl.classList.remove('panel-default');
            rowEl.classList.add('panel-danger');
        },

        /**
         * Clear a group row's error state.
         *
         * @param {HTMLElement} rowEl
         */
        clear(rowEl) {
            rowEl.classList.add('panel-default');
            rowEl.classList.remove('panel-danger');
        },

        /**
         * Clear all group row errors.
         *
         * @param {HTMLFormElement} form
         */
        clearAll(form) {
            form.querySelectorAll('.panel-danger').forEach(el => {
                el.classList.remove('panel-danger');
                el.classList.add('panel-default');
            });
        }
    }
};

export default validation;
