(function(){
 var root=document.documentElement;root.classList.add('portal-motion-ready');var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 if(!reduced){
  var fx=document.createElement('div');fx.className='portal-fx';fx.setAttribute('aria-hidden','true');
  var colors=['#ff48d7','#8c5bff','#36d5ff','#47f0b5'];
  var count=window.innerWidth<700?12:26;
  for(var i=0;i<count;i++){var dot=document.createElement('i');dot.style.setProperty('--x',((i*37)%97+1)+'%');dot.style.setProperty('--s',(2+(i%4))+'px');dot.style.setProperty('--d',(10+(i%9))+'s');dot.style.setProperty('--delay',(-1*(i%13))+'s');dot.style.setProperty('--drift',((-45+(i*29)%90))+'px');dot.style.setProperty('--c',colors[i%colors.length]);fx.appendChild(dot)}
  document.body.appendChild(fx);
  var moveQueued=false,lastX=72,lastY=18;
  window.addEventListener('pointermove',function(e){lastX=e.clientX/window.innerWidth*100;lastY=e.clientY/window.innerHeight*100;if(moveQueued)return;moveQueued=true;requestAnimationFrame(function(){root.style.setProperty('--portal-mx',lastX.toFixed(1)+'%');root.style.setProperty('--portal-my',lastY.toFixed(1)+'%');moveQueued=false})},{passive:true});
 }
 var cards=[].slice.call(document.querySelectorAll('.portal-card'));
 if('IntersectionObserver' in window){
  var observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target)}})},{threshold:.12,rootMargin:'0px 0px -40px'});
  cards.forEach(function(card){observer.observe(card)});
 }else cards.forEach(function(card){card.classList.add('is-visible')});
 if(!reduced&&window.matchMedia('(hover:hover) and (pointer:fine)').matches){
  cards.forEach(function(card){
   card.addEventListener('pointermove',function(e){var r=card.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;card.style.setProperty('--tilt-x',(-y*3.2).toFixed(2)+'deg');card.style.setProperty('--tilt-y',(x*4).toFixed(2)+'deg')});
   card.addEventListener('pointerleave',function(){card.style.setProperty('--tilt-x','0deg');card.style.setProperty('--tilt-y','0deg')});
  });
 }
})();