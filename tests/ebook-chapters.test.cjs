const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname, '../admin/ebook-chapters.js'), 'utf8');

function setup() {
  function element() {
    return {
      listeners: {}, dataset: {}, textContent: '', hidden: true,
      classList: { add() {}, remove() {}, toggle() {} },
      addEventListener(name, fn) { this.listeners[name] = fn; },
      focus() {}, scrollIntoView() {},
      hasPointerCapture() { return false; }, setPointerCapture() {}, releasePointerCapture() {}
    };
  }
  const form = element();
  const fields = { csrf: 'token', action: 'save_chapter', chapter_id: '1', title: 'Original', slug: 'original', body: 'Text' };
  const nodes = { 'chapter-form': form, 'save-status': element(), 'order-status': element(), 'retry-save': element(), title: { value: 'Original' } };
  const list = element();
  let rows = [1, 2, 3].map(id => {
    const row = element();
    row.dataset.id = String(id);
    const children = { '.chapter-num': element(), '.chapter-title-text': element(), '.pub-dot': element() };
    row.querySelector = selector => children[selector];
    row.getBoundingClientRect = () => ({ top: rows.indexOf(row) * 40, height: 40 });
    row.handle = element();
    row.handle.closest = selector => selector === '.drag-handle' ? row.handle : row;
    Object.defineProperties(row, {
      previousElementSibling: { get: () => rows[rows.indexOf(row) - 1] },
      nextElementSibling: { get: () => rows[rows.indexOf(row) + 1] }
    });
    nodes['ch-row-' + id] = row;
    return row;
  });
  list.querySelectorAll = () => rows;
  list.insertBefore = (row, before) => {
    rows = rows.filter(r => r !== row);
    rows.splice(before ? rows.indexOf(before) : rows.length, 0, row);
  };
  list.appendChild = row => list.insertBefore(row, null);
  const document = element(), window = element();
  const destinations = [];
  window.location = { assign: href => destinations.push(href) };
  document.getElementById = id => nodes[id];
  document.querySelector = selector => selector === '.chapter-list' ? list : element();
  const requests = [], timers = new Map();
  let timerId = 0;
  vm.runInNewContext(source, {
    document, window, PRODUCT_ID: 1, CSRF_TOKEN: 'token', URLSearchParams,
    FormData: class { constructor() { return Object.entries(fields); } },
    HTMLFormElement: class {}, requestAnimationFrame() {},
    setTimeout(fn) { timers.set(++timerId, fn); return timerId; },
    clearTimeout(id) { timers.delete(id); },
    fetch(url, options) { return new Promise(resolve => requests.push({ body: options.body, resolve })); }
  });
  return {
    nodes, form, fields, requests, list, document, window, destinations,
    ids: () => rows.map(row => Number(row.dataset.id)),
    edit(title) { fields.title = title; nodes.title.value = title; form.listeners.input(); },
    tick() { const pending = [...timers.values()]; timers.clear(); pending.forEach(fn => fn()); },
    reply(index, success = true) { requests[index].resolve({ ok: success, json: async () => ({ success, error: 'Save failed' }) }); },
    key(id, key) { list.listeners.keydown({ target: nodes['ch-row-' + id].handle, key, preventDefault() {} }); }
  };
}
const settle = () => new Promise(resolve => setImmediate(resolve));

test('debounces edits and serializes changes made during a save', async () => {
  const app = setup();
  app.edit('First'); app.edit('Second'); app.tick();
  assert.equal(app.requests.length, 1);
  assert.equal(app.requests[0].body.get('title'), 'Second');
  app.edit('Latest'); app.tick();
  assert.equal(app.requests.length, 1);
  app.reply(0); await settle();
  assert.equal(app.requests.length, 2);
  assert.equal(app.requests[1].body.get('title'), 'Latest');
  app.reply(1); await settle();
  assert.equal(app.nodes['save-status'].textContent, 'All changes saved');
  assert.equal(app.nodes['ch-row-1'].querySelector('.chapter-title-text').textContent, 'Latest');
});

test('navigation waits for save; failed save keeps edits and supports retry', async () => {
  const app = setup();
  app.edit('Pending');
  let prevented = false;
  const navigation = app.document.listeners.click({
    target: { closest: () => ({ href: '/next' }) }, button: 0,
    preventDefault() { prevented = true; }
  });
  assert.equal(prevented, true);
  assert.deepEqual(app.destinations, []);
  app.reply(0, false); await navigation;
  assert.deepEqual(app.destinations, []);
  assert.equal(app.nodes['retry-save'].hidden, false);
  const retry = app.nodes['retry-save'].listeners.click();
  app.reply(1); await retry;
  assert.equal(app.nodes['save-status'].textContent, 'All changes saved');
});

test('chapter navigation flushes pending debounce before leaving', async () => {
  const app = setup();
  app.edit('Latest');
  const navigation = app.document.listeners.click({
    target: { closest: () => ({ href: '/next' }) }, button: 0, preventDefault() {}
  });
  assert.equal(app.requests[0].body.get('title'), 'Latest');
  app.reply(0); await navigation;
  assert.deepEqual(app.destinations, ['/next']);
});

test('pointer drag saves full order and renumbers without navigation', async () => {
  const app = setup();
  app.list.listeners.pointerdown({ target: app.nodes['ch-row-1'].handle, button: 0, pointerId: 1, clientY: 20, preventDefault() {} });
  app.list.listeners.pointermove({ pointerId: 1, clientY: 150 });
  app.list.listeners.pointerup({ pointerId: 1 });
  assert.deepEqual(app.ids(), [2, 3, 1]);
  assert.equal(app.requests[0].body.get('chapter_ids'), '[2,3,1]');
  assert.equal(app.nodes['ch-row-1'].querySelector('.chapter-num').textContent, 3);
  app.reply(0); await settle();
  assert.equal(app.nodes['order-status'].textContent, 'Chapter order saved');
  assert.deepEqual(app.destinations, []);
});

test('failed reorder rolls back; keyboard cancellation sends no save', async () => {
  const app = setup();
  app.key(1, ' '); app.key(1, 'ArrowDown'); app.key(1, ' ');
  assert.deepEqual(app.ids(), [2, 1, 3]);
  app.reply(0, false); await settle();
  assert.deepEqual(app.ids(), [1, 2, 3]);
  app.key(1, ' '); app.key(1, 'ArrowDown'); app.key(1, 'Escape');
  assert.deepEqual(app.ids(), [1, 2, 3]);
  assert.equal(app.requests.length, 1);
});
