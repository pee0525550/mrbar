/* MR BAR v1.27.21 — auth/onboarding viewport + browser UX only */
(function(){
  'use strict';
  const root=document.documentElement,body=document.body;
  function syncViewport(){
    const vv=window.visualViewport;
    const h=Math.max(320,Math.round(vv?vv.height:window.innerHeight));
    root.style.setProperty('--mrbar-auth-vh',h+'px');
    const keyboard=!!vv && (window.innerHeight-vv.height)>140;
    body&&body.classList.toggle('keyboard-open',keyboard);
  }
  syncViewport();
  window.addEventListener('resize',syncViewport,{passive:true});
  window.addEventListener('orientationchange',()=>setTimeout(syncViewport,120),{passive:true});
  if(window.visualViewport){visualViewport.addEventListener('resize',syncViewport,{passive:true});visualViewport.addEventListener('scroll',syncViewport,{passive:true});}

  document.querySelectorAll('input[type="password"]').forEach(input=>{
    if(input.classList.contains('pin-input')||input.classList.contains('pin'))return;
    const parent=input.parentElement;if(!parent)return;
    const wrap=document.createElement('div');wrap.className='password-field-wrap';parent.insertBefore(wrap,input);wrap.appendChild(input);
    const btn=document.createElement('button');btn.type='button';btn.className='staff-auth-password-toggle';btn.textContent='ดู';btn.setAttribute('aria-label','แสดงรหัสผ่าน');
    btn.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';btn.textContent=show?'ซ่อน':'ดู';btn.setAttribute('aria-label',show?'ซ่อนรหัสผ่าน':'แสดงรหัสผ่าน');input.focus({preventScroll:true});});
    wrap.appendChild(btn);
  });

  const ua=navigator.userAgent||'';
  const inApp=/(Line\/|FBAN|FBAV|Instagram|Twitter|MicroMessenger|wv\)|; wv)/i.test(ua);
  if(inApp && sessionStorage.getItem('mrbar_inapp_note_closed')!=='1'){
    const note=document.createElement('aside');note.className='staff-browser-note';note.innerHTML='<span>!</span><div><b>กำลังเปิดผ่าน Browser ภายในแอป</b><small>เข้าสู่ระบบได้ตามปกติ แต่ตอนลงเวลา หาก GPS/Camera ไม่ขึ้น แนะนำเปิดลิงก์นี้ด้วย Chrome หรือ Safari</small></div><button type="button" aria-label="ปิด">×</button>';
    note.querySelector('button').addEventListener('click',()=>{sessionStorage.setItem('mrbar_inapp_note_closed','1');note.remove();});
    const section=document.querySelector('.wrap > section:last-child');const cardHost=document.querySelector('.card');if(section)section.prepend(note);else if(cardHost)cardHost.prepend(note);else body.prepend(note);
  }

  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const btn=form.querySelector('button[type="submit"],button:not([type])');if(btn&&!btn.disabled){btn.dataset.originalText=btn.textContent;btn.classList.add('is-submitting');setTimeout(()=>{if(document.visibilityState==='visible'&&btn.isConnected){btn.classList.remove('is-submitting');if(btn.dataset.originalText)btn.textContent=btn.dataset.originalText;}},5000);}},false));
})();
