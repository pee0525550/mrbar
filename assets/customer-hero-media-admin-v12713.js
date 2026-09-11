(function(){
  'use strict';
  const form=document.querySelector('[data-chm-add-form]');
  if(!form)return;
  const type=form.querySelector('[data-chm-type]');
  const fileField=form.querySelector('[data-chm-file-field]');
  const urlField=form.querySelector('[data-chm-url-field]');
  const posterField=form.querySelector('[data-chm-poster-field]');
  const file=form.querySelector('[data-chm-main-file]');
  const help=form.querySelector('[data-chm-file-help]');
  function sync(){
    const v=type.value;
    const needsFile=v==='image'||v==='video_upload';
    const needsUrl=v==='youtube'||v==='video_external';
    fileField.hidden=!needsFile;urlField.hidden=!needsUrl;posterField.hidden=v==='image';
    file.required=needsFile;
    if(v==='video_upload'){file.accept='video/mp4,video/webm';if(help)help.textContent='MP4 / WEBM · สูงสุด 100MB';}
    else{file.accept='image/jpeg,image/png,image/webp';if(help)help.textContent='JPG / PNG / WEBP · สูงสุด 12MB';}
    const duration=form.querySelector('[name="duration"]');if(duration&&!duration.dataset.touched)duration.value=v==='image'?'7':'12';
  }
  type.addEventListener('change',sync);
  const duration=form.querySelector('[name="duration"]');duration&&duration.addEventListener('input',()=>duration.dataset.touched='1');
  sync();
})();
