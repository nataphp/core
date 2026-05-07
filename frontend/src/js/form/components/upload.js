import Upload from '../../upload/Upload.js';

let _groupEventsBound = false;

/**
 * Bind Nata form-group integration for file uploaders.
 *
 * Hook: `nata:form:group:after:clear`
 * - Destroys any existing Upload instance in the new row
 * - Resets uploader dataset fields and increments ids/param names
 *
 * Bound once per page lifetime (idempotent).
 *
 * @returns {void}
 */
function _bindGroupEvents() {
    if (_groupEventsBound) {
        return;
    }
    _groupEventsBound = true;

    document.querySelectorAll('[data-group]').forEach(group => {
        if (group.classList.contains('panel-group-uploader')) {
            return;
        }

            const uploaders = group.querySelectorAll('.form-file-uploader');
            if (uploaders.length === 0) {
                return;
            }

            group.addEventListener('nata:form:group:after:clear', (event) => {
                const newRow = event.detail.newRow;
                const groupName = group.dataset.group;

                newRow.querySelectorAll('.form-file-uploader').forEach(zone => {
                Upload.getInstance(zone)?.destroy();

                    zone.setAttribute('data-files', '[]');
                    zone.querySelectorAll('.dz-preview').forEach(p => p.remove());
                zone.classList.remove('set');

                    if (window.nataForm && window.nataForm.groups && zone.id) {
                        zone.id = window.nataForm.groups.increment(groupName, zone.id);
                        const param = window.nataForm.groups.increment(groupName, zone.dataset.paramName || '');
                        zone.dataset.paramName = param;
                        zone.setAttribute('data-param-name', param);
                    }
                });
            });

            group.classList.add('panel-group-uploader');
        });
}

const upload = {
    /**
     * Backwards-compatible initializer for Nata form usage.
     *
     * @param {string} [containerSelector] Optional container selector; when provided,
     * initializes all `.form-file-uploader` nodes inside that container.
     * @returns {void}
     */
    init(containerSelector) {
        const selector = containerSelector
            ? (containerSelector + ' .form-file-uploader')
            : '.form-file-uploader';

        Upload.init(selector).catch(e => console.error(e));
        _bindGroupEvents();
    },

    /**
     * Backwards-compatible destroy helper.
     *
     * @param {string} [containerSelector] Optional container selector; when provided,
     * destroys all Upload instances for `.form-file-uploader` nodes inside that container.
     * @returns {void}
     */
    destroy(containerSelector) {
        const selector = containerSelector
            ? (containerSelector + ' .form-file-uploader')
            : '.form-file-uploader';

        document.querySelectorAll(selector).forEach(node => {
            Upload.getInstance(node)?.destroy();
        });
    }
};

export default upload;
