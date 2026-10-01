(() => {
    const form = document.querySelector('[data-line-broadcast]');
    if (!form) return;

    const message = form.querySelector('[name="message"]');
    const confirmAll = form.querySelector('[name="confirm_all"]');
    const confirmPhrase = form.querySelector('[name="confirm_phrase"]');
    const button = form.querySelector('[data-broadcast-submit]');
    const status = form.querySelector('[data-broadcast-status]');
    const count = form.querySelector('[data-broadcast-count]');
    const preview = form.querySelector('[data-broadcast-preview]');
    const ready = form.dataset.ready === '1';
    let pendingKey = '';
    let sending = false;
    let cooldownUntil = Number(form.dataset.cooldownUntil || 0);

    const newRetryKey = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    const setStatus = (text, state = '') => {
        status.textContent = text;
        status.dataset.state = state;
    };

    const refresh = () => {
        const length = Array.from(message.value).length;
        count.textContent = `${length.toLocaleString()} / 5,000`;
        preview.textContent = message.value.trim() || 'ตัวอย่างข้อความจะแสดงที่นี่';
        const wait = Math.max(0, cooldownUntil - Math.floor(Date.now() / 1000));
        const confirmed = confirmAll.checked && confirmPhrase.value.trim() === 'ยืนยันส่ง';
        button.disabled = !ready || sending || wait > 0 || !message.value.trim() || !confirmed;
        button.textContent = sending ? 'กำลังส่ง…' : (wait > 0 ? `ส่งได้อีก ${wait} วินาที` : 'ส่ง Broadcast');
    };

    message.addEventListener('input', () => {
        pendingKey = '';
        refresh();
    });
    confirmAll.addEventListener('change', refresh);
    confirmPhrase.addEventListener('input', refresh);

    const errorMessages = {
        unauthorized: 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่',
        forbidden: 'บัญชีนี้ไม่มีสิทธิ์ส่ง Broadcast',
        csrf_expired: 'เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่',
        confirmation_required: 'กรุณาติ๊กยืนยันและพิมพ์ “ยืนยันส่ง”',
        invalid_message: 'ข้อความว่างหรือยาวเกิน 5,000 ตัวอักษร',
        invalid_retry_key: 'สร้างรหัสป้องกันส่งซ้ำไม่สำเร็จ กรุณารีเฟรชหน้า',
        line_messaging_not_configured: 'ยังไม่ได้ตั้งค่า LINE Messaging API Token บน Server',
        rate_limited: 'มีการส่ง Broadcast ไปเมื่อครู่นี้ กรุณารอเวลานับถอยหลัง',
        line_credentials_rejected: 'LINE ปฏิเสธ Token กรุณาตรวจ Messaging API Channel Access Token',
        line_quota_or_rate_limit: 'ส่งไม่สำเร็จ: โควตาข้อความหรือขีดจำกัดการส่งของ LINE อาจเต็ม',
        line_request_rejected: 'LINE ไม่รับข้อความนี้ กรุณาตรวจเนื้อหาแล้วลองใหม่',
        storage_unavailable: 'ระบบบันทึกสถานะส่งไม่พร้อม จึงยังไม่ได้ส่งข้อความ',
        line_broadcast_failed: 'ส่งไม่สำเร็จ กรุณาตรวจการเชื่อมต่อและลองอีกครั้ง'
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        refresh();
        if (button.disabled) return;
        if (!window.confirm('ยืนยันส่งข้อความนี้ไปยังผู้ติดตาม LINE OA ทั้งหมด? การส่งจะใช้โควตาข้อความตามจำนวนผู้รับ')) return;
        if (!window.crypto?.getRandomValues) {
            setStatus('เบราว์เซอร์นี้สร้างรหัสป้องกันส่งซ้ำไม่ได้ กรุณาอัปเดตเบราว์เซอร์', 'error');
            return;
        }

        sending = true;
        if (!pendingKey) pendingKey = newRetryKey();
        setStatus('กำลังส่งคำสั่งไปยัง LINE…');
        refresh();
        const body = new URLSearchParams({
            csrf: form.querySelector('[name="csrf"]').value,
            message: message.value.trim(),
            confirm_all: confirmAll.checked ? '1' : '0',
            confirm_phrase: confirmPhrase.value.trim(),
            retry_key: pendingKey
        });

        try {
            const response = await fetch('api/line-broadcast.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body,
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const raw = await response.text();
            let result;
            try { result = JSON.parse(raw); } catch { result = { error: 'line_broadcast_failed' }; }

            if (response.ok && result.ok) {
                pendingKey = '';
                message.value = '';
                confirmAll.checked = false;
                confirmPhrase.value = '';
                cooldownUntil = Math.floor(Date.now() / 1000) + Number(result.cooldown_seconds || 60);
                setStatus(result.duplicate ? 'LINE รับคำสั่งนี้ไว้แล้วก่อนหน้านี้ ระบบป้องกันการส่งซ้ำให้แล้ว' : 'LINE รับคำสั่ง Broadcast แล้ว (สถานะนี้ไม่ได้ยืนยันว่าผู้รับทุกคนได้รับหรืออ่านข้อความ)', 'success');
            } else {
                const wait = Number(result.retry_after || 0);
                if (wait > 0) cooldownUntil = Math.floor(Date.now() / 1000) + wait;
                setStatus(errorMessages[result.error] || `ส่งไม่สำเร็จ${result.http_status ? ` (HTTP ${result.http_status})` : ''}`, 'error');
            }
        } catch {
            setStatus('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ หากลองซ้ำ ระบบจะใช้รหัสเดิมเพื่อกันส่งซ้ำ', 'error');
        } finally {
            sending = false;
            refresh();
        }
    });

    refresh();
    window.setInterval(refresh, 1000);
})();
