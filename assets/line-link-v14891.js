(() => {
  const button = document.querySelector('[data-line-link]');
  const status = document.querySelector('[data-line-status]');
  if (!button || !status || !window.liff) return;

  button.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'กำลังเชื่อมบัญชี...';
    try {
      await liff.init({ liffId: button.dataset.liffId });
      if (!liff.isLoggedIn() && !liff.isInClient()) {
        liff.login({ redirectUri: location.href });
        return;
      }
      const idToken = liff.getIDToken();
      if (!idToken) throw new Error('ไม่พบ ID Token จาก LINE');
      const response = await fetch('api/line-link.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
        body: new URLSearchParams({ csrf: button.dataset.csrf, id_token: idToken }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error === 'line_account_in_use' ? 'บัญชี LINE นี้ผูกกับผู้ใช้อื่นแล้ว' : 'ตรวจสอบบัญชี LINE ไม่สำเร็จ');
      status.textContent = 'เชื่อมบัญชี LINE เรียบร้อย';
      status.dataset.state = 'success';
      window.dispatchEvent(new CustomEvent('mrbar:line-linked'));
    } catch (error) {
      status.textContent = error.message || 'เชื่อมบัญชี LINE ไม่สำเร็จ';
      status.dataset.state = 'error';
      button.disabled = false;
    }
  });
})();
