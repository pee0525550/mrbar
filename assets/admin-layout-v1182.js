(function(){
  'use strict';
  var html=document.documentElement;
  var body=document.body;
  var side=document.getElementById('sidebar');
  var collapse=document.getElementById('adminSidebarCollapse');
  var backdrop=document.getElementById('adminSidebarBackdrop');
  var mobileLauncher=document.getElementById('adminMobileLauncher');
  if(!side){return;}

  var KEY='mrbar_admin_sidebar_compact';
  var previousFocus=null;
  var desktop=function(){return window.matchMedia('(min-width: 901px)').matches;};

  /* Tooltip labels are generated from visible menu text; no duplicated config. */
  side.querySelectorAll('nav a').forEach(function(a){
    var b=a.querySelector('b');
    if(b){a.setAttribute('data-admin-tooltip',b.textContent.trim()); if(!a.getAttribute('title'))a.setAttribute('title',b.textContent.trim());}
  });

  function applySaved(){
    if(desktop()){
      var compact='0';
      try{compact=localStorage.getItem(KEY)||'0';}catch(e){}
      html.classList.toggle('admin-sidebar-collapsed',compact==='1');
      closeMobile();
    }else{
      html.classList.remove('admin-sidebar-collapsed');
    }
    updateCollapseA11y();
    side.inert=!desktop()&&!side.classList.contains('open');
  }
  function updateCollapseA11y(){
    if(!collapse)return;
    var compact=html.classList.contains('admin-sidebar-collapsed');
    collapse.setAttribute('aria-expanded',compact?'false':'true');
    collapse.setAttribute('title',compact?'ขยายเมนู':'ย่อเมนู');
    collapse.setAttribute('aria-label',compact?'ขยายเมนูผู้ดูแล':'ย่อเมนูผู้ดูแล');
  }
  function toggleCompact(){
    if(!desktop()){closeMobile();return;}
    var compact=!html.classList.contains('admin-sidebar-collapsed');
    html.classList.toggle('admin-sidebar-collapsed',compact);
    try{localStorage.setItem(KEY,compact?'1':'0');}catch(e){}
    updateCollapseA11y();
    window.setTimeout(function(){window.dispatchEvent(new Event('resize'));},260);
  }
  function openMobile(){
    if(desktop())return;
    previousFocus=document.activeElement;
    side.inert=false;
    side.classList.add('open');
    body.classList.add('admin-mobile-nav-open');
    if(backdrop)backdrop.setAttribute('aria-hidden','false');
    if(mobileLauncher)mobileLauncher.setAttribute('aria-expanded','true');
    if(collapse){collapse.setAttribute('aria-label','ปิดเมนู');collapse.focus();}
  }
  function closeMobile(){
    var wasOpen=side.classList.contains('open');
    side.classList.remove('open');
    body.classList.remove('admin-mobile-nav-open');
    if(backdrop)backdrop.setAttribute('aria-hidden','true');
    side.inert=!desktop();
    if(mobileLauncher)mobileLauncher.setAttribute('aria-expanded','false');
    if(wasOpen&&previousFocus&&previousFocus.isConnected)previousFocus.focus();
  }

  if(collapse)collapse.addEventListener('click',toggleCompact);
  if(mobileLauncher){
    mobileLauncher.setAttribute('aria-controls','sidebar');
    mobileLauncher.setAttribute('aria-expanded','false');
    mobileLauncher.addEventListener('click',openMobile);
  }
  if(backdrop)backdrop.addEventListener('click',closeMobile);

  /* Existing page hamburger buttons continue to work; this only synchronizes overlay state. */
  document.addEventListener('click',function(ev){
    var btn=ev.target.closest && ev.target.closest('.menu-btn');
    if(btn && !desktop()){
      window.setTimeout(function(){
        if(side.classList.contains('open')){
          openMobile();
        }else{
          closeMobile();
        }
      },0);
    }
    var nav=ev.target.closest && ev.target.closest('#sidebar nav a');
    if(nav && !desktop())closeMobile();
  });

  document.addEventListener('keydown',function(ev){if(ev.key==='Escape')closeMobile();});
  document.addEventListener('keydown',function(ev){
    if(ev.key!=='Tab'||desktop()||!side.classList.contains('open'))return;
    var items=Array.from(side.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex="0"]')).filter(function(item){return item.getClientRects().length&&!item.closest('[inert]');});
    if(!items.length)return;
    var first=items[0],last=items[items.length-1];
    if(ev.shiftKey&&document.activeElement===first){ev.preventDefault();last.focus();}
    else if(!ev.shiftKey&&document.activeElement===last){ev.preventDefault();first.focus();}
  });
  window.addEventListener('resize',applySaved,{passive:true});
  applySaved();
})();
