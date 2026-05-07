/**
 * Dotted-notation object utilities.
 *
 * Example:
 *   hash.insert({}, 'this.path', 2);  // → {'this': {'path': 2}}
 */
const hash = {
    /**
     * Get a value from an object using dotted path notation.
     *
     * @param {object} obj
     * @param {string} path Dot-separated path, e.g. "a.b.c"
     * @return {*}
     */
    get(obj, path) {
        return path.split('.').reduce((o, i) => o ? o[i] : null, obj);
    },

    /**
     * Insert a value into an object at the given dotted path.
     *
     * @param {object} obj
     * @param {string|string[]} path
     * @param {*} value
     * @return {object}
     */
    insert(obj, path, value) {
        if (typeof path === 'string') {
            return this.insert(obj, path.split('.'), value);
        } else if (path.length === 1 && path[0] !== '') {
            obj[path[0]] = value;
        } else if (path.length > 1) {
            obj[path[0]] = {};
            this.insert(obj[path[0]], path.slice(1), value);
        }
        return obj;
    },

    /**
     * Remove a value from an object at the given dotted path.
     *
     * @param {object} obj
     * @param {string} path
     * @return {object}
     */
    remove(obj, path) {
        if (!path) {
            return obj;
        }
        const parts = path.split('.');
        const last = parts.pop();
        path.split('.').reduce((o, i) => {
            if (last === i) {
                delete o[i];
                return;
            }
            return o[i];
        }, obj);
        return obj;
    }
};

export default hash;
