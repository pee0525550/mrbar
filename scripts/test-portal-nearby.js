const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const portalCss = fs.readFileSync('assets/portal-visual-refresh-v14856.css', 'utf8');
assert.match(portalCss, /body\.portal-body \.portal-distance\{left:16px!important;right:auto!important;top:auto!important;bottom:16px!important;display:inline-flex!important;width:max-content!important/);
assert.match(portalCss, /@media\(max-width:780px\)\{body\.portal-body \.portal-distance\{left:10px!important;right:auto!important;top:auto!important;bottom:10px!important/);

function element(attrs = {}) {
  const listeners = {};
  return {
    attrs, hidden: false, disabled: false, textContent: '', innerHTML: '',
    addEventListener(name, callback) { listeners[name] = callback; },
    fire(name) { if (listeners[name]) listeners[name](); },
    getAttribute(name) { return this.attrs[name] ?? null; },
    setAttribute(name, value) { this.attrs[name] = value; },
    removeAttribute(name) { delete this.attrs[name]; },
    querySelector() { return this.badge ?? null; },
  };
}

const nearest = element({ 'data-lat': '13.01', 'data-lng': '100.01' });
const farthest = element({ 'data-lat': '14', 'data-lng': '101' });
const noCoordinates = element({ 'data-lat': '', 'data-lng': '' });
nearest.badge = element(); farthest.badge = element(); noCoordinates.badge = element();
const originalOrder = [farthest, noCoordinates, nearest];
let positionCallback; let errorCallback; let requestCount = 0; let requestOptions;
const geolocation = { getCurrentPosition(success, error, options) { requestCount++; positionCallback = success; errorCallback = error; requestOptions = options; } };
const grid = { children: originalOrder.slice(), appendChild(card) { this.children = this.children.filter((item) => item !== card); this.children.push(card); } };
const status = element();
const welcome = element();
const selectors = { '[data-portal-grid]': grid, '.portal-nearby': welcome, '[data-nearby-status]': status };
const document = {
  querySelector(selector) { return selectors[selector] ?? null; },
  querySelectorAll(selector) { return selector === '[data-portal-card]' ? originalOrder : []; },
};
const window = {
  navigator: { geolocation }, isSecureContext: true,
  IntersectionObserver: function (callback) {
    this.observe = () => callback([{ isIntersecting: true }]);
    this.disconnect = () => {};
  },
};
vm.runInNewContext(fs.readFileSync('assets/portal-visual-refresh-v14856.js', 'utf8'), { document, window, Number, Math, parseFloat, String });

assert.equal(requestCount, 1);
assert.equal(requestOptions.timeout, 12000);
assert.match(status.textContent, /กำลังขออนุญาตตำแหน่ง/);
positionCallback({ coords: { latitude: 13, longitude: 100 } });
assert.deepEqual(grid.children, [nearest, farthest, noCoordinates]);
assert.match(nearest.badge.textContent, /ม\. จากคุณ|กม\. จากคุณ/);
assert.equal(noCoordinates.badge.hidden, true);
assert.match(status.textContent, /เรียงร้านตามระยะทาง/);

errorCallback({ code: 1 });
assert.match(status.textContent, /ไม่ได้รับอนุญาต/);
console.log('Portal nearby location regression passed.');
