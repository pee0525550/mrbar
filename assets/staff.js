(()=>{
 const clock=document.getElementById('liveClock');
 if(clock)setInterval(()=>clock.textContent=new Date().toLocaleTimeString('th-TH',{hour12:false}),1000);
 const body=document.body, badge=document.getElementById('newData');
 let lastSig=null;
 async function poll(){
   if(body.dataset.autoRefresh!=='1')return;
   try{
     const r=await fetch('ops-api.php?scope=staff',{credentials:'same-origin',cache:'no-store'}); if(!r.ok)return;
     const j=await r.json();
     ['queue','online','occupied','service'].forEach(k=>{const el=document.getElementById('k'+k.charAt(0).toUpperCase()+k.slice(1));if(el&&j[k]!=null)el.textContent=j[k]});
     if(lastSig!==null&&j.signature!==lastSig){badge.hidden=false;badge.onclick=()=>location.reload();}
     lastSig=j.signature;
   }catch(e){}
 }
 setInterval(poll,8000);poll();
 document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{const b=f.querySelector('button[type=submit],button:not([type])');if(b){b.disabled=true;b.dataset.old=b.textContent;b.textContent='กำลังบันทึก...';}}));
})();
