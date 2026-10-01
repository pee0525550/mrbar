(function () {
  'use strict';
  if (window.MRBarLoading) return;

  var root = document.documentElement;
  var page = document.getElementById('mrPageLoader');
  var asyncBox = document.getElementById('mrAsyncLoader');
  var pending = 0;
  var timer = 0;
  var hideTimer = 0;
  var minimumUntil = performance.now() + 180;

  function pageVisible(label) {
    if (!page) return;
    var text = page.querySelector('b');
    if (text && label) text.textContent = label;
    clearTimeout(hideTimer);
    page.classList.add('is-visible');
    page.setAttribute('aria-hidden', 'false');
    root.classList.remove('mr-loading-init');
    document.body && document.body.setAttribute('aria-busy', 'true');
  }

  function pageHidden() {
    if (!page) return;
    var wait = Math.max(0, minimumUntil - performance.now());
    clearTimeout(hideTimer);
    hideTimer = window.setTimeout(function () {
      page.classList.remove('is-visible');
      page.setAttribute('aria-hidden', 'true');
      document.body && document.body.removeAttribute('aria-busy');
    }, wait);
  }

  function asyncVisible(label) {
    if (!asyncBox || root.classList.contains('mr-loading-init') || page && page.classList.contains('is-visible')) return;
    var text = asyncBox.querySelector('b');
    if (text && label) text.textContent = label;
    asyncBox.classList.add('is-visible');
    asyncBox.setAttribute('aria-hidden', 'false');
  }

  function asyncHidden() {
    if (!asyncBox) return;
    asyncBox.classList.remove('is-visible');
    asyncBox.setAttribute('aria-hidden', 'true');
  }

  function start(label) {
    pending++;
    clearTimeout(timer);
    timer = window.setTimeout(function () { asyncVisible(label || 'กำลังประมวลผล...'); }, 420);
  }

  function stop() {
    pending = Math.max(0, pending - 1);
    if (pending) return;
    clearTimeout(timer);
    asyncHidden();
  }

  window.MRBarLoading = {
    startPage: pageVisible,
    stopPage: pageHidden,
    start: start,
    stop: stop
  };

  window.addEventListener('load', pageHidden, { once: true });
  window.addEventListener('beforeunload', function () {
    minimumUntil = performance.now() + 180;
    pageVisible('กำลังเปิดหน้า...');
  });
  if (document.readyState === 'complete') pageHidden();

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link || link.target && link.target !== '_self' || link.hasAttribute('download') || link.hasAttribute('data-no-loading')) return;
    var raw = link.getAttribute('href') || '';
    if (!raw || raw.charAt(0) === '#' || /^\s*(javascript:|mailto:|tel:)/i.test(raw)) return;
    var url;
    try { url = new URL(link.href, location.href); } catch (e) { return; }
    if (url.origin !== location.origin || ['csv', 'xlsx', 'pdf'].indexOf((url.searchParams.get('export') || url.searchParams.get('format') || '').toLowerCase()) !== -1) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
    window.setTimeout(function () {
      if (event.defaultPrevented) return;
      minimumUntil = performance.now() + 180;
      pageVisible(link.getAttribute('data-loading-label') || 'กำลังเปิดหน้า...');
    }, 0);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.hasAttribute('data-no-loading') || form.target && form.target !== '_self') return;
    window.setTimeout(function () {
      if (event.defaultPrevented) return;
      minimumUntil = performance.now() + 180;
      pageVisible(form.getAttribute('data-loading-label') || 'กำลังบันทึกข้อมูล...');
    }, 0);
  });

  function isBackgroundPoll(input, init) {
    var rawUrl = typeof input === 'string' ? input : input && input.url;
    var method = (init && init.method || input && input.method || 'GET').toUpperCase();
    if (!rawUrl || method !== 'GET') return false;
    try {
      var url = new URL(rawUrl, location.href);
      return /\/ops-api\.php$/i.test(url.pathname) && (url.searchParams.get('scope') === 'pr' || url.searchParams.get('scope') === 'staff');
    } catch (e) { return false; }
  }

  if (typeof window.fetch === 'function') {
    var nativeFetch = window.fetch.bind(window);
    window.fetch = function () {
      var args = arguments;
      var init = args[1] || {};
      if (init.mrbarNoLoading || isBackgroundPoll(args[0], init)) return nativeFetch.apply(window, args);
      start(init.mrbarLoadingLabel || 'กำลังโหลดข้อมูล...');
      var request;
      try { request = nativeFetch.apply(window, args); }
      catch (error) { stop(); throw error; }
      return request.finally(stop);
    };
  }
})();
