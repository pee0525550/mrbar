/* MR BAR v1.29.14 — Night Operations artwork status */
(function(){
  'use strict';
  function init(){
    var img=document.querySelector('[data-nf-floor-artwork]');
    if(!img)return;
    var stage=img.closest('.nf-stage');
    var message=stage&&stage.querySelector('[data-nf-floor-artwork-error]');
    function ready(){
      if(!stage)return;
      stage.classList.add('artwork-loaded');
      stage.classList.remove('artwork-load-error');
      if(message)message.hidden=true;
    }
    function fail(){
      if(!stage)return;
      stage.classList.add('artwork-load-error');
      if(message)message.hidden=false;
    }
    img.addEventListener('load',ready,{once:true});
    img.addEventListener('error',fail,{once:true});
    if(img.complete){if(img.naturalWidth>0)ready();else fail();}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
