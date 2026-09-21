(function(){
  'use strict';
  var dataEl=document.getElementById('scheduleTeamData');
  var teamData={};
  try{teamData=dataEl?JSON.parse(dataEl.textContent||'{}'):{};}catch(e){teamData={};}
  var hover=document.getElementById('scheduleHoverCard');
  var modal=document.getElementById('scheduleTeamModal');
  var modalBody=document.getElementById('scheduleTeamModalBody');
  var hoverTimer=0;

  function esc(v){return String(v==null?'':v).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}
  function bandClass(b){return ['morning','day','evening','night','walkin','leave'].indexOf(b)>=0?b:'night';}
  function bandHtml(info){var out='';var bands=info&&info.bands||{};['morning','day','evening','night'].forEach(function(b){var n=Number(bands[b]||0);if(!n)return;var label={morning:'กะเช้า',day:'กะบ่าย',evening:'กะเย็น',night:'กะดึก'}[b];out+='<span class="team-band '+b+'">'+label+' '+n+'</span>';});if(Number(info&&info.unscheduled||0)>0)out+='<span class="team-band walkin">เข้างานนอกกะ '+Number(info.unscheduled)+'</span>';if(Number(info&&info.leave||0)>0)out+='<span class="team-band leave">ลา '+Number(info.leave)+'</span>';return out;}
  function rowsHtml(info,limit){var rows=info&&info.rows||[];if(!rows.length)return '<div class="empty">ยังไม่มีตารางงาน การลงเวลา หรือข้อมูลการลาในวันนี้</div>';var compact=typeof limit==='number';var max=compact?Math.min(limit,rows.length):rows.length;var html='<div class="team-pop-list">';for(var i=0;i<max;i++){var r=rows[i];var av=r.avatar?'<img src="'+esc(r.avatar)+'" alt="">':'<b>'+esc((r.name||r.code||'?').charAt(0))+'</b>';var time=r.time_label?esc(r.time_label):(r.start?esc(r.start)+'–'+esc(r.end):esc(r.band_label||'ลา'));var action=!compact&&r.manage_url?'<a class="team-pop-manage" href="'+esc(r.manage_url)+'">'+(r.start?'แก้ไขตาราง':'เปิดพนักงาน')+' →</a>':'';html+='<div class="team-pop-row '+(r.state==='unscheduled'?'is-unscheduled':'')+'"><span class="team-pop-avatar">'+av+'</span><div><b>'+esc(r.code)+' · '+esc(r.name)+'</b><small>'+esc(r.position)+' · '+esc(r.branch)+'</small></div><div class="team-pop-time"><strong>'+time+'</strong><em class="state-'+esc(r.state)+'">'+esc(r.state_label)+'</em></div>'+action+'</div>';}
    if(rows.length>max)html+='<div class="empty">+ '+(rows.length-max)+' รายการเพิ่มเติม</div>';return html+'</div>';
  }
  function previewHtml(info){return '<h4>'+esc(info.date_label||info.date)+'</h4><p>ทีมมีกะ '+Number(info.total||0)+' คน · เข้างานนอกกะ '+Number(info.unscheduled||0)+' · งาน '+Number(info.jobs||0)+' · ลา '+Number(info.leave||0)+'</p><div class="team-pop-bands">'+bandHtml(info)+'</div>'+rowsHtml(info,6);}
  function modalHtml(info){return '<h3>รายละเอียดทีม · '+esc(info.date_label||info.date)+'</h3><p class="team-summary">มีกะ '+Number(info.total||0)+' คน · เข้างานนอกกะ '+Number(info.unscheduled||0)+' · ลงเวลา '+Number(info.attendance_total||0)+' · ขาด '+Number(info.absent||0)+' · ลา '+Number(info.leave||0)+'</p><div class="team-pop-bands">'+bandHtml(info)+'</div>'+rowsHtml(info)+'<div class="v19-modal-actions"><button type="button" data-close-team-modal>ปิด</button><a href="'+esc(info.manage_url||'#')+'">จัดการตารางวันนี้ ↓</a></div>'; }
  function placeHover(el){if(!hover)return;var r=el.getBoundingClientRect();hover.hidden=false;var hw=hover.offsetWidth||360,hh=hover.offsetHeight||280;var left=Math.min(window.innerWidth-hw-12,Math.max(12,r.left+r.width*.55));var top=r.top-hh-8;if(top<12)top=Math.min(window.innerHeight-hh-12,r.bottom+8);hover.style.left=Math.round(left)+'px';hover.style.top=Math.round(Math.max(12,top))+'px';}
  function showHover(el){if(!hover)return;var info=teamData[el.getAttribute('data-date')];if(!info)return;window.clearTimeout(hoverTimer);hover.innerHTML=previewHtml(info);placeHover(el);}
  function hideHover(){if(!hover)return;hoverTimer=window.setTimeout(function(){hover.hidden=true;},90);}
  function openModal(info){if(!modal||!modalBody||!info)return;modalBody.innerHTML=modalHtml(info);modal.hidden=false;document.body.style.overflow='hidden';}
  function closeModal(){if(!modal)return;modal.hidden=true;document.body.style.overflow='';}

  document.querySelectorAll('.v19-day[data-date]').forEach(function(day){
    day.addEventListener('mouseenter',function(){showHover(day);});
    day.addEventListener('mouseleave',hideHover);
    day.addEventListener('focus',function(){showHover(day);});
    day.addEventListener('blur',hideHover);
    day.addEventListener('click',function(ev){if(ev.metaKey||ev.ctrlKey||ev.shiftKey||ev.altKey)return;var info=teamData[day.getAttribute('data-date')];if(!info)return;ev.preventDefault();if(hover)hover.hidden=true;openModal(info);});
  });
  document.addEventListener('click',function(ev){
    if(ev.target.closest('[data-close-team-modal]')){closeModal();return;}
    var open=ev.target.closest('[data-open-team-day]');if(open){var selected=document.querySelector('.v19-day.selected[data-date]');if(selected){openModal(teamData[selected.getAttribute('data-date')]);}return;}
    var manage=ev.target.closest('.v19-modal-actions a');if(manage){try{var targetUrl=new URL(manage.href,window.location.href);if(targetUrl.pathname===window.location.pathname&&targetUrl.search===window.location.search){ev.preventDefault();closeModal();var section=document.getElementById('selected-day');if(section)section.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'',targetUrl.hash||'#selected-day');return;}}catch(e){}}
    var closeEditor=ev.target.closest('[data-close-shift-editor]');if(closeEditor){var editor=document.getElementById('newShiftCard');var btn=document.getElementById('newShiftBtn');if(editor){editor.hidden=true;}if(btn){btn.classList.remove('is-open');}return;}
  });
  document.addEventListener('keydown',function(ev){if(ev.key==='Escape'){closeModal();closeWorkSchedule();}});

  /* Schedule controls are self-contained in v1.19.0. */
  var scheduleBtn=document.getElementById('scheduleWorkBtn'),scheduleModal=document.getElementById('workScheduleModal');
  function closeWorkSchedule(){if(scheduleModal)scheduleModal.hidden=true;document.body.classList.remove('ws-plan-open');}
  function openWorkSchedule(){if(scheduleModal){scheduleModal.hidden=false;document.body.classList.add('ws-plan-open');var first=scheduleModal.querySelector('input,select,button');if(first)first.focus();}}
  if(scheduleBtn&&scheduleModal)scheduleBtn.addEventListener('click',openWorkSchedule);
  document.addEventListener('click',function(ev){if(ev.target.closest('[data-close-work-schedule]')){closeWorkSchedule();return;}var all=ev.target.closest('[data-select-all-employees]');if(all&&scheduleModal){var boxes=[].slice.call(scheduleModal.querySelectorAll('input[name="employee_ids[]"]'));var should=boxes.some(function(x){return !x.checked;});boxes.forEach(function(x){x.checked=should;});all.textContent=should?'ยกเลิกทั้งหมด':'เลือกทั้งหมด';return;}});
  document.addEventListener('change',function(ev){if(ev.target.matches('[data-use-shift]')){var fields=document.querySelector('[data-shift-fields]');if(fields)fields.classList.toggle('is-enabled',ev.target.checked);}});
  document.addEventListener('click',function(ev){var b=ev.target.closest('[data-toggle-shift]');if(!b)return;var c=b.closest('.shift-card');var detail=c&&c.querySelector('.shift-detail');if(!detail)return;detail.hidden=!detail.hidden;b.textContent=detail.hidden?'แก้ไขตาราง ▾':'ปิด ▴';});
  var menu=document.getElementById('menuBtn'),side=document.getElementById('sidebar');if(menu&&side){menu.addEventListener('click',function(){if(window.matchMedia('(max-width: 900px)').matches){side.classList.toggle('open');document.body.classList.toggle('admin-mobile-nav-open',side.classList.contains('open'));}});}
})();
