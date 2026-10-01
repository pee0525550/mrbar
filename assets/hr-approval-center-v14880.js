(() => {
  const frame = document.querySelector('iframe[data-auto-height]');
  if (!frame) return;

  let scheduled = false;
  const resize = () => {
    scheduled = false;
    try {
      const doc = frame.contentDocument;
      if (!doc) return;
      const content = doc.querySelector('.leave-admin, .wx-shell, .pa-wrap, main') || doc.body;
      if (!content) return;
      const rect = content.getBoundingClientRect();
      const style = doc.defaultView.getComputedStyle(content);
      const height = Math.ceil(rect.top + content.scrollHeight + parseFloat(style.marginBottom || '0') + 24);
      if (height > 0) frame.style.height = `${Math.min(Math.max(height, 620), 5000)}px`;
    } catch (_) {
      frame.style.height = '820px';
    }
  };
  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(resize);
  };

  frame.addEventListener('load', () => {
    schedule();
    try {
      const doc = frame.contentDocument;
      if (!doc) return;
      const content = doc.querySelector('.leave-admin, .wx-shell, .pa-wrap, main') || doc.body;
      new ResizeObserver(schedule).observe(content);
      new MutationObserver(schedule).observe(content, {
        childList: true,
        subtree: true,
        attributes: true
      });
      doc.querySelectorAll('img').forEach((img) => img.addEventListener('load', schedule, { once: true }));
    } catch (_) {
      // Cross-origin content remains at the responsive fallback height.
    }
  });
  window.addEventListener('resize', schedule, { passive: true });
})();
