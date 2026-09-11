(function(){'use strict';var tabs=document.querySelectorAll('[data-section-tab]'),panels=document.querySelectorAll('[data-section-panel]');function openSection(key){tabs.forEach(function(t){t.classList.toggle('active',t.getAttribute('data-section-tab')===key);});panels.forEach(function(p){p.classList.toggle('active',p.getAttribute('data-section-panel')===key);});try{history.replaceState(null,'','#'+key);}catch(e){}}tabs.forEach(function(t){t.addEventListener('click',function(){openSection(t.getAttribute('data-section-tab'));});});document.querySelectorAll('[data-open-section]').forEach(function(b){b.addEventListener('click',function(){openSection(b.getAttribute('data-open-section'));});});var initial=(location.hash||'#publish').slice(1);if(document.querySelector('[data-section-panel="'+initial+'"]'))openSection(initial);document.querySelectorAll('[data-dirty-form]').forEach(function(form){var label=form.querySelector('[data-dirty-label]'),dirty=false;function mark(){if(dirty)return;dirty=true;if(label){label.classList.add('dirty');label.textContent='● มีการเปลี่ยนแปลงที่ยังไม่บันทึก';}}form.addEventListener('input',mark);form.addEventListener('change',mark);form.addEventListener('submit',function(ev){if(ev.submitter&&ev.submitter.hasAttribute('data-media-delete'))return;var b=form.querySelector('button[name="media_action"][value="save"], .cwa-save button, .chm-save-row button');if(b){b.disabled=true;b.textContent='กำลังบันทึก...';}});});})();

/* v1.17.0 non-destructive customer media editor */
(function(){
  'use strict';
  function n(form,name,def){var e=form.elements[name];return e?parseFloat(e.value||def):def;}
  function update(form){
    var box=form.querySelector('[data-media-preview]'),img=box&&box.querySelector('img');if(!box||!img)return;
    var x=n(form,'position_x',50),y=n(form,'position_y',50),z=n(form,'zoom',100)/100,b=n(form,'brightness',100)/100,c=n(form,'contrast',100)/100,s=n(form,'saturation',100)/100,blur=n(form,'blur',0),overlay=n(form,'overlay',20)/100;
    img.style.objectPosition=x+'% '+y+'%';img.style.transform='scale('+z.toFixed(2)+')';img.style.transformOrigin=x+'% '+y+'%';img.style.filter='brightness('+b.toFixed(2)+') contrast('+c.toFixed(2)+') saturate('+s.toFixed(2)+') blur('+blur+'px)';box.style.setProperty('--overlay',overlay.toFixed(2));
    ['position_x','position_y','zoom','brightness','contrast','saturation','overlay'].forEach(function(k){var o=form.querySelector('[data-range-output="'+k+'"]');if(o)o.textContent=Math.round(n(form,k,0))+'%';});var ob=form.querySelector('[data-range-output="blur"]');if(ob)ob.textContent=Math.round(blur)+'px';
    var slot=form.elements.slot,tag=box.querySelector(':scope > span');if(slot&&tag){var map={library:'คลังรูป',hero:'Hero',promo:'Promotion',gallery:'Gallery',tonight:'คืนนี้ / Live',pr_section:'PR Section',zones:'Zone & Table',floor_preview:'Floor Preview',location:'Location',policy:'Policy / CTA'};tag.textContent=map[slot.value]||slot.value;}
  }
  document.querySelectorAll('[data-media-editor]').forEach(function(form){
    form.querySelectorAll('input[type="range"],select[name="slot"]').forEach(function(e){e.addEventListener('input',function(){update(form);});e.addEventListener('change',function(){update(form);});});
    var initial={};['position_x','position_y','zoom','brightness','contrast','saturation','blur','overlay'].forEach(function(k){initial[k]=form.elements[k]?form.elements[k].value:'';});
    var reset=form.querySelector('[data-media-reset]');if(reset)reset.addEventListener('click',function(){Object.keys(initial).forEach(function(k){if(form.elements[k])form.elements[k].value=initial[k];});update(form);});
    var del=form.querySelector('[data-media-delete]');if(del)del.addEventListener('click',function(ev){if(!window.confirm('ลบรูปนี้ออกจาก Host และ Media Library ใช่หรือไม่?'))ev.preventDefault();});
    update(form);
  });
  document.querySelectorAll('.cwa-upload input[type="file"]').forEach(function(input){input.addEventListener('change',function(){var label=input.closest('.cwa-drop'),b=label&&label.querySelector('b');if(b&&input.files&&input.files.length)b.textContent='เลือกแล้ว '+input.files.length+' รูป';});});
})();

/* v1.17.2 responsive Customer View */
(function(){
  'use strict';
  var stage=document.querySelector('[data-preview-stage]'),frame=document.querySelector('[data-customer-preview]');
  if(!stage||!frame)return;
  var shell=stage.querySelector('.cwa-device-shell'),sizeLabel=document.querySelector('[data-preview-size]');
  var devices={desktop:{w:1440,h:900,label:'Desktop'},tablet:{w:768,h:1024,label:'Tablet'},mobile:{w:390,h:844,label:'Mobile'}};
  var current='desktop',timer=null;
  function fit(){var d=devices[current],maxW=Math.max(280,stage.clientWidth-40),maxH=720,scale=Math.min(1,maxW/d.w,maxH/(d.h+42));shell.style.width=d.w+'px';shell.style.height=(d.h+42)+'px';shell.style.transform='scale('+scale.toFixed(4)+')';stage.style.height=Math.ceil((d.h+42)*scale+40)+'px';stage.setAttribute('data-device',current);if(sizeLabel)sizeLabel.textContent=d.label+' · '+d.w+' × '+d.h;}
  function selectDevice(key){if(!devices[key])return;current=key;document.querySelectorAll('[data-preview-device]').forEach(function(b){b.classList.toggle('active',b.getAttribute('data-preview-device')===key);});fit();}
  function refresh(){try{var u=new URL(frame.src,window.location.href);u.searchParams.set('v',Date.now().toString());frame.src=u.toString();}catch(e){frame.src=frame.src.split('#')[0];}}
  document.querySelectorAll('[data-preview-device]').forEach(function(b){b.addEventListener('click',function(){selectDevice(b.getAttribute('data-preview-device'));});});
  var rb=document.querySelector('[data-preview-refresh]');if(rb)rb.addEventListener('click',function(){rb.disabled=true;var old=rb.textContent;rb.textContent='↻ กำลังโหลด...';refresh();setTimeout(function(){rb.disabled=false;rb.textContent=old;},900);});
  var auto=document.querySelector('[data-preview-auto]');if(auto)auto.addEventListener('change',function(){if(timer){clearInterval(timer);timer=null;}if(auto.checked)timer=setInterval(refresh,15000);});
  window.addEventListener('resize',function(){clearTimeout(window.__cwaPreviewResize);window.__cwaPreviewResize=setTimeout(fit,80);});
  frame.addEventListener('load',function(){stage.classList.remove('loading');});
  fit();
})();

/* v1.21.1 quick media replace/delete reliability */
(function(){
  'use strict';
  document.querySelectorAll('[data-quick-media-upload]').forEach(function(form){
    var input=form.querySelector('input[type="file"]'),button=form.querySelector('button');
    if(input)input.addEventListener('change',function(){var b=form.querySelector('label b');if(b&&input.files&&input.files.length)b.textContent='เลือกแล้ว '+input.files.length+' รูป · พร้อมอัปโหลด';});
    form.addEventListener('submit',function(){form.classList.add('is-uploading');if(button){button.disabled=true;button.textContent='กำลังอัปโหลด...';}});
  });
  document.querySelectorAll('[data-quick-media-detach]').forEach(function(form){form.addEventListener('submit',function(ev){if(!window.confirm('เอารูปออกจากส่วนนี้ใช่หรือไม่? ไฟล์จะยังอยู่ใน Media Manager'))ev.preventDefault();});});
  document.querySelectorAll('[data-quick-media-delete]').forEach(function(form){form.addEventListener('submit',function(ev){if(!window.confirm('ลบรูปนี้ออกจาก Host แบบถาวรใช่หรือไม่? การกระทำนี้ย้อนกลับไม่ได้'))ev.preventDefault();});});
})();

/* v1.21.2 Google Map Pin editor / no API key */
(function(){
  'use strict';
  var form=document.querySelector('[data-map-pin-form]');if(!form)return;
  var branch=form.querySelector('[data-map-branch]'),lat=form.querySelector('[data-map-lat]'),lng=form.querySelector('[data-map-lng]'),query=form.querySelector('[data-map-query]'),url=form.querySelector('[data-map-url]'),zoom=form.querySelector('[data-map-zoom]'),frame=form.querySelector('[data-map-preview]'),open=form.querySelector('[data-map-open]'),status=form.querySelector('[data-map-status]'),caption=form.querySelector('[data-map-caption]'),hint=form.querySelector('[data-map-hint]');
  function validCoord(a,b){var x=parseFloat(a),y=parseFloat(b);return isFinite(x)&&isFinite(y)&&x>=-90&&x<=90&&y>=-180&&y<=180;}
  function selected(){return branch&&branch.options[branch.selectedIndex]?branch.options[branch.selectedIndex]:null;}
  function parseMapUrl(v){v=(v||'').trim();if(!v)return null;var m=v.match(/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/);if(!m)m=v.match(/[?&](?:query|q|ll)=(-?\d+(?:\.\d+)?)(?:%2C|,)(-?\d+(?:\.\d+)?)/i);if(!m)return null;return [m[1],m[2]];}
  function currentData(){var la=(lat.value||'').trim(),ln=(lng.value||'').trim(),q=(query.value||'').trim(),opt=selected();if(!validCoord(la,ln)&&opt&&validCoord(opt.dataset.lat,opt.dataset.lng)){la=opt.dataset.lat;ln=opt.dataset.lng;}if(!q&&opt)q=opt.dataset.address||opt.textContent.trim();return {lat:la,lng:ln,q:q,z:Math.max(10,Math.min(20,parseInt(zoom.value||16,10)||16))};}
  function refresh(){var d=currentData(),embed='',generated='';if(validCoord(d.lat,d.lng)){var pair=d.lat+','+d.lng;embed='https://www.google.com/maps?q='+encodeURIComponent(pair)+'&z='+d.z+'&output=embed';generated='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(pair);if(status)status.textContent='หมุด '+pair;if(caption)caption.textContent=pair+' · Zoom '+d.z;}else if(d.q){embed='https://www.google.com/maps?q='+encodeURIComponent(d.q)+'&z='+d.z+'&output=embed';generated='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(d.q);if(status)status.textContent='ค้นจากชื่อ/ที่อยู่';if(caption)caption.textContent=d.q;}else{if(status)status.textContent='ยังไม่ได้ตั้งตำแหน่ง';if(caption)caption.textContent='กรอกพิกัดหรือเลือกสาขา';}
    if(frame){if(embed&&frame.src!==embed)frame.src=embed;if(!embed)frame.removeAttribute('src');}
    var explicit=(url.value||'').trim();if(open){open.href=/^https:\/\//i.test(explicit)?explicit:(generated||'#');open.style.opacity=(open.href.endsWith('#')?'0.45':'1');}
  }
  if(url){var parseUrl=function(){var c=parseMapUrl(url.value);if(c){lat.value=c[0];lng.value=c[1];if(hint){hint.textContent='อ่านพิกัดจาก Google Maps Link ให้แล้ว · '+c[0]+', '+c[1];hint.style.color='#d4aa50';}}refresh();};url.addEventListener('input',parseUrl);url.addEventListener('change',parseUrl);}
  [branch,lat,lng,query,zoom].forEach(function(e){if(!e)return;e.addEventListener('input',refresh);e.addEventListener('change',refresh);});
  var useBranch=form.querySelector('[data-map-use-branch]');if(useBranch)useBranch.addEventListener('click',function(){var o=selected();if(!o)return;if(validCoord(o.dataset.lat,o.dataset.lng)){lat.value=o.dataset.lat;lng.value=o.dataset.lng;if(!query.value.trim())query.value=o.dataset.address||o.textContent.trim();if(hint){hint.textContent='ดึงพิกัดจากสาขาที่เลือกแล้ว';hint.style.color='#69dca1';}}else{if(hint){hint.textContent='สาขานี้ยังไม่มี Latitude / Longitude · ไปตั้งที่ Settings Center > Branch ก่อน';hint.style.color='#ffb36c';}}refresh();});
  var useCurrent=form.querySelector('[data-map-use-current]');if(useCurrent)useCurrent.addEventListener('click',function(){if(!navigator.geolocation){if(hint)hint.textContent='Browser นี้ไม่รองรับ Location';return;}useCurrent.disabled=true;useCurrent.textContent='กำลังค้นหา...';navigator.geolocation.getCurrentPosition(function(pos){lat.value=pos.coords.latitude.toFixed(7);lng.value=pos.coords.longitude.toFixed(7);useCurrent.disabled=false;useCurrent.textContent='◎ ใช้ตำแหน่งเครื่องนี้';if(hint){hint.textContent='ใส่ตำแหน่งปัจจุบันแล้ว · ตรวจหมุดบน Preview ก่อนกดบันทึก';hint.style.color='#69dca1';}refresh();},function(){useCurrent.disabled=false;useCurrent.textContent='◎ ใช้ตำแหน่งเครื่องนี้';if(hint){hint.textContent='ไม่สามารถอ่านตำแหน่งได้ กรุณาอนุญาต Location หรือใส่พิกัดเอง';hint.style.color='#ff8fa0';}}, {enableHighAccuracy:true,timeout:12000,maximumAge:0});});
  refresh();
})();
