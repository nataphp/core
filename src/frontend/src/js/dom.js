/**
 * DOM utilities — jQuery-independent.
 */
const dom = {
    /**
     * Scroll smoothly to a target element.
     *
     * Note: unlike the jQuery version, the `callback` is fired via the
     * native `scrollend` event (supported in modern browsers). In browsers
     * without `scrollend`, the callback fires on the next animation frame
     * after the scroll call as a graceful fallback.
     *
     * @param {string|object} options Selector string or options object
     * @param {Function} [callback]
     */
    scrollTo(options, callback) {
        if (typeof options === 'string' || typeof options.target === 'undefined') {
            options = { target: options };
        }

        options = Object.assign({
            target: null,
            offset: 150,
            container: null
        }, options);

        if (!options.target) {
            console.warn('nata.dom.scrollTo(): Ignored. Missing target option.');
            return;
        }

        const target = typeof options.target === 'string'
            ? document.querySelector(options.target)
            : options.target;

        if (!target) {
            return;
        }

        const top = target.getBoundingClientRect().top + window.scrollY - options.offset;

        if (callback) {
            if ('onscrollend' in window) {
                window.addEventListener('scrollend', () => callback(), { once: true });
            } else {
                requestAnimationFrame(() => requestAnimationFrame(() => callback()));
            }
        }

        window.scrollTo({ top, behavior: 'smooth' });
    },

    /**
     * Check if a target element is in the viewport.
     *
     * @param {object} options
     * @return {boolean}
     */
    inView(options) {
        options = Object.assign({
            target: null,
            offset: 0,
            fullyInView: false
        }, options);

        const element = typeof options.target === 'string'
            ? document.querySelector(options.target)
            : options.target;

        if (!element) {
            return;
        }

        const pageTop = window.scrollY;
        const pageBottom = pageTop + window.innerHeight;
        const rect = element.getBoundingClientRect();
        const elementTop = rect.top + window.scrollY - options.offset;
        const elementBottom = elementTop + rect.height;

        if (options.fullyInView) {
            return (pageTop < elementTop) && (pageBottom > elementBottom);
        }

        return (elementTop <= pageBottom) && (elementBottom >= pageTop);
    },

    /**
     * Watch a target element and call callback when it enters/leaves viewport.
     *
     * @param {object} options
     */
    inViewNew(options) {
        options = Object.assign({
            target: null,
            offset: 0,
            fullyInView: false,
            callback: () => {}
        }, options);

        const element = typeof options.target === 'string'
            ? document.querySelector(options.target)
            : options.target;

        if (!element) {
            return;
        }

        const check = () => {
            const pageTop = window.scrollY;
            const pageBottom = pageTop + window.innerHeight;
            const rect = element.getBoundingClientRect();
            const elementTop = rect.top + window.scrollY - options.offset;
            const elementBottom = elementTop + rect.height;
            const inView = (elementTop <= pageBottom) && (elementBottom >= pageTop);
            const fullyInView = (pageTop < elementTop) && (pageBottom > elementBottom);

            if (options.callback) {
                options.callback(element, inView, fullyInView);
            }
        };

        if (options.callback) {
            if (!window._nataInViewListenerSet) {
                window.addEventListener('scroll', () => {
                    (window._nataInViewCallbacks || []).forEach(cb => cb());
                });
                window._nataInViewListenerSet = true;
                window._nataInViewCallbacks = [];
            }
            window._nataInViewCallbacks.push(check);
        }

        check();
    },

    /**
     * Make a target element sticky on scroll.
     *
     * Dispatches CustomEvents: 'stuck', 'unstuck', 'stuck-change', 'stuck-direction'
     * on the target element (replacing the old jQuery .trigger() calls).
     *
     * @param {object} options
     * @return {boolean}
     */
    sticky(options) {
        options = Object.assign({
            target: null,
            scrollTarget: window,
            offset: 0,
            onScrollUp: false,
            autoWidth: false,
            handler: () => {}
        }, options);

        const stickyTarget = typeof options.target === 'string'
            ? document.querySelector(options.target)
            : options.target;

        if (!stickyTarget) {
            console.warn('Sticky: target "' + options.target + '" not found.');
            return false;
        }

        if (stickyTarget.parentElement && stickyTarget.parentElement.classList.contains('nata-sticky-wrapper')) {
            console.warn('Sticky: target "' + options.target + '" already attached.');
            return false;
        }

        const scrollElem = options.scrollTarget === window ? window : (
            typeof options.scrollTarget === 'string'
                ? document.querySelector(options.scrollTarget)
                : options.scrollTarget
        );

        const wrapper = document.createElement('div');
        wrapper.className = 'nata-sticky-wrapper';
        stickyTarget.parentNode.insertBefore(wrapper, stickyTarget);
        wrapper.appendChild(stickyTarget);

        const wrapperRect = wrapper.getBoundingClientRect();
        const targetRect = stickyTarget.getBoundingClientRect();
        const stickyOffset = targetRect.top - wrapperRect.top + options.offset;
        stickyTarget._nataOffset = stickyOffset;

        const setHeight = () => {
            if (!stickyTarget.classList.contains('stuck')) {
                wrapper.style.height = stickyTarget.offsetHeight + 'px';
            }
        };

        const autoWidth = (enabled) => {
            if (options.autoWidth === false) {
                return;
            }
            if (enabled === false) {
                stickyTarget.removeAttribute('style');
                return;
            }
            stickyTarget.style.width = stickyTarget.offsetWidth + 'px';
        };

        const getScrollTop = () => scrollElem === window
            ? window.scrollY
            : scrollElem.scrollTop;

        let prevScrollOffset = getScrollTop();
        let resizeTimeout = null;
        let prevDirection = null;

        const check = (scrolling) => {
            let scrollOffset = getScrollTop();
            scrollOffset = scrollOffset < 2 ? 0 : scrollOffset;
            let direction = null;
            let stuck = scrollOffset > stickyTarget._nataOffset;

            if (scrolling) {
                direction = scrollOffset > prevScrollOffset ? 'down' : 'up';
            }

            if (options.onScrollUp && direction === 'down') {
                stuck = false;
            }

            if (scrollOffset > 0) {
                const handlerResult = options.handler(stuck, direction);
                if (typeof handlerResult === 'boolean') {
                    stuck = handlerResult;
                }
            }

            if (stuck) {
                autoWidth();
                setHeight();

                if (!stickyTarget.classList.contains('stuck')) {
                    stickyTarget.classList.add('stuck');
                    stickyTarget.dispatchEvent(new CustomEvent('stuck', { detail: { direction, scrollOffset }, bubbles: true }));
                    stickyTarget.dispatchEvent(new CustomEvent('stuck-change', { detail: { stuck: true, direction, scrollOffset }, bubbles: true }));
                }
            } else if (stickyTarget.classList.contains('stuck')) {
                autoWidth(false);
                stickyTarget.classList.remove('stuck');
                stickyTarget.dispatchEvent(new CustomEvent('unstuck', { detail: { direction, scrollOffset }, bubbles: true }));
                stickyTarget.dispatchEvent(new CustomEvent('stuck-change', { detail: { stuck: false, direction, scrollOffset }, bubbles: true }));
            }

            if (direction !== prevDirection) {
                stickyTarget.dispatchEvent(new CustomEvent('stuck-direction', { detail: { direction, scrollOffset }, bubbles: true }));
            }

            prevScrollOffset = scrollOffset;
            prevDirection = direction;
        };

        scrollElem.addEventListener('scroll', () => check(true));

        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => setHeight(), 500);
        });

        check(false);

        return true;
    },

    /**
     * Check if an event's target is outside of the given element(s).
     *
     * @param {string|string[]} elem CSS selector(s)
     * @param {Event} event
     * @return {boolean}
     */
    isEventOutsideOf(elem, event) {
        if (typeof elem === 'string') {
            elem = [elem];
        }

        const target = event.target;
        let isOutside = true;

        for (let i = 0; i < elem.length; i++) {
            const selector = elem[i];
            if (target.matches(selector) || target.closest(selector)) {
                isOutside = false;
                break;
            }
        }

        return isOutside;
    }
};

export default dom;
