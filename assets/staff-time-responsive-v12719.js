(()=>{
  const root=document.documentElement;
  const ua=navigator.userAgent||'';
  const isAndroid=/Android/i.test(ua);
  const isWebView=/;\s*wv\)|\bwv\b|Line\/|FBAN|FBAV|Instagram|Messenger/i.test(ua);
  root.dataset.staffPlatform=isAndroid?'android':'other';
  if(isWebView)root.dataset.staffBrowserShell='embedded';

  let timer=0,lastH=0,lastW=0;
  function syncViewport(){
    const vv=window.visualViewport;
    const h=Math.round(vv?.height||window.innerHeight||0);
    const w=Math.round(vv?.width||window.innerWidth||0);
    if(!h||!w)return;
    root.style.setProperty('--staff-viewport-height',h+'px');
    root.style.setProperty('--staff-viewport-width',w+'px');
    const short=h<=760;
    const ratio=short ? .39 : .43;
    const min=short?250:270,max=short?310:380;
    const map=Math.max(min,Math.min(max,Math.round(h*ratio)));
    root.style.setProperty('--staff-map-height',map+'px');
    if(h!==lastH||w!==lastW){
      lastH=h;lastW=w;
      clearTimeout(timer);
      timer=setTimeout(()=>{try{window.dispatchEvent(new Event('resize'));}catch(e){}},120);
    }
  }
  syncViewport();
  window.addEventListener('resize',syncViewport,{passive:true});
  window.addEventListener('orientationchange',()=>setTimeout(syncViewport,120),{passive:true});
  if(window.visualViewport){
    visualViewport.addEventListener('resize',syncViewport,{passive:true});
  }
})();
