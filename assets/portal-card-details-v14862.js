(function(){
 'use strict';
 var dialog=document.querySelector('[data-portal-lightbox]');
 if(!dialog)return;
 var image=dialog.querySelector('[data-lightbox-image]');
 var video=dialog.querySelector('[data-lightbox-video]');
 var frame=dialog.querySelector('[data-lightbox-frame]');
 var caption=dialog.querySelector('[data-lightbox-caption]');
 var count=dialog.querySelector('[data-lightbox-count]');
 var previous=dialog.querySelector('[data-lightbox-prev]');
 var next=dialog.querySelector('[data-lightbox-next]');
 var close=dialog.querySelector('[data-lightbox-close]');
 var active=[];
 var index=0;
 function stopMedia(){
  image.hidden=true;image.removeAttribute('src');
  video.pause();video.hidden=true;video.removeAttribute('src');video.removeAttribute('poster');video.load();
  frame.hidden=true;frame.src='about:blank';
 }
 function render(){
  var item=active[index];
  if(!item)return;
  stopMedia();
  if(item.type==='image'){image.src=item.source;image.alt=item.caption+' · ภาพที่ '+(index+1);image.hidden=false}
  else if(item.type==='youtube'){frame.src=item.source;frame.hidden=false}
  else{video.src=item.source;if(item.thumbnail)video.poster=item.thumbnail;video.hidden=false;video.load()}
  caption.textContent=item.caption;
  count.textContent=(index+1)+' / '+active.length;
  previous.disabled=active.length<2;
  next.disabled=active.length<2;
 }
 function move(delta){index=(index+delta+active.length)%active.length;render()}
 document.querySelectorAll('[data-gallery-open]').forEach(function(button){
  button.addEventListener('click',function(){
   var card=button.closest('[data-portal-card]');
   if(!card)return;
   active=[].slice.call(card.querySelectorAll('[data-gallery-open]')).map(function(item){return{type:item.getAttribute('data-gallery-type')||'image',source:item.getAttribute('data-gallery-source')||item.getAttribute('data-gallery-image')||'',thumbnail:item.getAttribute('data-gallery-image')||'',caption:item.getAttribute('data-gallery-caption')||''}}).filter(function(item){return item.source!==''});
   index=Math.max(0,active.indexOf(active.find(function(item){return item.source===(button.getAttribute('data-gallery-source')||button.getAttribute('data-gallery-image'))})));
   if(!active.length)return;
   render();
   if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');
  });
 });
 previous.addEventListener('click',function(){if(active.length>1)move(-1)});
 next.addEventListener('click',function(){if(active.length>1)move(1)});
 close.addEventListener('click',function(){if(typeof dialog.close==='function')dialog.close();else dialog.removeAttribute('open');stopMedia()});
 dialog.addEventListener('close',stopMedia);
 dialog.addEventListener('click',function(event){if(event.target===dialog&&typeof dialog.close==='function')dialog.close()});
 dialog.addEventListener('keydown',function(event){if(active.length<2)return;if(event.key==='ArrowLeft'){event.preventDefault();move(-1)}if(event.key==='ArrowRight'){event.preventDefault();move(1)}});
})();
