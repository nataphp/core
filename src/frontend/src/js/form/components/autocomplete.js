/**
 * Autocomplete component — wraps Flexdatalist (ES6 standalone class).
 *
 * HTML: <input class="flexdatalist" data-url="/ajax/search" ... />
 *
 * Group events handled:
 *   nata:form:group:after:clear       — removes flexdatalist DOM artefacts from cloned row
 *   nata:form:group:before:remove     — destroys instances before a row is removed
 *   nata:form:group:reset             — re-inits flexdatalist on the new row
 *   nata:form:group:before:increment  — increments data-relatives attributes
 */
import validation from '../validation.js';

const autocomplete = {
    /**
     * Initialize Flexdatalist on all uninitiated inputs inside form.nata.
     */
    async init() {
        const forms = document.querySelectorAll('form.nata');
        for (const form of forms) {
            const inputs = form.querySelectorAll('input.flexdatalist:not(.flexdatalist-set)');
            if (inputs.length === 0) {
                continue;
            }

            inputs.forEach(input => {
                // On init, hook into dynamic title refresh
                input.addEventListener('init:flexdatalist', () => {
                    if (window.nataForm?.groups) {
                        window.nataForm.groups.dynamicTitle.init();
                    }
                });

                // On validation: mark individual valueError tag items
                input.addEventListener('nata:form:element:validation:after', (event) => {
                    const { element } = event.detail || {};
                    if (!element?.valueError) {
                        return;
                    }
                    const container = input.parentElement?.querySelector('ul.flexdatalist-multiple');
                    if (!container) {
                        return;
                    }
                    const valueLis = container.querySelectorAll('li.value');
                    element.valueError.forEach(err => {
                        valueLis[err.index]?.classList.add('has-error');
                    });
                });
            });

            await autocomplete._load(inputs);

            var attempts = 0;
            const interval = setInterval(() => {
                if (Flexdatalist) {
                    clearInterval(interval);
                    Flexdatalist.init(inputs);
                }
                attempts++;
                if (attempts > 10) {
                    clearInterval(interval);
                }
            }, 500);

            autocomplete._bindGroupEvents(form);
            autocomplete._bindAddNew(form);
        }
    },

    /**
     * Inject flexdatalist CSS + JS into the DOM if not already loaded.
     * Resolves once the script is ready.
     *
     * @returns {Promise<void>}
     */
    _load() {
        const base = (document.documentElement.dataset.baseUrl || '').replace(/\/$/, '');
        const jsUrl  = base + '/nata/vendor/flexdatalist/flexdatalist.js';
        const cssUrl = base + '/nata/vendor/flexdatalist/flexdatalist.css';

        if (!document.querySelector('link[href*="flexdatalist"]')) {
            const link = document.createElement('link');
            link.rel  = 'stylesheet';
            link.href = cssUrl;
            document.head.appendChild(link);
        }

        if (typeof Flexdatalist !== 'undefined') {
            return Promise.resolve();
        }

        const existing = document.querySelector('script[src*="flexdatalist"]');
        if (existing) {
            // Script tag exists but Flexdatalist isn't ready yet — wait for it
            return new Promise(resolve => existing.addEventListener('load', resolve, { once: true }));
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src     = jsUrl;
            script.onload  = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    /**
     * Bind group lifecycle events to handle Flexdatalist in repeated rows.
     *
     * @param {HTMLFormElement} form
     */
    _bindGroupEvents(form) {
        form.querySelectorAll('[data-group]:not(.panel-group-flexdatalist)').forEach(group => {
            if (group.querySelectorAll('input.flexdatalist').length === 0) {
                return;
            }

            // Before remove: destroy instances so they don't leak
            group.addEventListener('nata:form:group:before:remove', (event) => {
                event.detail.row.querySelectorAll('input.flexdatalist').forEach(input => {
                    Flexdatalist.getInstance(input)?.destroy();
                });
            });

            // After clear: remove DOM artifacts from the cloned row and restore original value
            group.addEventListener('nata:form:group:after:clear', (event) => {
                const newRow = event.detail.newRow;
                newRow.querySelectorAll('.flexdatalist-alias, .flexdatalist-multiple').forEach(el => el.remove());
                newRow.querySelectorAll('input.flexdatalist').forEach(input => {
                    const original = input.getAttribute('value');
                    if (original && !input.value) {
                        input.value = original;
                    }
                });
            });

            // After reset (new row added): init Flexdatalist on the new inputs
            group.addEventListener('nata:form:group:reset', (event) => {
                const inputs = event.detail.newRow.querySelectorAll('input.flexdatalist');
                if (inputs.length > 0) {
                    Flexdatalist.init(inputs);
                    autocomplete._bindAddNew(event.detail.newRow);
                }
            });

            // Before increment: update data-relatives attribute indices
            group.addEventListener('nata:form:group:before:increment', (event) => {
                const { newRow, groupName, index } = event.detail;
                newRow.querySelectorAll('input.flexdatalist').forEach(input => {
                    const relatives = input.getAttribute('data-relatives');
                    if (!relatives) {
                        return;
                    }
                    const updated = relatives.split(',').map(rel => {
                        if (window.nataForm?.groups) {
                            return window.nataForm.groups.increment(groupName, rel.trim(), index);
                        }
                        return rel;
                    });
                    input.setAttribute('data-relatives', updated.join(','));
                });
            });

            group.classList.add('panel-group-flexdatalist');
        });
    },

    /**
     * Bind the addnew:flexdatalist event on inputs that have data-add-new-url.
     * Opens a quick-create dialog, submits via AJAX, then selects the new item.
     *
     * @param {HTMLElement} container
     */
    _bindAddNew(container) {
        container.querySelectorAll('input.flexdatalist[data-add-new-url]').forEach(input => {
            input.addEventListener('addnew:flexdatalist', (event) => {
                const keyword = event.detail ?? '';
                const url = input.dataset.addNewUrl + '?name=' + encodeURIComponent(keyword);
                const label = input.closest('.form-control')?.querySelector('label')?.textContent?.trim();
                const title = label
                    ? ((window.__?.('Add') ?? 'Add') + ' ' + label)
                    : (window.__?.('Add new') ?? 'Add new');
                const isMultiple = input.dataset.multiple === 'true' || input.hasAttribute('multiple');

                window.nata.dialog.modal({
                    name: 'flexdatalist-quick-add',
                    title: title,
                    url: url,
                    dismissable: true,
                    dismissButton: true,
                    removeOnClose: true,
                    complete: (dlg) => {
                        const formEl = dlg.querySelector('form');
                        if (!formEl) {
                            return;
                        }
                        formEl.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            const submitBtn = formEl.querySelector('[type="submit"]');
                            if (submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.classList.add('loading');
                            }
                            try {
                                const resp = await fetch(formEl.action, {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    body: new FormData(formEl),
                                });
                                const json = await resp.json();

                                // Nata form response: use validation.js to display field errors
                                if (json.form) {
                                    validation.check(formEl, json.form);
                                    if (json.form.isValid) {
                                        const fd = Flexdatalist.getInstance(input);
                                        if (fd) {
                                            if (isMultiple) {
                                                fd.addValue(String(json.id));
                                            } else {
                                                fd.setValue(String(json.id));
                                            }
                                        }
                                        dlg.close();
                                    }
                                    return;
                                }

                                // Simple response: { success, id, error }
                                if (json.success) {
                                    const fd = Flexdatalist.getInstance(input);
                                    if (fd) {
                                        if (isMultiple) {
                                            fd.addValue(String(json.id));
                                        } else {
                                            fd.setValue(String(json.id));
                                        }
                                    }
                                    dlg.close();
                                } else {
                                    const errEl = dlg.querySelector('[data-error]');
                                    if (errEl) {
                                        errEl.textContent = json.error ?? 'Error';
                                        errEl.hidden = false;
                                    }
                                }
                            } finally {
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.classList.remove('loading');
                                }
                            }
                        });
                    }
                });
            });
        });
    },

    /**
     * Destroy all Flexdatalist instances inside a container (e.g. before cloning).
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('input.flexdatalist').forEach(input => {
            Flexdatalist?.getInstance(input)?.destroy();
        });
    },
};

export default autocomplete;
