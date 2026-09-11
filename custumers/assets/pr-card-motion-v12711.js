(function(){
  'use strict';
  const containers=[...document.querySelectorAll('[data-pr-motion]')];
  if(!containers.length)return;
  const reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const states=new Map();

  function visibleCards(container){
    return [...container.children].filter(el=>el.classList&&el.classList.contains('cw-pr-portrait')&&!el.hidden);
  }
  function decorate(container){
    const cards=visibleCards(container);
    cards.forEach((card,i)=>card.style.setProperty('--pr-motion-index',String(i)));
    [...container.querySelectorAll('.is-motion-lead')].forEach(card=>card.classList.remove('is-motion-lead'));
    if(cards[0])cards[0].classList.add('is-motion-lead');
    return cards;
  }
  function clearTimer(state){if(state.timer){clearTimeout(state.timer);state.timer=0;}}
  function canRun(state){return !reduce&&!state.paused&&state.inView&&!document.hidden&&visibleCards(state.container).length>1;}
  function schedule(state,delay){
    clearTimer(state);
    if(!canRun(state))return;
    state.timer=setTimeout(()=>rotate(state),delay==null?state.interval:delay);
  }
  function rotate(state){
    clearTimer(state);
    if(!canRun(state))return;
    const container=state.container;
    const beforeCards=visibleCards(container);
    if(beforeCards.length<2)return;
    const before=new Map(beforeCards.map(card=>[card,card.getBoundingClientRect()]));
    const first=beforeCards[0];
    container.appendChild(first);
    const afterCards=decorate(container);
    const animations=[];
    afterCards.forEach((card,index)=>{
      const oldRect=before.get(card);if(!oldRect)return;
      const newRect=card.getBoundingClientRect();
      const dx=oldRect.left-newRect.left,dy=oldRect.top-newRect.top;
      if(Math.abs(dx)<.5&&Math.abs(dy)<.5)return;
      card.classList.add('is-motion-sliding');
      if(typeof card.animate==='function'){
        const animation=card.animate([
          {transform:`translate3d(${dx}px,${dy}px,0)`},
          {transform:'translate3d(0,0,0)'}
        ],{duration:720,delay:Math.min(index*22,110),easing:'cubic-bezier(.22,.82,.22,1)'});
        animation.addEventListener('finish',()=>card.classList.remove('is-motion-sliding'),{once:true});
        animation.addEventListener('cancel',()=>card.classList.remove('is-motion-sliding'),{once:true});
        animations.push(animation);
      }else{
        card.classList.remove('is-motion-sliding');
      }
    });
    schedule(state,state.interval);
  }
  function pause(state,reason){
    state.paused=true;state.container.classList.add('is-motion-paused');clearTimer(state);
    state.pauseReason=reason||'';
  }
  function resume(state,delay){
    state.paused=false;state.pauseReason='';state.container.classList.remove('is-motion-paused');decorate(state.container);schedule(state,delay==null?1400:delay);
  }

  containers.forEach(container=>{
    const mode=container.dataset.prMotion||'home';
    const state={container,interval:mode==='catalog'?6200:4700,timer:0,paused:false,pauseReason:'',inView:true};
    states.set(container,state);decorate(container);
    container.addEventListener('pointerenter',()=>pause(state,'pointer'));
    container.addEventListener('pointerleave',()=>resume(state,1500));
    container.addEventListener('focusin',()=>pause(state,'focus'));
    container.addEventListener('focusout',e=>{if(!container.contains(e.relatedTarget))resume(state,1500);});
    container.addEventListener('touchstart',()=>{pause(state,'touch');setTimeout(()=>{if(state.pauseReason==='touch')resume(state,2600);},3200);},{passive:true});
    if('IntersectionObserver' in window){
      const io=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.target!==container)return;state.inView=entry.isIntersecting&&entry.intersectionRatio>.08;if(state.inView)schedule(state,1000);else clearTimer(state);});},{threshold:[0,.08,.25]});
      io.observe(container);
    }
    schedule(state,mode==='catalog'?2600:2100);
  });

  document.addEventListener('click',e=>{
    const filter=e.target.closest('[data-pr-filter]');if(!filter)return;
    setTimeout(()=>{
      states.forEach(state=>{if(state.container.dataset.prMotion!=='catalog')return;decorate(state.container);schedule(state,1900);});
    },60);
  });
  document.addEventListener('visibilitychange',()=>{
    states.forEach(state=>{if(document.hidden)clearTimer(state);else schedule(state,1500);});
  });
})();
