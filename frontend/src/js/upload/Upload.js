/**
 * Standalone Upload component — Dropzone v7.
 *
 * Goals:
 * - Standalone, reusable outside Nata form builder
 * - One instance per element (WeakMap registry)
 * - Async lifecycle: `instance.ready` + promise-returning `init()`
 * - Emits DOM CustomEvents (`nata:upload:*`) for decoupled integrations
 * - Keeps legacy `nata:form:upload:change` event for backwards compatibility
 */
/* global Dropzone */

// ---------------------------------------------------------------------------
// SVG file-type icon template — extension placeholder: {{EXT}}
// ---------------------------------------------------------------------------
const SVG_TEMPLATE = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="100%" height="180px" viewBox="128 0 1536 1792" enable-background="new 128 0 1536 1792" xml:space="preserve">'
    + '<polygon fill="#FFFFFF" points="1556.5,1668.5 256,1668.5 256,128 1288.5,128 1440.5,260.5 1556.5,396.5"/>'
    + '<path fill="#999" d="M1644,456c-13.333-32-29.333-57.333-48-76L1284,68c-18.667-18.667-44-34.667-76-48s-61.333-20-88-20H224c-26.667,0-49.333,9.333-68,28c-18.667,18.667-28,41.333-28,68v1600c0,26.667,9.333,49.333,28,68c18.667,18.667,41.333,28,68,28h1344c26.667,0,49.333-9.333,68-28s28-41.333,28-68V544C1664,517.333,1657.333,488,1644,456z M1152,136c19.333,6.667,33,14,41,22l313,313c8,8,15.333,21.667,22,41h-376V136z M1536,1664H256V128h768v416c0,26.667,9.333,49.333,28,68s41.333,28,68,28h416V1664z"/>'
    + '<text fill="#999" transform="matrix(1 0 0 1 301.9077 483.5)" font-family="Verdana" letter-spacing="-17" font-size="200">{{EXT}}</text>'
    + '</svg>';

// ---------------------------------------------------------------------------
// Utilities (kept compatible with old upload.js behavior)
// ---------------------------------------------------------------------------
/**
 * Translate a string using the global Nata gettext helper when available.
 *
 * @param {string} singular Translation key / default string.
 * @param {*} [args] Sprintf-style replacement values passed to `window.__`.
 * @returns {string}
 */
function _t(singular, args) {
    return typeof window.__ === 'function' ? window.__(singular, args) : singular;
}

/**
 * Translate a string with plural support using the global Nata gettext helper when available.
 *
 * @param {string} singular Singular form.
 * @param {string} plural Plural form.
 * @param {number} count Quantity used to select the correct form.
 * @returns {string}
 */
function _tn(singular, plural, count) {
    return typeof window.__n === 'function' ? window.__n(singular, plural, count) : (count === 1 ? singular : plural);
}

/**
 * Convert a byte count into a human-readable size string (e.g. "2.3 MB").
 *
 * @param {number} bytes File size in bytes.
 * @returns {string}
 */
function _humanFileSize(bytes) {
    if (window.nata && window.nata.number) {
        return window.nata.number.toReadableSize(bytes);
    }
    const thresh = 1024;
    if (bytes < thresh) {
        return bytes + ' B';
    }
    const units = ['KB', 'MB', 'GB', 'TB'];
    let u = -1;
    do {
        bytes /= thresh;
        ++u;
    } while (bytes >= thresh);
    return bytes.toFixed(1) + ' ' + units[u];
}

/**
 * Read the current CSRF token from Nata's cookie helper when available.
 *
 * @returns {string|undefined}
 */
function _csrfToken() {
    if (window.nata && window.nata.cookie) {
        return window.nata.cookie('csrfToken');
    }
    return undefined;
}

/**
 * Build an absolute URL by prepending the app base URL to the given path.
 *
 * @param {string} path Root-relative path (e.g. "/api/upload").
 * @returns {string}
 */
function _routerUrl(path) {
    if (window.nata && window.nata.router) {
        return window.nata.router.url(path);
    }
    const base = document.documentElement.dataset.baseUrl || '';
    return (base === '/' ? '' : base) + path;
}

/**
 * Display an error notification via Nata dialog when available, falling back to console.
 *
 * @param {string|[string,string]} error Error message or a `[title, body]` tuple.
 * @returns {void}
 */
function _notify(error) {
    if (window.nata && window.nata.dialog) {
        const title = Array.isArray(error) ? error[0] : error;
        const body = (Array.isArray(error) && error[1]) ? error[1] : null;
        window.nata.dialog.alert({ title, body });
        return;
    }
    console.error(Array.isArray(error) ? error.join(' — ') : error);
}

/**
 * Compute the greatest common divisor (Euclidean algorithm).
 *
 * @param {number} a
 * @param {number} b
 * @returns {number}
 */
function _gcd(a, b) {
    return b === 0 ? a : _gcd(b, a % b);
}

/**
 * Compute reduced aspect ratio string for dimensions (e.g. 1920×1080 → "16:9").
 *
 * @param {number|string} w Width in pixels.
 * @param {number|string} h Height in pixels.
 * @returns {string|undefined}
 */
function _aspectRatio(w, h) {
    if (!w || !h) {
        return undefined;
    }
    const g = _gcd(parseInt(w), parseInt(h));
    return (parseInt(w) / g) + ':' + (parseInt(h) / g);
}

/**
 * Read the app base URL prefix.
 *
 * @returns {string} Base URL without a trailing "/"-only value.
 */
function _base() {
    const base = document.documentElement.dataset.baseUrl || '';
    return base === '/' ? '' : base;
}

/**
 * Inject a stylesheet into `<head>` if it is not already present.
 *
 * @param {string} href Absolute/relative stylesheet URL.
 * @returns {void}
 */
function _injectCss(href) {
    if (!document.querySelector('link[href="' + href + '"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }
}

/**
 * Parse the JSON value of a metadata-definition attribute.
 *
 * @param {string|undefined|null} raw JSON string from `data-edit-metadata`.
 * @returns {Array<{key: string, label: string, type: string}>}
 */
function _parseEditMetadata(raw) {
    if (!raw) {
        return [];
    }
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

// Keys shared between _readConfig output and _mergeConfig allowlist.
const CONFIG_KEYS = [
    'disabled', 'dztheme', 'maxFiles', 'maxFilesize', 'acceptedFiles',
    'multiple', 'paramName', 'paramPrependName', 'store', 'presignUrl', 'formName',
    'featured', 'editMetadata', 'draggable', 'dimensions', 'aspectRatio',
    'minWidth', 'maxWidth', 'minHeight', 'maxHeight', 'portrait', 'landscape', 'files',
    'previewTarget',
];

/**
 * Read Dropzone uploader configuration from the element's dataset.
 *
 * @param {HTMLElement} el Uploader element (typically `.form-file-uploader`).
 * @returns {object}
 */
function _readConfig(el) {
    const data = (window.nata && typeof window.nata.parseDataset === 'function')
        ? window.nata.parseDataset(el)
        : (el.dataset || {});

    return {
        disabled: data.disabled,
        dztheme: data.dztheme || 'default',
        maxFiles: data.maxFiles || 1,
        maxFilesize: data.maxFilesize || 10,
        acceptedFiles: data.acceptedFiles || null,
        multiple: data.multiple,
        paramName: data.paramName || 'file',
        paramPrependName: data.paramPrependName || null,
        store: data.store || null,
        presignUrl: data.presignUrl || null,
        formName: data.formName || null,
        featured: data.featured || false,
        editMetadata: _parseEditMetadata(data.editMetadata),
        draggable: data.draggable === true || data.draggable === 1,
        dimensions: data.dimensions || null,
        aspectRatio: data.aspectRatio || null,
        minWidth: parseInt(data.minWidth) || 0,
        maxWidth: parseInt(data.maxWidth) || 0,
        minHeight: parseInt(data.minHeight) || 0,
        maxHeight: parseInt(data.maxHeight) || 0,
        portrait: data.portrait === true || data.portrait === 1,
        landscape: data.landscape === true || data.landscape === 1,
        files: data.files,
        previewTarget: data.previewTarget || null,
    };
}

/**
 * Ensure Dropzone assets are available. If Dropzone is not present, inject CSS + JS.
 *
 * @param {Function} cb Callback invoked once Dropzone is available.
 * @returns {void}
 */
function _loadDropzone(cb) {
    if (typeof Dropzone !== 'undefined') {
        cb();
        return;
    }

    const base = _base();
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = base + '/nata/vendor/dropzone/dropzone.min.css?v7.0.1';
    document.head.appendChild(link);

    const script = document.createElement('script');
    script.src = base + '/nata/vendor/dropzone/dropzone.min.js?v7.0.1';
    script.onload = cb;
    document.head.appendChild(script);
}

// ---------------------------------------------------------------------------
// Theme handlers (copied from original file)
// ---------------------------------------------------------------------------
const themes = {
    small: {
        /**
         * Theme hook invoked before Dropzone init.
         * The "small" theme adds an external "Pick files" button and uses it as Dropzone's clickable target.
         *
         * @param {HTMLElement} el
         * @param {object} config
         * @returns {void}
         */
        beforeInit(el, config) {
            if (el.classList.contains('disabled')) {
                return;
            }
            const id = el.id + '-add-files';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dztheme-small dropzone-add-files';
            btn.id = id;
            btn.innerHTML = '<span>' + _tn('Pick a file', 'Pick files', config.maxFiles) + '</span>';
            el.insertAdjacentElement('afterend', btn);
            el.dataset.clickable = '#' + id;
        },
        /**
         * Theme hook invoked when Dropzone reaches max files.
         *
         * @param {HTMLElement} el
         * @returns {void}
         */
        maxfilesreached(el) {
            const btn = document.getElementById(el.id + '-add-files');
            if (btn) {
                btn.style.display = 'none';
            }
        },
        /**
         * Theme hook invoked after a file removal.
         *
         * @param {HTMLElement} el
         * @returns {void}
         */
        removedfile(el) {
            if (!el.classList.contains('dz-max-files-reached')) {
                const btn = document.getElementById(el.id + '-add-files');
                if (btn) {
                    btn.style.display = '';
                }
            }
        },
        setInformation() {}
    },

    /**
     * Theme handler for avatar uploaders.
     *
     * Example:
     *
     * <div id="personal-avatar-uploader"
     *      class="form-file-uploader"
     *      data-dztheme="avatar"
     *      data-param-name="picture"
     *      data-preview-target="#personal-avatar-img"
     *      data-clickable="#personal-avatar-btn"
     *      data-accepted-files="image/jpeg,image/png,image/webp"
     *      data-max-files="1"
     *      data-max-filesize="5"></div>
     *
     * @type {object}
     */
    avatar: {
        /**
         * Captures the external preview <img> and its original src before Dropzone mounts.
         * Sets data-clickable to make an existing button (e.g. camera icon) trigger the file picker.
         *
         * @param {HTMLElement} el
         * @param {object} config
         * @returns {void}
         */
        beforeInit(el, config) {
            const img = config.previewTarget ? document.querySelector(config.previewTarget) : null;
            el._avatarPreviewImg = img || null;
            el._avatarOriginalSrc = img ? img.src : null;
            el._avatarRemoveBtn = null;
        },

        /**
         * Updates the external <img> as soon as a thumbnail data URL is available.
         * Receives the same dataURL argument that Dropzone passes to the thumbnail event,
         * which covers both new file picks and existing-file population via _populateExistingFiles.
         *
         * @param {HTMLElement} el
         * @param {object} file
         * @param {string} dataURL Data URL or absolute URL for the thumbnail.
         * @returns {void}
         */
        thumbnail(el, file, dataURL) {
            const img = el._avatarPreviewImg;
            const src = dataURL || file.dataURL || null;
            if (img && src) {
                img.src = src;
            }
        },

        /**
         * Injects the "×" remove button into the avatar's .relative container.
         * file.previewElement is set by Dropzone's own addedfile handler before this runs.
         *
         * @param {HTMLElement} el
         * @param {Dropzone} dz
         * @returns {void}
         */
        addedfile(el, dz) {
            if (el._avatarRemoveBtn || !el._avatarPreviewImg) {
                return;
            }
            const container = el._avatarPreviewImg.closest('.relative');
            if (!container) {
                return;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dz-avatar-remove absolute top-0 right-0 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center shadow-sm z-10';
            btn.setAttribute('aria-label', _t('Remove'));
            btn.innerHTML = '<i class="fa-solid fa-xmark text-white text-[8px]"></i>';
            btn.addEventListener('click', () => dz.removeAllFiles(true));
            container.appendChild(btn);
            el._avatarRemoveBtn = btn;
        },

        /**
         * Resets the external <img> to its original src and removes the X button.
         *
         * @param {HTMLElement} el
         * @returns {void}
         */
        removedfile(el) {
            if (el._avatarPreviewImg && el._avatarOriginalSrc !== null) {
                el._avatarPreviewImg.src = el._avatarOriginalSrc;
            }
            if (el._avatarRemoveBtn) {
                el._avatarRemoveBtn.remove();
                el._avatarRemoveBtn = null;
            }
        },

        /**
         * After existing files are populated, sync the <img> from the first file's preview URL.
         * Also injects the X button so the existing avatar can be replaced.
         *
         * @param {HTMLElement} el
         * @param {Dropzone} dz
         * @param {object} config
         * @returns {void}
         */
        afterInit(el, dz, config) {
            if (!Array.isArray(config.files) || config.files.length === 0) {
                return;
            }
            const first = config.files[0];
            if (el._avatarPreviewImg && first._preview) {
                el._avatarPreviewImg.src = _routerUrl(first._preview);
            }
            this.addedfile(el, dz);
        },

        /**
         * Cleans up injected DOM nodes and resets stored references on destroy.
         *
         * @param {HTMLElement} el
         * @returns {void}
         */
        destroy(el) {
            if (el._avatarRemoveBtn) {
                el._avatarRemoveBtn.remove();
                el._avatarRemoveBtn = null;
            }
            if (el._avatarPreviewImg && el._avatarOriginalSrc !== null) {
                el._avatarPreviewImg.src = el._avatarOriginalSrc;
            }
            el._avatarPreviewImg = null;
            el._avatarOriginalSrc = null;
        },

        maxfilesreached() {},
        setInformation() {}
    },

    default: {
        /**
         * Theme hook that inserts an information block (limits, size, constraints).
         *
         * @param {HTMLElement} el
         * @param {object} config
         * @returns {void}
         */
        setInformation(el, config) {
            const parent = el.closest('.form-group') || el.parentElement;
            if (parent && parent.querySelector('.text-background')) {
                return;
            }
            if (config.disabled) {
                return;
            }

            const container = document.createElement('div');
            container.className = 'text-background dztheme-' + config.dztheme;

            const label = document.createElement('p');
            label.className = 'text-label';
            label.innerHTML = '<i class="fa fa-upload"></i><br>' + _t('Upload files!');
            container.appendChild(label);

            const info = document.createElement('p');
            info.className = 'text-muted text-configuration';
            info.innerHTML += '<span>' + _t('Maximum: <strong>%s</strong>', _humanFileSize(config.maxFilesize * 1024 * 1024)) + '</span>';
            info.innerHTML += '&middot;<span>' + _t('Limit of <strong>%d</strong>', config.maxFiles) + '</span>';

            if (config.dimensions) {
                info.innerHTML += '&middot;<span>' + _t('Image must have the exact size of <strong>%spx</strong>', config.dimensions) + '</span>';
            }
            if (config.aspectRatio) {
                info.innerHTML += '&middot;<span>' + _t('Image must have aspect ratio <strong>%s</strong>', config.aspectRatio) + '</span>';
            }
            if (config.minHeight) {
                info.innerHTML += '&middot;<span>' + _t('Image with minimum height of <strong>%dpx</strong>', config.minHeight) + '</span>';
            }
            if (config.maxHeight) {
                info.innerHTML += '&middot;<span>' + _t('Image with maximum height of <strong>%dpx</strong>', config.maxHeight) + '</span>';
            }
            if (config.minWidth) {
                info.innerHTML += '&middot;<span>' + _t('Image with minimum width of <strong>%dpx</strong>', config.minWidth) + '</span>';
            }
            if (config.maxWidth) {
                info.innerHTML += '&middot;<span>' + _t('Image with maximum width of <strong>%dpx</strong>', config.maxWidth) + '</span>';
            }
            if (config.portrait || config.landscape) {
                info.innerHTML += '&middot;<span>' + _t('Image must be in the <strong>' + (config.landscape ? 'landscape' : 'portrait') + '</strong> orientation.') + '</span>';
            }

            container.appendChild(info);
            el.appendChild(container);
        }
    }
};

// ---------------------------------------------------------------------------
// Internal helpers for Dropzone integration (copied from original file)
// ---------------------------------------------------------------------------
/**
 * Locate the response repository (files array) for the uploader's element id.
 *
 * @param {string} id Uploader element id.
 * @param {object} response JSON response that contains a `.form` tree.
 * @returns {Array<object>|null}
 */
function _findRepository(id, response) {
    if (!response || !response.form) {
        return null;
    }

    function search(searchId, data) {
        if (!data || typeof data !== 'object') {
            return null;
        }
        for (const val of Object.values(data)) {
            if (val && typeof val === 'object') {
                if (val.id && val.id === searchId) {
                    return val;
                }
                const found = search(searchId, val);
                if (found) {
                    return found;
                }
            }
        }
        return null;
    }

    const element = search(id, response.form);
    return (element && element.files) ? element.files : null;
}

/**
 * Append a single hidden input to a parent element.
 *
 * @param {HTMLElement} parent
 * @param {string} name Input name.
 * @param {string|number|null|undefined} value Input value.
 * @returns {void}
 */
function _insertInput(parent, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value ?? '';
    parent.appendChild(input);
}

/**
 * Insert a nested tree of hidden inputs under a base field name.
 *
 * @param {HTMLElement} preview The file preview element receiving inputs.
 * @param {string} field Base field name prefix.
 * @param {object} fields Scalar or nested (one-level) values.
 * @returns {void}
 */
function _insertInputTree(preview, field, fields) {
    for (const [key, val] of Object.entries(fields)) {
        if (key === 'metadata') {
            continue;
        }
        if (val && typeof val === 'object') {
            for (const [k, v] of Object.entries(val)) {
                if (k === 'metadata') {
                    continue;
                }
                _insertInput(preview, field + '[' + key + '][' + k + ']', v);
            }
        } else {
            _insertInput(preview, field + '[' + key + ']', val);
        }
    }
}

/**
 * Replace non-image thumbnails with a SVG file-type icon.
 *
 * @param {object} file Dropzone file object.
 * @param {HTMLElement} preview Preview element.
 * @returns {void}
 */
function _applyMimeThumbnail(file, preview) {
    if (!preview) {
        return;
    }
    const repo = file.repository;
    const mime = (repo && repo.mime) ? repo.mime : (file.type || 'text/plain');
    const mimeParts = mime.split('/');

    if (mimeParts[0] === 'image') {
        return;
    }

    let ext = (repo && repo.extension) ? repo.extension : null;
    if (!ext) {
        const parts = file.name ? file.name.split('.') : [];
        ext = parts.length > 1 ? parts[parts.length - 1] : mimeParts[1];
    }

    const svgStr = SVG_TEMPLATE.replace('{{EXT}}', (ext || 'FILE').toUpperCase());
    const parser = new DOMParser();
    const doc = parser.parseFromString(svgStr, 'image/svg+xml');
    const svgEl = doc.documentElement;
    const img = preview.querySelector('.dz-image img');
    if (img) {
        img.replaceWith(svgEl);
    }
}

/**
 * Validate an image file against configured constraints (dimensions, ratio, orientation).
 *
 * @param {object} file Dropzone file object with `width` and `height`.
 * @param {object} config Parsed uploader config.
 * @returns {[string,string]|null} Error tuple or null when valid.
 */
function _validateImageDimensions(file, config) {
    if (config.minWidth && file.width < config.minWidth) {
        return [_t('Image is too small.'), _t('Needs to have at least an width of %spx', config.minWidth)];
    }
    if (config.maxWidth && file.width > config.maxWidth) {
        return [_t('Image is too big.'), _t('Maximum width of %spx', config.maxWidth)];
    }
    if (config.minHeight && file.height < config.minHeight) {
        return [_t('Image is too small.'), _t('Needs to have at least an height of %spx', config.minHeight)];
    }
    if (config.maxHeight && file.height > config.maxHeight) {
        return [_t('Image is too big.'), _t('Maximum height of %spx', config.maxHeight)];
    }
    if (config.dimensions) {
        const [dw, dh] = config.dimensions.split('x').map(Number);
        if (file.width !== dw || (dh && file.height !== dh)) {
            return [_t('Image with invalid size.'), _t('Size must be exactly %spx', config.dimensions)];
        }
    }
    if (config.aspectRatio && _aspectRatio(file.width, file.height) !== config.aspectRatio) {
        return [_t('Wrong image aspect ratio.'), _t('Image must have aspect ratio of %s', config.aspectRatio)];
    }
    if (config.portrait && file.width > file.height) {
        return [_t('Wrong image orientation.'), _t('Image must be in portrait orientation')];
    }
    if (config.landscape && file.width < file.height) {
        return [_t('Wrong image orientation.'), _t('Image must be in landscape orientation')];
    }
    return null;
}

/**
 * Render per-file action buttons (remove, cover, edit metadata) into the preview element.
 * Buttons are enabled after upload success by `_enableFileActions`.
 *
 * @param {HTMLElement} el Uploader element.
 * @param {Dropzone} dz Dropzone instance.
 * @param {object} file Dropzone file object.
 * @param {object} config Parsed config.
 * @returns {void}
 */
function _addActionButtons(el, dz, file, config) {
    if (config.disabled) {
        return;
    }

    const preview = file.previewElement;
    if (!preview) {
        return;
    }

    const container = document.createElement('div');
    container.className = 'file-actions';
    preview.appendChild(container);

    if (config.editMetadata.length > 0) {
        const label = config.editMetadata.length === 1
            ? _t('Edit') + ' ' + config.editMetadata[0].label
            : _t('Edit Details');
        const metaBtn = document.createElement('button');
        metaBtn.type = 'button';
        metaBtn.className = 'btn btn-info btn-block btn-metainfo-file btn-xs';
        metaBtn.innerHTML = '<i class="fa fa-edit"></i> <span class="btn-label">' + label + '</span>';
        metaBtn.disabled = true;
        container.appendChild(metaBtn);
    }

    if (config.featured) {
        const featBtn = document.createElement('button');
        featBtn.type = 'button';
        featBtn.className = 'btn btn-primary btn-block btn-featured-file btn-xs';
        featBtn.innerHTML = '<i class="fa fa-star"></i> <span class="btn-label">' + _t('Cover') + '</span>';
        container.appendChild(featBtn);
    }

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-danger btn-block btn-remove-file btn-xs';
    removeBtn.innerHTML = '<i class="fa fa-times"></i> <span class="btn-label">' + _t('Remove') + '</span>';
    removeBtn.addEventListener('click', () => {
        const featInput = preview.querySelector('input[name*="[featured]"]');
        const isFeatured = featInput && featInput.value === '1';
        const isLast = el.querySelectorAll('.dz-preview').length <= 2;
        dz.removeFile(file);
        if (isFeatured || isLast) {
            const first = el.querySelector('.dz-preview');
            if (first) {
                _setFeatured(el, first, config);
            }
        }
    });
    container.appendChild(removeBtn);
}

/**
 * Open a dialog to edit configured metadata fields for the uploaded file.
 *
 * @param {object} file Dropzone file object.
 * @param {HTMLElement} preview Preview element containing hidden inputs.
 * @param {object} config Parsed config.
 * @returns {void}
 */
function _openMetaModal(file, preview, config) {
    const formEl = document.createElement('div');

    config.editMetadata.forEach(field => {
        const hidden = preview.querySelector('input[name*="[' + field.key + ']"]');
        const fg = document.createElement('div');
        fg.className = 'form-group';
        const lbl = document.createElement('label');
        lbl.htmlFor = file.hash + field.key;
        lbl.className = 'control-label';
        lbl.textContent = field.label;
        let input;
        if (field.type === 'textarea') {
            input = document.createElement('textarea');
        } else {
            input = document.createElement('input');
            input.type = field.type || 'text';
        }
        input.id = file.hash + field.key;
        input.className = 'form-control';
        input.dataset.metaKey = field.key;
        input.value = hidden ? hidden.value : '';
        fg.appendChild(lbl);
        fg.appendChild(input);
        formEl.appendChild(fg);
    });

    window.nata.dialog.modal(file.hash, {
        title: _t('File %s', file.name),
        body: formEl,
        dismissable: true,
        removeOnClose: true,
        buttons: {
            ghost: {
                label: _t('Cancel'),
                click(dlg) {
                    dlg.close();
                }
            },
            primary: {
                label: _t('Done'),
                click(dlg) {
                    dlg.querySelectorAll('[data-meta-key]').forEach(input => {
                        const hidden = preview.querySelector('input[name*="[' + input.dataset.metaKey + ']"]');
                        if (hidden) {
                            hidden.value = input.value;
                        }
                    });
                    dlg.close();
                }
            }
        }
    });
}

/**
 * Enable and bind action buttons after upload success.
 *
 * @param {HTMLElement} el
 * @param {object} file Dropzone file object.
 * @param {object} config
 * @returns {void}
 */
function _enableFileActions(el, file, config) {
    if (config.disabled) {
        return;
    }

    const preview = file.previewElement;
    const container = preview && preview.querySelector('.file-actions');
    if (!container) {
        return;
    }

    if (config.featured > 0) {
        const featBtn = container.querySelector('.btn-featured-file');
        if (featBtn) {
            featBtn.disabled = false;
            featBtn.addEventListener('click', () => {
                _setFeatured(el, preview, config);
            });
        }
    }

    if (config.editMetadata.length > 0) {
        const metaBtn = container.querySelector('.btn-metainfo-file');
        if (metaBtn) {
            metaBtn.disabled = false;
            metaBtn.addEventListener('click', () => {
                _openMetaModal(file, preview, config);
            });
        }
    }
}

/**
 * Mark a preview as the featured/cover file (updates hidden inputs and UI label).
 *
 * @param {HTMLElement} el Uploader element.
 * @param {HTMLElement} preview Preview element to (un)feature.
 * @param {object} config Parsed config.
 * @returns {void}
 */
function _setFeatured(el, preview, config) {
    if (!config.featured || !preview || preview.classList.contains('dz-error')) {
        return;
    }

    const allPreviews = el.querySelectorAll('.dz-preview');
    let setFeatured = true;
    if (config.featured > 1) {
        setFeatured = !preview.classList.contains('featured');
    }

    if (config.featured === 1 || config.featured === true) {
        allPreviews.forEach(p => {
            p.classList.remove('featured');
            const fi = p.querySelector('input[name*="[featured]"]');
            if (fi) {
                fi.value = 0;
            }
            const fl = p.querySelector('span.featured-label');
            if (fl) {
                fl.remove();
            }
            const fb = p.querySelector('button.btn-featured-file');
            if (fb) {
                fb.disabled = false;
            }
        });
    }

    const existingLabel = preview.querySelector('.featured-label');
    if (!setFeatured) {
        if (existingLabel) {
            existingLabel.remove();
        }
        preview.classList.remove('featured');
    } else if (!existingLabel) {
        const span = document.createElement('span');
        span.className = 'featured-label';
        span.innerHTML = '<i class="fa fa-star"></i> ' + _t('Cover');
        preview.appendChild(span);
        preview.classList.add('featured');
    }

    const featInput = preview.querySelector('input[name*="[featured]"]');
    if (featInput) {
        featInput.value = setFeatured ? 1 : 0;
    }

    if (config.featured === 1 || config.featured === true) {
        const fb = preview.querySelector('button.btn-featured-file');
        if (fb) {
            fb.disabled = true;
        }
    } else {
        const featuredPreviews = el.querySelectorAll('.dz-preview.featured');
        const count = featuredPreviews.length;
        if (count > config.featured) {
            const first = el.querySelector('.dz-preview.featured');
            if (first && first !== preview) {
                first.classList.remove('featured');
                const fi = first.querySelector('input[name*="[featured]"]');
                if (fi) {
                    fi.value = 0;
                }
                const fl = first.querySelector('span.featured-label');
                if (fl) {
                    fl.remove();
                }
            }
        } else if (count === 1) {
            featuredPreviews.forEach(p => {
                const fb = p.querySelector('button.btn-featured-file');
                if (fb) {
                    fb.disabled = true;
                }
            });
        } else {
            el.querySelectorAll('.dz-preview .btn-featured-file').forEach(fb => {
                fb.disabled = false;
            });
        }
    }
}

/**
 * Insert all hidden inputs for a successfully uploaded file.
 *
 * @param {HTMLElement} el
 * @param {object} file Dropzone file object with `file.repository`.
 * @param {object} config Parsed config.
 * @returns {void}
 */
function _addFileInputs(el, file, config) {
    const preview = file.previewElement;
    const repo = (file && file.repository && typeof file.repository === 'object') ? file.repository : {};
    const field = config.paramPrependName
        ? config.paramPrependName + '[' + config.paramName + ']'
        : config.paramName;

    _applyMimeThumbnail(file, preview);

    let fieldKey = field;
    if (config.multiple) {
        const existingCount = el.querySelectorAll('input[name^="' + field + '"][name$="[mime]"]').length;
        fieldKey = field + '[' + (file.sha256 ?? repo.hash ?? existingCount) + ']';
    }

    const jd = repo._joinData || {};
    const inputs = {
        id: file.id ?? repo.id,
        store: repo.store ?? file.store ?? (config && config.store ? config.store : null),
        path: file.path ?? repo.path ?? repo.url ?? null,
        hash: file.sha256 ?? repo.hash ?? null,
        name: repo.name ?? file.name,
        basename: repo.basename ?? file.name,
        mime: repo.mime ?? file.type,
        size: file.size,
        width: file.width ?? repo.width ?? null,
        height: file.height ?? repo.height ?? null,
        metadata: repo.metadata ?? (file.metadata ? JSON.stringify(file.metadata) : null),
        featured: repo.featured ?? jd.featured ?? 0,
        position: repo.position ?? jd.position ?? 0,
        caption: repo.caption ?? jd.caption ?? '',
        description: repo.description ?? jd.description ?? '',
    };

    _insertInputTree(preview, fieldKey, inputs);

    if (repo.orientation && repo.orientation > 1) {
        const mark = document.createElement('div');
        mark.className = 'dz-orientation-mark';
        const icon = document.createElement('i');
        icon.className = 'fa ' + (repo.orientation === 8 ? 'fa-rotate-left' : 'fa-rotate-right');
        mark.appendChild(icon);
        const successMark = preview && preview.querySelector('.dz-success-mark');
        if (successMark) {
            successMark.insertAdjacentElement('afterend', mark);
        }
    }
}

/**
 * Upload success handler: attach repository, insert inputs, enable actions, update drag/featured state.
 *
 * @param {HTMLElement} el
 * @param {Dropzone} dz
 * @param {object} file Dropzone file object.
 * @param {object|null} repo Repository object from server response.
 * @param {object} config Parsed config.
 * @param {Function} dispatchChange Callback to emit legacy change events.
 * @returns {void}
 */
function _onSuccess(el, dz, file, repo, config, dispatchChange) {
    if (!repo) {
        return;
    }
    file.repository = repo;

    if (repo.error) {
        dz.emit('error', file, repo.error);
        return;
    }

    _addFileInputs(el, file, config);
    _enableFileActions(el, file, config);

    if (repo.featured === 1) {
        _setFeatured(el, file.previewElement, config);
    }
    if (config.draggable && !config.disabled) {
        _initDrag(el);
    }

    dispatchChange('success');
}

/**
 * Render already-existing uploaded files from `config.files` into Dropzone UI and inputs.
 *
 * @param {Dropzone} dz
 * @param {HTMLElement} el
 * @param {object} config
 * @returns {void}
 */
function _populateExistingFiles(dz, el, config) {
    if (!Array.isArray(config.files) || config.files.length === 0) {
        return;
    }

    const sorted = [...config.files].sort((a, b) => {
        if (a._joinData && b._joinData && a._joinData.position && b._joinData.position) {
            return parseInt(a._joinData.position) - parseInt(b._joinData.position);
        }
        return 0;
    });

    sorted.forEach(file => {
        if (typeof file !== 'object') {
            return;
        }
        file.repository = file;
        dz.emit('addedfile', file);
        if (file._preview) {
            dz.emit('thumbnail', file, _routerUrl(file._preview));
        }
        dz.emit('complete', file);
        _addFileInputs(el, file, config);
        _enableFileActions(el, file, config);
    });

    if (config.featured) {
        const hasFeatured = el.querySelector('.dz-preview.featured');
        if (!hasFeatured) {
            const first = el.querySelector('.dz-preview');
            if (first) {
                _setFeatured(el, first, config);
            }
        }
    }
}

/**
 * Synchronize hidden `position` inputs to match current DOM order after drag reorder.
 *
 * @param {HTMLElement} el
 * @returns {void}
 */
function _syncPositions(el) {
    let fired = false;
    el.querySelectorAll('.dz-preview').forEach((p, i) => {
        const inp = p.querySelector('input[name*="[position]"]');
        const pos = String(i + 1);
        if (inp && inp.value !== pos) {
            inp.value = pos;
            if (!fired) {
                el.dispatchEvent(new CustomEvent('nata:form:upload:change', { bubbles: true, detail: { type: 'sorted' } }));
                fired = true;
            }
        }
    });
}

/**
 * Enable vanilla HTML5 drag-to-reorder for `.dz-preview` items.
 *
 * @param {HTMLElement} el
 * @returns {void}
 */
function _initDrag(el) {
    let dragSrc = null;

    el.querySelectorAll('.dz-preview:not(.draggable)').forEach(p => {
        p.setAttribute('draggable', 'true');
        p.classList.add('draggable');

        p.addEventListener('dragstart', function (e) {
            dragSrc = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
            this.classList.add('dz-dragging');
        });

        p.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!dragSrc || dragSrc === this) {
                return;
            }
            const previews = [...el.querySelectorAll('.dz-preview')];
            if (previews.indexOf(dragSrc) < previews.indexOf(this)) {
                el.insertBefore(dragSrc, this.nextSibling);
            } else {
                el.insertBefore(dragSrc, this);
            }
        });

        p.addEventListener('dragend', function () {
            this.classList.remove('dz-dragging');
            dragSrc = null;
            _syncPositions(el);
        });

        const img = p.querySelector('img');
        if (img) {
            img.setAttribute('draggable', 'false');
            img.style.userSelect = 'none';
        }
    });
}

// ---------------------------------------------------------------------------
// Upload class
// ---------------------------------------------------------------------------
const _instances = new WeakMap();

export default class Upload {
    /**
     * Initialize Upload on one or more elements. This is the recommended entry-point for batch init.
     *
     * Normalization (Flexdatalist-style):
     * - string: querySelectorAll
     * - NodeList/Array: spread
     * - HTMLElement: wrap
     *
     * @param {string|HTMLElement|NodeList|ArrayLike<HTMLElement>} target
     * @param {object} [overrides={}] Optional config overrides.
     * @returns {Promise<Upload[]>} Resolves when all instances are ready.
     */
    static async init(target, overrides = {}) {
        let els;
        if (typeof target === 'string') {
            els = [...document.querySelectorAll(target)];
        } else if (target instanceof NodeList || Array.isArray(target)) {
            els = [...target];
        } else {
            els = [target];
        }

        const instances = els
            .filter(Boolean)
            .map(el => new Upload(el, overrides));

        await Promise.all(instances.map(u => u.ready ?? u.init()));
        return instances;
    }

    /**
     * Get the Upload instance previously registered for a given element.
     *
     * @param {HTMLElement|string} el Element or CSS selector.
     * @returns {Upload|null} The associated instance, or `null` if none exists.
     */
    static getInstance(el) {
        if (typeof el === 'string') {
            el = document.querySelector(el);
        }
        if (!(el instanceof HTMLElement)) {
            return null;
        }
        return _instances.get(el) ?? null;
    }

    /**
     * Create an Upload instance bound to a single uploader element.
     *
     * Singleton-per-element: if an instance already exists for the resolved element, the constructor
     * returns that existing instance — no additional side effects are performed.
     *
     * Call `instance.init()` (or use the static `Upload.init()`) to mount Dropzone. The constructor
     * itself is intentionally side-effect-free so callers can hold a reference before committing to init.
     *
     * @param {HTMLElement|string} target Element or CSS selector targeting the uploader element.
     * @param {object} [overrides={}] Config overrides applied on top of the element's `data-*` attributes.
     *   Accepted top-level keys mirror `_readConfig` output (see `CONFIG_KEYS`).
     *   Pass `{ dropzone: { ... } }` to forward additional options directly to the Dropzone constructor.
     *   Pass `{ cssHref: '/path/to/custom.css' }` to override the injected stylesheet, or `{ cssHref: false }` to skip injection.
     *
     * @property {HTMLElement} el The mounted uploader element.
     * @property {object} overrides Resolved override map passed to the constructor.
     * @property {Promise<Upload>|null} ready Resolves to `this` once `init()` completes; `null` before init.
     * @property {boolean} _initialized True after `_initDropzone()` completes successfully.
     * @property {boolean} _destroyed True after `destroy()` is called.
     * @property {object} _config Merged config set by `_initDropzone()`.
     * @property {string} _formAction Parent form `action` URL captured at init time.
     * @property {number} _allowedNumFiles Remaining file slots (decremented on add, incremented on remove).
     * @property {boolean} _initializing True while populating pre-existing files — suppresses legacy events.
     * @property {Array} _globalHandlers Stored `{ target, type, handler, options }` tuples for cleanup on `destroy()`.
     *
     * @throws {TypeError} If `target` does not resolve to an HTMLElement.
     */
    constructor(target, overrides = {}) {
        const el = (typeof target === 'string') ? document.querySelector(target) : target;

        if (el instanceof HTMLElement) {
            const existing = Upload.getInstance(el);
            if (existing) {
                return existing;
            }
        }

        if (!(el instanceof HTMLElement)) {
            throw new TypeError('Upload: first argument must be an HTMLElement or valid CSS selector.');
        }

        this.el = el;
        this.overrides = overrides || {};

        this.ready = null;
        this._initialized = false;
        this._destroyed = false;

        // Global listener references (if we ever attach any)
        this._globalHandlers = [];

        _instances.set(el, this);
    }

    /**
     * Dispatch a native DOM event on the uploader element.
     *
     * @param {string} name Event name (e.g. `nata:upload:success`).
     * @param {object} [detail={}] CustomEvent `detail` payload.
     * @returns {void}
     */
    _emit(name, detail = {}) {
        this.el.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }

    /**
     * Load assets and mount Dropzone on `this.el`.
     *
     * Idempotent: repeated calls return the same `Promise` — Dropzone is mounted only once.
     * Emits `nata:upload:before:init` before starting and `nata:upload:after:init` on success.
     *
     * CSS injection behaviour (controlled via `overrides.cssHref`):
     * - Default: injects `/nata/nata.upload.css` (served from `public/nata/`)
     * - Override: `{ cssHref: '/path/to/custom.css' }`
     * - Disable: `{ cssHref: false }`
     *
     * @returns {Promise<Upload>} Resolves with `this` when Dropzone is fully initialised.
     * @throws {Error} If called on a destroyed instance.
     */
    init() {
        if (this._destroyed) {
            return Promise.reject(new Error('Upload: cannot init() a destroyed instance.'));
        }
        if (this.ready) {
            return this.ready;
        }

        this._emit('nata:upload:before:init', {});

        this.ready = new Promise((resolve, reject) => {
            try {
                const cssHref = (this.overrides && Object.prototype.hasOwnProperty.call(this.overrides, 'cssHref'))
                    ? this.overrides.cssHref
                    : (_base() + '/nata/nata.upload.css');

                if (cssHref) {
                    _injectCss(cssHref);
                }

                _loadDropzone(() => {
                    if (typeof Dropzone !== 'undefined') {
                        Dropzone.autoDiscover = false;
                    }

                    try {
                        this._initDropzone();
                        this._initialized = true;
                        this._emit('nata:upload:after:init', {});
                        resolve(this);
                    } catch (e) {
                        reject(e);
                    }
                });
            } catch (e) {
                reject(e);
            }
        });

        return this.ready;
    }

    /**
     * Tear down Dropzone and revert the element to its pre-init "zero state".
     *
     * Performs: Dropzone destroy → preview removal → small-theme button removal →
     * class cleanup (`set`, `disabled`, `dz-*`, `dztheme-*`) → dataset cleanup →
     * WeakMap deregistration → global handler unbinding.
     *
     * After `destroy()`, the instance is inert. Call `new Upload(el).init()` to re-mount.
     * Emits `nata:upload:state:change` `{ from: 'active', to: 'destroyed' }` before teardown.
     *
     * @returns {void}
     */
    destroy() {
        if (this._destroyed) {
            return;
        }
        this._emit('nata:upload:state:change', { from: 'active', to: 'destroyed', reason: 'destroy' });

        // Unbind any stored global listeners
        this._globalHandlers.forEach(({ target, type, handler, options }) => {
            if (target && type && handler) {
                target.removeEventListener(type, handler, options);
            }
        });
        this._globalHandlers = [];

        // Destroy Dropzone
        if (this.el._dropzone) {
            this.el._dropzone.destroy();
            this.el._dropzone = null;
        }

        // Remove previews
        this.el.querySelectorAll('.dz-preview').forEach(p => p.remove());

        // Call theme cleanup hook if defined
        if (this._config && this._config.dztheme) {
            const activeTheme = themes[this._config.dztheme] || themes.default;
            if (typeof activeTheme.destroy === 'function') {
                activeTheme.destroy(this.el, this._config);
            }
        }

        // Remove small-theme button if present
        if (this.el.id) {
            const btn = document.getElementById(this.el.id + '-add-files');
            if (btn) {
                btn.remove();
            }
        }

        // Reset classes
        [
            'set',
            'disabled',
            'dz-started',
            'dz-max-files-reached',
            'dz-clickable',
            'dz-drag-hover',
            'dz-dragging',
        ].forEach(cls => this.el.classList.remove(cls));

        // Remove dztheme-* classes
        [...this.el.classList].forEach(cls => {
            if (cls.startsWith('dztheme-')) {
                this.el.classList.remove(cls);
            }
        });

        // Clear transient dataset
        if (this.el.dataset && this.el.dataset.clickable) {
            delete this.el.dataset.clickable;
        }

        _instances.delete(this.el);
        this._destroyed = true;
        this.ready = null;
        this._initialized = false;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Merge element dataset config with constructor overrides.
     *
     * Only keys listed in `CONFIG_KEYS` are merged from `overrides` to prevent accidental
     * pollution from unrelated properties. An additional `dropzone` key is merged as-is and
     * forwarded directly to the Dropzone constructor (passthrough for any undocumented option).
     *
     * @param {object} config Base config produced by `_readConfig`.
     * @returns {object} Merged config, always including a `dropzone` passthrough object.
     */
    _mergeConfig(config) {
        const overrides = this.overrides || {};
        const merged = { ...config };

        CONFIG_KEYS.forEach(k => {
            if (typeof overrides[k] !== 'undefined') {
                merged[k] = overrides[k];
            }
        });

        merged.dropzone = (typeof overrides.dropzone === 'object' && overrides.dropzone) ? overrides.dropzone : {};
        return merged;
    }

    /**
     * Mount Dropzone on `this.el` and configure instance state.
     *
     * Sets `this._config`, `this._formAction`, `this._allowedNumFiles`, and `this._initializing`.
     * Delegates options construction to `_buildDzOptions` and event wiring to `_bindDzEvents`
     * (called from the Dropzone `init()` hook so the Dropzone instance is already live).
     *
     * Guard: if `el._dropzone` already exists (double-init scenario), the existing instance is
     * reused and `nata:upload:after:init` is re-emitted with `{ reused: true }`.
     *
     * @returns {void}
     */
    _initDropzone() {
        const el = this.el;

        // Defensive: avoid "Dropzone already attached" when init runs twice
        // (e.g. nata.form auto-init + a manual nata.form.init() call).
        if (el._dropzone || el.dropzone) {
            el._dropzone = el._dropzone || el.dropzone;
            el.classList.add('set');
            this._emit('nata:upload:after:init', { reused: true });
            return;
        }

        const config = this._mergeConfig(_readConfig(el));
        this._config = config;

        if (config.disabled) {
            el.classList.add('disabled');
        }

        const formEl = el.closest('form');
        this._formAction = formEl ? (formEl.getAttribute('action') || '') : '';
        const theme = themes[config.dztheme] || themes.default;

        if (typeof theme.beforeInit === 'function') {
            theme.beforeInit(el, config);
        }
        el.classList.add('dztheme-' + config.dztheme);

        this._allowedNumFiles = (config.disabled || config.maxFiles === 0) ? 0 : config.maxFiles + 1;
        this._initializing = true;

        this._emit('nata:upload:state:change', { from: 'idle', to: 'ready', reason: 'dropzone-create' });

        const dzOptions = this._buildDzOptions(config, theme);
        const dz = new Dropzone('#' + el.id, { ...dzOptions, ...(config.dropzone || {}) });
        el._dropzone = dz;
        el.classList.add('set');
    }

    /**
     * Dispatch the legacy `nata:form:upload:change` event on `this.el`.
     *
     * Suppressed while `this._initializing` is `true` so that pre-populating existing files
     * does not trigger change listeners registered by the surrounding form.
     *
     * @param {string} type Payload for `detail.type` — one of `'added'`, `'removed'`, `'sending'`,
     *   `'sendingmultiple'`, `'complete'`, `'completemultiple'`, `'error'`, `'imageerror'`, `'sorted'`.
     * @returns {void}
     */
    _dispatchLegacyChange(type) {
        if (this._initializing) {
            return;
        }
        this.el.dispatchEvent(new CustomEvent('nata:form:upload:change', {
            bubbles: true,
            detail: { type }
        }));
    }

    /**
     * Adjust `this._allowedNumFiles` by `delta`.
     *
     * No-op when `config.maxFiles` is 0 (unlimited). Clamped to `>= 0` so the counter
     * never goes negative even if Dropzone fires removal events unexpectedly.
     *
     * @param {number} delta Positive to allow more files (+1 on remove), negative to reserve a slot (-1 on add).
     * @returns {void}
     */
    _updateAllowedFiles(delta) {
        if (this._config.maxFiles > 0) {
            this._allowedNumFiles = Math.max(0, this._allowedNumFiles + delta);
        }
    }

    /**
     * Disable or re-enable all `[type="submit"]` buttons in the document while an upload is active.
     *
     * When `loading` is `true`, every non-disabled submit button is disabled and marked with the
     * `nata-upload-processing` class. When `loading` is `false`, buttons are re-enabled only if
     * no `.dz-processing` previews remain in the uploader element (guards against multiple concurrent uploads).
     *
     * @param {boolean} loading `true` to block submission, `false` to restore when the queue is drained.
     * @returns {void}
     */
    _setSubmitLoading(loading) {
        const el = this.el;
        document.querySelectorAll('[type="submit"]').forEach(btn => {
            if (loading) {
                if (!btn.disabled) {
                    btn.disabled = true;
                    btn.classList.add('nata-upload-processing');
                }
            } else if (el.querySelectorAll('.dz-processing').length === 0) {
                btn.disabled = false;
                btn.classList.remove('nata-upload-processing');
            }
        });
    }

    /**
     * Assemble the Dropzone constructor options object.
     *
     * Notable decisions baked in:
     * - `binaryBody: true` + `method: 'put'` — raw binary PUT for presigned object-storage URLs.
     *   Switch to `binaryBody: false` and POST if you need presigned POST policy uploads instead.
     * - `url` defaults to the parent `<form action>` or `/upload` as a safety net (never blank,
     *   because an empty URL resolves to the current page in browsers).
     * - `resize()` scales thumbnails to a fixed 180 px height while preserving aspect ratio.
     * - `accept()` enforces the remaining-slot guard (`_allowedNumFiles`) and kicks off the
     *   presigned-URL request immediately for non-image files. For raster images, the presign
     *   is deferred to the `thumbnail` event so dimension validation can run first.
     * - The `init()` hook is a regular function (not an arrow) so Dropzone passes its own
     *   instance as `this`; the Upload instance is captured as `upload` from the outer scope.
     *
     * @param {object} config Merged uploader config (from `_mergeConfig`).
     * @param {object} theme Active theme handler (`themes.default` or `themes.small`).
     * @returns {object} Options object ready to pass to `new Dropzone(selector, options)`.
     */
    _buildDzOptions(config, theme) {
        const upload = this;
        const formAction = this._formAction;
        const clickable = config.disabled ? false : (this.el.dataset.clickable || true);

        return {
            // Never allow an empty URL ("" resolves to current page URL in browsers).
            // This is a safety net; presigned uploads will override the transport anyway.
            url: formAction || '/upload',
            clickable,
            // For presigned PUT uploads we must send the raw file body (not multipart/form-data).
            // Note: If you switch back to presigned POST policies, set binaryBody=false and use fields.
            binaryBody: true,
            method: 'put',
            maxFiles: config.disabled ? 0 : config.maxFiles,
            maxFilesize: config.maxFilesize,
            acceptedFiles: config.acceptedFiles || null,
            uploadMultiple: config.maxFiles > 1,
            parallelUploads: config.maxFiles > 1 ? config.maxFiles : 1,
            maxThumbnailFilesize: 25,
            timeout: 0,
            chunking: false,
            forceChunking: false,
            chunkSize: 5 * 1024 * 1024,
            parallelChunkUploads: true,
            retryChunks: true,
            retryChunksLimit: 3,

            previewTemplate: theme.previewTemplate !== undefined
                ? theme.previewTemplate
                : '<div class="dz-preview dz-file-preview">'
                    + '<div class="dz-image"><img data-dz-thumbnail /></div>'
                    + '<div class="dz-details">'
                    + '<div class="dz-filename"><span data-dz-name></span></div>'
                    + '<div class="dz-size" data-dz-size></div>'
                    + '</div>'
                    + '<div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>'
                    + '<div class="dz-success-mark"><span>\u2714</span></div>'
                    + '<div class="dz-error-mark"><span>\u2718</span></div>'
                    + '<div class="dz-error-message"><span data-dz-errormessage></span></div>'
                    + '</div>',

            resize(file) {
                const trgH = 180;
                const pct = (100 * trgH) / file.height;
                const trgW = Math.ceil((file.width * pct) / 100);
                return {
                    srcX: 0, srcY: 0, trgX: 0, trgY: 0,
                    srcWidth: file.width, srcHeight: file.height,
                    trgWidth: trgW, trgHeight: trgH
                };
            },

            /**
             * Accept or reject a file based on the uploader configuration.
             *
             * Called from Dropzone's `accept` hook when a file is dropped or added.
             *
             * @param {File} file Dropzone file object.
             * @param {Function} done Callback to invoke with an error message or `undefined` to accept.
             * @returns {void}
             */
            accept(file, done) {
                if (config.disabled) {
                    done(_t('Upload disabled'));
                    return;
                }
                if (upload._allowedNumFiles === 0) {
                    done(_t('You can not upload any more files.'));
                    return;
                }

                file.acceptUpload = done;
                file.rejectUpload = (msg) => done(msg);

                // For non-image files we can request the presigned URL immediately.
                // For images we defer until after dimension validation in `thumbnail`.
                const rasterImages = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp'];
                if (rasterImages.indexOf(file.type) === -1) {
                    upload._prepareAndAccept(file, config);
                }
            },

            dictFallbackMessage: _t("Your browser does not support drag'n'drop file uploads."),
            dictFallbackText: _t('Please use the fallback form below to upload your files like in the olden days.'),
            dictFileTooBig: _t('File is too big. Max filesize: %s.', _humanFileSize(config.maxFilesize * 1024 * 1024)),
            dictInvalidFileType: _t("You can't upload files of this type."),
            dictCancelUpload: _t('Cancel upload'),
            dictCancelUploadConfirmation: _t('Are you sure you want to cancel this upload?'),
            dictRemoveFile: _t('Remove'),
            dictMaxFilesExceeded: _t('You can not upload any more files.'),

            init() {
                const dz = this; // Dropzone instance
                upload._overrideUploadFiles(dz);
                upload._bindDzEvents(dz, theme);
            },

            ...(theme.dzOptions || {}),
        };
    }

    /**
     * Override Dropzone's upload transport for presigned PUT URLs.
     *
     * Dropzone is optimized for multipart/form-data uploads. Even with `method: 'PUT'`,
     * it can still attempt to send a multipart request to `options.url` as part of its
     * internal queue processing.
     *
     * For object-storage presigned PUT URLs we must upload raw bytes:
     * `PUT <presignedUrl>` with `xhr.send(file)`.
     *
     * This override ensures there is exactly one PUT request per file (to the provider),
     * and prevents accidental PUTs to the current page URL when `formAction` is empty.
     *
     * @param {Dropzone} dz
     * @returns {void}
     */
    _overrideUploadFiles(dz) {
        const originalUploadFiles = dz.uploadFiles.bind(dz);
        dz.uploadFiles = (files) => {
            const allPresignedPut = Array.isArray(files) && files.length > 0 && files.every(f => {
                return !!(f && f.uploadUrl) && !(f && f.additionalData && typeof f.additionalData === 'object');
            });

            if (!allPresignedPut) {
                originalUploadFiles(files);
                return;
            }

            files.forEach((file) => {
                const putXhr = new XMLHttpRequest();
                putXhr.open('PUT', file.uploadUrl, true);

                try {
                    putXhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
                } catch (_) {}

                putXhr.upload.onprogress = (e) => {
                    if (e && e.lengthComputable) {
                        dz.emit('uploadprogress', file, (e.loaded / e.total) * 100, e.loaded);
                    }
                };

                putXhr.onload = () => {
                    if (putXhr.status >= 200 && putXhr.status < 300) {
                        file.status = Dropzone.SUCCESS;
                        const config = this._config || {};
                        const repo = {
                            store: (file.metadata && file.metadata.store) ? file.metadata.store : (config.store || null),
                            path: file.path || null,
                            hash: file.sha256 || null,
                            name: file.name,
                            basename: file.name,
                            mime: file.type || 'application/octet-stream',
                            size: file.size,
                            width: file.width ?? null,
                            height: file.height ?? null,
                            metadata: file.metadata ? JSON.stringify(file.metadata) : null,
                            featured: 0,
                            position: 0,
                            caption: '',
                            description: '',
                        };

                        // Insert hidden inputs immediately for presigned PUT uploads.
                        // We still emit Dropzone's `success` event so the UI marks the file as successful,
                        // but downstream `success` handlers must guard against double-processing.
                        _onSuccess(this.el, dz, file, repo, config, (type) => this._dispatchLegacyChange(type));
                        this._emit('nata:upload:success', { file, repository: repo });
                        dz.emit('success', file, putXhr.responseText, putXhr);
                    } else {
                        file.status = Dropzone.ERROR;
                        dz.emit('error', file, putXhr.responseText || _t('Upload failed.'), putXhr);
                    }
                    dz.emit('complete', file);
                    dz.processQueue();
                };

                putXhr.onerror = () => {
                    file.status = Dropzone.ERROR;
                    dz.emit('error', file, _t('Upload failed.'), putXhr);
                    dz.emit('complete', file);
                    dz.processQueue();
                };

                dz.emit('processing', file);
                this._setSubmitLoading(true);
                this._dispatchLegacyChange('sending');
                this._emit('nata:upload:state:change', { from: 'ready', to: 'uploading', reason: 'sending' });
                putXhr.send(file);
            });
        };
    }

    /**
     * Wire all Dropzone event handlers and finalize uploader initialization.
     *
     * Events bound (in order):
     * - `addedfile`       — slot accounting, action buttons, legacy + DOM events
     * - `maxfilesreached` — delegates to theme handler
     * - `removedfile`     — slot accounting, legacy + theme callbacks
     * - `sending`         — sets correct HTTP method/URL, CSRF header, presigned POST fields
     * - `sendingmultiple` — submit-button lock + legacy/state events
     * - `complete`        — submit-button unlock, `nata:upload:after:upload`, state change
     * - `completemultiple`— legacy event only
     * - `success`         — single-file path: find repo in response → `_onSuccess`
     * - `successmultiple` — multi-file path: iterate repos → `_onSuccess` per file
     * - `thumbnail`       — image dimension validation → presign or reject
     * - `error`           — notify user, restore slot, emit `nata:upload:error`
     * - `errormultiple`   — fans out to individual `error` events
     *
     * After wiring events: calls `theme.setInformation`, `_populateExistingFiles`, optionally
     * `_initDrag`, then sets `this._initializing = false` to unmute legacy change events.
     *
     * @param {Dropzone} dz Fully constructed Dropzone instance (received from the `init()` hook).
     * @param {object} theme Active theme handler object.
     * @returns {void}
     */
    _bindDzEvents(dz, theme) {
        const el = this.el;
        const config = this._config;
        const formAction = this._formAction;

        /**
         * Update slot accounting and dispatch legacy change event.
         *
         * Called from Dropzone's `addedfile` hook when a file is added.
         *
         * @param {File} file Dropzone file object.
         * @returns {void}
         */
        dz.on('addedfile', (file) => {
            this._updateAllowedFiles(-1);
            _addActionButtons(el, dz, file, config);
            this._dispatchLegacyChange('added');
            this._emit('nata:upload:before:upload', { file });
            if (typeof theme.addedfile === 'function') {
                theme.addedfile(el, dz, file);
            }
        });

        /**
         * Delegate to theme handler when the maximum number of files is reached.
         *
         * Called from Dropzone's `maxfilesreached` hook when the maximum number of files is reached.
         *
         * @returns {void}
         */
        dz.on('maxfilesreached', () => {
            if (typeof theme.maxfilesreached === 'function') {
                theme.maxfilesreached(el);
            }
        });

        /**
         * Update slot accounting and dispatch legacy change event.
         *
         * Called from Dropzone's `removedfile` hook when a file is removed.
         *
         * @returns {void}
         */
        dz.on('removedfile', () => {
            this._updateAllowedFiles(+1);
            this._dispatchLegacyChange('removed');
            if (typeof theme.removedfile === 'function') {
                theme.removedfile(el);
            }
        });

        /**
         * Set the correct HTTP method/URL, CSRF header, presigned POST fields.
         *
         * Called from Dropzone's `sending` hook when a file is about to be uploaded.
         *
         * @param {File} file Dropzone file object.
         * @param {XMLHttpRequest} xhr The XHR object used for the upload.
         * @param {FormData} formData The form data object (multipart/form-data).
         * @returns {void}
         */
        dz.on('sending', (file, xhr, formData) => {
            // If we are uploading to the app (same-origin), keep CSRF header.
            // For presigned uploads to S3/R2 (cross-origin) avoid custom headers to prevent CORS issues.
            const uploadUrl = file && file.uploadUrl ? file.uploadUrl : formAction;
            const isPresignedPost = !!(file && file.additionalData && typeof file.additionalData === 'object');
            const httpMethod = isPresignedPost ? 'POST' : String(dz.options.method || 'POST').toUpperCase();

            // Presigned POST policy: backend returns `{ url, fields }` — use Dropzone's default multipart transport.
            // Presigned PUT URL: backend returns `{ url }` only — bypassed via _overrideUploadFiles.
            try {
                xhr.open(httpMethod, uploadUrl, true);
            } catch (_) {}
            try {
                const resolved = new URL(uploadUrl, window.location.href);
                if (resolved.origin === window.location.origin) {
                    const token = _csrfToken();
                    if (token !== undefined) {
                        xhr.setRequestHeader('X-CSRF-Token', token);
                    }
                }
            } catch (_) {}

            // Presigned POST policy fields must be included in the multipart/form-data body.
            if (isPresignedPost && formData && typeof formData.append === 'function') {
                Object.entries(file.additionalData).forEach(([k, v]) => {
                    if (typeof v !== 'undefined' && v !== null) {
                        formData.append(k, v);
                    }
                });
            }

            this._setSubmitLoading(true);
            this._dispatchLegacyChange('sending');
            this._emit('nata:upload:state:change', { from: 'ready', to: 'uploading', reason: 'sending' });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `sendingmultiple` hook when multiple files are about to be uploaded.
         *
         * @returns {void}
         */
        dz.on('sendingmultiple', () => {
            this._setSubmitLoading(true);
            this._dispatchLegacyChange('sendingmultiple');
            this._emit('nata:upload:state:change', { from: 'ready', to: 'uploading', reason: 'sendingmultiple' });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `complete` hook when a file upload is complete.
         *
         * @param {File} file Dropzone file object.
         * @returns {void}
         */
        dz.on('complete', (file) => {
            if (file.previewElement) {
                file.previewElement.classList.remove('dz-processing');
            }
            this._setSubmitLoading(false);
            this._dispatchLegacyChange('complete');
            this._emit('nata:upload:after:upload', { file });
            this._emit('nata:upload:state:change', {
                from: 'uploading',
                to: 'ready',
                reason: 'complete',
                file: file
            });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `completemultiple` hook when multiple files are uploaded.
         *
         * @returns {void}
         */
        dz.on('completemultiple', () => {
            this._dispatchLegacyChange('completemultiple');
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `success` hook when a file upload is successful.
         *
         * @param {File} file Dropzone file object.
         * @param {object} response The response object from the server.
         * @returns {void}
         */
        dz.on('success', (file, response) => {
            if (file && file.repository) {
                return;
            }
            if (config.maxFiles > 1) {
                return;
            }
            if (!response || !response.form) {
                console.warn('nata:upload: missing form instance from response');
                return;
            }
            let repo = _findRepository(el.id, response);
            if (Array.isArray(repo) && typeof repo[0] === 'object') {
                repo = repo[0];
            }
            _onSuccess(el, dz, file, repo, config, (type) => this._dispatchLegacyChange(type));
            this._emit('nata:upload:success', { file, repository: repo });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `successmultiple` hook when multiple files are uploaded.
         *
         * @param {File[]} files Array of Dropzone file objects.
         * @param {object} response The response object from the server.
         * @returns {void}
         */
        dz.on('successmultiple', (files, response) => {
            files.forEach((file, i) => {
                if (file && file.repository) {
                    return;
                }
                if (!response || !response.form) {
                    console.warn('nata:upload: missing form instance from response');
                    return;
                }
                const repos = _findRepository(el.id, response);
                const repo = Array.isArray(repos) ? repos[i] : repos;
                _onSuccess(el, dz, file, repo, config, (type) => this._dispatchLegacyChange(type));
                this._emit('nata:upload:success', { file, repository: repo });
            });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `thumbnail` hook when a file thumbnail is generated.
         *
         * @param {File} file Dropzone file object.
         * @returns {void}
         */
        dz.on('thumbnail', (file, dataURL) => {
            if (typeof theme.thumbnail === 'function') {
                theme.thumbnail(el, file, dataURL, config);
            }
            if (!file.rejectUpload) {
                return;
            }
            const err = _validateImageDimensions(file, config);
            // Backend presign endpoint is authoritative; we intentionally do not reject here.
            // (We still compute `err` so future UI hooks can show a local hint if desired.)
            this._prepareAndAccept(file, config);
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `error` hook when a file upload fails.
         *
         * @param {File} file Dropzone file object.
         * @param {string} errorMessage The error message from the server.
         * @param {XMLHttpRequest} xhr The XHR object used for the upload.
         * @returns {void}
         */
        dz.on('error', (file, errorMessage, xhr) => {
            let error;
            if (xhr && typeof errorMessage !== 'string') {
                const detail = xhr.statusText ? (xhr.status + ' · ' + xhr.statusText) : _t('Unknown error.');
                error = [_t('File %s not uploaded', file.name), detail];
            } else {
                error = [_t('File %s not uploaded', file.name), errorMessage];
            }
            _notify(error);
            this._dispatchLegacyChange('error');
            this._updateAllowedFiles(+1);
            this._emit('nata:upload:error', { file, error });
            this._emit('nata:upload:state:change', { from: 'uploading', to: 'ready', reason: 'error' });
        });

        /**
         * Update submit button state and dispatch legacy change event.
         *
         * Called from Dropzone's `errormultiple` hook when multiple files fail to upload.
         *
         * @param {File[]} files Array of Dropzone file objects.
         * @param {string} errorMessage The error message from the server.
         * @param {XMLHttpRequest} xhr The XHR object used for the upload.
         * @returns {void}
         */
        dz.on('errormultiple', (files, errorMessage, xhr) => {
            files.forEach(file => dz.emit('error', file, errorMessage, xhr));
        });

        if (typeof theme.setInformation === 'function') {
            theme.setInformation(el, config);
        }

        _populateExistingFiles(dz, el, config);

        if (typeof theme.afterInit === 'function') {
            theme.afterInit(el, dz, config);
        }

        if (config.draggable && !config.disabled) {
            _initDrag(el);
        }

        this._initializing = false;
    }

    /**
     * SHA-256 hash the file, request a presigned upload URL, then accept or reject the file.
     *
     * Flow:
     * 1. Hash the file with `_calculateSha256` (Web Crypto API, reads entire file into RAM).
     * 2. POST `{ name, type, mime, size, width, height, orientation, extension, store, sha256 }`
     *    to the presign endpoint (`config.presignUrl` or `/upload/presigned-form-upload-url`).
     * 3a. On success with `{ url, fields }` → presigned POST policy: set `file.uploadUrl` +
     *    `file.additionalData`; Dropzone uses its default multipart transport.
     * 3b. On success with `{ url }` only → presigned PUT: set `file.uploadUrl` only;
     *    `_overrideUploadFiles` bypasses Dropzone's transport and sends raw bytes.
     * 4. On any error, calls `file.rejectUpload` so Dropzone can mark the file as failed.
     *
     * Called from `accept()` (non-image files) and `thumbnail` handler (image files, post-validation).
     *
     * @param {object} file Dropzone file object with `file.acceptUpload` / `file.rejectUpload` already set.
     * @param {object} config Merged uploader config.
     * @returns {Promise<void>}
     */
    async _prepareAndAccept(file, config) {
        try {
            const hash = await this._calculateSha256(file);
            file.sha256 = hash;

            const deriveExtension = (name) => {
                if (!name || typeof name !== 'string') {
                    return null;
                }
                const parts = name.split('.');
                if (parts.length < 2) {
                    return null;
                }
                return '.' + parts.pop().toLowerCase();
            };

            file.metadata = {
                name: file.name,
                formName: config.formName,
                field: config.paramName,
                type: file.type,
                mime: file.type,
                size: file.size,
                width: file.width ?? null,
                height: file.height ?? null,
                orientation: file.width > file.height ? 'landscape' : 'portrait',
                extension: deriveExtension(file.name),
                store: config.store || null,
                sha256: hash
            };

            const presignUrl = _routerUrl(config.presignUrl || '/upload/presigned-form-upload-url');
            const res = await fetch(presignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': _csrfToken()
                },
                body: JSON.stringify(file.metadata)
            });

            let data = null;
            try {
                data = await res.json();
            } catch (_) {
                data = null;
            }

            if (!res.ok) {
                const msg = (data && data.error) ? data.error : _t('Could not get upload URL.');
                file.rejectUpload(msg);
                return;
            }

            if (!data || data.error) {
                file.rejectUpload((data && data.error) ? data.error : _t('Could not get upload URL.'));
                return;
            }

            if (!data.url) {
                file.rejectUpload(_t('Could not get upload URL.'));
                return;
            }

            file.uploadUrl = data.url;
            file.path = data.path;
            file.store = data.store;
            file.additionalData = (data.fields && typeof data.fields === 'object') ? data.fields : null;
            file.acceptUpload();
        } catch (err) {
            file.rejectUpload(_t('Error calculating file hash or requesting URL.'));
        }
    }

    /**
     * Compute the SHA-256 digest of a `File` using the native Web Crypto API.
     *
     * The entire file is read into an `ArrayBuffer` before hashing, so memory usage scales
     * linearly with file size. For files >100 MB consider streaming alternatives if RAM is a concern.
     *
     * @param {File} file The file to hash.
     * @returns {Promise<string>} Lowercase hex-encoded SHA-256 digest (64 characters).
     */
    async _calculateSha256(file) {
        const crypto = window.crypto || window.msCrypto;
        // For very large files (>100 MB) this consumes RAM proportional to the file size.
        const hashBuffer = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
        return Array.from(new Uint8Array(hashBuffer))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

}

