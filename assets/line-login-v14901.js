(() => {
  'use strict';
  const pinPane=document.getElementById('pinPane');
  const passwordPane=document.getElementById('passwordPane');
  document.querySelector('[data-show-password]')?.addEventListener('click',()=>{pinPane?.classList.add('is-hidden');passwordPane?.classList.remove('is-hidden');document.getElementById('username')?.focus();});
  document.querySelector('[data-show-pin]')?.addEventListener('click',()=>{passwordPane?.classList.add('is-hidden');pinPane?.classList.remove('is-hidden');document.getElementById('pin')?.focus();});
  const toggle=document.querySelector('[data-password-toggle]');
  toggle?.addEventListener('click',()=>{const input=document.getElementById('password');const show=input.type==='password';input.type=show?'text':'password';toggle.textContent=show?'ซ่อน':'ดู';toggle.setAttribute('aria-label',show?'ซ่อนรหัสผ่าน':'แสดงรหัสผ่าน');});
  const pin=document.getElementById('pin');
  pin?.addEventListener('input',()=>{pin.value=pin.value.replace(/\D/g,'').slice(0,6);});
  document.querySelectorAll('.auth-pane form').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('.auth-submit');if(button){button.disabled=true;button.textContent='กำลังเข้าสู่ระบบ...';}}));
})();
