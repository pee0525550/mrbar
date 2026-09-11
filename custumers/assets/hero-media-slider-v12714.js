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

    const ghostLeft=document.createElement('div');
    ghostLeft.className='cw-hero-ghosts left';
    ghostLeft.setAttribute('aria-label','สไลด์ก่อนหน้า');
    const ghostRight=document.createElement('div');
    ghostRight.className='cw-hero-ghosts right';
    ghostRight.setAttribute('aria-label','สไลด์ถัดไป');
    root.appendChild(ghostLeft);root.appendChild(ghostRight);

    function mediaThumb(slide){
      const img=slide.querySelector('img.cw-hero-slide-media');
      if(img&&img.getAttribute('src'))return img.getAttribute('src');
      const video=slide.querySelector('video');
      if(video&&video.getAttribute('poster'))return video.getAttribute('poster');
      const ytPoster=slide.querySelector('.cw-hero-youtube-poster');
      if(ytPoster){
        const inline=ytPoster.style.backgroundImage||'';
        const m=inline.match(/url\(["']?(.*?)["']?\)/);if(m&&m[1])return m[1];
      }
      return '';
    }
    function mediaLabel(slide,n){
      const type=slide.dataset.heroType||'';
      if(type==='youtube')return 'YT';
      if(type.indexOf('video')!==-1)return 'VIDEO';
      return String(n+1).padStart(2,'0');
    }
    function decorateSwitcher(){
      dots.forEach((d,n)=>{
        d.classList.add('cw-hero-thumb');
        const thumb=mediaThumb(slides[n]);
        if(thumb)d.style.backgroundImage='url("'+thumb.replace(/"/g,'%22')+'")';
        d.innerHTML='<span>'+mediaLabel(slides[n],n)+'</span>';
        d.title='Slide '+(n+1);
      });
    }
    function buildGhostSide(host,dir){
      host.innerHTML='';
      const max=Math.min(4,slides.length-1);
      for(let rank=1;rank<=max;rank++){
        const target=(index+(dir*rank)+slides.length*10)%slides.length;
        const slide=slides[target];
        const btn=document.createElement('button');
        btn.type='button';btn.className='cw-hero-ghost';btn.style.setProperty('--ghost-rank',String(rank));btn.style.setProperty('--ghost-offset',((rank-1)*38)+'px');btn.style.setProperty('--ghost-offset-compact',((rank-1)*29)+'px');btn.style.setProperty('--ghost-scale',(1-((rank-1)*.07)).toFixed(2));btn.style.setProperty('--ghost-opacity',(0.64-(rank*.10)).toFixed(2));btn.style.setProperty('--ghost-blur',((rank-1)*.45)+'px');btn.style.setProperty('--ghost-glass',(rank*.55)+'px');
        btn.dataset.target=String(target);btn.setAttribute('aria-label','ไปสไลด์ '+(target+1));
        const thumb=mediaThumb(slide);if(thumb)btn.style.backgroundImage='url("'+thumb.replace(/"/g,'%22')+'")';
        btn.innerHTML='<i></i><span>'+mediaLabel(slide,target)+'</span>';
        btn.addEventListener('click',()=>show(target));host.appendChild(btn);
      }
    }
    function updateGhosts(){buildGhostSide(ghostLeft,-1);buildGhostSide(ghostRight,1);}

    function stopMedia(slide){
      if(!slide)return;
      slide.querySelectorAll('video').forEach(v=>{try{v.pause();v.currentTime=0;}catch(e){}});
      slide.querySelectorAll('.cw-hero-youtube iframe').forEach(f=>f.remove());
    }
    function startMedia(slide){
      if(!slide)return;
      slide.querySelectorAll('video').forEach(v=>{
        v.controls=false;v.muted=true;v.loop=true;v.playsInline=true;
        if(!reduce){const p=v.play();if(p&&p.catch)p.catch(()=>{});}
      });
      const box=slide.querySelector('[data-hero-youtube]');
      if(box&&!box.querySelector('iframe')){
        const id=box.dataset.heroYoutube||'';if(!id)return;
        const functional=document.documentElement.getAttribute('data-cookie-functional')!=='0';
        if(!functional){
          if(!box.querySelector('.cw-hero-consent-gate')){const gate=document.createElement('div');gate.className='cw-hero-consent-gate';gate.innerHTML='<span>▶</span><b>YouTube ถูกพักไว้</b><small>อนุญาต Functional เพื่อเล่นวิดีโอจากภายนอก</small><button type="button">ตั้งค่าคุกกี้</button>';const btn=gate.querySelector('button');btn&&btn.addEventListener('click',()=>{const settings=document.querySelector('[data-cookie-settings]');if(settings)settings.click();});box.appendChild(gate);}return;
        }
        const gate=box.querySelector('.cw-hero-consent-gate');if(gate)gate.remove();
        const iframe=document.createElement('iframe');
        const params=new URLSearchParams({autoplay:reduce?'0':'1',mute:'1',controls:'0',disablekb:'1',fs:'0',rel:'0',playsinline:'1',modestbranding:'1',iv_load_policy:'3',loop:'1',playlist:id});
        iframe.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(id)+'?'+params.toString();
        iframe.title='YouTube video';iframe.allow='autoplay; encrypted-media';iframe.referrerPolicy='strict-origin-when-cross-origin';
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
      dots.forEach((d,n)=>{d.classList.toggle('active',n===index);d.setAttribute('aria-selected',n===index?'true':'false');});
      updateGhosts();startMedia(current);schedule();
    }
    function pause(){paused=true;clearTimeout(timer);if(progress){const w=getComputedStyle(progress).width;progress.style.transition='none';progress.style.width=w;}}
    function resume(){paused=false;schedule();startMedia(slides[index]);}

    decorateSwitcher();updateGhosts();
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
