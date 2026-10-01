(() => {
  const button = document.querySelector('[data-line-test]');
  const status = document.querySelector('[data-line-status]');
  if (!button || !status) return;

  window.addEventListener('mrbar:line-linked', () => {
    button.disabled = false;
    if (button.textContent.trim().includes('รอตั้งค่า')) return;
    status.textContent = 'เชื่อมบัญชีแล้ว กดส่งข้อความทดสอบเพื่อเช็ก LINE บนมือถือ';
  });

  button.addEventListener('click', async () => {
    button.disabled = true;
    button.textContent = 'กำลังส่งข้อความทดสอบ...';
    status.dataset.state = '';
    status.textContent = 'กำลังส่งข้อความไปยัง LINE ที่เชื่อมไว้';

    try {
      const response = await fetch('api/line-test.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
        body: new URLSearchParams({ csrf: button.dataset.csrf }),
      });
      const result = await response.json();
      if (!response.ok) {
        const messages = {
          line_not_linked: 'กรุณาเชื่อมบัญชี LINE ก่อน',
          line_messaging_not_configured: 'ยังไม่ได้ตั้งค่า LINE Messaging API บน Server',
          line_push_failed: 'ส่งไม่สำเร็จ กรุณาตรวจ LINE OA, Token และสถานะการบล็อก',
          rate_limited: `รอสักครู่แล้วลองใหม่ (${result.retry_after || 60} วินาที)`,
        };
        throw new Error(messages[result.error] || 'ส่งข้อความทดสอบไม่สำเร็จ');
      }
      status.dataset.state = 'success';
      status.textContent = 'ส่งแล้วครับ เปิด LINE บนมือถือเพื่อตรวจสอบข้อความจาก MR BAR';
    } catch (error) {
      status.dataset.state = 'error';
      status.textContent = error.message || 'ส่งข้อความทดสอบไม่สำเร็จ';
    } finally {
      button.textContent = 'ส่งข้อความทดสอบ';
      window.setTimeout(() => { button.disabled = false; }, 60000);
    }
  });
})();
