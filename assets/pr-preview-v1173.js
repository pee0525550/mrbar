(()=>{
 const params=new URLSearchParams(location.search);if(params.get('admin_staff_preview')!=='1'&&params.get('admin_pr_preview')!=='1')return;
 document.body.classList.add('admin-pr-preview');
 const employeeId=params.get('employee_id')||'',prId=params.get('pr_id')||'',token=params.get('preview_token')||'';
 const toast=document.createElement('div');toast.className='admin-pr-preview-toast';toast.textContent='MR BAR TIME Preview: คำสั่งนี้จะไม่ถูกบันทึก';document.body.appendChild(toast);let timer;
 const blocked=()=>{clearTimeout(timer);toast.classList.add('show');timer=setTimeout(()=>toast.classList.remove('show'),2200);};
 document.addEventListener('submit',e=>{e.preventDefault();e.stopImmediatePropagation();blocked();},true);
 try{HTMLFormElement.prototype.submit=function(){blocked();};HTMLFormElement.prototype.requestSubmit=function(){blocked();};}catch(e){}
 const allowed=/(?:^|\/)(pr\.php|pr-calendar\.php|pr-jobs\.php|employee-time\.php|employee-calendar\.php|employee-income\.php)$/;
 document.querySelectorAll('a[href]').forEach(a=>{const raw=a.getAttribute('href')||'';if(!raw||raw.startsWith('#')||raw.startsWith('javascript:'))return;let u;try{u=new URL(raw,location.href);}catch(e){return;}if(u.origin!==location.origin)return;if(!allowed.test(u.pathname))return;u.searchParams.set('admin_staff_preview','1');u.searchParams.set('employee_id',employeeId);u.searchParams.set('preview_token',token);if(/(?:^|\/)(pr\.php|pr-calendar\.php|pr-jobs\.php)$/.test(u.pathname)&&prId){u.searchParams.set('admin_pr_preview','1');u.searchParams.set('pr_id',prId);}a.href=u.pathname+u.search+u.hash;});
 document.addEventListener('click',e=>{const a=e.target.closest&&e.target.closest('a[href]');if(!a)return;const raw=a.getAttribute('href')||'';if(!raw||raw.startsWith('#')||raw.startsWith('javascript:'))return;let u;try{u=new URL(raw,location.href);}catch(_){return;}if(u.origin!==location.origin)return;if(!allowed.test(u.pathname)){e.preventDefault();e.stopImmediatePropagation();blocked();}},true);
 const banner=document.querySelector('[data-staff-preview-banner],[data-pr-preview-banner]');if(banner){const close=banner.querySelector('[data-staff-preview-close],[data-pr-preview-close]');if(close)close.addEventListener('click',()=>{banner.style.display='none';document.body.classList.remove('admin-pr-preview');});}
})();
