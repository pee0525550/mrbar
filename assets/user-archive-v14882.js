(function(){
  var dialog=document.getElementById('archiveUserDialog');
  var form=document.getElementById('archiveUserForm');
  if(!dialog||!form)return;
  var idInput=form.querySelector('[name="id"]');
  var pinInput=form.querySelector('[name="admin_pin"]');
  var nameLabel=dialog.querySelector('[data-archive-name]');
  document.addEventListener('click',function(event){
    var trigger=event.target.closest('[data-archive-user]');
    if(trigger){
      idInput.value=trigger.getAttribute('data-id')||'';
      nameLabel.textContent=trigger.getAttribute('data-name')||'ผู้ใช้';
      pinInput.value='';
      dialog.showModal();
      window.setTimeout(function(){pinInput.focus();},30);
      return;
    }
    if(event.target.closest('[data-archive-close]'))dialog.close();
  });
  dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
  dialog.addEventListener('close',function(){pinInput.value='';idInput.value='';});
  form.addEventListener('submit',function(event){
    if(!/^\d{6}$/.test(pinInput.value)){event.preventDefault();pinInput.setCustomValidity('กรุณากรอก Admin PIN 6 หลัก');pinInput.reportValidity();}
    else pinInput.setCustomValidity('');
  });
  pinInput.addEventListener('input',function(){pinInput.value=pinInput.value.replace(/\D/g,'').slice(0,6);pinInput.setCustomValidity('');});
})();
