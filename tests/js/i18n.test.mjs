/**
 * NataI18n: the dictionary of the page's language, or of its base language
 * (pt-PT → pt), requested only when it exists.
 *
 * Run with: npm test (node --test, no browser: a minimal document stands in).
 */
import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import NataI18n from '../../frontend/src/js/i18n.js';

/** Files on the "server": URL → dictionary it defines (code and messages), or the language list. */
let server;
/** URLs requested, in order. */
let requested;

/**
 * A page in a language, with these dictionary files and (optionally) the
 * list written by generate_i18n_js.
 *
 * @param {string} lang <html lang>.
 * @param {Object<string, Object>} dictionaries Code → messages.
 * @param {string[]|null} list languages.json, or null when missing.
 */
function page(lang, dictionaries, list) {
    server = {};
    for (const [code, messages] of Object.entries(dictionaries)) {
        server['/i18n/' + code + '.js'] = { code, messages };
    }
    if (list !== null) {
        server['/i18n/languages.json'] = list;
    }
    requested = [];
    delete globalThis.i18n;
    globalThis.document = {
        documentElement: { lang, dataset: {} },
        createElement: () => ({}),
        head: {
            appendChild(script) {
                requested.push(script.src);
                const file = server[script.src];
                setTimeout(() => {
                    if (file && file.code) {
                        // What a generated file does: if(typeof i18n==='undefined')i18n={};i18n['pt']={...};
                        globalThis.i18n = globalThis.i18n || {};
                        globalThis.i18n[file.code] = { default: { LC_MESSAGES: file.messages } };
                        script.onload();
                    } else {
                        script.onerror(new Error('404'));
                    }
                }, 0);
            },
        },
    };
    globalThis.fetch = async (url) => {
        requested.push(url);
        const body = server[url];
        return Array.isArray(body)
            ? { ok: true, status: 200, json: async () => body }
            : { ok: false, status: 404, json: async () => { throw new Error('404'); } };
    };
}

beforeEach(() => {
    page('pt-PT', {}, []);
});

test('candidates: the language as written, then its base language', () => {
    const i18n = new NataI18n();
    assert.deepEqual(i18n.candidates('pt-PT'), ['pt-PT', 'pt-pt', 'pt']);
    assert.deepEqual(i18n.candidates('en_GB'), ['en_GB', 'en-GB', 'en-gb', 'en']);
    assert.deepEqual(i18n.candidates('pt'), ['pt']);
    assert.deepEqual(i18n.candidates(''), []);
});

test('the regional dictionary exists: it is used', async () => {
    page('pt-PT', { 'pt-PT': { 'Delete': 'Eliminar' }, 'pt': { 'Delete': 'Apagar' } }, ['en', 'pt', 'pt-PT']);
    const i18n = new NataI18n();

    assert.equal(await i18n.translateAsync('Delete'), 'Eliminar');
    assert.deepEqual(requested, ['/i18n/languages.json', '/i18n/pt-PT.js']);
});

test('only the base dictionary exists: pt-PT gets pt, without asking for pt-PT.js', async () => {
    page('pt-PT', { 'pt': { 'Please wait...': 'Aguarde…' } }, ['en', 'pt']);
    const i18n = new NataI18n();

    assert.equal(await i18n.translateAsync('Please wait...'), 'Aguarde…');
    assert.deepEqual(requested, ['/i18n/languages.json', '/i18n/pt.js'], 'No request for a file that does not exist.');
    assert.equal(i18n.translate('Please wait...'), 'Aguarde…', 'Synchronous from then on.');
    assert.equal(globalThis.i18n['pt-PT'], globalThis.i18n['pt'], 'Also kept under the language asked for.');

    page('en-GB', { 'en': { 'Colour': 'Colour' }, 'pt': {} }, ['en', 'pt']);
    assert.equal(await new NataI18n().translateAsync('Colour'), 'Colour');
    assert.deepEqual(requested, ['/i18n/languages.json', '/i18n/en.js']);
});

test('no dictionary exists: the original strings, nothing requested but the list, nothing thrown', async () => {
    page('fr-FR', { 'en': {}, 'pt': {} }, ['en', 'pt']);
    const i18n = new NataI18n();

    assert.equal(i18n.translate('Delete'), 'Delete', 'Synchronous call while loading.');
    assert.equal(await i18n.translateAsync('Delete'), 'Delete');
    assert.equal(i18n.translate('Delete', 'Delete all', null, null, null, 2), 'Delete all');
    await assert.rejects(i18n.ensureLanguageLoaded('fr-FR'));
    assert.deepEqual(requested, ['/i18n/languages.json'], 'The list is read once; no dictionary requested.');
});

test('a dictionary already on the page is used at once, with no request', async () => {
    page('pt-PT', {}, ['pt']);
    globalThis.i18n = { pt: { default: { LC_MESSAGES: { 'Delete': 'Apagar' } } } };
    const i18n = new NataI18n();

    assert.equal(i18n.translate('Delete'), 'Apagar');
    await i18n.ensureLanguageLoaded('pt-PT');
    assert.deepEqual(requested, []);
});

test('without languages.json (dictionaries generated before it): the regional file, then the base one', async () => {
    page('pt-PT', { 'pt': { 'Delete': 'Apagar' } }, null);
    const i18n = new NataI18n();

    assert.equal(await i18n.translateAsync('Delete'), 'Apagar');
    assert.deepEqual(requested, ['/i18n/languages.json', '/i18n/pt-PT.js', '/i18n/pt-pt.js', '/i18n/pt.js']);
});

test('the list is read once for every language and call', async () => {
    page('pt-PT', { 'pt': { 'Delete': 'Apagar' }, 'en': { 'Delete': 'Delete' } }, ['en', 'pt']);
    const i18n = new NataI18n();

    await Promise.all([i18n.translateAsync('Delete'), i18n.translateAsync('Delete'), i18n.translateAsync('Delete', null, null, null, null, null, 'en-US')]);
    assert.equal(requested.filter((url) => url.endsWith('languages.json')).length, 1);
    assert.equal(requested.filter((url) => url === '/i18n/pt.js').length, 1);
});
