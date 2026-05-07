/**
 * Input mask component — uses the InputMask library (standalone).
 *
 * HTML: <input class="form-input-mask" data-mask="999-999-9999" ... />
 */
const mask = {
    /**
     * Initialize InputMask on all uninitialized .form-input-mask inputs.
     * The mask pattern and options are read from the input's data attributes.
     */
    init() {
        if (document.querySelectorAll('input.form-input-mask').length === 0) {
            return;
        }

        _loadInputMask(() => {
            document.querySelectorAll('input.form-input-mask:not(.set)').forEach(input => {
                const instance = new InputMask(input, input.dataset);
                input._inputMask = instance;
                input.classList.add('set');
            });
        });
    },

    /**
     * Destroy InputMask instances inside a container and remove the `.set` marker
     * so inputs can be re-initialized (e.g. after cloning a group row).
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('input.form-input-mask.set').forEach(input => {
            if (input._inputMask && typeof input._inputMask.destroy === 'function') {
                input._inputMask.destroy();
                delete input._inputMask;
            }
            input.classList.remove('set');
        });
    }
};

/**
 * Ensure the standalone InputMask library is loaded, then call cb.
 * The script is fetched from {baseUrl}/nata/input-mask.min.js.
 *
 * @param {function(): void} cb
 */
function _loadInputMask(cb) {
    if (typeof InputMask !== 'undefined') {
        cb();
        return;
    }

    const base = document.documentElement.dataset.baseUrl || '';
    const script = document.createElement('script');
    script.src = base + '/nata/input-mask.min.js';
    script.onload = cb;
    document.head.appendChild(script);
}

export default mask;
