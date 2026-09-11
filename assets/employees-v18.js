(function(){
 const q=document.getElementById('empSearch'),pos=document.getElementById('empPosition'),st=document.getElementById('empState'),cards=[...document.querySelectorAll('.employee-card')];
 function apply(){const s=(q&&q.value||'').toLowerCase(),p=pos&&pos.value||'',v=st&&st.value||'';cards.forEach(c=>{let ok=(!s||(c.dataset.search||'').includes(s))&&(!p||c.dataset.position===p);if(v==='active')ok=ok&&c.dataset.active==='1';if(v==='inactive')ok=ok&&c.dataset.active==='0';if(v==='attendance')ok=ok&&c.dataset.attendance==='1';c.classList.toggle('is-hidden',!ok);});}
 [q,pos,st].forEach(x=>x&&x.addEventListener('input',apply));
 document.addEventListener('click',e=>{const b=e.target.closest('[data-emp-toggle]');if(!b)return;const card=b.closest('.employee-card'),d=card&&card.querySelector('.emp-detail');if(!d)return;d.hidden=!d.hidden;b.textContent=d.hidden?'จัดการ ▾':'ปิด ▴';});
 const menu=document.getElementById('menuBtn'),side=document.getElementById('sidebar');if(menu&&side)menu.addEventListener('click',()=>side.classList.toggle('open'));
})();
