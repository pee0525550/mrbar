(() => {
  const button = document.querySelector('[data-line-auth]');
  if (!button || !window.liff) return;
  const panel = button.closest('[data-line-flow]') || button;
  let status = panel.querySelector ? panel.querySelector('[data-line-status]') : null;
  if (!status) {
    status = document.createElement('p');
    status.dataset.lineStatus = '';
    button.insertAdjacentElement('afterend', status);
  }

  const mode = panel.dataset.lineFlow;
  const pendingKey = `mrbar_line_auth_pending:${mode}`;
  let busy = false;
  const liffReady = liff.init({ liffId: panel.dataset.lineLiffId });

  const show = (message, state = '') => {
    status.textContent = message;
    status.dataset.state = state;
  };

  async function authenticate() {
    if (busy) return;
    busy = true;
    button.disabled = true;
    show('กำลังตรวจสอบบัญชี LINE...');
    try {
      const idToken = liff.getIDToken();
      if (!idToken) throw new Error('ไม่พบ ID Token กรุณาตรวจว่า LIFF เปิด scope openid แล้ว');
      const body = new URLSearchParams({ csrf: panel.dataset.lineCsrf, id_token: idToken });
      if (mode === 'staff_login') body.set('return_to', panel.dataset.lineReturn || '');
      if (mode === 'customer_booking') body.set('return_to', panel.dataset.lineReturn || '');
      const response = await fetch(panel.dataset.lineEndpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body,
      });
      const result = await response.json();
      if (!response.ok) {
        const errors = {
          line_account_not_linked: 'LINE นี้ยังไม่ได้ผูกกับบัญชีพนักงาน กรุณาเข้าสู่ระบบด้วยรหัสผ่านแล้วเลือก “เชื่อมบัญชี LINE” ก่อน',
          line_account_ambiguous: 'พบหลายบัญชีที่ผูก LINE นี้ กรุณาติดต่อผู้ดูแลระบบ',
          line_account_in_use: 'บัญชี LINE นี้ถูกผูกกับพนักงานคนอื่นแล้ว',
          csrf_expired: 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่',
          line_token_invalid: 'ยืนยันบัญชี LINE ไม่สำเร็จ กรุณาลองใหม่',
        };
        throw new Error(errors[result.error] || 'ดำเนินการด้วย LINE ไม่สำเร็จ กรุณาลองใหม่');
      }
      sessionStorage.removeItem(pendingKey);
      location.assign(result.redirect);
    } catch (error) {
      sessionStorage.removeItem(pendingKey);
      show(error.message || 'ดำเนินการด้วย LINE ไม่สำเร็จ', 'error');
      button.disabled = false;
      busy = false;
    }
  }

  async function init() {
    try {
      await liffReady;
      if (sessionStorage.getItem(pendingKey) === '1' && liff.isLoggedIn()) {
        await authenticate();
      }
    } catch (_) {
      show('เปิด LINE Login ไม่สำเร็จ กรุณาเปิดลิงก์ LIFF ใหม่อีกครั้ง', 'error');
    }
  }

  button.addEventListener('click', async () => {
    button.disabled = true;
    show('กำลังเปิด LINE Login...');
    try {
      await liffReady;
      if (!liff.isLoggedIn() && !liff.isInClient()) {
        sessionStorage.setItem(pendingKey, '1');
        liff.login({ redirectUri: location.href });
        return;
      }
      await authenticate();
    } catch (_) {
      sessionStorage.removeItem(pendingKey);
      show('เปิด LINE Login ไม่สำเร็จ กรุณาตรวจ LIFF URL และลองใหม่', 'error');
      button.disabled = false;
    }
  });

  init();
})();
