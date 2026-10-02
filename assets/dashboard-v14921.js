(() => {
  'use strict';
  const output = document.querySelector('#salesReadout');
  document.querySelectorAll('[data-dash-view]').forEach(button => {
    button.addEventListener('click', () => {
      const view = button.dataset.dashView;
      document.querySelectorAll('[data-dash-view]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
      document.querySelectorAll('[data-dash-section]').forEach(section => { section.hidden = view !== 'all' && section.dataset.dashSection !== view; });
    });
  });
  document.querySelectorAll('[data-sales-point]').forEach(button => {
    const show = () => { if (output) output.textContent = button.getAttribute('aria-label'); };
    button.addEventListener('mouseenter', show);
    button.addEventListener('focus', show);
    button.addEventListener('click', show);
  });
  const motion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-countup]').forEach(el => {
    const value = Number(el.dataset.countup);
    if (motion || !Number.isFinite(value)) return;
    const start = performance.now();
    const tick = now => {
      const progress = Math.min(1, (now-start)/600);
      el.textContent = (value*(1-Math.pow(1-progress,3))).toLocaleString('th-TH',{minimumFractionDigits:2,maximumFractionDigits:2});
      if (progress<1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
})();
