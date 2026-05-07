/**
 * Rating component — RateYo (jQuery-dependent, deferred replacement).
 *
 * Loads and initializes RateYo only if jQuery is present on the page.
 * This will be replaced with a vanilla star rating in a future effort.
 *
 * HTML: <input class="form-rating" data-rating="3" data-num-stars="5" ... />
 */
const rating = {
    /**
     * Initialize RateYo star-rating widgets on all .form-rating inputs.
     * Skips silently when jQuery is absent. Read-only inputs are rendered
     * directly; interactive ones get a sibling container with a rateyo.set listener
     * that syncs the chosen value back to the hidden input.
     */
    init() {
        if (document.querySelectorAll('.form-rating').length === 0) {
            return;
        }

        if (typeof window.jQuery === 'undefined') {
            console.warn('NataForm rating: jQuery required for RateYo — skipping');
            return;
        }

        _loadRateYo(() => {
            window.jQuery('.form-rating:not(.set)').each(function () {
                const $input = window.jQuery(this);
                if (!$input.data('readOnly')) {
                    const $container = window.jQuery('<div>').insertAfter($input);
                    $container.rateYo($input.data()).on('rateyo.set', function (e, data) {
                        $input.val(data.rating);
                    });
                } else {
                    $input.rateYo($input.data());
                }
            }).addClass('set');
        });
    },

    /**
     * Tear down RateYo widgets inside a container.
     * Currently a no-op placeholder — RateYo does not require explicit destruction.
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        // RateYo cleanup if needed
    }
};

/**
 * Dynamically load the RateYo jQuery plugin (CSS + JS) and call cb once ready.
 * Skips loading if the plugin is already registered on jQuery.
 *
 * @param {function(): void} cb
 */
function _loadRateYo(cb) {
    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.rateYo !== 'undefined') {
        cb();
        return;
    }

    const base = document.documentElement.dataset.baseUrl || '';

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = base + '/vendor/nata/vendor/rateYo/jquery.rateyo.min.css';
    document.head.appendChild(link);

    const script = document.createElement('script');
    script.src = base + '/vendor/nata/vendor/rateYo/jquery.rateyo.min.js';
    script.onload = cb;
    document.head.appendChild(script);
}

export default rating;
