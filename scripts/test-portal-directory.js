const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function element(attrs = {}) {
  const listeners = {};
  return {
    attrs,
    hidden: false,
    value: '',
    textContent: '',
    focused: false,
    addEventListener(name, callback) { listeners[name] = callback; },
    fire(name) { if (listeners[name]) listeners[name](); },
    getAttribute(name) { return this.attrs[name] ?? null; },
    setAttribute(name, value) { this.attrs[name] = value; },
    focus() { this.focused = true; },
  };
}

const cards = [
  element({ 'data-search': 'priest exclusive club bangkok p01', 'data-open': '1', 'data-available': '1', 'data-featured': '1' }),
  element({ 'data-search': 'meet way chiang mai m02', 'data-open': '1', 'data-available': '0', 'data-featured': '0' }),
  element({ 'data-search': 'remember phuket r03', 'data-open': '0', 'data-available': '1', 'data-featured': '0' }),
];
const search = element();
const clear = element();
const filters = ['all', 'open', 'available', 'featured'].map((filter) => element({ 'data-portal-filter': filter, 'aria-pressed': 'false' }));
const count = element();
const status = element();
const empty = element();
const reset = element();
const selectors = {
  '[data-portal-search]': search,
  '[data-portal-clear]': clear,
  '[data-portal-visible]': count,
  '[data-portal-status]': status,
  '[data-portal-empty]': empty,
  '[data-portal-reset]': reset,
};
const document = {
  querySelectorAll(selector) {
    if (selector === '[data-portal-card]') return cards;
    if (selector === '[data-portal-filter]') return filters;
    return [];
  },
  querySelector(selector) { return selectors[selector] ?? null; },
};

vm.runInNewContext(fs.readFileSync('assets/portal-directory-v14855.js', 'utf8'), { document });
assert.equal(count.textContent, '3');

filters[1].fire('click');
assert.deepEqual(cards.map((card) => card.hidden), [false, false, true]);
assert.equal(count.textContent, '2');

filters[2].fire('click');
assert.deepEqual(cards.map((card) => card.hidden), [false, true, false]);
assert.equal(count.textContent, '2');

filters[0].fire('click');
search.value = 'chiang';
search.fire('input');
assert.deepEqual(cards.map((card) => card.hidden), [true, false, true]);
assert.equal(status.textContent, 'แสดง 1 จาก 3 สาขา');
assert.equal(clear.hidden, false);

search.value = 'not found';
search.fire('input');
assert.equal(empty.hidden, false);

reset.fire('click');
assert.equal(search.value, '');
assert.equal(search.focused, true);
assert.deepEqual(cards.map((card) => card.hidden), [false, false, false]);
assert.equal(filters[0].getAttribute('aria-pressed'), 'true');
assert.equal(empty.hidden, true);

console.log('Portal directory regression passed.');
