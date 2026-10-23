const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync('content.php', 'utf8');
const script = source.match(/<script>\n([\s\S]*?)\n<\/script>/)[1];

class Element {
    constructor(id) { this.id = id; this.value = ''; this.textContent = ''; this.disabled = false; this.checked = false; this.indeterminate = false; this.hidden = false; this.handlers = {}; this._html = ''; this.classList = {contains: () => false}; }
    addEventListener(type, handler) { this.handlers[type] = handler; }
    dispatch(type, event = {}) { return this.handlers[type](event); }
    set innerHTML(value) {
        this._html = value;
        if (this.id === 'efm-list') this.inputs = [...value.matchAll(/class="efm-file"[^>]*value="([^"]+)"[^>]*( checked)?/g)].map((match) => { const input = new Element('file'); input.value = match[1]; input.checked = Boolean(match[2]); input.classList = {contains: (name) => name === 'efm-file'}; return input; });
    }
    get innerHTML() { return this._html; }
}

const elements = {};
['efm-selected-count', 'efm-add', 'efm-clear', 'efm-repair', 'efm-repair-panel', 'efm-repair-results', 'efm-repair-cancel', 'efm-repair-confirm', 'efm-all', 'efm-playlist', 'efm-list', 'efm-breadcrumb', 'efm-message'].forEach((id) => { elements[id] = new Element(id); });
elements['efm-playlist'].value = 'Christmas';
const folders = {
    '': [{type: 'directory', name: 'Christmas', path: 'Christmas'}, {type: 'directory', name: 'Easter', path: 'Easter'}],
    Christmas: [{type: 'file', name: 'christmas-1.mp4', path: 'Christmas/christmas-1.mp4'}, {type: 'file', name: 'christmas-2.mp4', path: 'Christmas/christmas-2.mp4'}],
    Easter: [{type: 'file', name: 'easter-1.mp4', path: 'Easter/easter-1.mp4'}]
};
let addResponse = {ok: true, body: {added: 1, skipped: 0}};
let addedPayloads = [];
let repairResponse = {ok: true, body: {ok: true, playlist: 'Easter', applied: false, updates: [{section: 'mainPlaylist', index: 0, path: 'Easter/easter-1.mp4', currentDuration: null, proposedDuration: 12.5}], errors: [], before: {total_items: 1, total_duration: 0}, after: {total_items: 1, total_duration: 12.5}}};
let repairPayloads = [];

const document = {
    getElementById: (id) => elements[id],
    querySelectorAll: (selector) => selector === '.efm-file' ? (elements['efm-list'].inputs || []) : [],
    listeners: {},
    addEventListener: (type, handler) => { document.listeners[type] = handler; }
};

global.fetch = async (url, options) => {
    if (url === 'api/playlists') return {ok: true, json: async () => ['Christmas', 'Easter']};
    if (url.includes('/media?path=')) return {ok: true, json: async () => ({items: folders[decodeURIComponent(url.split('path=')[1])] || []})};
    if (url.endsWith('/add')) { addedPayloads.push(JSON.parse(options.body)); if (addResponse instanceof Error) throw addResponse; return {ok: addResponse.ok, json: async () => addResponse.body}; }
    if (url.endsWith('/repair')) { const payload = JSON.parse(options.body); repairPayloads.push(payload); if (repairResponse instanceof Error) throw repairResponse; const body = payload.apply ? Object.assign({}, repairResponse.body, {applied: true}) : repairResponse.body; return {ok: repairResponse.ok, text: async () => repairResponse.raw !== undefined ? repairResponse.raw : JSON.stringify(body)}; }
    throw new Error('Unexpected fetch: ' + url);
};

const context = {document, console, fetch: global.fetch, Promise, Set, JSON, String};
vm.runInNewContext(script, context);
const settle = () => new Promise((resolve) => setImmediate(resolve));
const clickPath = (path) => document.listeners.click({target: {closest: () => ({dataset: {path}})}});

(async () => {
    await settle();
    await clickPath('Christmas');
    await settle();
    elements['efm-all'].dispatch('change', {target: {checked: true}});
    assert.strictEqual(elements['efm-selected-count'].textContent, 2, 'select-all updates count');
    assert.strictEqual(elements['efm-add'].disabled, false, 'add enables with selection');

    await clickPath('Christmas');
    await settle();
    assert.strictEqual(elements['efm-selected-count'].textContent, 2, 'same-folder refresh preserves selection');

    await clickPath('Easter');
    await settle();
    assert.strictEqual(elements['efm-selected-count'].textContent, 0, 'different-folder navigation clears selection');
    assert.strictEqual(elements['efm-list'].inputs[0].checked, false, 'navigation clears visible checkboxes');
    elements['efm-list'].inputs[0].checked = true;
    elements['efm-list'].dispatch('change', {target: elements['efm-list'].inputs[0]});
    assert.strictEqual(elements['efm-selected-count'].textContent, 1, 'single selection updates count');
    await elements['efm-add'].dispatch('click');
    await settle();
    assert.strictEqual(addedPayloads.length, 1, 'successful add request is sent');
    assert.strictEqual(elements['efm-selected-count'].textContent, 0, 'successful add clears selection');
    assert.strictEqual(elements['efm-list'].inputs[0].checked, false, 'successful add clears visible checkboxes');
    assert.strictEqual(addedPayloads[0].paths[0], 'Easter/easter-1.mp4', 'successful add sends current-folder selection');

    elements['efm-list'].inputs[0].checked = true;
    elements['efm-list'].dispatch('change', {target: elements['efm-list'].inputs[0]});
    addResponse = {ok: false, body: {error: 'retry me'}};
    await elements['efm-add'].dispatch('click');
    await settle();
    assert.strictEqual(elements['efm-selected-count'].textContent, 1, 'failed add retains selection for retry');
    assert.strictEqual(elements['efm-list'].inputs[0].checked, true, 'failed add retains visible checkbox');
    assert.strictEqual(elements['efm-add'].disabled, false, 'add remains enabled after failure');

    await elements['efm-repair'].dispatch('click');
    await settle();
    assert.strictEqual(repairPayloads[0].apply, undefined, 'repair starts with a dry-run');
    assert.strictEqual(elements['efm-repair-confirm'].hidden, false, 'repair confirmation appears for changes');
    assert.strictEqual(elements['efm-selected-count'].textContent, 1, 'repair preview preserves selection');
    await elements['efm-repair-cancel'].dispatch('click');
    assert.strictEqual(elements['efm-repair-panel'].hidden, true, 'cancel hides repair preview');

    await elements['efm-repair'].dispatch('click');
    await settle();
    await elements['efm-repair-confirm'].dispatch('click');
    await settle();
    assert.strictEqual(repairPayloads[2].apply, true, 'confirm sends explicit apply');
    assert.strictEqual(elements['efm-repair-confirm'].hidden, true, 'confirm is removed after apply');
    assert.strictEqual(elements['efm-selected-count'].textContent, 1, 'successful repair preserves selection');

    repairResponse = {ok: true, raw: 'not-json'};
    await elements['efm-repair'].dispatch('click');
    await settle();
    assert.ok(elements['efm-repair-results'].innerHTML.includes('malformed'), 'malformed repair responses are reported');
    assert.strictEqual(elements['efm-repair-confirm'].hidden, true, 'malformed response cannot be confirmed');
    console.log('PASS');
})().catch((error) => { console.error(error); process.exitCode = 1; });
