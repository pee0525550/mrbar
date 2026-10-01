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
 var welcome=document.querySelector('.portal-nearby');
 var status=document.querySelector('[data-nearby-status]');
 if(!grid||!cards.length||!welcome||!status)return;
 var original=cards.slice();
 var geo=window.navigator&&window.navigator.geolocation;
 function distanceKm(lat1,lon1,lat2,lon2){
  var rad=Math.PI/180,dLat=(lat2-lat1)*rad,dLon=(lon2-lon1)*rad;
  var a=Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(lat1*rad)*Math.cos(lat2*rad)*Math.sin(dLon/2)*Math.sin(dLon/2);
  return 6371*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
 }
 function fail(message){status.textContent=message}
 function requestLocation(){
  if(window.isSecureContext===false){fail('เปิดตำแหน่งไม่ได้ เนื่องจาก Portal ยังไม่ใช่ HTTPS · เลือกร้านได้ตามปกติ');return}
  if(!geo||typeof geo.getCurrentPosition!=='function'){fail('เบราว์เซอร์นี้ไม่รองรับ GPS · เลือกร้านได้ตามปกติ');return}
  status.textContent='กำลังขออนุญาตตำแหน่ง เพื่อจัดร้านตามระยะทางใกล้คุณ…';
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
   status.textContent=located?'เรียงร้านตามระยะทางใกล้คุณแล้ว · ใช้ตำแหน่งเพื่อค้นหาเท่านั้น ไม่บันทึกพิกัด':'ยังไม่มีพิกัดสาขาสำหรับคำนวณระยะทาง · แสดงลำดับร้านเดิม';
  },function(error){
   var messages={1:'ไม่ได้รับอนุญาตตำแหน่ง · แสดงร้านตามลำดับปกติ คุณยังเลือกดูร้านได้ทั้งหมด',2:'ระบุตำแหน่งไม่ได้ในขณะนี้ · แสดงร้านตามลำดับปกติ',3:'การขอตำแหน่งใช้เวลานานเกินไป · แสดงร้านตามลำดับปกติ'};
   fail(messages[error&&error.code]||'ขอตำแหน่งไม่สำเร็จ · แสดงร้านตามลำดับปกติ');
  },{enableHighAccuracy:false,timeout:12000,maximumAge:60000})}catch(error){fail('ขอตำแหน่งไม่สำเร็จ · คุณยังเลือกดูร้านได้ตามปกติ')}
 }
 if(typeof window.IntersectionObserver==='function'){
  var observer=new window.IntersectionObserver(function(entries){
   if(entries.some(function(entry){return entry.isIntersecting})){observer.disconnect();requestLocation()}
  },{rootMargin:'0px 0px -10% 0px'});
  observer.observe(welcome);
 }
})();
