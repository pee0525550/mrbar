(function(){
'use strict';
var modal=document.getElementById('employeeDeleteModal');
function closeDelete(){if(!modal)return;modal.hidden=true;document.body.classList.remove('emp-delete-open');}
document.addEventListener('click',function(ev){
  var btn=ev.target.closest('[data-delete-employee]');
  if(btn&&modal){var id=modal.querySelector('[data-delete-id]'),name=modal.querySelector('[data-delete-name]');if(id)id.value=btn.getAttribute('data-id')||'';if(name)name.textContent=(btn.getAttribute('data-code')||'')+' · '+(btn.getAttribute('data-name')||'');modal.hidden=false;document.body.classList.add('emp-delete-open');return;}
  if(ev.target.closest('[data-delete-close]')){closeDelete();return;}
});
document.addEventListener('keydown',function(ev){if(ev.key==='Escape')closeDelete();});
})();