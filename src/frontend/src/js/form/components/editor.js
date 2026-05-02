/**
 * WYSIWYG editor component — Quill.js (replaces Trumbowyg).
 *
 * HTML: <textarea class="form-text-editor" name="body" ...></textarea>
 *
 * Quill renders a sibling <div> as the editor; the original <textarea> is hidden.
 * On form submit (nata:form:before:submit), Quill content is synced back to the textarea.
 *
 * Group integration: before adding a new row, Quill instances are destroyed and
 * the textarea is restored so cloneNode() copies clean HTML.
 */
function _quillBase() {
    const b = document.documentElement.dataset.baseUrl || '';
    return b === '/' ? '' : b;
}
const QUILL_VENDOR = () => _quillBase() + '/nata/vendor/quill/';

const editor = {
    /**
     * Initialize Quill on all uninitiated .form-text-editor textareas.
     */
    init() {
        const textareas = document.querySelectorAll('textarea.form-text-editor:not(.set)');
        if (textareas.length === 0) {
            return;
        }

        _loadQuill(() => {
            textareas.forEach(textarea => {
                if (textarea.classList.contains('set')) {
                    return;
                }

                const initialContent = textarea.value || '';
                textarea.style.display = 'none';
                textarea.classList.add('set');

                // Create Quill container
                const container = document.createElement('div');
                container.className = 'nata-quill-editor';
                textarea.parentNode.insertBefore(container, textarea.nextSibling);

                const quill = new Quill(container, {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ header: [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            ['link', 'blockquote', 'code-block'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['clean']
                        ]
                    }
                });

                if (initialContent) {
                    quill.clipboard.dangerouslyPasteHTML(initialContent);
                }

                // Store reference
                textarea._quillInstance = quill;
                container._quillTextarea = textarea;

                // Sync on change
                quill.on('text-change', () => {
                    textarea.value = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML;
                });
            });

            // Bind submit sync for all forms containing editors
            editor._bindSubmitSync();
            // Bind group events for editors inside groups
            editor._bindGroupEvents();
        });
    },

    /**
     * Sync all Quill editors to their textareas before form submission.
     */
    _bindSubmitSync() {
        document.querySelectorAll('form.nata').forEach(form => {
            if (form.dataset.editorSyncBound) {
                return;
            }
            form.dataset.editorSyncBound = '1';
            form.addEventListener('nata:form:before:submit', () => {
                form.querySelectorAll('textarea.form-text-editor.set').forEach(textarea => {
                    if (textarea._quillInstance) {
                        const html = textarea._quillInstance.root.innerHTML;
                        textarea.value = html === '<p><br></p>' ? '' : html;
                    }
                });
            });
        });
    },

    /**
     * Bind group lifecycle events to handle Quill in repeated rows.
     */
    _bindGroupEvents() {
        document.querySelectorAll('[data-group]').forEach(group => {
            if (group.dataset.groupEditorBound) {
                return;
            }
            if (group.querySelectorAll('textarea.form-text-editor').length === 0) {
                return;
            }
            group.dataset.groupEditorBound = '1';

            // Before adding a new row: destroy Quill so cloneNode copies clean DOM
            group.addEventListener('nata:form:group:before:clone', (event) => {
                const row = event.detail.row;
                row.querySelectorAll('textarea.form-text-editor.set').forEach(textarea => {
                    editor.destroy(textarea.parentNode);
                });
            });

            // After adding new row: re-init editors in new row
            group.addEventListener('nata:form:group:after:add', (event) => {
                const newRow = event.detail.newRow;
                newRow.querySelectorAll('textarea.form-text-editor').forEach(textarea => {
                    textarea.style.display = '';
                    textarea.classList.remove('set');
                });
                editor.init();
            });
        });
    },

    /**
     * Destroy Quill instance(s) within a container and restore textarea.
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('textarea.form-text-editor.set').forEach(textarea => {
            if (textarea._quillInstance) {
                delete textarea._quillInstance;
            }
            textarea.style.display = '';
            textarea.classList.remove('set', 'trumbowyg-textarea');
        });

        container.querySelectorAll('.nata-quill-editor, .ql-toolbar, .ql-container').forEach(el => el.remove());
    }
};

let _quillLoading = false;
let _quillCallbacks = [];
let _quillLoaded = false;

/**
 * Ensure Quill JS and its Snow CSS are loaded before running a callback.
 * Concurrent calls before load completes are queued and flushed together.
 *
 * @param {function(): void} cb  Called once Quill is available on window
 */
function _loadQuill(cb) {
    if (_quillLoaded && typeof Quill !== 'undefined') {
        cb();
        return;
    }

    _quillCallbacks.push(cb);

    if (_quillLoading) {
        return;
    }
    _quillLoading = true;

    const base = QUILL_VENDOR();

    // CSS
    if (!document.querySelector('link[href*="quill"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = base + 'quill.snow.css';
        document.head.appendChild(link);
    }

    // JS
    const script = document.createElement('script');
    script.src = base + 'quill.js';
    script.onload = () => {
        _quillLoaded = true;
        _quillLoading = false;
        _quillCallbacks.forEach(fn => fn());
        _quillCallbacks = [];
    };
    script.onerror = () => {
        console.error('NataForm: failed to load Quill from', base + 'quill.js');
        _quillLoading = false;
        _quillCallbacks = [];
    };
    document.head.appendChild(script);
}

export default editor;
