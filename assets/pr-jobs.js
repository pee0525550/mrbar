(()=>{'use strict';
 const isAdminPreview=new URLSearchParams(location.search).get('admin_pr_preview')==='1';
 const drawer=document.getElementById('prDrawer'),backdrop=document.getElementById('prDrawerBackdrop'),open=document.getElementById('drawerOpen'),close=document.getElementById('drawerClose');
 function show(){if(!drawer)return;drawer.classList.add('open');drawer.setAttribute('aria-hidden','false');if(backdrop)backdrop.hidden=false;document.body.classList.add('drawer-open');}
 function hide(){if(!drawer)return;drawer.classList.remove('open');drawer.setAttribute('aria-hidden','true');if(backdrop)backdrop.hidden=true;document.body.classList.remove('drawer-open');}
 if(open)open.addEventListener('click',show);if(close)close.addEventListener('click',hide);if(backdrop)backdrop.addEventListener('click',hide);
 let sig=null;async function poll(){if(document.body.dataset.autoRefresh!=='1')return;try{const r=await fetch('ops-api.php?scope=pr',{credentials:'same-origin',cache:'no-store'});if(!r.ok)return;const j=await r.json();if(sig!==null&&j.signature!==sig){let n=document.getElementById('jobLiveUpdate');if(!n){n=document.createElement('button');n.type='button';n.id='jobLiveUpdate';n.className='job-live-update';n.textContent='✦ มีอัปเดตงานใหม่ · แตะเพื่อรีเฟรช';n.addEventListener('click',()=>location.reload());document.body.appendChild(n);}}sig=j.signature;}catch(e){}}
 if(!isAdminPreview){setInterval(poll,10000);poll();}
})();
