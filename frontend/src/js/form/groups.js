/**
 * Repeatable group rows — add / remove / clone / clear / index increment.
 *
 * HTML contract:
 *   [data-group="groupName"]         — group container
 *   .group-row                       — individual repeatable row
 *   .panel-group-add-another         — "Add another" button (sibling after last .group-row)
 *   .remove (inside .group-row)      — remove button
 *   data-max on [data-group]         — maximum number of rows
 *
 * Custom events dispatched on the [data-group] element:
 *   nata:form:group:before:add, nata:form:group:after:add
 *   nata:form:group:before:remove, nata:form:group:after:remove
 *   nata:form:group:before:clone, nata:form:group:after:clone
 *   nata:form:group:before:clear, nata:form:group:after:clear
 *   nata:form:group:before:increment, nata:form:group:after:increment
 */
import { DETECTION_CLASS } from './changes.js';

const groups = {
    /**
     * Bind add/remove buttons. Call once on init and again after adding rows.
     */
    init() {
        // Inject groups CSS when groups are present
        if (document.querySelector('[data-group]')) {
            const base = document.documentElement.dataset.baseUrl || '';
            const href = (base === '/' ? '' : base) + '/nata/nata.groups.min.css';
            if (!document.querySelector('link[href="' + href + '"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            }
        }

        // "Add another" buttons
        document.querySelectorAll('.panel-group-add-another:not(.set)').forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                const row = btn.previousElementSibling;
                if (row && row.classList.contains('group-row')) {
                    groups.add(row, true);
                }
            });
            btn.classList.add('set');
        });

        // Remove buttons
        document.querySelectorAll('.group-row .remove:not(.set)').forEach(btn => {
            btn.addEventListener('click', event => {
                event.preventDefault();
                groups.confirmRemove(btn);
            });
            btn.classList.add('set');
        });

        // Toggle collapse — framework-agnostic via [data-group-toggle]
        document.querySelectorAll('.group-row [data-group-toggle]:not(.set)').forEach(toggle => {
            toggle.addEventListener('click', event => {
                if (event.target.closest('.remove')) {
                    return;
                }
                const row = toggle.closest('.group-row');
                if (!row) {
                    return;
                }
                const isOpen = row.classList.contains('open');
                // Accordion: close siblings in same group
                const group = row.closest('[data-group]');
                if (group) {
                    group.querySelectorAll('.group-row.open').forEach(r => r.classList.remove('open'));
                }
                if (!isOpen) {
                    row.classList.add('open');
                }
            });
            toggle.classList.add('set');
        });

        groups.dynamicTitle.init();

        document.querySelectorAll('[data-group]').forEach(g => {
            g.dispatchEvent(new CustomEvent('nata:form:group:init', { bubbles: true }));
        });
    },

    /**
     * Add a new row after the given row.
     *
     * @param {HTMLElement} row     Existing .group-row to clone
     * @param {boolean}     scroll  Whether to scroll the new row into view
     * @return {HTMLElement} The new row element
     */
    add(row, scroll) {
        const group = row.closest('[data-group]');
        if (!group) {
            return null;
        }
        const addBtn = group.querySelector('.panel-group-add-another');
        const max = parseInt(group.dataset.max) || 0;
        const form = row.closest('form');

        const newRow = groups.clone(row);
        group.dispatchEvent(new CustomEvent('nata:form:group:before:add', { bubbles: true, detail: { newRow } }));

        // Accordion: close all open rows before inserting
        group.querySelectorAll('.group-row.open').forEach(r => r.classList.remove('open'));

        row.after(newRow);

        if (scroll && window.nata && window.nata.dom && window.nata.dom.scrollTo) {
            window.nata.dom.scrollTo({ target: newRow, offset: 100 });
        } else if (scroll) {
            newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        groups.incrementRowIndex(newRow);
        groups.clearRow(newRow);

        group.dispatchEvent(new CustomEvent('nata:form:group:reset', { bubbles: true, detail: { newRow } }));

        // Re-init everything on the new row
        groups.init();
        if (window.nataForm) {
            window.nataForm.components.init();
            window.nataForm.translate.init();
        }
        if (form) {
            // Re-bind change detection for new inputs
            import('./changes.js').then(m => m.default._bindEvents(form));
        }

        // Open the new row
        newRow.classList.add('open');

        const count = group.querySelectorAll('.group-row').length;
        if (max > 0 && count >= max && addBtn) {
            addBtn.classList.add('hidden');
        }

        group.dispatchEvent(new CustomEvent('nata:form:group:after:add', { bubbles: true, detail: { newRow } }));

        return newRow;
    },

    /**
     * Remove a row from the group.
     *
     * @param {HTMLElement} row
     */
    remove(row) {
        const group = row.closest('[data-group]');
        if (!group) {
            return;
        }
        const addBtn = group.querySelector('.panel-group-add-another');
        const max = parseInt(group.dataset.max) || 0;
        const count = group.querySelectorAll('.group-row').length;
        const form = row.closest('form');

        group.dispatchEvent(new CustomEvent('nata:form:group:before:remove', { bubbles: true, detail: { row, count } }));

        if (count > 1) {
            row.remove();
        } else {
            // Last row: clone + clear instead of remove
            const newRow = groups.clone(row);
            row.remove();
            group.prepend(newRow);
            groups.clearRow(newRow);
            group.dispatchEvent(new CustomEvent('nata:form:group:reset', { bubbles: true, detail: { newRow } }));
            groups.init();
            if (window.nataForm) {
                window.nataForm.components.init();
                window.nataForm.translate.init();
            }
        }

        const newCount = group.querySelectorAll('.group-row').length;
        if (max > 0 && newCount < max && addBtn) {
            addBtn.classList.remove('hidden');
        }

        group.dispatchEvent(new CustomEvent('nata:form:group:after:remove', { bubbles: true, detail: { row, count: newCount } }));

        if (form && window.nataForm) {
            window.nataForm.changes._check(form);
        }
    },

    /**
     * Deep-clone a row.
     *
     * @param {HTMLElement} row
     * @return {HTMLElement}
     */
    clone(row) {
        const group = row.closest('[data-group]');
        group.dispatchEvent(new CustomEvent('nata:form:group:before:clone', { bubbles: true, detail: { row } }));

        const newRow = row.cloneNode(true);

        group.dispatchEvent(new CustomEvent('nata:form:group:after:clone', { bubbles: true, detail: { row, newRow } }));

        return newRow;
    },

    /**
     * Clear/reset all field values in a cloned row.
     *
     * @param {HTMLElement} newRow
     */
    clearRow(newRow) {
        const group = newRow.closest('[data-group]');
        group.dispatchEvent(new CustomEvent('nata:form:group:before:clear', { bubbles: true, detail: { newRow } }));

        newRow.classList.remove('iconset');

        // Reset toggle title to default
        newRow.querySelectorAll('[data-group-toggle]').forEach(toggle => {
            const titleEl = toggle.querySelector('.panel-toggle-title');
            if (titleEl && toggle.dataset.defaultTitle) {
                titleEl.innerHTML = toggle.dataset.defaultTitle;
            }
        });

        // Remove re-init markers so components get re-initialized
        newRow.querySelectorAll('.set').forEach(el => el.classList.remove('set'));
        newRow.querySelectorAll('.' + DETECTION_CLASS).forEach(el => el.classList.remove(DETECTION_CLASS));

        // Strip cloned translate pill navs so translate.init() rebuilds them with live listeners
        newRow.querySelectorAll('.translation-controls').forEach(el => el.remove());

        // Reset field values
        const fields = newRow.querySelectorAll('input[name], select[name], textarea[name]');
        if (fields.length === 0) {
            console.warn('NataForm group: no fields found in row');
        }

        fields.forEach(field => {
            if (field.tagName === 'SELECT') {
                const opts = field.querySelectorAll('option');
                opts.forEach(opt => opt.removeAttribute('selected'));
                const defaultOption = field.dataset.defaultOption;
                if (defaultOption && !field.multiple) {
                    const existing = field.querySelector('option[value=""]');
                    if (!existing) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = defaultOption;
                        opt.selected = true;
                        field.prepend(opt);
                    } else {
                        existing.selected = true;
                    }
                }
            } else if (field.type === 'checkbox') {
                field.checked = false;
            } else if (field.type !== 'hidden') {
                field.value = '';
            }
        });

        group.dispatchEvent(new CustomEvent('nata:form:group:after:clear', { bubbles: true, detail: { newRow } }));
    },

    /**
     * Increment the array-index in all IDs, names, labels and aria attributes of a row.
     *
     * @param {HTMLElement} newRow
     * @param {number}      [index]  Force a specific index; auto-increment if omitted
     */
    incrementRowIndex(newRow, index) {
        const group = newRow.closest('[data-group]');
        const groupName = group.dataset.group;
        if (!groupName) {
            console.warn('NataForm group: unable to find group name');
            return;
        }

        group.dispatchEvent(new CustomEvent('nata:form:group:before:increment', {
            bubbles: true, detail: { newRow, groupName, index }
        }));

        const inc = str => groups.increment(groupName, str, index);

        // Row id
        if (newRow.id) {
            newRow.id = inc(newRow.id);
        }

        // Content body (if it carries an id)
        newRow.querySelectorAll('[data-group-content]').forEach(body => {
            if (body.id) {
                body.id = inc(body.id);
            }
        });

        // Fields
        newRow.querySelectorAll('input[name], select[name], textarea[name]').forEach(field => {
            const newName = inc(field.getAttribute('name'));
            field.setAttribute('name', newName);
            if (field.id) {
                field.id = inc(field.id);
            }
        });

        // Labels
        newRow.querySelectorAll('label[for]').forEach(label => {
            label.setAttribute('for', inc(label.getAttribute('for')));
        });

        // Error wrappers
        newRow.querySelectorAll('.nataphp-error-message, [id$="-error"]').forEach(el => {
            if (el.id) {
                el.id = inc(el.id);
            }
        });

        group.dispatchEvent(new CustomEvent('nata:form:group:after:increment', {
            bubbles: true, detail: { newRow, groupName, index }
        }));
    },

    /**
     * Increment the numeric index of a groupName segment within a string.
     *
     * Works for both ID-style ("group-0-field") and name-style ("group[0][field]").
     *
     * @param {string} groupName  e.g. "pictures" or "address.pictures"
     * @param {string} string     e.g. "form-pictures-0-caption" or "form[pictures][0][caption]"
     * @param {number} [index]    Force this index; auto-increments current if omitted
     * @return {string}
     */
    increment(groupName, string, index) {
        if (!groupName) {
            throw new Error('Increment: groupName is null');
        }
        if (!string) {
            throw new Error('Increment: string is null for group "' + groupName + '"');
        }

        const isName = string.indexOf('][') > -1;

        // Normalize bracket notation to hyphen-separated
        let working = string;
        if (isName) {
            working = working
                .split('][').join('-')
                .split('[').join('-')
                .split(']').join('');
        }

        const parts = working.split('-');
        const groupParts = groupName.split('.');
        let found = 0;

        for (let i = 0; i < parts.length; i++) {
            if (!groupParts.includes(parts[i])) {
                found = 0;
                continue;
            }
            if (groupParts.length === 1 && parts[i] !== groupName) {
                continue;
            }
            found++;
            if (found !== groupParts.length) {
                continue;
            }

            const key = parts[i + 1];
            if (key === undefined || isNaN(parseFloat(key)) || !isFinite(key)) {
                continue;
            }

            parts[i + 1] = String(index !== undefined && index > -1 ? index : parseInt(key, 10) + 1);
            break;
        }

        if (isName) {
            return parts.reduce((acc, part, idx) => acc + (idx === 0 ? part : '[' + part + ']'), '');
        }

        return parts.join('-');
    },

    /**
     * Confirm and remove a row via nata.dialog.
     *
     * @param {HTMLElement} btn  The remove button inside the row
     */
    confirmRemove(btn) {
        const row = btn.closest('.group-row');
        if (!row) {
            return;
        }
        const labelText = btn.previousElementSibling ? btn.previousElementSibling.textContent.trim() : '';
        const title = (window.__ ? window.__('Delete') : 'Delete') + (labelText ? ' ' + labelText : '');
        const body = window.__ ? window.__('Are you sure?') : 'Are you sure?';

        if (window.nata && window.nata.dialog) {
            window.nata.dialog.confirm(body, confirmed => {
                if (confirmed) {
                    groups.remove(row);
                }
            });
        } else {
            if (confirm(body)) {
                groups.remove(row);
            }
        }
    },

    /**
     * Group dynamic title — updates row panel titles based on field values.
     */
    dynamicTitle: {
        _patterns: {},

        /**
         * Scan all .group-row elements on the page, process their initial titles,
         * and bind change listeners so titles re-render when field values change.
         */
        init() {
            const rows = document.querySelectorAll('.group-row');
            this.process(rows);

            rows.forEach(row => {
                row.querySelectorAll('select, input, textarea').forEach(field => {
                    field.removeEventListener('change', field._nataGroupTitleHandler || null);
                    field._nataGroupTitleHandler = () => this.process([row]);
                    field.addEventListener('change', field._nataGroupTitleHandler);
                });
            });
        },

        /**
         * Update dynamic titles for given rows.
         *
         * @param {NodeList|HTMLElement[]} rows
         */
        process(rows) {
            rows.forEach(row => {
                const toggle = row.querySelector('[data-group-toggle]');
                if (!toggle) {
                    return;
                }
                const pattern = toggle.dataset.titlePattern;
                if (!pattern) {
                    return;
                }

                const matches = this._extractPatterns(pattern);
                const data = matches.map(placeholder => ({
                    placeholder,
                    text: this._text(placeholder, row)
                }));

                const title = this._replaceTitle(pattern, data) || toggle.dataset.defaultTitle || '';
                const titleEl = toggle.querySelector('.panel-toggle-title');
                if (titleEl) {
                    titleEl.innerHTML = title;
                }
            });
        },

        /**
         * Extract all {placeholder} tokens from a title pattern string.
         * Results are cached by pattern for performance.
         *
         * @param {string} pattern  e.g. "{name} — {category}"
         * @return {string[]}       e.g. ["name", "category"]
         */
        _extractPatterns(pattern) {
            if (this._patterns[pattern]) {
                return this._patterns[pattern];
            }
            const re = /\{(.+?)\}/gi;
            const found = [];
            let m;
            while ((m = re.exec(pattern)) !== null) {
                found.push(m[1]);
            }
            this._patterns[pattern] = found;
            return found;
        },

        /**
         * Resolve the display text for a placeholder token inside a group row.
         *
         * Special placeholder:
         *   {_count}  — current row index (0-based) plus optional |start: offset.
         *
         * Field types handled:
         *   select    — joined selected-option labels (filtered by non-empty value)
         *   checkbox  — joined label texts of checked items
         *   radio     — label text of the checked item (prefers <strong> inside label)
         *   other     — trimmed .value
         *
         * After the raw value is resolved, |prepend: and |append: are applied
         * (only when the value is non-empty).
         *
         * @param {string}      placeholder  Full token content e.g. "note|append: - "
         * @param {HTMLElement} row          The .group-row element to search within
         * @return {string}
         */
        _text(placeholder, row) {
            const options = this._placeholderOptions(placeholder);
            const sep = options.separator || ', ';

            // Special placeholder: row counter
            if (options.name === '_count') {
                // `offset` is the canonical option; `start` is a deprecated BC alias
                const start = parseInt(options.offset ?? options.start ?? 0, 10);
                const group = row.closest('[data-group]');
                const siblings = group ? Array.from(group.querySelectorAll('.group-row')) : [];
                const idx = siblings.indexOf(row);
                const val = String(idx < 0 ? start : idx + start);
                return this._applyModifiers(val, options);
            }

            const controlName = this._parseControlName(options.name);
            // `scope` is the canonical option; `group` is a deprecated BC alias
            const groupScope = (options.scope || options.group) ? '[data-group="' + (options.scope || options.group) + '"]' : '';
            const base = '[name$="' + controlName + '"]';
            const baseArr = '[name$="' + controlName + '[]"]';
            const selector = groupScope ? groupScope + ' ' + base + ',' + groupScope + ' ' + baseArr : base + ',' + baseArr;

            const input = row.querySelector(selector);
            if (!input) {
                return '';
            }

            let val = '';

            // Flexdatalist: read display text from alias input / multiple list
            const fdAlias = input.parentElement?.querySelector('.flexdatalist-alias');
            if (fdAlias) {
                const fdMultiple = input.parentElement?.querySelector('ul.flexdatalist-multiple');
                const text = fdMultiple
                    ? Array.from(fdMultiple.querySelectorAll('li.value span.text')).map(s => s.textContent).join(sep)
                    : fdAlias.value.trim();
                return this._applyModifiers(text, options);
            }

            if (input.tagName === 'SELECT') {
                val = Array.from(input.selectedOptions)
                    .filter(o => o.value.trim().length > 0)
                    .map(o => o.text)
                    .join(sep);
            } else if (input.type === 'checkbox') {
                val = Array.from(row.querySelectorAll(selector + ':checked'))
                    .map(el => el.closest('label')?.textContent?.trim() || el.value)
                    .join(sep);
            } else if (input.type === 'radio') {
                const checked = row.querySelector(selector + ':checked');
                if (!checked) {
                    return '';
                }
                const label = checked.closest('label');
                val = (label?.querySelector('strong') || label)?.textContent?.trim() || '';
            } else {
                val = input.value.trim();
            }

            return this._applyModifiers(val, options);
        },

        /**
         * Apply |prepend: and |append: modifiers to a resolved value.
         * Modifiers are only applied when val is non-empty.
         *
         * @param {string} val
         * @param {{prepend?: string, append?: string}} options
         * @return {string}
         */
        _applyModifiers(val, options) {
            if (!val) {
                return '';
            }
            if (options.prepend) {
                val = options.prepend + val;
            }
            if (options.append) {
                val = val + options.append;
            }
            return val;
        },

        /**
         * Parse a placeholder token into an options object.
         * Pipe-delimited modifiers are extracted as key/value pairs.
         * Use :pipe: in a value to represent a literal | character.
         * Leading/trailing single or double quotes are stripped from values.
         *
         * @param {string} placeholder  e.g. "note|append: - |separator:, "
         * @return {{name: string, separator?: string, scope?: string, group?: string, append?: string, prepend?: string, offset?: string, start?: string}}
         *
         * @example
         * _placeholderOptions('city|scope:addresses|separator: / ')
         * // => { name: 'city', scope: 'addresses', separator: ' / ' }
         */
        _placeholderOptions(placeholder) {
            const parts = placeholder.split('|');
            const opts = { name: parts[0] };
            for (let i = 1; i < parts.length; i++) {
                const part = parts[i];
                const colonIdx = part.indexOf(':');
                if (colonIdx > -1) {
                    const key = part.slice(0, colonIdx);
                    let val = part.slice(colonIdx + 1).replace(/:pipe:/g, '|');
                    val = val.replace(/^['"]|['"]$/g, '');
                    opts[key] = val;
                }
            }
            return opts;
        },

        /**
         * Convert a dot-separated field name into a CSS [name$="..."] selector suffix.
         * Single segment names are wrapped in brackets; dot-separated ones become nested brackets.
         *
         * @param {string} name  e.g. "title" or "address.city"
         * @return {string}      e.g. "[title]" or "[address][city]"
         */
        _parseControlName(name) {
            if (name.indexOf('.') > -1) {
                return '[' + name.split('.').join('][') + ']';
            }
            return '[' + name + ']';
        },

        /**
         * Replace all {placeholder} tokens in a pattern with resolved text values.
         * Returns false when every placeholder resolved to an empty string (so the
         * caller can fall back to the default title instead).
         *
         * @param {string} pattern
         * @param {Array<{placeholder: string, text: string}>} data
         * @return {string|false}
         */
        _replaceTitle(pattern, data) {
            let title = pattern;
            let empty = 0;
            data.forEach(({ placeholder, text }) => {
                title = title.replace('{' + placeholder + '}', text);
                if (text === '') {
                    empty++;
                }
            });
            return empty === data.length ? false : title;
        }
    },

    /** Group-level validation state */
    validation: {
        /**
         * Apply panel-danger / panel-default classes to group rows based on
         * server-side validation results in groups_data.
         *
         * @param {HTMLFormElement} form
         * @param {object|null} groups_data  Validation payload keyed by group name,
         *                                   each having a `rows` array with `{id, isValid}` entries
         */
        check(form, groups_data) {
            if (!groups_data) {
                return;
            }
            for (const [, group] of Object.entries(groups_data)) {
                if (!group.rows) {
                    continue;
                }
                group.rows.forEach(row => {
                    const rowEl = document.getElementById(row.id);
                    if (!rowEl) {
                        return;
                    }
                    row.isValid === false
                        ? rowEl.classList.replace('panel-default', 'panel-danger') || rowEl.classList.add('panel-danger')
                        : (rowEl.classList.remove('panel-danger'), rowEl.classList.add('panel-default'));
                });
            }
        }
    }
};

export default groups;
