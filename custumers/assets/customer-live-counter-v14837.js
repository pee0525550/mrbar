(function(){
  'use strict';
  var wrap=document.querySelector('[data-live-counter]');
  if(!wrap)return;
  var nodes=[].slice.call(wrap.querySelectorAll('[data-count-up]'));
  if(!nodes.length)return;
  var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var done=false;
  function format(v){try{return Number(v).toLocaleString('th-TH');}catch(e){return String(v);}}
  function runOne(el){
    var target=Math.max(0,parseInt(el.getAttribute('data-count-up')||'0',10)||0);
    if(reduced){el.textContent=format(target);if(el.parentElement)el.parentElement.classList.add('is-counted');return;}
    var duration=1350,start=null;
    function tick(now){
      if(start===null)start=now;
      var p=Math.min(1,(now-start)/duration);
      var eased=1-Math.pow(1-p,3);
      el.textContent=format(Math.floor(target*eased));
      if(p<1)requestAnimationFrame(tick);else{el.textContent=format(target);if(el.parentElement)el.parentElement.classList.add('is-counted');}
    }
    requestAnimationFrame(tick);
  }
  function start(){
    if(done)return;done=true;nodes.forEach(runOne);
  }
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(entries){for(var i=0;i<entries.length;i++){if(entries[i].isIntersecting){start();io.disconnect();break;}}},{threshold:.28});
    io.observe(wrap);
  }else start();
})();