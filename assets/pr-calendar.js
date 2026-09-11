(function(){
  'use strict';
  var dataEl=document.getElementById('calendarData');
  var data={};
  try{data=JSON.parse(dataEl?dataEl.textContent:'{}')||{};}catch(e){data={};}
  var selected=window.MRBAR_CAL_SELECTED||'';
  var detailDate=document.getElementById('detailDate');
  var detailStatus=document.getElementById('detailStatus');
  var attendanceDetail=document.getElementById('attendanceDetail');
  var leaveDetail=document.getElementById('leaveDetail');
  var leaveStart=document.getElementById('leaveStart');
  var leaveEnd=document.getElementById('leaveEnd');
  var thaiMonths=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function thDate(date){var p=date.split('-');if(p.length!==3)return date;return Number(p[2])+' '+thaiMonths[Number(p[1])-1]+' '+(Number(p[0])+543);}
  function statusText(status){var x={worked:['ลงเวลาปกติ','มีบันทึกเวลาเข้า/ออกงาน','worked'],late:['มาสาย','มีการบันทึกเวลา แต่เกินเวลาเริ่มงาน','late'],missing_checkout:['ขาด Check-out','มีเวลาเข้างาน แต่เวลาออกยังรอตรวจสอบ','missing_checkout'],leave:['ลาได้รับอนุมัติ','วันนี้มีใบลาที่ Admin อนุมัติแล้ว','leave'],leave_pending:['รออนุมัติการลา','มีคำขอลาสำหรับวันนี้','leave_pending'],absent:['ขาดงาน','มีกะงานแต่ไม่พบบันทึกเวลา/ใบลาอนุมัติ','absent'],scheduled:['มีกะงาน','มีตารางกะที่กำหนดไว้','scheduled'],none:['ไม่มีรายการ','ยังไม่มีการลงเวลาหรือกะงานในวันนี้','none']};return x[status]||x.none;}
  function render(date){selected=date;document.querySelectorAll('.day-cell[data-date]').forEach(function(b){b.classList.toggle('is-selected',b.getAttribute('data-date')===date);});if(leaveStart)leaveStart.value=date;if(leaveEnd)leaveEnd.value=date;var d=data[date]||{meta:{status:'none'},attendance:[],leaves:[],shifts:[]};if(detailDate)detailDate.textContent=thDate(date);var st=statusText((d.meta||{}).status);if(detailStatus){var sh=(d.shifts||[]).map(function(x){return esc(x.start)+'–'+esc(x.end);}).join(', ');detailStatus.innerHTML='<div class="status-banner '+esc(st[2])+'"><b>'+esc(st[0])+'</b><span>'+esc(st[1])+(sh?' · กะ '+sh:'')+'</span></div>'; }
    var att='';(d.attendance||[]).forEach(function(a){att+='<article class="attendance-row"><div class="timebox"><span><small>CHECK-IN</small><strong>'+esc(a.in)+'</strong></span><span><small>CHECK-OUT</small><strong>'+esc(a.out)+'</strong></span></div><div class="meta"><span>สาขา '+esc(a.branch)+'</span><span>ทำงาน '+esc(a.worked_label)+'</span>'+(a.late?'<span class="late-pill">สาย +'+esc(a.late)+' นาที</span>':'<span>ตรงเวลา</span>')+(a.gps_in!=null?'<span>GPS IN '+esc(a.gps_in)+'m</span>':'')+(a.gps_out!=null?'<span>GPS OUT '+esc(a.gps_out)+'m</span>':'')+'</div>';
      if(a.in_photo||a.out_photo){att+='<div class="evidence-thumbs">';if(a.in_photo)att+='<button type="button" class="evidence-photo" data-src="'+esc(a.in_photo)+'" data-caption="Check-In · '+esc(thDate(date))+'"><img loading="lazy" src="'+esc(a.in_photo)+'" alt="Check-In"></button>';if(a.out_photo)att+='<button type="button" class="evidence-photo" data-src="'+esc(a.out_photo)+'" data-caption="Check-Out · '+esc(thDate(date))+'"><img loading="lazy" src="'+esc(a.out_photo)+'" alt="Check-Out"></button>';att+='</div>';}
      att+='</article>';});if(!att)att='<div class="empty mini">ไม่มีบันทึกเวลาในวันที่เลือก</div>';if(attendanceDetail)attendanceDetail.innerHTML=att;
    var lv='';(d.leaves||[]).forEach(function(l){lv+='<article class="leave-day-card"><b>'+esc(l.type_label)+' · '+esc(l.status_label)+'</b><span>'+esc(l.portion_label)+' · '+esc(l.start_date)+(l.end_date!==l.start_date?' → '+esc(l.end_date):'')+'</span><small>'+esc(l.reason)+'</small>'+(l.admin_note?'<small>Admin: '+esc(l.admin_note)+'</small>':'')+'</article>';});if(leaveDetail)leaveDetail.innerHTML=lv;
    bindPhotos();
  }
  document.querySelectorAll('.day-cell[data-date]').forEach(function(b){b.addEventListener('click',function(){render(b.getAttribute('data-date')||'');var dd=document.getElementById('dayDetail');if(dd&&window.innerWidth<620)dd.scrollIntoView({behavior:'smooth',block:'start'});});});
  var modal=document.getElementById('leaveModal'),open1=document.getElementById('leaveOpen'),open2=document.getElementById('leaveOpen2'),close=document.getElementById('leaveClose');
  function openLeave(){if(leaveStart)leaveStart.value=selected;if(leaveEnd)leaveEnd.value=selected;if(modal){modal.hidden=false;document.body.style.overflow='hidden';}}
  function closeLeave(){if(modal){modal.hidden=true;document.body.style.overflow='';}}
  if(open1)open1.addEventListener('click',openLeave);if(open2)open2.addEventListener('click',openLeave);if(close)close.addEventListener('click',closeLeave);if(modal)modal.addEventListener('click',function(e){if(e.target===modal)closeLeave();});
  if(leaveStart&&leaveEnd)leaveStart.addEventListener('change',function(){if(!leaveEnd.value||leaveEnd.value<leaveStart.value)leaveEnd.value=leaveStart.value;leaveEnd.min=leaveStart.value;});
  var viewer=document.getElementById('photoViewer'),photo=document.getElementById('photoImage'),caption=document.getElementById('photoCaption'),photoClose=document.getElementById('photoClose');
  function bindPhotos(){document.querySelectorAll('.evidence-photo').forEach(function(b){if(b.dataset.bound)return;b.dataset.bound='1';b.addEventListener('click',function(){if(photo)photo.src=b.getAttribute('data-src')||'';if(caption)caption.textContent=b.getAttribute('data-caption')||'';if(viewer){viewer.hidden=false;document.body.style.overflow='hidden';}});});}
  function closePhoto(){if(viewer)viewer.hidden=true;if(photo)photo.removeAttribute('src');document.body.style.overflow='';}if(photoClose)photoClose.addEventListener('click',closePhoto);if(viewer)viewer.addEventListener('click',function(e){if(e.target===viewer)closePhoto();});
  var drawer=document.getElementById('calDrawer'),back=document.getElementById('calDrawerBackdrop'),drawerOpen=document.getElementById('calDrawerOpen'),drawerClose=document.getElementById('calDrawerClose');
  function openDrawer(){if(drawer)drawer.classList.add('open');if(back)back.hidden=false;document.body.classList.add('drawer-open');}
  function closeDrawerFn(){if(drawer)drawer.classList.remove('open');if(back)back.hidden=true;document.body.classList.remove('drawer-open');}
  if(drawerOpen)drawerOpen.addEventListener('click',openDrawer);if(drawerClose)drawerClose.addEventListener('click',closeDrawerFn);if(back)back.addEventListener('click',closeDrawerFn);
  document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeLeave();closePhoto();closeDrawerFn();}});
  render(selected);
})();
