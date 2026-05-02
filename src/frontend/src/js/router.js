/**
 * Router and URL utilities.
 */
import hash from './hash.js';

/**
 * Trim a specific character from both ends of a string.
 *
 * @param {string} string
 * @param {string} [character=' ']
 * @return {string}
 */
function _trim(string, character) {
    character = character ? character : ' ';
    while (string.charAt(0) == character) {
        string = string.substring(1);
    }
    while (string.charAt(string.length - 1) == character) {
        string = string.substring(0, string.length - 1);
    }
    return string;
}

/**
 * Serialize a params object to bracket-notation query string.
 * Handles nested objects: {page: {Model: 2}} → "page[Model]=2"
 *
 * @param {object} obj
 * @param {string} [prefix]
 * @return {string}
 */
function _toQueryString(obj, prefix) {
    const parts = [];
    for (const key in obj) {
        if (!Object.prototype.hasOwnProperty.call(obj, key)) {
            continue;
        }
        const fullKey = prefix ? prefix + '[' + key + ']' : key;
        const val = obj[key];
        if (val !== null && typeof val === 'object') {
            parts.push(_toQueryString(val, fullKey));
        } else {
            parts.push(encodeURIComponent(fullKey) + '=' + encodeURIComponent(val ?? ''));
        }
    }
    return parts.join('&');
}

/**
 * Basic router — mirrors NataPHP's Router class.
 */
const router = {
    /**
     * Build a URL relative to the app base.
     *
     * @param {string|object} [url]
     * @param {boolean} [full=false] Prepend protocol + host
     * @return {string}
     */
    url(url, full) {
        let _url = this.base(full);

        if (typeof url === 'undefined' || url == null) {
            return this.here(full);
        }

        if (url === '/') {
            return _url;
        }

        _url = _url.replace(/\/$/, '');

        if (typeof url === 'string') {
            const _replace = (_url === '/' ? '' : _url);
            return _url + url.replace(_replace, '');
        }

        if (url.prefix) {
            _url += '/' + url.prefix;
        }

        if (url.plugin) {
            _url += '/' + url.plugin;
        }

        if (url.controller) {
            _url += '/' + url.controller;
        }

        if (url.action) {
            _url += '/' + url.action;
        }

        return _url;
    },

    /**
     * Get the application base URL.
     *
     * Reads from <html data-base-url="...">.
     *
     * @param {boolean} [full=false] Prepend protocol + host
     * @return {string}
     */
    base(full) {
        const base = document.documentElement.dataset.baseUrl;
        if (!base) {
            console.warn('Warning: html[data-base-url] attr not set!');
        }
        return full ? this.host() + base : base;
    },

    /**
     * Get the current host (protocol + hostname).
     *
     * @return {string}
     */
    host() {
        return location.protocol + '//' + location.hostname;
    },

    /**
     * Get the current page URL.
     *
     * @param {boolean} [full=false] Return full URL including protocol + host
     * @return {string}
     */
    here(full) {
        return full ? window.location.href : window.location.href.replace(this.host(), '');
    },

    /**
     * Get or set a query parameter on the current URL.
     *
     * @param {string} name
     * @param {string|null} [value]
     * @return {string}
     */
    param(name, value) {
        let uri = this.url();

        if (typeof value === 'undefined') {
            const url = new URL(uri, this.host());
            return url.searchParams.get(name);
        }

        const regex = new RegExp('([?&])' + name + '=.*?(&|$)', 'i');
        const separator = uri.indexOf('?') !== -1 ? '&' : '?';

        if (value === null) {
            if (uri.match(regex)) {
                uri = uri.replace(regex, '$1$2');
            }
            return _trim(_trim(uri, '&'), '?');
        }

        if (uri.match(regex)) {
            return uri.replace(regex, '$1' + name + '=' + value + '$2');
        }

        return uri + separator + name + '=' + value;
    }
};

/**
 * URL component builder/parser.
 *
 * @param {string} [url] Defaults to current page URL
 * @return {object}
 */
function url(urlStr) {
    urlStr = urlStr ? urlStr : router.url(undefined, true);
    const components = {};
    let _paramsObj = undefined;

    const self = {
        /**
         * Get or set the protocol component (e.g. "https").
         * Call with no argument to read; pass a value to set and return `self` for chaining.
         *
         * @param {string} [protocol]
         * @return {string|object}
         */
        protocol(protocol) {
            if (typeof protocol === 'undefined') {
                protocol = self._components('protocol');

                if (protocol === undefined) {
                    protocol = location.protocol;

                    if (urlStr.indexOf('://') > -1) {
                        const parts = urlStr.split('://');
                        if (typeof parts[0] !== 'undefined') {
                            protocol = parts[0];
                        }
                    }

                    self.protocol(protocol);
                }

                return protocol;
            }

            self._components('protocol', protocol);
            return self;
        },

        /**
         * Get or set the hostname component (e.g. "example.com").
         * Getting the host also parses and sets the port as a side-effect.
         *
         * @param {string} [host]
         * @return {string|object}
         */
        host(host) {
            if (typeof host === 'undefined') {
                host = self._components('host');

                if (host === undefined) {
                    let port = location.port.length > 0 ? location.port : '';
                    host = location.hostname;

                    if (urlStr.indexOf('://') > -1) {
                        let tmp = urlStr;
                        if (tmp.indexOf('?') > -1) {
                            tmp = tmp.split('?')[0];
                        }

                        const parts = tmp.split('://');

                        if (typeof parts[1] !== 'undefined') {
                            host = parts[1];

                            if (host.indexOf('/') > -1) {
                                host = host.substring(0, host.indexOf('/'));
                            }

                            const hostParts = host.split(':');
                            if (typeof hostParts[1] !== 'undefined') {
                                port = parseInt(hostParts[1]);
                                host = hostParts[0];
                            }
                        }
                    }

                    self.host(host);
                    self.port(port);
                }

                return host;
            }

            self._components('host', host);
            return self;
        },

        /**
         * Get or set the port component (e.g. 8080).
         * Calling with no argument triggers host() parsing if the port is not yet resolved.
         *
         * @param {number|string} [port]
         * @return {number|string|object}
         */
        port(port) {
            if (typeof port === 'undefined') {
                port = self._components('port');

                if (port === undefined) {
                    self.host();
                    port = self._components('port');
                }

                return port;
            }

            self._components('port', port);
            return self;
        },

        /**
         * Get or set the application base path (read from data-base-url on <html>).
         * A "/" base is normalized to an empty string.
         *
         * @param {string} [base]
         * @return {string|object}
         */
        base(base) {
            if (typeof base === 'undefined') {
                base = self._components('base');

                if (base === undefined) {
                    base = document.documentElement.dataset.baseUrl;
                    base = base === '/' ? '' : base;
                    self.base(base);
                }

                return base;
            }

            self._components('base', base);
            return self;
        },

        /**
         * Get or set the path component, stripped of host, base, query string and fragment.
         *
         * @param {string} [path]
         * @return {string|object}
         *
         * @example
         * url('/admin/users?page=2').path() // => '/admin/users'
         */
        path(path) {
            if (typeof path === 'undefined') {
                path = self._components('path');

                if (path === undefined) {
                    path = urlStr;
                    const h = self.host() + (self.port() > 0 ? ':' + self.port() : '');

                    if (path.indexOf(h) > -1) {
                        path = path.split(h)[1];
                    }

                    if (path.indexOf('#') > -1) {
                        path = path.substring(0, path.indexOf('#'));
                    }

                    if (path.indexOf('?') > -1) {
                        path = path.substring(0, path.indexOf('?'));
                    }

                    if (self.base().length > 0 && path.indexOf(self.base()) > -1) {
                        path = path.split(self.base())[1];
                    }

                    self.path(path);
                }

                return path;
            }

            self._components('path', path);
            return self;
        },

        /**
         * Get or set the raw query string (without the leading "?").
         *
         * @param {string} [query]
         * @return {string|object}
         *
         * @example
         * url('/search?q=hello').query() // => 'q=hello'
         */
        query(query) {
            if (typeof query === 'undefined') {
                query = self._components('query');

                if (query === undefined) {
                    query = '';

                    if (urlStr.indexOf('?') > -1) {
                        let tmp = urlStr;
                        if (tmp.indexOf('#') > -1) {
                            tmp = tmp.substring(0, tmp.indexOf('#'));
                        }
                        const parts = tmp.split('?');
                        query = typeof parts[1] !== 'undefined' ? parts[1] : '';
                    }

                    self.query(query);
                }

                return query;
            }

            self._components('query', query);
            return self;
        },

        /**
         * Parse a query string into a nested plain object, converting bracket notation
         * (e.g. "filter[status]=active") into nested keys via hash.insert.
         *
         * @param {string} query  Raw query string (without "?")
         * @return {object}
         *
         * @example
         * _getParams('page=2&filter[status]=active')
         * // => { page: '2', filter: { status: 'active' } }
         */
        _getParams(query) {
            const parts = decodeURIComponent(query).split('&');
            const params = {};

            for (let i = 0; i < parts.length; i++) {
                const param = parts[i].split('=');
                const name = param[0]
                    .split('[').join('.')
                    .split('][').join('.')
                    .split(']').join('');

                hash.insert(params, name, param[1]);
            }

            return params;
        },

        /**
         * Get, set, or remove a query parameter by dot-path name.
         * Omit both arguments to get the full params object.
         * Pass null as value to delete the key.
         *
         * @param {string} [name]   Dot-path key, e.g. "filter.status" or "page"
         * @param {*}      [value]  New value; null to remove
         * @return {*|object}  The param value, all params, or `self` for chaining
         *
         * @example
         * url('/list?page=1').params('page', 2).get()  // => '/list?page=2'
         * url('/list?page=1').params('page', null).get() // => '/list'
         */
        params(name, value) {
            if (typeof _paramsObj === 'undefined') {
                _paramsObj = self._getParams(self.query());
            }

            if (typeof name === 'undefined') {
                return _paramsObj;
            } else if (typeof value === 'undefined') {
                return hash.get(_paramsObj, name);
            } else if (value === null) {
                hash.remove(_paramsObj, name);
            } else {
                hash.insert(_paramsObj, name, value);
            }

            self.query(decodeURIComponent(_toQueryString(_paramsObj)));

            return self;
        },

        /**
         * Get or set the URL fragment (the part after "#").
         *
         * @param {string} [fragment]
         * @return {string|object}
         */
        fragment(fragment) {
            if (typeof fragment === 'undefined') {
                if (typeof self._fragment === 'undefined') {
                    const parts = urlStr.split('#');
                    self._fragment = typeof parts[1] !== 'undefined' ? parts[1] : '';
                }
                return self._fragment;
            }

            self._fragment = fragment;
            return self;
        },

        /**
         * Reconstruct and return the full URL string from the current components.
         * Automatically returns the absolute form (with protocol + host) when the
         * original URL contained "http".
         *
         * @param {boolean} [full=false]  Force absolute URL even for relative inputs
         * @return {string}
         *
         * @example
         * url('/search').params('q', 'hello').get()
         * // => '/search?q=hello'
         *
         * url('https://example.com/path').params('ref', '1').get()
         * // => 'https://example.com/path?ref=1'
         */
        get(full) {
            full = urlStr.indexOf('http') > -1 ? true : full;
            let _url = '';

            if (full) {
                _url += self.protocol() + '://';
                _url += self.host();
                if (self.port().length > 0) {
                    _url += ':' + self.port();
                }
            }

            _url += self.base();
            _url += self.path();

            if (self.query().length > 0) {
                _url += '?' + self.query();
            }

            if (self.fragment().length > 0) {
                _url += '#' + self.fragment();
            }

            return _url;
        },

        /**
         * Low-level getter/setter for the internal components cache.
         * Returns undefined when a component has not been resolved yet.
         *
         * @param {string} name   Component key (e.g. "protocol", "host", "path")
         * @param {*}      [value]
         * @return {*|undefined}
         */
        _components(name, value) {
            if (typeof value === 'undefined') {
                return components[name];
            }
            components[name] = value;
        }
    };

    return self;
}

export { router, url };
