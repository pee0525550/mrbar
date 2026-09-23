/* MR BAR TIME PWA helper — v1.27.21 */
(function(){
  'use strict';
  const productionHost=/^(?:www\.)?mrbarsupport\.com$/i.test(location.hostname);
  if(productionHost&&location.protocol!=='https:'){
    const secureUrl='https://'+location.host+location.pathname+location.search+location.hash;
    location.replace(secureUrl);
    return;
  }
  const script=document.currentScript;
  const scriptUrl=script&&script.src?new URL(script.src,location.href):new URL('assets/pwa-time.js',location.href);
  const appBase=new URL('../',scriptUrl);
  const isLocal=['localhost','127.0.0.1'].includes(location.hostname);
  const secure=location.protocol==='https:'||isLocal;
  const standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  document.documentElement.classList.toggle('mr-pwa-standalone',standalone);
  if('serviceWorker' in navigator&&secure){
    window.addEventListener('load',()=>navigator.serviceWorker.register(new URL('pwa-sw.js?v=1.48.52',appBase).href,{scope:appBase.pathname}).catch(()=>{}));
  }
  const page=location.pathname.split('/').pop()||'';
  const relevant=['login.php','setup-pin.php','employee-activate.php','employee-time.php','employee-calendar.php','employee-income.php','pr.php','pr-calendar.php','pr-jobs.php','time.php'].includes(page)||new URLSearchParams(location.search).get('pwa')==='1';
  if(!relevant||standalone)return;
  let deferredPrompt=null;
  let card=null;
  const ua=navigator.userAgent||'';
  const isiOS=/iPhone|iPad|iPod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
  const inApp=/(Line\/|FBAN|FBAV|Instagram|Twitter|MicroMessenger|wv\)|; wv)/i.test(ua);
  function makeCard(mode){
    if(card||inApp)return card;
    card=document.createElement('aside');
    card.className='mr-pwa-install-card';
    card.setAttribute('role','region');
    card.setAttribute('aria-label','ติดตั้ง MR BAR TIME');
    card.innerHTML='<div class="mr-pwa-install-icon">◷</div><div class="mr-pwa-install-copy"><b>MR BAR TIME</b><small>เพิ่มลงหน้าจอมือถือ เพื่อเปิด Check-in / Check-out ได้สะดวกขึ้น</small></div><div class="mr-pwa-install-actions"><button type="button" class="mr-pwa-install-btn">ติดตั้ง</button><button type="button" class="mr-pwa-close" aria-label="ปิด">×</button></div>';
    if(mode==='ios'){
      const steps=document.createElement('div');
      steps.className='mr-pwa-ios-steps';
      steps.innerHTML='Safari: กด <strong>Share</strong> → <strong>Add to Home Screen / เพิ่มไปยังหน้าจอโฮม</strong>';
      card.appendChild(steps);
      card.querySelector('.mr-pwa-install-btn').textContent='วิธีติดตั้ง';
    }
    card.querySelector('.mr-pwa-close').addEventListener('click',()=>{card.remove();card=null;sessionStorage.setItem('mrbar_pwa_tip_closed','1');});
    card.querySelector('.mr-pwa-install-btn').addEventListener('click',async()=>{
      if(deferredPrompt){
        deferredPrompt.prompt();
        try{await deferredPrompt.userChoice;}catch(e){}
        deferredPrompt=null;
        if(card){card.remove();card=null;}
      }else if(isiOS){
        const steps=card.querySelector('.mr-pwa-ios-steps');
        if(steps)steps.scrollIntoView({behavior:'smooth',block:'nearest'});
      }
    });
    document.body.appendChild(card);
    return card;
  }
  if(sessionStorage.getItem('mrbar_pwa_tip_closed')!=='1'&&isiOS)window.addEventListener('load',()=>makeCard('ios'));
  window.addEventListener('beforeinstallprompt',event=>{
    event.preventDefault();deferredPrompt=event;
    if(sessionStorage.getItem('mrbar_pwa_tip_closed')!=='1')makeCard('install');
  });
  window.addEventListener('appinstalled',()=>{deferredPrompt=null;if(card){card.remove();card=null;}});
})();
