/**
 * Dialog — native <dialog> API.
 *
 * Replaces Bootstrap modal dependency with the browser's built-in <dialog>
 * element. Styled with DaisyUI classes (.modal, .modal-box, .modal-action).
 *
 * API mirrors the old $.nata.modal() / alert() / confirm() / prompt() interface.
 */
const dialog = {
    /**
     * Create or show a modal dialog.
     *
     * @param {string|object} name  Dialog name (used as id="dialog-{name}") or options object
     * @param {object} [options]
     * @return {HTMLDialogElement}
     */
    modal(name, options) {
        if (typeof name === 'object') {
            options = name;
            name = options.name || 'default';
        }

        options = Object.assign({
            name: name,
            title: null,
            content: null,
            body: null,
            url: null,
            buttons: {},
            dismissable: true,
            dismissButton: true,
            fullscreen: false,
            centered: true,
            removeOnClose: false,
            shown: () => {},
            hidden: () => {},
            complete: () => {}
        }, options);

        // Reuse existing dialog if present
        let dlg = document.getElementById('dialog-' + options.name);
        if (dlg) {
            dlg.showModal();
            return dlg;
        }

        // Build <dialog>
        dlg = document.createElement('dialog');
        dlg.id = 'dialog-' + options.name;
        dlg.className = 'modal';

        const box = document.createElement('div');
        box.className = 'modal-box' + (options.fullscreen ? ' w-full max-w-full h-full rounded-none' : '');

        // Dismiss button (top-right)
        if (options.dismissable && options.dismissButton) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn btn-sm btn-circle btn-ghost absolute right-2 top-2';
            closeBtn.textContent = '✕';
            closeBtn.addEventListener('click', () => dlg.close());
            box.appendChild(closeBtn);
        }

        // Title
        if (options.title) {
            const h3 = document.createElement('h3');
            h3.className = 'font-bold text-lg';
            if (typeof options.title === 'string') {
                h3.textContent = options.title;
            } else {
                h3.appendChild(options.title);
            }
            box.appendChild(h3);
        }

        // Body content
        const bodyEl = document.createElement('div');
        bodyEl.className = 'py-4';

        const initialBody = options.content || options.body;
        if (typeof initialBody === 'string') {
            bodyEl.innerHTML = initialBody;
        } else if (initialBody instanceof Element) {
            bodyEl.appendChild(initialBody);
        } else if (options.url) {
            bodyEl.innerHTML = '<div class="flex justify-center"><span class="loading loading-spinner loading-md"></span></div>';
        }

        box.appendChild(bodyEl);

        // Footer buttons
        const buttonKeys = Object.keys(options.buttons);
        if (buttonKeys.length > 0) {
            const footer = document.createElement('div');
            footer.className = 'modal-action';

            for (const [state, btnOptions] of Object.entries(options.buttons)) {
                const btn = document.createElement(btnOptions.href ? 'a' : 'button');
                btn.type = 'button';
                btn.className = 'btn btn-' + state + (btnOptions.class ? ' ' + btnOptions.class : '');

                if (btnOptions.href) {
                    btn.href = btnOptions.href;
                }

                if (btnOptions.prependIcon || btnOptions.icon) {
                    const icon = document.createElement('i');
                    icon.className = btnOptions.prependIcon || btnOptions.icon;
                    btn.appendChild(icon);
                    btn.appendChild(document.createTextNode(' '));
                }

                const label = btnOptions.label ?? btnOptions.text ?? '';
                btn.appendChild(document.createTextNode(label));

                if (btnOptions.appendIcon) {
                    btn.appendChild(document.createTextNode(' '));
                    const icon = document.createElement('i');
                    icon.className = btnOptions.appendIcon;
                    btn.appendChild(icon);
                }

                btn.addEventListener('click', () => {
                    if (typeof btnOptions.click === 'function') {
                        btnOptions.click(dlg);
                    }
                });

                footer.appendChild(btn);
            }

            box.appendChild(footer);
        }

        dlg.appendChild(box);

        // Click backdrop to close (when dismissable)
        if (options.dismissable) {
            dlg.addEventListener('click', (e) => {
                if (e.target === dlg) {
                    dlg.close();
                }
            });
        }

        // Close event
        dlg.addEventListener('close', () => {
            options.hidden(dlg);
            if (options.removeOnClose) {
                dlg.remove();
            }
        });

        document.body.appendChild(dlg);

        // Load remote content
        if (options.url) {
            fetch(options.url)
                .then(r => r.text())
                .then(html => {
                    bodyEl.innerHTML = html;
                    options.complete(dlg);
                })
                .catch(err => console.error('nata.dialog: failed to load ' + options.url, err));
        }

        dlg.showModal();
        options.shown(dlg);

        return dlg;
    },

    /**
     * Show an alert dialog with an OK button.
     *
     * @param {string|object} message  String or {title, body}
     * @param {Function} [callback]
     * @return {HTMLDialogElement}
     */
    alert(message, callback) {
        return this.modal({
            name: 'alert',
            title: message.title ? message.title : message,
            body: message.body ? message.body : null,
            dismissable: false,
            removeOnClose: true,
            buttons: {
                primary: {
                    label: 'OK',
                    click(dlg) {
                        if (callback) {
                            callback(dlg);
                        }
                        dlg.close();
                    }
                }
            }
        });
    },

    /**
     * Show a confirmation dialog with Cancel/OK buttons.
     *
     * @param {string|object} message
     * @param {Function} [callback]  Called with true (OK) or false (Cancel)
     * @return {HTMLDialogElement}
     */
    confirm(message, callback) {
        return this.modal({
            name: 'confirm',
            title: message.title ? message.title : message,
            body: message.body ? message.body : null,
            dismissable: false,
            removeOnClose: true,
            buttons: {
                ghost: {
                    label: window.__('Cancel'),
                    click(dlg) {
                        if (callback) {
                            callback(false);
                        }
                        dlg.close();
                    }
                },
                primary: {
                    label: window.__('OK'),
                    click(dlg) {
                        if (callback) {
                            callback(true);
                        }
                        dlg.close();
                    }
                }
            }
        });
    },

    /**
     * Show a prompt dialog with a text input.
     *
     * @param {string} message
     * @param {Function} [callback]  Called with the input value or false (Cancel)
     * @return {HTMLDialogElement}
     */
    prompt(message, callback) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'input input-bordered w-full';
        input.autocomplete = 'off';

        const dlg = this.modal({
            name: 'prompt',
            title: message,
            body: input,
            dismissable: false,
            removeOnClose: true,
            shown(dlg) {
                dlg.querySelector('input').focus();
            },
            buttons: {
                ghost: {
                    label: window.__('Cancel'),
                    click(dlg) {
                        if (callback) {
                            callback(false);
                        }
                        dlg.close();
                    }
                },
                primary: {
                    label: window.__('OK'),
                    click(dlg) {
                        if (callback) {
                            callback(dlg.querySelector('input').value);
                        }
                        dlg.close();
                    }
                }
            }
        });

        return dlg;
    },

    /**
     * Close a dialog by name.
     *
     * @param {string} name
     */
    close(name) {
        const dlg = document.getElementById('dialog-' + name);
        if (dlg) {
            dlg.close();
        }
    }
};

export default dialog;
