(() => {
  'use strict';
  const input = document.getElementById('pin');
  const form = document.getElementById('pinForm');
  const entry = document.getElementById('pinEntry');
  const feedback = document.getElementById('pinFeedback');
  const slots = [...document.querySelectorAll('.pin-slot')];
  if (!input || !form || !entry) return;

  const paint = () => {
    const value = input.value.replace(/\D/g, '').slice(0, 6);
    if (input.value !== value) input.value = value;
    slots.forEach((slot, index) => {
      slot.classList.toggle('is-filled', index < value.length);
      slot.classList.toggle('is-current', index === value.length && value.length < 6);
    });
    entry.classList.remove('is-error');
    if (feedback) feedback.textContent = value.length
      ? `${6 - value.length} หลักที่เหลือ`
      : 'กรอก PIN 6 หลักเพื่อเข้าสู่ระบบ';
    if (value.length === 6 && !form.dataset.submitting) {
      form.dataset.submitting = '1';
      entry.classList.add('is-verifying');
      if (feedback) feedback.textContent = 'กำลังตรวจสอบ PIN...';
      form.requestSubmit();
    }
  };

  input.addEventListener('input', paint);
  input.addEventListener('focus', () => entry.classList.add('is-focused'));
  input.addEventListener('blur', () => entry.classList.remove('is-focused'));
  entry.addEventListener('click', () => input.focus());
  entry.classList.add('is-enhanced');
  if (entry.dataset.invalid === '1') {
    entry.classList.add('is-error');
    slots.forEach(slot => slot.classList.add('is-invalid'));
  }
  paint();
})();
