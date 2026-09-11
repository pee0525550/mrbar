(()=>{
 const root=document.documentElement,body=document.body;
 if(!body||!body.classList.contains('staff-time-page'))return;
 let raf=0;
 const sync=()=>{
   raf=0;
   const vv=window.visualViewport;
   const w=Math.round(vv?vv.width:window.innerWidth);
   const h=Math.round(vv?vv.height:window.innerHeight);
   root.style.setProperty('--staff-vh',h+'px');
   root.style.setProperty('--staff-vw',w+'px');
   body.classList.toggle('staff-short-viewport',h<700);
   body.classList.toggle('staff-narrow-viewport',w<390);
   body.classList.toggle('staff-landscape',w>h);
   const baseline=Math.max(document.documentElement.clientHeight||0,window.innerHeight||0);
   body.classList.toggle('staff-keyboard-open',!!vv&&h<baseline*.72);
 };
 const queue=()=>{if(raf)return;raf=requestAnimationFrame(sync);};
 sync();
 addEventListener('resize',queue,{passive:true});
 addEventListener('orientationchange',()=>setTimeout(sync,120),{passive:true});
 if(window.visualViewport){visualViewport.addEventListener('resize',queue,{passive:true});visualViewport.addEventListener('scroll',queue,{passive:true});}
 document.addEventListener('focusin',e=>{if(e.target&&/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)){setTimeout(queue,80);}},true);
 document.addEventListener('focusout',e=>{if(e.target&&/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)){setTimeout(sync,220);}},true);
})();
