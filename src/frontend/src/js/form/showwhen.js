/**
 * Conditional field display — show/hide form groups based on controller values.
 *
 * showWhen: [data-show-when] — element is hidden by default, shown when condition matches
 * hideWhen: [data-hide-when] — element is visible by default, hidden when condition matches
 *
 * Condition format (JSON on the data attribute):
 *   { "key": { "id": "controller-element-id", "value": "expected-value" }, ... }
 *   or with array of values: { "key": { "id": "...", "value": ["val1", "val2"] } }
 */

const showWhen = {
    init() {
        _setup('[data-show-when]', '_sw_s', '_sw_c', '_sw_o', '_sw_d', false);
    }
};

const hideWhen = {
    init() {
        _setup('[data-hide-when]', '_hw_s', '_hw_c', '_hw_o', '_hw_d', true);
    }
};

/**
 * Set up conditional display observers.
 *
 * @param {string}  attrSelector   Selector for elements with the condition attribute
 * @param {string}  setClass       Class added to mark element as processed
 * @param {string}  ctrlClass      Class added to controller elements
 * @param {string}  observersKey   dataset key for storing observers on controller
 * @param {string}  boundClass     Class added after binding change listeners
 * @param {boolean} invertLogic    true for hideWhen, false for showWhen
 */
function _setup(attrSelector, setClass, ctrlClass, observersKey, boundClass, invertLogic) {
    // The attribute name without "data-" and camelCased (for dataset access)
    // e.g. "data-show-when" → "showWhen"
    const dataKey = attrSelector
        .replace('[data-', '')
        .replace(']', '')
        .replace(/-([a-z])/g, (_, c) => c.toUpperCase());

    document.querySelectorAll(attrSelector + ':not(.' + setClass + ')').forEach(dependentEl => {
        const conditions = dependentEl.dataset[dataKey];
        if (!conditions) {
            return;
        }

        let parsed;
        try {
            parsed = JSON.parse(conditions);
        } catch (e) {
            console.warn('NataForm: malformed condition on', dependentEl, e);
            return;
        }

        // Default visibility
        const formGroup = dependentEl.closest('.form-group');
        if (formGroup) {
            if (invertLogic) {
                // hideWhen: visible by default
                formGroup.classList.remove('hidden');
            } else {
                // showWhen: hidden by default
                formGroup.classList.add('hidden');
            }
        }

        if (!parsed || Object.keys(parsed).length === 0) {
            dependentEl.classList.add(setClass);
            return;
        }

        for (const [, condition] of Object.entries(parsed)) {
            if (!condition || typeof condition.id === 'undefined' || typeof condition.value === 'undefined') {
                console.warn('NataForm: condition malformed:', condition);
                continue;
            }

            const controller = document.getElementById(condition.id);
            if (!controller) {
                console.warn('NataForm: controller not found with id:', condition.id);
                continue;
            }

            controller.classList.add(ctrlClass);

            // Store observer list on the controller element via dataset
            let observers = controller[observersKey] || [];
            observers.push({
                element: dependentEl,
                formGroup: formGroup,
                targetValues: Array.isArray(condition.value) ? condition.value : [String(condition.value)]
            });
            controller[observersKey] = observers;
        }

        dependentEl.classList.add(setClass);
    });

    // Bind change listeners to controllers and run initial check
    document.querySelectorAll('.' + ctrlClass + ':not(.' + boundClass + ')').forEach(ctrl => {
        ctrl.addEventListener('change', () => _evaluate(ctrl, observersKey, invertLogic));
        ctrl.classList.add(boundClass);
        _evaluate(ctrl, observersKey, invertLogic);
    });
}

/**
 * Evaluate all observers for a controller and show/hide their form groups.
 *
 * @param {HTMLElement} ctrl
 * @param {string}      observersKey
 * @param {boolean}     invertLogic
 */
function _evaluate(ctrl, observersKey, invertLogic) {
    const observers = ctrl[observersKey];
    if (!observers) {
        return;
    }

    const value = ctrl.value;

    observers.forEach(observer => {
        const match = observer.targetValues.includes(value);
        const show = invertLogic ? !match : match;
        if (observer.formGroup) {
            observer.formGroup.classList.toggle('hidden', !show);
        }
    });
}

export { showWhen, hideWhen };
