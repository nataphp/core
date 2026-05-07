/**
 * Location map component — Google Maps location picker.
 *
 * Text input shows a human-readable address. Hidden input stores lat,lon.
 *
 * data-inline=1  → map toggled inline below the input via [data-location-toggle]
 * data-inline=0  → map opens in a native <dialog id="modal-{id}"> on demand
 *
 * Required template elements:
 *   <input class="form-control-location-map" id="{id}" data-zoom data-marker-position data-autocomplete data-inline>
 *   <input type="hidden" id="{id}-hidden" name="...">
 *   <button data-location-geolocation="{id}">          — triggers GPS fill
 *   inline:  <button data-location-toggle="{id}">      — show/hide map
 *            <div data-location-map-for="{id}" hidden>  — map mount point
 *   modal:   <button onclick="...showModal()">          — opens the dialog
 *            <dialog id="modal-{id}">
 *              <div data-location-map-for="{id}">       — map mount point
 *              <button data-location-confirm>           — confirms selection
 */
const location = {
    /**
     * Scan the page for uninitialized .form-control-location-map inputs and set up
     * each one as either an inline or modal map picker, plus geolocation support.
     */
    init() {
        const fields = document.querySelectorAll('.form-control-location-map:not(.set)');
        if (fields.length === 0) {
            return;
        }

        fields.forEach(input => {
            location._handleGroup(input);

            if (input.dataset.inline === '1' || input.dataset.inline === 'true') {
                location._initInline(input);
            } else {
                location._initModal(input);
            }

            location._bindGeolocation(input);
            input.classList.add('set');
        });
    },

    /**
     * Set up an inline map that toggles open/closed via a [data-location-toggle] button.
     * The map is lazy-initialized on first open.
     *
     * @param {HTMLInputElement} input  The .form-control-location-map element
     */
    _initInline(input) {
        const id = input.id;
        const mapContainer = document.querySelector('[data-location-map-for="' + id + '"]');
        if (!mapContainer) {
            return;
        }

        const toggleBtn = document.querySelector('[data-location-toggle="' + id + '"]');
        let initialized = false;

        const toggle = () => {
            const opening = mapContainer.hidden;
            mapContainer.hidden = !opening;

            if (opening && !initialized) {
                initialized = true;
                location._initMap(input, mapContainer);
            }

            if (toggleBtn) {
                const label = toggleBtn.querySelector('span');
                if (label) {
                    label.textContent = opening
                        ? (window.__ ? window.__('Hide map') : 'Hide map')
                        : (window.__ ? window.__('Show map') : 'Show map');
                }
            }
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }
    },

    /**
     * Set up a modal-based map inside <dialog id="modal-{id}">.
     * The map is lazy-initialized when the dialog opens. A [data-location-confirm]
     * button reverse-geocodes the marker position back into the text input on confirm.
     *
     * @param {HTMLInputElement} input  The .form-control-location-map element
     */
    _initModal(input) {
        const id = input.id;
        const dialog = document.getElementById('modal-' + id);
        if (!dialog) {
            return;
        }

        const mapContainer = dialog.querySelector('[data-location-map-for="' + id + '"]');
        if (!mapContainer) {
            return;
        }

        let initialized = false;
        const observer = new MutationObserver(() => {
            if (dialog.open && !initialized) {
                initialized = true;
                location._initMap(input, mapContainer);
                observer.disconnect();
            }
        });
        observer.observe(dialog, { attributes: true, attributeFilter: ['open'] });

        // Reverse-geocode the marker position into the text input on confirm
        const confirmBtn = dialog.querySelector('[data-location-confirm]');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                const hiddenInput = document.getElementById(id + '-hidden');
                if (!hiddenInput || !hiddenInput.value) {
                    dialog.close();
                    return;
                }
                const parts = hiddenInput.value.split(',');
                const lat = parseFloat(parts[0]);
                const lon = parseFloat(parts[1]);
                if (!isNaN(lat) && !isNaN(lon)) {
                    _geocode(new google.maps.LatLng(lat, lon), result => {
                        if (result) {
                            input.value = result.formatted_address;
                        }
                        dialog.close();
                    });
                } else {
                    dialog.close();
                }
            });
        }
    },

    /**
     * Initialize a Google Maps instance inside mapContainer, pre-positioned at the
     * value stored in the hidden input (or data-marker-position). Binds marker drag,
     * address autocomplete, and nata:location:update / keyup reverse-geocoding.
     *
     * @param {HTMLInputElement} input         The .form-control-location-map element
     * @param {HTMLElement}      mapContainer  DOM node to render the map into
     */
    _initMap(input, mapContainer) {
        _loadGoogleMaps(() => {
            const id = input.id;
            const hiddenInput = document.getElementById(id + '-hidden');
            const latitudeInput = document.getElementById(id + '-latitude');
            const longitudeInput = document.getElementById(id + '-longitude');
            let lat = 0, lon = 0;
            const pos = (hiddenInput && hiddenInput.value) || input.dataset.markerPosition || '';

            if (pos) {
                const parts = pos.split(',');
                lat = parseFloat(parts[0]) || 0;
                lon = parseFloat(parts[1]) || 0;
            }

            const latlng = new google.maps.LatLng(lat, lon);
            const map = new google.maps.Map(mapContainer, {
                center: latlng,
                zoom: parseInt(input.dataset.zoom) || 14,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            const marker = new google.maps.Marker({
                position: latlng,
                map,
                draggable: true
            });

            const update = (latlngObj) => {
                marker.setPosition(latlngObj);
                map.setCenter(latlngObj);
                if (hiddenInput) {
                    hiddenInput.value = latlngObj.lat() + ',' + latlngObj.lng();
                }
            };

            google.maps.event.addListener(marker, 'dragend', event => {
                update(event.latLng);
                if (input.dataset.inline) {
                    _geocode(event.latLng, result => {
                        if (result) { input.value = result.formatted_address; }
                    });
                }
            });

            input.addEventListener('nata:location:update', event => {
                const { lat, lon } = event.detail;
                const newLatLng = new google.maps.LatLng(lat, lon);
                update(newLatLng);
                _geocode(newLatLng, result => {
                    if (result) { input.value = result.formatted_address; }
                });
            });

            let keyupTimeout = null;
            input.addEventListener('keyup', () => {
                const val = input.value;
                if (val.length < 5 || input.dataset.autocomplete) { return; }
                clearTimeout(keyupTimeout);
                keyupTimeout = setTimeout(() => {
                    _geocode(val, result => {
                        if (result) { update(result.geometry.location); }
                    });
                }, 600);
            });

            if (input.dataset.autocomplete) {
                const ac = new google.maps.places.Autocomplete(input, { fields: ['geometry'] });
                ac.addListener('place_changed', () => {
                    const place = ac.getPlace();
                    if (place.geometry) {
                        update(place.geometry.location);
                    }
                });
            }

            const isModal = input.dataset.inline !== '1' && input.dataset.inline !== 'true';
            if (isModal && google.maps.places && google.maps.places.AutocompleteService) {
                const wrapper = document.createElement('div');
                wrapper.style.cssText = 'position:relative;margin:10px;';

                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.placeholder = window.__ ? window.__('Search address…') : 'Search address…';
                searchInput.style.cssText = 'padding:11px 12px;width:280px;border-radius:4px;font-size:14px;box-shadow:0 0 3px rgba(0,0,0,.2);outline:none;background:#fff;border:none;display:block;box-sizing:border-box;';

                const dropdown = document.createElement('ul');
                dropdown.style.cssText = 'position:absolute;top:100%;left:0;right:0;margin:2px 0 0;padding:0;list-style:none;background:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.25);max-height:240px;overflow-y:auto;z-index:1;display:none;';

                wrapper.appendChild(searchInput);
                wrapper.appendChild(dropdown);
                map.controls[google.maps.ControlPosition.TOP_LEFT].push(wrapper);

                const acService = new google.maps.places.AutocompleteService();
                const placesService = new google.maps.places.PlacesService(map);
                let debounce = null;

                const clearDropdown = () => {
                    dropdown.innerHTML = '';
                    dropdown.style.display = 'none';
                };

                searchInput.addEventListener('input', () => {
                    clearTimeout(debounce);
                    const val = searchInput.value.trim();
                    if (val.length < 3) { clearDropdown(); return; }
                    debounce = setTimeout(() => {
                        acService.getPlacePredictions({ input: val }, (predictions, status) => {
                            clearDropdown();
                            if (status !== google.maps.places.PlacesServiceStatus.OK || !predictions) { return; }
                            predictions.forEach(pred => {
                                const li = document.createElement('li');
                                li.textContent = pred.description;
                                li.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:14px;border-bottom:1px solid #f0f0f0;';
                                li.addEventListener('mouseenter', () => { li.style.background = '#f5f5f5'; });
                                li.addEventListener('mouseleave', () => { li.style.background = ''; });
                                li.addEventListener('mousedown', e => {
                                    e.preventDefault();
                                    searchInput.value = pred.description;
                                    clearDropdown();
                                    placesService.getDetails({ placeId: pred.place_id, fields: ['geometry'] }, (place, st) => {
                                        if (st === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                                            update(place.geometry.location);
                                        }
                                    });
                                });
                                dropdown.appendChild(li);
                            });
                            dropdown.style.display = 'block';
                        });
                    }, 300);
                });

                searchInput.addEventListener('blur', clearDropdown);
            }
        });
    },

    /**
     * Bind click handlers to all [data-location-geolocation="{id}"] buttons.
     * On click, requests the browser's current GPS position and fires a
     * nata:location:update event so the map updates accordingly.
     *
     * @param {HTMLInputElement} input  The .form-control-location-map element
     */
    _bindGeolocation(input) {
        const btns = document.querySelectorAll('[data-location-geolocation="' + input.id + '"]');
        if (!btns.length) { return; }

        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    console.warn('NataForm location: geolocation not supported');
                    return;
                }

                btns.forEach(b => { b.disabled = true; });

                navigator.geolocation.getCurrentPosition(
                    pos => {
                        btns.forEach(b => { b.disabled = false; });
                        input.dispatchEvent(new CustomEvent('nata:location:update', {
                            bubbles: true,
                            detail: { lat: pos.coords.latitude, lon: pos.coords.longitude }
                        }));
                        if (window.nata && window.nata.notify) {
                            window.nata.notify(window.__ ? window.__('Your position was added!') : 'Your position was added!');
                        }
                    },
                    err => {
                        btns.forEach(b => { b.disabled = false; });
                        console.warn('NataForm geolocation error:', err);
                    }
                );
            });
        });
    },

    /**
     * If the input lives inside a repeatable group, register a one-time listener
     * to clear cloned map containers after a new row is added, preventing stale
     * map instances from carrying over into new rows.
     *
     * @param {HTMLInputElement} input
     */
    _handleGroup(input) {
        const group = input.closest('[data-group]');
        if (!group || group.classList.contains('form-location')) {
            return;
        }

        group.addEventListener('nata:form:group:after:add', event => {
            const newRow = event.detail.newRow;
            // Clear map containers in the cloned row so they can be re-initialised
            newRow.querySelectorAll('.location-map-container').forEach(el => {
                el.innerHTML = '';
                el.hidden = true;
            });
            newRow.querySelectorAll('[data-location-toggle]').forEach(btn => {
                const label = btn.querySelector('span');
                if (label) {
                    label.textContent = window.__ ? window.__('Show map') : 'Show map';
                }
            });
        });

        group.classList.add('form-location');
    },

    /**
     * Tear down all maps inside a container: empties map DOM nodes, hides them,
     * and removes the `.set` marker so they can be re-initialized if needed.
     *
     * @param {HTMLElement} container
     */
    destroy(container) {
        container.querySelectorAll('.location-map-container').forEach(el => {
            el.innerHTML = '';
            el.hidden = true;
        });
        container.querySelectorAll('.form-control-location-map.set').forEach(input => {
            input.classList.remove('set');
        });
    }
};

/**
 * Geocode an address string or reverse-geocode a LatLng object using the
 * Google Maps Geocoder API. Calls cb with the first result, or null on failure.
 *
 * @param {string|google.maps.LatLng} query  Address string or LatLng to look up
 * @param {function(google.maps.GeocoderResult|null): void} cb
 *
 * @example
 * _geocode('Rua Augusta, Lisboa', result => {
 *     if (result) map.setCenter(result.geometry.location);
 * });
 */
function _geocode(query, cb) {
    if (typeof google === 'undefined') { return; }
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({
        address: typeof query === 'string' ? query : undefined,
        location: typeof query === 'object' ? query : undefined
    }, (results, status) => {
        if (status === 'OK' && results[0]) {
            cb(results[0]);
        } else {
            cb(null);
        }
    });
}

let _mapsLoading = false;
let _mapsCallbacks = [];
let _mapsLoaded = false;

/**
 * Ensure Google Maps JS is loaded before executing a callback.
 * Multiple calls before load completes are queued and flushed together.
 * Detects an existing <script src="maps.googleapis.com"> tag and polls for
 * the global `google` object rather than injecting a second script tag.
 *
 * @param {function(): void} cb  Called once google.maps is available
 */
function _loadGoogleMaps(cb) {
    if (_mapsLoaded && typeof google !== 'undefined') {
        cb();
        return;
    }
    _mapsCallbacks.push(cb);
    if (_mapsLoading) { return; }
    _mapsLoading = true;

    // Find existing Google Maps script tag added by the app
    const existing = document.querySelector('script[src*="maps.googleapis.com"]');
    if (existing) {
        // Maps is already being loaded — poll until ready
        const poll = setInterval(() => {
            if (typeof google !== 'undefined' && google.maps) {
                clearInterval(poll);
                _mapsLoaded = true;
                _mapsCallbacks.forEach(fn => fn());
                _mapsCallbacks = [];
            }
        }, 100);
        return;
    }

    // Load via window.nata if available (uses nata's script loader)
    if (window.nata && window.nata.script) {
        window.nata.script('gmaps', () => {
            _mapsLoaded = true;
            _mapsCallbacks.forEach(fn => fn());
            _mapsCallbacks = [];
        });
    } else {
        console.warn('NataForm location: Google Maps not loaded and no nata.script() available');
    }
}

export default location;
