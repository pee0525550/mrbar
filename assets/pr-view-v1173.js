(()=>{
 const sel=document.getElementById('prSelect'),frame=document.getElementById('prPreviewFrame'),stage=document.querySelector('[data-pv-stage]');
 if(sel)sel.addEventListener('change',()=>{const u=new URL(location.href);u.searchParams.set('pr_id',sel.value);location.href=u.toString();});
 document.querySelectorAll('[data-pv-device]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-pv-device]').forEach(x=>x.classList.remove('active'));btn.classList.add('active');if(stage)stage.dataset.device=btn.dataset.pvDevice||'mobile';}));
 const refresh=document.querySelector('[data-pv-refresh]');if(refresh&&frame)refresh.addEventListener('click',()=>{const u=new URL(frame.src,location.href);u.searchParams.set('v',Date.now().toString());frame.src=u.toString();});
})();
