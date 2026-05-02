/**
 * Visual options component — image and color selection panels.
 *
 * Handles inputs that use visual panels (images or color swatches) as selectors.
 * Clicking a panel sets the associated hidden input value and marks the panel as selected.
 *
 * HTML (image option):
 *   <div class="form-visual-option" data-value="someValue" data-target="inputId">
 *     <img src="..." />
 *   </div>
 *   <input type="hidden" id="inputId" name="..." />
 *
 * HTML (color option, data-image attribute on input):
 *   <input type="radio" data-image="..." class="form-visual-option-radio" ... />
 */
const visualOptions = {
    /**
     * Build visual panels from radio/checkbox inputs that carry data-image or data-color
     * attributes, then bind click handlers so selecting a panel updates the hidden input.
     */
    init() {
        // Image/panel selectors
        document.querySelectorAll('[data-image]:not(.set), [data-color]:not(.set)').forEach(input => {
            const container = input.closest('.form-group');
            if (!container) {
                input.classList.add('set');
                return;
            }

            // Build visual panels from radio/checkbox options
            const type = input.type;
            if (type === 'radio' || type === 'checkbox') {
                _buildPanel(input, container);
            }

            input.classList.add('set');
        });

        // Click handlers for visual panels
        document.querySelectorAll('.nata-visual-panel:not(.set)').forEach(panel => {
            panel.addEventListener('click', () => {
                const targetId = panel.dataset.target;
                const value = panel.dataset.value;
                const target = targetId ? document.getElementById(targetId) : null;

                if (target) {
                    target.value = value;
                    // Deselect siblings
                    target.closest('.form-group')?.querySelectorAll('.nata-visual-panel').forEach(p => {
                        p.classList.remove('selected');
                    });
                    panel.classList.add('selected');
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            panel.classList.add('set');
        });
    },

    /**
     * Remove the `.set` and `.selected` markers from all visual panels inside
     * a container so they can be re-initialized (e.g. after a group row clone).
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('.nata-visual-panel').forEach(p => {
            p.classList.remove('set', 'selected');
        });
        container.querySelectorAll('[data-image].set, [data-color].set').forEach(el => {
            el.classList.remove('set');
        });
    }
};

/**
 * Create a .nata-visual-panel div for a radio/checkbox input with a data-image
 * or data-color attribute, insert it after the input, and hide the original input.
 *
 * @param {HTMLInputElement} input      Radio or checkbox with data-image / data-color
 * @param {HTMLElement}      container  The .form-group wrapper
 */
function _buildPanel(input, container) {
    const imageSrc = input.dataset.image;
    const color = input.dataset.color;
    if (!imageSrc && !color) {
        return;
    }

    const panel = document.createElement('div');
    panel.className = 'nata-visual-panel';
    panel.dataset.value = input.value;
    panel.dataset.target = input.id;

    if (imageSrc) {
        const img = document.createElement('img');
        img.src = imageSrc;
        img.alt = '';
        panel.appendChild(img);
    } else if (color) {
        panel.style.backgroundColor = color;
        panel.classList.add('nata-color-panel');
    }

    if (input.checked) {
        panel.classList.add('selected');
    }

    input.after(panel);
    input.style.display = 'none';
}

export default visualOptions;
