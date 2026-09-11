(function(){
  'use strict';
  document.querySelectorAll('[data-brand-file]').forEach(function(input){
    input.addEventListener('change',function(){
      var file=input.files&&input.files[0];
      var targetId=input.getAttribute('data-preview-target');
      var img=targetId?document.getElementById(targetId):null;
      var fallbackId=input.getAttribute('data-preview-fallback');
      var fallback=fallbackId?document.getElementById(fallbackId):null;
      if(!file||!img)return;
      var url=URL.createObjectURL(file);
      img.onload=function(){try{URL.revokeObjectURL(url);}catch(e){}};
      img.src=url;img.hidden=false;
      if(fallback)fallback.hidden=true;
      var label=input.closest('.branding-file-label');
      if(label){var small=label.querySelector('small');if(small)small.textContent=file.name;}
    });
  });
})();
