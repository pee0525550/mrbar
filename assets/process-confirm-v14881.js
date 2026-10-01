(() => {
  'use strict';
  const key = 'mrbar_process_pending_v14881';
  const messageSelectors = {
    error: '.emp-note.err,.notice.err,.la-note.err,.wx-note.err,.notice.error,.alert-danger,.alert-error,.flash.error,.toast.error,.error-message,[data-process-result="error"]',
    success: '.emp-note.ok,.notice.ok,.la-note.ok,.wx-note.ok,.notice.success,.alert-success,.flash.success,.toast.success,.success-message,[data-process-result="success"]'
  };
  const visibleText = (selectors) => [...document.querySelectorAll(selectors)]
    .filter((node) => node.getClientRects().length && !node.closest('[hidden]'))
    .map((node) => (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim())
    .find(Boolean) || '';

  const displayResult = (state, title, detail) => {
    const toast = document.createElement('aside');
    toast.className = 'mr-process-confirm';
    toast.dataset.state = state;
    toast.setAttribute('role', state === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-live', state === 'error' ? 'assertive' : 'polite');
    const heading = document.createElement('b');
    heading.textContent = title;
    const text = document.createElement('p');
    text.textContent = detail;
    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'ปิดการแจ้งเตือน');
    close.textContent = '×';
    close.addEventListener('click', () => toast.remove());
    toast.append(heading, text, close);
    document.body.append(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    window.setTimeout(() => toast.remove(), state === 'error' ? 12000 : 8500);
  };

  const waitForLoader = (callback, deadline = Date.now() + 3500) => {
    const loader = document.getElementById('mrPageLoader');
    if ((!loader || !loader.classList.contains('is-visible')) && !document.documentElement.classList.contains('mr-loading-init')) {
      window.setTimeout(callback, 140);
    } else if (Date.now() < deadline) {
      window.setTimeout(() => waitForLoader(callback, deadline), 100);
    } else {
      callback();
    }
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-confirm') || (form.method || 'get').toLowerCase() !== 'post') return;
    if (form.target && form.target !== '_self') return;
    window.setTimeout(() => {
      if (event.defaultPrevented) return;
      try {
        sessionStorage.setItem(key, JSON.stringify({
          at: Date.now(),
          action: form.querySelector('[name="action"]')?.value || '',
          label: form.dataset.loadingLabel || form.querySelector('button[type="submit"]')?.innerText?.trim() || 'รายการที่ส่ง'
        }));
      } catch (_) {}
    }, 0);
  }, true);

  let pending;
  try { pending = JSON.parse(sessionStorage.getItem(key) || 'null'); sessionStorage.removeItem(key); } catch (_) {}
  if (pending?.at && Date.now() - pending.at <= 120000) {
    const showResult = () => {
      const error = visibleText(messageSelectors.error);
      const success = visibleText(messageSelectors.success);
      if (error) {
        displayResult('error', 'บันทึกไม่สำเร็จ', error);
      } else if (success && /บันทึก|อัปเดต|สร้าง|เพิ่ม|ลบ|อนุมัติ|ปฏิเสธ|สำเร็จ|เรียบร้อย|import|ยกเลิก/i.test(success)) {
        displayResult('success', 'ดำเนินการเรียบร้อย', success.replace(/^[✓✔⚠\s]+/, ''));
      } else {
        displayResult('info', 'ระบบตอบกลับแล้ว', 'ส่งข้อมูลแล้ว แต่หน้านี้ไม่ได้ระบุผลยืนยันการบันทึก');
      }
    };
    if (document.readyState === 'complete') waitForLoader(showResult);
    else window.addEventListener('load', () => waitForLoader(showResult), { once: true });
  }

  if (typeof window.fetch === 'function') {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (...args) => {
      const input = args[0];
      const init = args[1] || {};
      const method = String(init.method || input?.method || 'GET').toUpperCase();
      const noConfirm = init.mrbarNoConfirm || input?.headers?.get?.('X-MRBar-No-Confirm') === '1';
      const request = nativeFetch(...args);
      if (method !== 'POST' || noConfirm) return request;
      return request.then((response) => {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
          if (!response.ok) window.setTimeout(() => waitForLoader(() => displayResult('error', 'บันทึกไม่สำเร็จ', `Server ตอบกลับ ${response.status}${response.statusText ? ` · ${response.statusText}` : ''}`)), 0);
          return response;
        }
        response.clone().json().then((payload) => {
          const message = String(payload?.message || payload?.error || payload?.detail || '').trim();
          const explicitError = !response.ok || payload?.success === false || payload?.ok === false || Boolean(payload?.error);
          const explicitSuccess = response.ok && (payload?.success === true || payload?.ok === true || payload?.status === 'success');
          if (!message || (!explicitError && !explicitSuccess)) return;
          const state = explicitError ? 'error' : 'success';
          window.setTimeout(() => waitForLoader(() => displayResult(state, explicitError ? 'บันทึกไม่สำเร็จ' : 'ดำเนินการเรียบร้อย', message)), 0);
        }).catch(() => {});
        return response;
      }, (error) => {
        window.setTimeout(() => waitForLoader(() => displayResult('error', 'เชื่อมต่อไม่สำเร็จ', 'ระบบไม่ได้รับคำตอบจาก Server กรุณาตรวจสอบเครือข่ายแล้วลองอีกครั้ง')), 0);
        throw error;
      });
    };
  }
})();
