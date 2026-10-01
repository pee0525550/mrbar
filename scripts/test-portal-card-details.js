const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const css = fs.readFileSync('assets/portal-card-details-v14862.css', 'utf8');
assert.match(css, /\.portal-card\.featured\{grid-column:1\/-1\}/);
assert.match(css, /\.portal-mini-map iframe\{height:205px\}/);
assert.match(css, /@media\(max-width:780px\)\{[\s\S]*\.portal-card-details\{grid-template-columns:minmax\(0,1fr\)/);

function element(attrs = {}) {
  const listeners = {};
  return {
    attrs, listeners, disabled: false, textContent: '', src: '', alt: '', hidden: false, opened: false,
    addEventListener(name, callback) { listeners[name] = callback; },
    fire(name, event = {}) { if (listeners[name]) listeners[name](event); },
    getAttribute(name) { return this.attrs[name] ?? null; },
    setAttribute(name, value) { this.attrs[name] = value; },
    removeAttribute(name) { delete this.attrs[name]; if (name === 'src') this.src = ''; if (name === 'poster') this.poster = ''; },
    querySelector(selector) { return this.parts[selector] ?? null; },
    querySelectorAll(selector) { return selector === '[data-gallery-open]' ? this.gallery : []; },
    closest(selector) { return selector === '[data-portal-card]' ? this.card : null; },
    showModal() { this.opened = true; }, close() { this.opened = false; this.fire('close'); },
    pause() {}, load() {},
  };
}

const images = [
  element({ 'data-gallery-type': 'image', 'data-gallery-image': '/one.jpg', 'data-gallery-source': '/one.jpg', 'data-gallery-caption': 'Branch' }),
  element({ 'data-gallery-type': 'video_upload', 'data-gallery-image': '/poster.jpg', 'data-gallery-source': '/clip.mp4', 'data-gallery-caption': 'Branch' }),
  element({ 'data-gallery-type': 'youtube', 'data-gallery-image': '/youtube.jpg', 'data-gallery-source': 'https://www.youtube-nocookie.com/embed/abcdefghi?autoplay=1', 'data-gallery-caption': 'Branch' }),
];
const card = element(); card.gallery = images;
images.forEach((image) => { image.card = card; });
const dialog = element();
const selectors = {
  '[data-lightbox-image]': element(), '[data-lightbox-caption]': element(),
  '[data-lightbox-video]': element(), '[data-lightbox-frame]': element(),
  '[data-lightbox-count]': element(), '[data-lightbox-prev]': element(),
  '[data-lightbox-next]': element(), '[data-lightbox-close]': element(),
};
dialog.parts = selectors;
const document = {
  querySelector(selector) { return selector === '[data-portal-lightbox]' ? dialog : null; },
  querySelectorAll(selector) { return selector === '[data-gallery-open]' ? images : []; },
};

vm.runInNewContext(fs.readFileSync('assets/portal-card-details-v14862.js', 'utf8'), { document });
images[1].fire('click');
assert.equal(dialog.opened, true);
assert.equal(selectors['[data-lightbox-image]'].hidden, true);
assert.equal(selectors['[data-lightbox-count]'].textContent, '2 / 3');
assert.equal(selectors['[data-lightbox-video]'].src, '/clip.mp4');
assert.equal(selectors['[data-lightbox-video]'].poster, '/poster.jpg');
selectors['[data-lightbox-next]'].fire('click');
assert.equal(selectors['[data-lightbox-frame]'].src, 'https://www.youtube-nocookie.com/embed/abcdefghi?autoplay=1');
selectors['[data-lightbox-next]'].fire('click');
assert.equal(selectors['[data-lightbox-image]'].src, '/one.jpg');
selectors['[data-lightbox-close]'].fire('click');
assert.equal(dialog.opened, false);
assert.equal(selectors['[data-lightbox-video]'].src, '');
const portal = fs.readFileSync('portal.php', 'utf8');
assert.match(portal, /\$heroSlides=chm_active\(\$bd\)/);
assert.match(portal, /data-gallery-type=/);
console.log('Portal card gallery lightbox regression passed.');
