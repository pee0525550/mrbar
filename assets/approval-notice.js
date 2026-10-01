(()=> {
  const panel = document.getElementById('mrApprovalToast');
  if (!panel) return;
  const itemsNode = panel.querySelector('[data-approval-items]');
  const countNode = panel.querySelector('[data-approval-count]');
  const dismiss = panel.querySelector('[data-approval-dismiss]');
  const endpoint = panel.dataset.endpoint;
  const userKey = panel.dataset.user || '0';
  let signature = '';
  let busy = false;
  const reopen = document.createElement('button');
  reopen.type = 'button';
  reopen.className = 'mr-approval-reopen';
  reopen.setAttribute('aria-label', 'เปิดรายการรออนุมัติ');
  reopen.title = 'รายการรออนุมัติ';
  reopen.textContent = '✓';
  reopen.hidden = true;
  document.body.append(reopen);

  function setOpen(open) {
    panel.hidden = !open;
    reopen.hidden = open || !signature;
  }
  function renderItem(item) {
    const link = document.createElement('a');
    const href = String(item.href || '');
    if (!/^(hr-approval-center\.php\?view=(leave|correction|substitute|exception|penalty))$/.test(href)) return null;
    link.className = 'mr-approval-item';
    link.href = href;
    const title = document.createElement('b');
    title.textContent = String(item.label || 'รายการใหม่');
    const name = document.createElement('span');
    name.textContent = String(item.name || 'พนักงาน') + ' · ' + String(item.detail || '');
    const badge = document.createElement('em');
    badge.textContent = 'รอตรวจ';
    link.append(title, name, badge);
    return link;
  }
  async function refresh() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const payload = await response.json();
      const items = Array.isArray(payload.items) ? payload.items : [];
      const nextSignature = JSON.stringify([Number(payload.count) || 0, items.map(x => [x.type, x.name, x.detail, x.at])]);
      if (!items.length) {
        signature = '';
        panel.hidden = true;
        reopen.hidden = true;
        return;
      }
      if (nextSignature !== signature) {
        let dismissed = false;
        try {
          dismissed = localStorage.getItem('mr-approval-dismissed:' + userKey) === nextSignature;
          if (!dismissed) localStorage.removeItem('mr-approval-dismissed:' + userKey);
        } catch (_) {}
        signature = nextSignature;
        itemsNode.replaceChildren(...items.map(renderItem).filter(Boolean));
        countNode.textContent = String(Number(payload.count) || items.length) + ' รายการรอดำเนินการ';
        setOpen(!dismissed);
      } else if (panel.hidden) {
        reopen.hidden = false;
      }
    } catch (_) {
      // A transient notification failure must not disrupt the page.
    } finally {
      busy = false;
    }
  }
  dismiss.addEventListener('click', () => {
    try { localStorage.setItem('mr-approval-dismissed:' + userKey, signature); } catch (_) {}
    setOpen(false);
  });
  reopen.addEventListener('click', () => setOpen(true));
  refresh();
  window.setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
