/* MR BAR v1.29.13 — customer floor artwork status */
(function(){
  'use strict';
  function bind(img){
    if(!img||img.dataset.artworkBound==='1')return;
    img.dataset.artworkBound='1';
    var stage=img.closest('.cw-floor-map-stage,.cw-floor-preview-stage');
    var fail=function(){
      if(!stage)return;
      stage.classList.add('artwork-load-error');
      var message=stage.querySelector('[data-floor-artwork-error]');
      if(message)message.hidden=false;
    };
    var ready=function(){
      if(!stage)return;
      stage.classList.add('artwork-loaded');
      stage.classList.remove('artwork-load-error');
      var message=stage.querySelector('[data-floor-artwork-error]');
      if(message)message.hidden=true;
    };
    img.addEventListener('load',ready,{once:true});
    img.addEventListener('error',fail,{once:true});
    if(img.complete){if(img.naturalWidth>0)ready();else fail();}
  }
  function init(){document.querySelectorAll('[data-floor-artwork]').forEach(bind);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
