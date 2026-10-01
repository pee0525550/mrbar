(() => {
  const button = document.querySelector('[data-line-test]');
  const status = document.querySelector('[data-line-status]');
  if (!button || !status) return;

  button.addEventListener('click', async () => {
    button.disabled = true;
    button.textContent = 'กำลังส่งข้อความทดสอบ...';
    status.dataset.state = '';
    status.textContent = 'กำลังส่งข้อความไปยัง LINE ที่เชื่อมไว้';
    let cooldown = 0;

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
          line_push_failed: 'ส่งไม่สำเร็จ กรุณาตรวจ Token และสถานะการบล็อก OA',
          rate_limited: `รอสักครู่แล้วลองใหม่ (${result.retry_after || 60} วินาที)`,
        };
        if (result.error === 'rate_limited') cooldown = Math.max(1, Number(result.retry_after) || 60);
        throw new Error(messages[result.error] || 'ส่งข้อความทดสอบไม่สำเร็จ');
      }
      cooldown = 60;
      status.dataset.state = 'success';
      status.textContent = 'ส่งแล้ว เปิด LINE บนมือถือเพื่อตรวจข้อความจาก MR BAR';
    } catch (error) {
      status.dataset.state = 'error';
      status.textContent = error.message || 'ส่งข้อความทดสอบไม่สำเร็จ';
    } finally {
      button.textContent = 'ส่งข้อความทดสอบ';
      if (cooldown > 0) {
        window.setTimeout(() => { button.disabled = false; }, cooldown * 1000);
      } else {
        button.disabled = false;
      }
    }
  });
})();
