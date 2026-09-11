(function(){
  'use strict';
  if(window.__MRBAR_THEME_ENGINE__) return;
  window.__MRBAR_THEME_ENGINE__=true;
  var root=document.documentElement;
  var key='mrbar_theme';
  function safeGet(){try{var t=localStorage.getItem(key);return t==='light'||t==='dark'?t:(root.getAttribute('data-mr-theme')||'dark');}catch(e){return root.getAttribute('data-mr-theme')||'dark';}}
  function safeSet(t){try{localStorage.setItem(key,t);}catch(e){}}
  function themeMeta(t){
    var m=document.querySelector('meta[name="theme-color"]');
    if(!m){m=document.createElement('meta');m.name='theme-color';document.head.appendChild(m);}
    m.content=t==='light'?'#f4f7ff':'#070912';
  }
  function updateButton(btn,t){
    if(!btn)return;
    btn.setAttribute('aria-pressed',t==='light'?'true':'false');
    btn.setAttribute('aria-label',t==='light'?'เปลี่ยนเป็นโหมดมืด':'เปลี่ยนเป็นโหมดสว่าง');
    btn.title=t==='light'?'Dark mode':'Light mode';
    var label=btn.querySelector('.mr-theme-label');
    if(label)label.textContent=t==='light'?'LIGHT':'DARK';
  }
  function apply(t,persist){
    if(t!=='light'&&t!=='dark')t='dark';
    root.setAttribute('data-mr-theme',t);
    if(document.body)document.body.setAttribute('data-mr-theme',t);
    if(persist)safeSet(t);
    themeMeta(t);
    document.querySelectorAll('.mr-theme-toggle').forEach(function(btn){updateButton(btn,t);});
    try{window.dispatchEvent(new CustomEvent('mrbar:themechange',{detail:{theme:t}}));}catch(e){}
  }
  function createSwitch(){
    if(document.querySelector('.mr-theme-toggle'))return;
    var btn=document.createElement('button');
    btn.type='button';
    btn.className='mr-theme-toggle';
    btn.innerHTML='<span class="mr-theme-label">DARK</span><span class="mr-theme-track" aria-hidden="true"><i class="mr-theme-stars">✦</i><i class="mr-theme-sun">☀</i><b class="mr-theme-knob"><span>☾</span></b></span>';
    btn.addEventListener('click',function(){apply(safeGet()==='dark'?'light':'dark',true);});
    var host=document.querySelector('[data-theme-slot], .appbar-right, .top-actions, .head-actions');
    if(host){btn.classList.add('mr-theme-inline');host.insertBefore(btn,host.firstChild);}else{btn.classList.add('mr-theme-floating');document.body.appendChild(btn);}
    updateButton(btn,safeGet());
  }
  function init(){apply(safeGet(),false);createSwitch();}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
