/**
 * NataPHP nata.form.js — jQuery-independent form library.
 *
 * Exposes window.nata.form and auto-initialises on DOMContentLoaded.
 *
 * Usage (PHP template):
 *   <script src="/nata/nata.form.js"></script>
 */
import submit        from './submit.js';
import validation    from './validation.js';
import groups        from './groups.js';
import changes       from './changes.js';
import livevalidation from './livevalidation.js';
import { showWhen, hideWhen } from './showwhen.js';
import translate     from './translate.js';
import datetime      from './components/datetime.js';
import autocomplete  from './components/autocomplete.js';
import upload        from './components/upload.js';
import editor        from './components/editor.js';
import rating        from './components/rating.js';
import mask          from './components/mask.js';
import location      from './components/location.js';
import visualOptions from './components/visualoptions.js';

const components = {
    datetime,
    autocomplete,
    upload,
    editor,
    rating,
    mask,
    location,
    visualOptions,

    /**
     * Initialize all form UI components (datetime pickers, autocomplete, file upload,
     * rich-text editor, rating, input masks, location maps, and visual options).
     * Can be called again after dynamic content insertion (e.g. new group rows).
     */
    init() {
        datetime.init();
        autocomplete.init();
        upload.init();
        editor.init();
        rating.init();
        mask.init();
        location.init();
        visualOptions.init();
    }
};

const form = {
    submit,
    validation,
    groups,
    changes,
    livevalidation,
    showWhen,
    hideWhen,
    translate,
    components,

    /**
     * Full initialisation — call once per page load, or after dynamic content insertion.
     */
    init() {
        submit.init();
        groups.init();
        changes.init();
        livevalidation.init();
        showWhen.init();
        hideWhen.init();
        translate.init();
        components.init();
    }
};

// Expose on window
window.nata = window.nata || {};
window.nata.form = form;

// Inject form CSS
(function () {
    const base = document.documentElement.dataset.baseUrl || '';
    const href = (base === '/' ? '' : base) + '/nata/nata.form.min.css';
    if (!document.querySelector('link[href="' + href + '"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }
}());

// Auto-init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => form.init());
} else {
    form.init();
}

export default form;
