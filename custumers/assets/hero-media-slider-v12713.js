(function(){
  'use strict';
  const reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-hero-slider]').forEach(init);

  function init(root){
    const slides=[...root.querySelectorAll('[data-hero-slide]')];
    if(!slides.length)return;
    const dots=[...root.querySelectorAll('[data-hero-dot]')];
    const prev=root.querySelector('[data-hero-prev]');
    const next=root.querySelector('[data-hero-next]');
    const progress=root.querySelector('[data-hero-progress]');
    let index=Math.max(0,slides.findIndex(s=>s.classList.contains('is-active')));
    let timer=0,paused=false,startX=null;

    function stopMedia(slide){
      if(!slide)return;
      slide.querySelectorAll('video').forEach(v=>{try{v.pause();v.currentTime=0;}catch(e){}});
      slide.querySelectorAll('.cw-hero-youtube iframe').forEach(f=>f.remove());
    }
    function startMedia(slide){
      if(!slide)return;
      const autoplay=slide.dataset.autoplay==='1'&&!reduce;
      slide.querySelectorAll('video').forEach(v=>{v.muted=true;v.loop=slide.dataset.loop==='1';if(autoplay){const p=v.play();if(p&&p.catch)p.catch(()=>{});}});
      const box=slide.querySelector('[data-hero-youtube]');
      if(box&&!box.querySelector('iframe')){
        const id=box.dataset.heroYoutube||'';if(!id)return;
        const functional=document.documentElement.getAttribute('data-cookie-functional')!=='0';
        if(!functional){
          if(!box.querySelector('.cw-hero-consent-gate')){const gate=document.createElement('div');gate.className='cw-hero-consent-gate';gate.innerHTML='<span>▶</span><b>YouTube ถูกพักไว้</b><small>อนุญาต Functional เพื่อเล่นวิดีโอจากภายนอก</small><button type="button">ตั้งค่าคุกกี้</button>';const btn=gate.querySelector('button');btn&&btn.addEventListener('click',()=>{const settings=document.querySelector('[data-cookie-settings]');if(settings)settings.click();});box.appendChild(gate);}return;
        }
        const gate=box.querySelector('.cw-hero-consent-gate');if(gate)gate.remove();
        const iframe=document.createElement('iframe');
        const loop=slide.dataset.loop==='1';
        const params=new URLSearchParams({autoplay:autoplay?'1':'0',mute:'1',controls:'1',rel:'0',playsinline:'1',modestbranding:'1'});
        if(loop){params.set('loop','1');params.set('playlist',id);}
        iframe.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(id)+'?'+params.toString();
        iframe.title='YouTube video';iframe.allow='autoplay; encrypted-media; picture-in-picture';iframe.allowFullscreen=true;iframe.referrerPolicy='strict-origin-when-cross-origin';
        box.appendChild(iframe);
      }
    }
    function resetProgress(duration){
      if(!progress)return;
      progress.style.transition='none';progress.style.width='0%';
      requestAnimationFrame(()=>requestAnimationFrame(()=>{progress.style.transition='width '+duration+'ms linear';progress.style.width=(!paused&&!reduce)?'100%':'0%';}));
    }
    function schedule(){
      clearTimeout(timer);if(reduce||paused||slides.length<2)return;
      const duration=Math.max(3000,Math.min(60000,(parseInt(slides[index].dataset.duration||'7',10)||7)*1000));
      resetProgress(duration);timer=window.setTimeout(()=>show((index+1)%slides.length),duration);
    }
    function show(i){
      i=(i+slides.length)%slides.length;
      if(i===index){schedule();return;}
      const old=slides[index];stopMedia(old);old.classList.remove('is-active');old.setAttribute('aria-hidden','true');
      index=i;const current=slides[index];current.classList.add('is-active');current.setAttribute('aria-hidden','false');
      dots.forEach((d,n)=>d.classList.toggle('active',n===index));
      startMedia(current);schedule();
    }
    function pause(){paused=true;clearTimeout(timer);if(progress){const w=getComputedStyle(progress).width;progress.style.transition='none';progress.style.width=w;}}
    function resume(){paused=false;schedule();startMedia(slides[index]);}

    prev&&prev.addEventListener('click',()=>show(index-1));
    next&&next.addEventListener('click',()=>show(index+1));
    dots.forEach((d,n)=>d.addEventListener('click',()=>show(n)));
    if(window.matchMedia&&window.matchMedia('(hover:hover)').matches){root.addEventListener('mouseenter',pause);root.addEventListener('mouseleave',resume);}
    root.addEventListener('focusin',pause);root.addEventListener('focusout',e=>{if(!root.contains(e.relatedTarget))resume();});
    root.addEventListener('pointerdown',e=>{if(e.pointerType==='touch'||e.pointerType==='pen')startX=e.clientX;});
    root.addEventListener('pointerup',e=>{if(startX===null)return;const dx=e.clientX-startX;startX=null;if(Math.abs(dx)>45)show(dx<0?index+1:index-1);});
    document.addEventListener('visibilitychange',()=>{document.hidden?pause():resume();});
    document.addEventListener('mrbar:consentchange',()=>{stopMedia(slides[index]);startMedia(slides[index]);});
    startMedia(slides[index]);schedule();
  }
})();
