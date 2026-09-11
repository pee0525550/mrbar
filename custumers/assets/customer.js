(function(){
  'use strict';
  var p=document.getElementById('cwParticles');
  if(p){for(var i=0;i<30;i++){var e=document.createElement('i');e.style.left=(Math.random()*100)+'%';e.style.animationDuration=(9+Math.random()*16)+'s';e.style.animationDelay=(-Math.random()*20)+'s';e.style.opacity=(.12+Math.random()*.5);p.appendChild(e);}}
  var modal=document.getElementById('bookingModal');
  function openModal(tab){if(!modal)return;modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';if(tab)showTab(tab);var iframe=modal.querySelector('iframe[data-src]');if(iframe&&!iframe.getAttribute('src'))iframe.setAttribute('src',iframe.getAttribute('data-src'));}
  function closeModal(){if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
  function showTab(tab){if(!modal)return;modal.querySelectorAll('[data-book-tab]').forEach(function(b){b.classList.toggle('active',b.getAttribute('data-book-tab')===tab);});modal.querySelectorAll('[data-book-view]').forEach(function(v){v.classList.toggle('active',v.getAttribute('data-book-view')===tab);});if(tab==='reserve'){var f=modal.querySelector('iframe[data-src]');if(f&&!f.getAttribute('src'))f.setAttribute('src',f.getAttribute('data-src'));}}
  document.querySelectorAll('[data-booking-open]').forEach(function(b){b.addEventListener('click',function(){openModal('reserve');});});
  document.querySelectorAll('[data-booking-close]').forEach(function(b){b.addEventListener('click',closeModal);});
  document.querySelectorAll('[data-book-tab]').forEach(function(b){b.addEventListener('click',function(){showTab(b.getAttribute('data-book-tab'));});});
  document.addEventListener('keydown',function(ev){if(ev.key==='Escape')closeModal();});
  var form=document.getElementById('checkinForm');if(form){form.addEventListener('submit',function(){var b=form.querySelector('button[type="submit"]');if(b){b.disabled=true;var s=b.querySelector('span');if(s)s.textContent='CONNECTING...';}});}
  if(document.body&&document.body.getAttribute('data-auto-open')==='1')setTimeout(function(){openModal('checkin');},350);
})();

/* v1.16.1 premium topbar: active section tracking */
(function(){
  'use strict';
  var links=[].slice.call(document.querySelectorAll('.cw-main-nav a[href^="#"]'));
  if(!links.length)return;
  var pairs=links.map(function(link){var id=link.getAttribute('href').slice(1);return {link:link,section:document.getElementById(id)};}).filter(function(x){return x.section;});
  function setActive(link){links.forEach(function(a){a.classList.toggle('active',a===link);});}
  if(pairs.length&&!document.querySelector('.cw-main-nav a.active[href=\"#'+pairs[0].section.id+'\"]'))setActive(pairs[0].link);
  links.forEach(function(link){link.addEventListener('click',function(){setActive(link);});});
  if('IntersectionObserver' in window&&pairs.length){
    var visible={};
    var io=new IntersectionObserver(function(entries){
      entries.forEach(function(e){visible[e.target.id]=e.isIntersecting?e.intersectionRatio:0;});
      var best=null,bestScore=0;
      pairs.forEach(function(p){var score=visible[p.section.id]||0;if(score>bestScore){best=p;bestScore=score;}});
      if(best)setActive(best.link);
    },{rootMargin:'-18% 0px -58% 0px',threshold:[0,.08,.2,.45,.7]});
    pairs.forEach(function(p){io.observe(p.section);});
  }
})();


/* v1.22.0 Cookie Consent + PDPA preference center */
(function(){
  'use strict';
  var configNode=document.getElementById('mrPrivacyConfig');
  if(!configNode)return;
  var cfg={};
  try{cfg=JSON.parse(configNode.textContent||'{}');}catch(e){return;}
  var state=cfg.preferences||{necessary:true,functional:false,analytics:false,marketing:false};
  var banner=document.querySelector('[data-cookie-banner]');
  var modal=document.querySelector('[data-cookie-modal]');
  var statusNode=modal?modal.querySelector('[data-consent-status]'):null;

  function setStatus(text,isError){
    if(!statusNode)return;
    statusNode.textContent=text||'';
    statusNode.classList.toggle('error',!!isError);
  }
  function setBusy(busy){
    document.querySelectorAll('[data-consent-choice],[data-consent-save]').forEach(function(btn){btn.disabled=!!busy;});
  }
  function emitChange(){
    try{document.dispatchEvent(new CustomEvent('mrbar:consentchange',{detail:{preferences:state,policyVersion:cfg.policyVersion}}));}catch(e){}
  }
  function applyProtectedFeatures(){
    var allowFunctional=!cfg.enabled||!!state.functional;
    document.documentElement.setAttribute('data-cookie-functional',allowFunctional?'1':'0');
    document.querySelectorAll('iframe[data-cookie-src]').forEach(function(frame){
      var gate=frame.parentElement?frame.parentElement.querySelector('[data-cookie-gate="functional"]'):null;
      if(allowFunctional){
        var src=frame.getAttribute('data-cookie-src');
        if(src&&frame.getAttribute('src')!==src)frame.setAttribute('src',src);
        frame.classList.add('cookie-allowed');
        if(gate)gate.hidden=true;
      }else{
        if(frame.getAttribute('src')&&frame.getAttribute('src')!=='about:blank')frame.setAttribute('src','about:blank');
        frame.classList.remove('cookie-allowed');
        if(gate)gate.hidden=false;
      }
    });
    emitChange();
  }
  function syncPreferenceInputs(){
    if(!modal)return;
    modal.querySelectorAll('[data-cookie-pref]').forEach(function(input){
      var key=input.getAttribute('data-cookie-pref');
      input.checked=!!state[key];
    });
  }
  function openPreferences(){
    if(!cfg.enabled||!modal)return;
    syncPreferenceInputs();
    setStatus('',false);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('cw-cookie-open');
  }
  function closePreferences(){
    if(!modal)return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('cw-cookie-open');
  }
  function updateBanner(){
    if(!banner)return;
    banner.hidden=!cfg.enabled||!!cfg.hasConsent;
  }
  function selectedPreferences(){
    var next={necessary:true,functional:false,analytics:false,marketing:false};
    if(!modal)return next;
    modal.querySelectorAll('[data-cookie-pref]').forEach(function(input){
      var key=input.getAttribute('data-cookie-pref');
      if(Object.prototype.hasOwnProperty.call(next,key))next[key]=!!input.checked;
    });
    return next;
  }
  function postConsent(mode,prefs){
    if(!cfg.enabled)return Promise.resolve({ok:true,preferences:state});
    var body=new URLSearchParams();
    body.append('csrf',cfg.csrf||'');
    body.append('mode',mode);
    body.append('functional',prefs&&prefs.functional?'1':'0');
    body.append('analytics',prefs&&prefs.analytics?'1':'0');
    body.append('marketing',prefs&&prefs.marketing?'1':'0');
    setBusy(true);setStatus('กำลังบันทึกตัวเลือก...',false);
    return fetch(cfg.endpoint||'privacy-consent.php',{
      method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
      body:body.toString()
    }).then(function(res){return res.json().then(function(data){if(!res.ok||!data.ok)throw new Error(data.message||'บันทึก Consent ไม่สำเร็จ');return data;});})
      .then(function(data){
        state=data.preferences||state;cfg.preferences=state;cfg.hasConsent=true;
        applyProtectedFeatures();updateBanner();syncPreferenceInputs();
        setStatus('บันทึกตัวเลือกแล้ว',false);setTimeout(closePreferences,260);
        return data;
      }).catch(function(err){setStatus(err&&err.message?err.message:'บันทึก Consent ไม่สำเร็จ',true);throw err;})
      .finally(function(){setBusy(false);});
  }

  document.querySelectorAll('[data-cookie-settings]').forEach(function(btn){btn.addEventListener('click',openPreferences);});
  document.querySelectorAll('[data-cookie-close]').forEach(function(btn){btn.addEventListener('click',closePreferences);});
  document.querySelectorAll('[data-consent-choice]').forEach(function(btn){btn.addEventListener('click',function(){var mode=btn.getAttribute('data-consent-choice')==='all'?'all':'necessary';postConsent(mode,{functional:false,analytics:false,marketing:false}).catch(function(){});});});
  document.querySelectorAll('[data-consent-save]').forEach(function(btn){btn.addEventListener('click',function(){postConsent('preferences',selectedPreferences()).catch(function(){});});});
  document.addEventListener('keydown',function(ev){if(ev.key==='Escape'&&modal&&modal.classList.contains('open'))closePreferences();});

  updateBanner();applyProtectedFeatures();syncPreferenceInputs();
  try{var q=new URLSearchParams(window.location.search);if(q.get('privacy')==='settings')setTimeout(openPreferences,80);}catch(e){}
})();
