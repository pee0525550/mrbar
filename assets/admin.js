(()=>{
 const q=(s,r=document)=>r.querySelector(s), qa=(s,r=document)=>[...r.querySelectorAll(s)];
 const clock=()=>{const d=new Date(),opt={timeZone:'Asia/Bangkok'};q('#liveClock')&&(q('#liveClock').textContent=d.toLocaleTimeString('th-TH',{...opt,hour12:false}));q('#liveDate')&&(q('#liveDate').textContent=d.toLocaleDateString('th-TH',{...opt,weekday:'short',day:'numeric',month:'short',year:'numeric'}));};clock();setInterval(clock,1000);
 qa('[data-open]').forEach(b=>b.addEventListener('click',()=>q('#'+b.dataset.open)?.classList.add('open')));
 qa('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('.modal')?.classList.remove('open')));
 qa('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open')}));
 qa('[data-pr-user]').forEach(b=>b.addEventListener('click',()=>{q('#prUserId').value=b.dataset.prUser;q('#prUserTitle').textContent='PR: '+b.dataset.prName;q('#prUser').classList.add('open')}));
 const menu=q('#menuBtn'),side=q('#sidebar');menu?.addEventListener('click',()=>side.classList.toggle('open'));qa('.sidebar nav a').forEach(a=>a.addEventListener('click',()=>{qa('.sidebar nav a').forEach(x=>x.classList.remove('active'));a.classList.add('active');if(innerWidth<901)side.classList.remove('open')}));
 qa('.status-select').forEach(s=>s.addEventListener('change',()=>{s.className='status-select s-'+s.value}));
})();
