(function(){
 var clock=document.querySelector('[data-portal-clock]');
 if(!clock||!window.Intl)return;
 var dateFormat=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{timeZone:'Asia/Bangkok',weekday:'short',day:'numeric',month:'short',year:'numeric'});
 var timeFormat=new Intl.DateTimeFormat('th-TH',{timeZone:'Asia/Bangkok',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
 function tick(){var now=new Date();clock.textContent=dateFormat.format(now)+' · '+timeFormat.format(now);clock.dateTime=now.toISOString();}
 tick();window.setInterval(tick,60000);
})();

(function(){
 'use strict';
 var grid=document.querySelector('[data-portal-grid]');
 var cards=[].slice.call(document.querySelectorAll('[data-portal-card]'));
 var start=document.querySelector('[data-nearby-start]');
 var reset=document.querySelector('[data-nearby-reset]');
 var status=document.querySelector('[data-nearby-status]');
 if(!grid||!cards.length||!start||!status)return;
 var original=cards.slice();
 var geo=window.navigator&&window.navigator.geolocation;
 function distanceKm(lat1,lon1,lat2,lon2){
  var rad=Math.PI/180,dLat=(lat2-lat1)*rad,dLon=(lon2-lon1)*rad;
  var a=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(lat1*rad)*Math.cos(lat2*rad)*Math.sin(dLon/2)*Math.sin(dLon/2);
  return 6371*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
 }
 function restore(){original.forEach(function(card){grid.appendChild(card)});cards.forEach(function(card){var badge=card.querySelector('[data-distance]');if(badge){badge.hidden=true;badge.textContent=''}card.removeAttribute('data-distance-km')});}
 function fail(message){start.disabled=false;start.removeAttribute('aria-busy');status.textContent=message}
 start.addEventListener('click',function(){
  restore();if(reset)reset.hidden=true;
  if(window.isSecureContext===false){fail('การใช้ตำแหน่งต้องเปิด Portal ผ่าน HTTPS · คุณยังเลือกดูร้านได้ตามปกติ');return}
  if(!geo||typeof geo.getCurrentPosition!=='function'){fail('อุปกรณ์หรือเบราว์เซอร์นี้ไม่รองรับ GPS · คุณยังเลือกดูร้านได้ตามปกติ');return}
  start.disabled=true;start.setAttribute('aria-busy','true');status.textContent='กำลังขอตำแหน่งจากอุปกรณ์ของคุณ…';
  try{geo.getCurrentPosition(function(position){
   var here=position.coords,located=0;
   cards.forEach(function(card){
    var lat=parseFloat(card.getAttribute('data-lat')),lng=parseFloat(card.getAttribute('data-lng'));
    var badge=card.querySelector('[data-distance]');
    if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat < -90||lat > 90||lng < -180||lng > 180||(!lat&&!lng)){card.removeAttribute('data-distance-km');if(badge)badge.hidden=true;return}
    var km=distanceKm(here.latitude,here.longitude,lat,lng);card.setAttribute('data-distance-km',String(km));located++;
    if(badge){badge.textContent=km<1?Math.round(km*1000)+' ม. จากคุณ':km.toFixed(1)+' กม. จากคุณ';badge.hidden=false}
   });
   cards.slice().sort(function(a,b){var da=parseFloat(a.getAttribute('data-distance-km')),db=parseFloat(b.getAttribute('data-distance-km'));if(!Number.isFinite(da))da=Infinity;if(!Number.isFinite(db))db=Infinity;return da-db}).forEach(function(card){grid.appendChild(card)});
   start.disabled=false;start.removeAttribute('aria-busy');start.innerHTML='<span aria-hidden="true">⌖</span> อัปเดตตำแหน่ง';if(reset)reset.hidden=false;
   status.textContent=located?'เรียงร้านใกล้คุณก่อนแล้ว · ใช้ตำแหน่งบนอุปกรณ์นี้เท่านั้น':'ยังไม่มีพิกัดร้านสำหรับคำนวณระยะทาง · แสดงลำดับร้านเดิม';
  },function(error){
   var messages={1:'ไม่ได้รับอนุญาตตำแหน่ง · เปลี่ยนลำดับร้านได้จากปุ่มตั้งค่าตำแหน่งของเบราว์เซอร์',2:'ระบุตำแหน่งไม่ได้ในขณะนี้ · ตรวจ GPS หรือสัญญาณเครือข่ายแล้วลองอีกครั้ง',3:'การขอตำแหน่งใช้เวลานานเกินไป · ลองอีกครั้งเมื่อสัญญาณดีขึ้น'};
   fail(messages[error&&error.code]||'ขอตำแหน่งไม่สำเร็จ · คุณยังเลือกดูร้านได้ตามปกติ');
  },{enableHighAccuracy:false,timeout:12000,maximumAge:60000})}catch(error){fail('ขอตำแหน่งไม่สำเร็จ · คุณยังเลือกดูร้านได้ตามปกติ')}
 });
 if(reset)reset.addEventListener('click',function(){restore();reset.hidden=true;start.innerHTML='<span aria-hidden="true">⌖</span> ใช้ตำแหน่งของฉัน';status.textContent='อนุญาตตำแหน่งเพื่อเรียงสาขาตามระยะทาง · เราไม่บันทึกพิกัด';});
})();
