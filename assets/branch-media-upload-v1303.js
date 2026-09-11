document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('[data-branch-media]').forEach(function(box){
  var input=box.querySelector('[data-media-file]'),preview=box.querySelector('[data-media-preview]'),name=box.querySelector('[data-media-name]'),objectUrl='';
  if(!input||!preview)return;
  input.addEventListener('change',function(){
   var file=input.files&&input.files[0];if(!file)return;
   if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(file);
   preview.innerHTML='';var img=document.createElement('img');img.src=objectUrl;img.alt='ตัวอย่างรูปที่เลือก';preview.appendChild(img);preview.classList.add('has-image');
   if(name)name.textContent=file.name+' · '+Math.max(.01,file.size/1048576).toFixed(2)+' MB · พร้อมอัปโหลดเมื่อกดบันทึกร้าน';
  });
  window.addEventListener('beforeunload',function(){if(objectUrl)URL.revokeObjectURL(objectUrl);});
 });
});
