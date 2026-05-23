/**
 * Nata Notifier — vanilla JS notification toasts
 *
 * No jQuery, no Bootstrap dependency.
 * Styles: frontend/src/css/form/nata.notifier.css
 *
 * Usage:
 *   nata.notifier('Title');
 *   nata.notifier('Title', { type: 'success', body: 'All done.' });
 *   nata.notifier(['Title', 'Body text'], { type: 'warning' });
 *   nata.notifier({ title: 'Title', body: 'Body', type: 'danger' });
 */

const DEFAULTS = {
    container: 'body',
    id: null,
    title: '',
    body: '',
    type: 'info',         // 'info' | 'success' | 'warning' | 'danger'
    offsetX: 35,          // Horizontal offset in px
    offsetY: 55,          // Vertical offset in px
    position: 'top',      // 'top' | 'bottom'
    align: 'center',      // 'left' | 'center' | 'right'
    width: 'auto',
    duration: 7000,       // Auto-dismiss delay in ms; 0 = no auto-dismiss
    transition: 'all 800ms ease',
    dismissable: true,
    icon: true,           // true = auto-detect FA / fallback SVG; false = none; string = custom
    stackupSpacing: 10,   // Gap between stacked toasts in px
    showing: () => {},    // Fired after the toast animates into view
    clicked: () => {},    // Fired when the toast body is clicked
    dismissed: () => {},  // Fired when closed via the × button
    hidden: () => {},     // Fired when the toast leaves the DOM
    handler: 'default',   // 'default' | 'system' (Web Notifications API)
};

// Inline SVG icons — used when FontAwesome is not available
const SVG_ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5-4-4 1.41-1.41L10 13.67l6.59-6.59L18 8.5l-8 8z"/></svg>',
    danger:  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
    info:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
};

// Inject Notifier CSS
(function () {
    const base = document.documentElement.dataset.baseUrl || '';
    const href = (base === '/' ? '' : base) + '/nata/nata.notifier.min.css';
    if (!document.querySelector('link[href="' + href + '"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }
}());

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function _detectFontAwesome() {
    const prefixes = ['fas', 'far', 'fa'];
    for (const prefix of prefixes) {
        const span = document.createElement('span');
        span.className = prefix;
        span.style.display = 'none';
        document.body.insertBefore(span, document.body.firstChild);
        const fontFamily = (window.getComputedStyle(span).fontFamily ?? '').toLowerCase().replace(/\s/g, '');
        document.body.removeChild(span);
        if (fontFamily.includes('fontawesome')) {
            const match = fontFamily.match(/(\d+)/);
            return { installed: true, version: match ? parseInt(match[0]) : 4, prefix };
        }
    }
    return { installed: false, version: null, prefix: 'fa' };
}

function _faIconClass(prefix, type) {
    const map = {
        success: 'fa-check-circle',
        danger:  'fa-times-circle',
        warning: 'fa-warning',
        info:    'fa-info-circle',
    };
    return `${prefix} ${map[type] ?? 'fa-info-circle'}`;
}

function _restack(position, align, offsetY, spacing) {
    const nodes = document.querySelectorAll(`.notifier[data-position="${position}"][data-align="${align}"]`);
    let offset = offsetY;
    for (const el of nodes) {
        el.style[position] = offset + 'px';
        offset += el.offsetHeight + spacing;
    }
}

// ---------------------------------------------------------------------------
// Web Notifications API (system) handler
// ---------------------------------------------------------------------------

function _systemNotification(opts) {
    const create = () => {
        const n = new Notification(opts.title, {
            tag: opts.id,
            body: opts.body,
            icon: typeof opts.icon === 'string' ? opts.icon : undefined,
            requireInteraction: !(opts.duration > 0),
        });
        n.addEventListener('show', opts.showing, false);
        n.addEventListener('close', opts.hidden, false);
        return n;
    };

    if (Notification.permission === 'granted') {
        return create();
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(perm => {
            if (perm === 'granted') {
                create();
            }
        });
    }
}

// ---------------------------------------------------------------------------
// Custom (in-page) toast handler
// ---------------------------------------------------------------------------

function _buildIcon(opts) {
    const wrap = document.createElement('span');
    wrap.className = 'notifier-icon';

    if (opts.icon === true) {
        const fa = _detectFontAwesome();
        if (fa.installed) {
            const i = document.createElement('i');
            i.className = _faIconClass(fa.prefix, opts.type);
            wrap.appendChild(i);
        } else {
            wrap.innerHTML = SVG_ICONS[opts.type] ?? SVG_ICONS.info;
        }
    } else if (/<\/?[a-z][\s\S]*>/i.test(opts.icon)) {
        wrap.innerHTML = opts.icon;
    } else if (opts.icon.indexOf('.') === -1) {
        const i = document.createElement('i');
        i.className = opts.icon;
        wrap.appendChild(i);
    } else {
        const img = document.createElement('img');
        img.src = opts.icon;
        img.alt = '';
        wrap.appendChild(img);
    }

    return wrap;
}

function _customNotification(opts) {
    opts.offsetY = parseInt(opts.offsetY);
    opts.offsetX = parseInt(opts.offsetX);
    opts.stackupSpacing = parseInt(opts.stackupSpacing);

    // Generate a stable ID
    if (!opts.id) {
        opts.id = (opts.title.substring(0, 10).replace(/\W/g, '').toLowerCase()) || 'msg';
    }
    opts.id = `notifier-${opts.type}-${opts.id}`;

    if (sessionStorage.getItem(opts.id)) {
        return;
    }

    // Remove any duplicate
    document.getElementById(opts.id)?.remove();

    const containerEl = (typeof opts.container === 'string')
        ? document.querySelector(opts.container)
        : opts.container;

    // ── Build toast ────────────────────────────────────────────
    const toast = document.createElement('div');
    toast.id = opts.id;
    toast.className = `notifier notifier-${opts.type} notifier-${opts.position} notifier-${opts.align}`;
    toast.setAttribute('data-position', opts.position);
    toast.setAttribute('data-align', opts.align);
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    // Inner alert row
    const alertDiv = document.createElement('div');
    alertDiv.className = 'notifier-alert';
    toast.appendChild(alertDiv);

    // Icon
    if (opts.icon) {
        const iconEl = _buildIcon(opts);
        alertDiv.appendChild(iconEl);
        alertDiv.classList.add(opts.body ? 'notifier-has-icon-large' : 'notifier-has-icon');
    }

    // Text block
    const textDiv = document.createElement('div');
    textDiv.className = 'notifier-text';

    const titleEl = document.createElement('strong');
    titleEl.textContent = opts.title;
    textDiv.appendChild(titleEl);

    if (opts.body) {
        const bodyEl = document.createElement('div');
        bodyEl.className = 'notifier-body';
        bodyEl.innerHTML = opts.body;
        textDiv.appendChild(bodyEl);
    }

    alertDiv.appendChild(textDiv);

    // Close button
    if (opts.dismissable) {
        const closeBtn = document.createElement('button');
        closeBtn.className = 'notifier-close';
        closeBtn.setAttribute('type', 'button');
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => {
            toast.remove();
            _restack(opts.position, opts.align, opts.offsetY, opts.stackupSpacing);
            opts.dismissed(toast, opts);
            opts.hidden(toast, opts);
        });
        toast.appendChild(closeBtn);
    }

    // Click callback on the body
    alertDiv.addEventListener('click', () => opts.clicked(toast, opts));

    // ── Position & style ───────────────────────────────────────
    const isBodyContainer = opts.container === 'body' || containerEl === document.body;
    toast.style.cssText = [
        `position:${isBodyContainer ? 'fixed' : 'absolute'}`,
        'z-index:9999999',
        `transition:${opts.transition}`,
        `width:${opts.width}`,
        // Start off-screen
        `${opts.position}:${-(120 + opts.offsetY)}px`,
    ].join(';');

    switch (opts.align) {
        case 'center':
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            break;
        case 'left':
            toast.style.left = opts.offsetX + 'px';
            break;
        default: // right
            toast.style.right = opts.offsetX + 'px';
    }

    containerEl.insertBefore(toast, containerEl.firstChild);

    // ── Animate in ─────────────────────────────────────────────
    // rAF double-frame trick ensures the off-screen position is painted first
    requestAnimationFrame(() => requestAnimationFrame(() => {
        _restack(opts.position, opts.align, opts.offsetY, opts.stackupSpacing);
        toast.addEventListener('transitionend', () => opts.showing(toast, opts), { once: true });
    }));

    // ── Auto-dismiss ───────────────────────────────────────────
    if (opts.duration > 0) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.addEventListener('transitionend', () => {
                toast.remove();
                _restack(opts.position, opts.align, opts.offsetY, opts.stackupSpacing);
                opts.hidden(toast, opts);
            }, { once: true });
        }, opts.duration);
    }

    sessionStorage.setItem(opts.id, true);

    return toast;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Display a notification toast.
 *
 * Signatures:
 *   notifier(title [, options])
 *   notifier([title, body] [, options])
 *   notifier(optionsWithTitle)
 *
 * @param {string|Array|object} title
 * @param {object}              [options]
 * @returns {HTMLElement|Notification}
 */
function notifier(title, options = {}) {
    if (title !== null && typeof title === 'object' && !Array.isArray(title) && typeof title.title !== 'undefined') {
        options = title;
        title = undefined;
    }

    if (title !== undefined) {
        if (typeof title === 'string') {
            options.title = title;
        } else if (typeof title[1] === 'undefined') {
            options.title = title[0];
        } else {
            options.title = title[0];
            options.body = title[1];
        }
    }

    const opts = Object.assign({}, DEFAULTS, options);

    if (opts.type === 'error') {
        opts.type = 'danger';
    }

    if (opts.handler === 'system' && !('Notification' in window)) {
        opts.handler = 'default';
    }

    return opts.handler === 'system' ? _systemNotification(opts) : _customNotification(opts);
}

export default notifier;
