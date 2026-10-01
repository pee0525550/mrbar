(() => {
  'use strict';
  const panel = document.querySelector('[data-line-flow]');
  if (!panel) return;
  const action = panel.querySelector('[data-line-action]');
  const unlink = panel.querySelector('[data-line-unlink]');
  const status = panel.querySelector('[data-line-status]');
  const mode = panel.dataset.lineFlow;
  const pendingKey = 'mrbar_line_pending_flow';
  const show = (message, state = '') => { if (status) { status.textContent = message; status.dataset.state = state; } };
  const errors = { line_account_not_linked: 'LINE นี้ยังไม่ผูกกับบัญชีพนักงาน กรุณาเข้าด้วยรหัสผ่านก่อน แล้วเลือก “เชื่อม LINE ของฉัน”', line_account_ambiguous: 'LINE นี้ผูกกับหลายบัญชี กรุณาติดต่อผู้ดูแล', line_account_in_use: 'LINE นี้ผูกกับพนักงานคนอื่นแล้ว', line_already_linked: 'บัญชีนี้ผูก LINE อื่นอยู่ กรุณายกเลิกการเชื่อมเดิมก่อน', csrf_expired: 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่', line_login_not_configured: 'ระบบยังตั้งค่า LINE Login ไม่ครบ กรุณาติดต่อผู้ดูแล', line_token_missing: 'ไม่พบ Token ยืนยัน LINE กรุณาตรวจว่า LIFF เปิด scope openid และอนุญาตสิทธิ์แล้ว', line_channel_mismatch: 'LIFF ID กับ LINE Login Channel ID ไม่ตรงกัน กรุณาให้ผู้ดูแลตรวจว่า LIFF อยู่ใน Channel เดียวกับที่ตั้งค่าไว้', line_token_expired: 'Token ยืนยัน LINE หมดอายุ กรุณาปิดหน้านี้แล้วเริ่มจากปุ่มจองอีกครั้ง', line_verify_unavailable: 'ติดต่อระบบยืนยันตัวตน LINE ไม่สำเร็จ กรุณาลองใหม่เมื่อเครือข่ายพร้อม', line_token_invalid: 'LINE ปฏิเสธ Token ยืนยัน กรุณาเริ่มขั้นตอนใหม่จากปุ่มจอง', line_verify_failed: 'ตรวจสอบบัญชี LINE ไม่สำเร็จ กรุณาลองใหม่หรือติดต่อผู้ดูแล', line_server_error: 'ระบบหลังบ้านเกิดข้อผิดพลาดระหว่างตรวจสอบ LINE กรุณาลองใหม่ หากยังเป็นให้แจ้งผู้ดูแลพร้อมรหัส HTTP ที่แสดง' };
  const bookingErrors = {
    line_oa_not_configured: 'ร้านยังไม่ได้ตั้งค่าลิงก์ OA กรุณาติดต่อร้านก่อนจอง',
    line_oa_friend_required: 'ต้องเพิ่มเพื่อน LINE OA ก่อนจึงจะจองได้ เปิดลิงก์ OA แล้วกลับมาตรวจสอบอีกครั้ง',
    line_access_token_missing: 'อ่านสิทธิ์ LINE ไม่ได้ กรุณาปิดแล้วเริ่มจองใหม่จากใน LINE',
    line_access_token_invalid: 'เซสชัน LINE หมดอายุหรือสิทธิ์ไม่ครบ กรุณาปิดแล้วเริ่มจองใหม่จากใน LINE',
    line_access_token_channel_mismatch: 'สิทธิ์ LINE ไม่ตรงกับ Login Channel ของร้าน กรุณาแจ้งผู้ดูแลตรวจการตั้งค่า',
    line_token_subject_mismatch: 'บัญชี LINE ยืนยันไม่ตรงกัน กรุณาปิดแล้วเริ่มจองใหม่',
    line_profile_scope_required: 'LIFF ยังไม่ได้เปิดสิทธิ์ profile และ openid กรุณาแจ้งผู้ดูแล',
    line_friendship_unavailable: 'LINE ตรวจสถานะเพื่อนไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
  };
  let busy = false;
  const ready = window.liff ? liff.init({ liffId: panel.dataset.lineLiffId }) : Promise.reject(new Error('โหลด LINE SDK ไม่สำเร็จ'));
  async function requestCustomerFriendship() {
    if (mode !== 'customer_booking') return 'unknown';
    if (panel.dataset.lineOaConfigured !== '1') throw new Error(bookingErrors.line_oa_not_configured);
    if (typeof liff.getFriendship !== 'function') throw new Error('ตรวจสถานะเพื่อน OA ไม่ได้ กรุณาเปิด LIFF จากใน LINE และให้ผู้ดูแลตรวจ scope profile กับการผูก OA ใน LINE Developers');
    let current;
    try { current = await liff.getFriendship(); }
    catch (_) { throw new Error('ตรวจสถานะเพื่อน OA ไม่ได้ กรุณาเปิด LIFF จากใน LINE และให้ผู้ดูแลตรวจ scope profile กับการผูก OA ใน LINE Developers'); }
    if (current && current.friendFlag === true) return 'friend';
    if (typeof liff.requestFriendship !== 'function') throw new Error('เปิดหน้าต่างเพิ่มเพื่อน OA ไม่ได้ กรุณาเปิดลิงก์ OA สำรอง แล้วกลับมาตรวจสอบอีกครั้ง');
    try {
      show('กรุณาตรวจสอบหน้าต่าง LINE เพื่อเพิ่มเพื่อนรับข่าวการจอง...');
      await liff.requestFriendship();
    } catch (_) { throw new Error('เปิดหน้าต่างเพิ่มเพื่อน OA ไม่สำเร็จ กรุณาเปิดลิงก์ OA สำรอง แล้วกลับมากดตรวจสอบอีกครั้ง'); }
    let updated;
    try { updated = await liff.getFriendship(); }
    catch (_) { throw new Error('ตรวจสถานะเพื่อน OA ไม่ได้ กรุณาเปิด LIFF จากใน LINE และให้ผู้ดูแลตรวจ scope profile กับการผูก OA ใน LINE Developers'); }
    if (!updated || updated.friendFlag !== true) throw new Error(bookingErrors.line_oa_friend_required);
    return 'friend';
  }
  async function sendToken() {
    if (busy || !action) return;
    busy = true; action.disabled = true; show('กำลังยืนยันบัญชี LINE...');
    try {
      await ready;
      if (mode === 'customer_booking') await requestCustomerFriendship();
      const decodedToken = typeof liff.getDecodedIDToken === 'function' ? liff.getDecodedIDToken() : null;
      if (decodedToken && decodedToken.aud && panel.dataset.lineChannelId && decodedToken.aud !== panel.dataset.lineChannelId) throw new Error(errors.line_channel_mismatch);
      const token = liff.getIDToken();
      if (!token) throw new Error(errors.line_token_missing);
      const body = new URLSearchParams({ csrf: panel.dataset.lineCsrf, id_token: token });
      if (mode !== 'link') body.set('return_to', panel.dataset.lineReturn || '');
      if (mode === 'customer_booking') {
        const accessToken = typeof liff.getAccessToken === 'function' ? liff.getAccessToken() : '';
        if (!accessToken) throw new Error(errors.line_access_token_missing);
        body.set('access_token', accessToken);
      }
      const response = await fetch(panel.dataset.lineEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body });
      const rawResponse = await response.text();
      let result = null;
      try { result = rawResponse ? JSON.parse(rawResponse) : null; } catch (_) { /* Show a controlled message for empty or non-JSON server errors. */ }
      if (!result || typeof result !== 'object' || Array.isArray(result)) {
        const httpStatus = response.status ? ` (HTTP ${response.status})` : '';
        throw new Error(`เซิร์ฟเวอร์ยืนยัน LINE ตอบกลับไม่สมบูรณ์${httpStatus} กรุณาลองใหม่ หากยังเป็นให้แจ้งผู้ดูแล`);
      }
      if (!response.ok) throw new Error(errors[result.error] || bookingErrors[result.error] || 'ดำเนินการไม่สำเร็จ กรุณาลองใหม่');
      sessionStorage.removeItem(pendingKey);
      if (mode === 'link') { show('เชื่อมบัญชี LINE สำเร็จ ตอนนี้ใช้ LINE เข้าระบบได้แล้ว', 'success'); window.location.reload(); }
      else window.location.assign(result.redirect);
    } catch (error) { sessionStorage.removeItem(pendingKey); show(error.message || 'ยืนยัน LINE ไม่สำเร็จ', 'error'); action.disabled = false; busy = false; }
  }
  if (action) {
    action.addEventListener('click', async () => {
      action.disabled = true; show('กำลังเปิด LINE...');
      try {
        await ready;
        if (!liff.isLoggedIn()) { sessionStorage.setItem(pendingKey, mode); liff.login({ redirectUri: window.location.href }); return; }
        await sendToken();
      } catch (error) { show(error.message || 'เปิด LINE ไม่สำเร็จ กรุณาลองใหม่', 'error'); action.disabled = false; }
    });
    ready.then(() => { if (sessionStorage.getItem(pendingKey) === mode && liff.isLoggedIn()) sendToken(); }).catch(() => show('โหลด LINE ไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วรีเฟรช', 'error'));
  }
  if (unlink) unlink.addEventListener('click', async () => {
    if (!window.confirm('ยกเลิกการเชื่อม LINE ของบัญชีนี้? หลังจากนี้ต้องใช้รหัสผ่านหรือ PIN เพื่อเข้าระบบ')) return;
    unlink.disabled = true; show('กำลังยกเลิกการเชื่อม...');
    try {
      const response = await fetch('api/line-unlink.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' }, body: new URLSearchParams({ csrf: panel.dataset.lineCsrf }) });
      if (!response.ok) throw new Error('ยกเลิกการเชื่อมไม่สำเร็จ กรุณาลองใหม่');
      window.location.reload();
    } catch (error) { show(error.message, 'error'); unlink.disabled = false; }
  });
})();
