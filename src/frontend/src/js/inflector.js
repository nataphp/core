/**
 * String inflection utilities.
 */
const inflector = {
    /**
     * Convert CamelCase to underscore_case.
     *
     * @param {string} text
     * @return {string}
     */
    underscore(text) {
        return text.replace(/(?:^|\.?)([A-Z])/g, (x, y) => '_' + y.toLowerCase()).replace(/^_/, '');
    }
};

export default inflector;
