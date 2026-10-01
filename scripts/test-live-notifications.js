const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class Element {
  constructor() { this.children = []; this.events = {}; this.dataset = {}; this.isConnected = true; this.classList = { add() {} }; }
  append(...children) { for (const child of children) { child.parent = this; this.children.push(child); } }
  setAttribute() {}
  addEventListener(name, callback) { this.events[name] = callback; }
  remove() { this.isConnected = false; if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
  querySelector(selector) { return this.children.find(child => child.className === selector.slice(1)) || null; }
}
async function run() {
  const root = new Element();
  root.dataset = { endpoint: '/feed', ackEndpoint: '/ack', enabled: '1', desktop: '1', csrf: 'test' };
  const document = { visibilityState: 'visible', getElementById: () => root, createElement: () => new Element(), querySelectorAll: () => [], addEventListener() {} };
  const timers = []; const intervals = new Map(); const native = []; let items = []; let status = 200; let ackOk = false; let requests = 0;
  function Notification(title, options) { native.push({ title, options }); }
  Notification.permission = 'granted';
  const window = { Notification, setTimeout: (fn, delay) => timers.push({ fn, delay }), setInterval: fn => { intervals.set(1, fn); return 1; }, clearInterval: id => intervals.delete(id), addEventListener() {} };
  const fetch = async (url) => {
    if (url === '/ack') return { ok: ackOk, json: async () => ({ ok: ackOk }) };
    requests++;
    return { status, ok: status === 200, json: async () => ({ enabled: true, desktop: true, items }) };
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/live-notifications-v14895.js'), 'utf8'), { window, document, Notification, fetch, URLSearchParams });
  const flush = () => new Promise(resolve => setImmediate(resolve));
  await flush();
  items = Array.from({ length: 7 }, (_, i) => ({ id: i + 1, message: `Booking ${i + 1}` }));
  await intervals.get(1)();
  assert.equal(root.children.length, 7, 'unread notices are not discarded after four');
  assert.equal(timers.length, 0, 'real notices have no dismissal timer');
  const toast = root.children[0]; const button = toast.querySelector('.mr-live-notification-ack');
  await button.events.click();
  assert.equal(button.disabled, false, 'ack failure can be retried');
  assert.equal(toast.isConnected, true, 'ack failure retains notice');
  ackOk = true; await button.events.click();
  assert.equal(timers[0].delay, 180, 'only confirmed ack starts removal');
  timers.shift().fn(); assert.equal(root.children.length, 6);
  document.visibilityState = 'hidden'; items = [{ id: 8, message: 'New hidden booking' }];
  await intervals.get(1)();
  assert.equal(native.length, 1, 'hidden tab can notify when browser permission is granted');
  status = 401; await intervals.get(1)();
  assert.equal(intervals.size, 0, 'unauthorized response stops polling');
  assert.equal(root.isConnected, false);
  assert.equal(requests, 4);
  console.log('Live notification persistence regression passed.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
