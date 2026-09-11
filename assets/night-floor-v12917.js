/* MR BAR v1.29.17 — Flicker-free Quick Floor Switch */
(function(){
'use strict';
var wrap=document.querySelector('[data-night-floor]');if(!wrap)return;
var raw=document.getElementById('nightFloorState'),state={tables:{},prs:[],sales:[]};
try{state=JSON.parse(raw?raw.textContent:'{}')||state;}catch(e){}
state.tables=state.tables||{};state.prs=state.prs||[];state.sales=state.sales||[];
var detail=wrap.querySelector('[data-nf-detail]'),selected=null,busy=false,syncing=false;
var endpoint=wrap.dataset.quickEndpoint||'night-ops-api.php';
var labels={available:'ว่าง / พร้อมรับลูกค้า',requested:'มีคำขอโต๊ะรอยืนยัน',occupied:'มีลูกค้าใช้งาน',blocked:'ปิดรับลูกค้าชั่วคราว',mockup:'ยังไม่เชื่อม Table Directory'};
function txt(sel,v){var n=detail&&detail.querySelector(sel);if(n)n.textContent=(v===undefined||v===null||v==='')?'—':v;}
function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function hide(el,value){if(el)el.hidden=value;}
function setValue(root,sel,value){var el=root&&root.querySelector(sel);if(el)el.value=value==null?'':String(value);}
function tableState(t){if(!t)return'mockup';if(!t.active||t.status==='blocked')return'blocked';if(t.checkin||t.status==='occupied')return'occupied';if((t.requests||[]).length)return'requested';return'available';}
function tableElement(id){return wrap.querySelector('[data-nf-table][data-table-id="'+String(id).replace(/"/g,'')+'"]');}
function paintTable(id){
 var el=tableElement(id),t=state.tables&&state.tables[String(id)];if(!el||!t)return;
 ['available','requested','occupied','blocked','mockup'].forEach(function(s){el.classList.remove('state-'+s);});
 el.classList.add('state-'+tableState(t));
}
function mergeState(next){
 if(!next)return;
 if(next.tables){Object.keys(next.tables).forEach(function(id){var prev=state.tables[id]||{};var requests=prev.requests||[];state.tables[id]=Object.assign({},prev,next.tables[id]);if(!Object.prototype.hasOwnProperty.call(next.tables[id],'requests'))state.tables[id].requests=requests;paintTable(id);});}
 if(Array.isArray(next.prs))state.prs=next.prs;
 if(Array.isArray(next.sales))state.sales=next.sales;
 var rows=Object.keys(state.tables).map(function(id){return state.tables[id];}),free=rows.filter(function(t){return t.active&&t.status==='available'&&!t.checkin;}).length,active=rows.filter(function(t){return !!t.checkin;}).length;
 var freeKpi=document.querySelector('.kpi.green b'),activeKpi=document.querySelector('.kpi.gold b');if(freeKpi)freeKpi.textContent=String(free);if(activeKpi)activeKpi.textContent=String(active);
}
function fillRequests(t){
 var box=detail&&detail.querySelector('[data-nf-requests]');if(!box)return;var rows=(t&&t.requests)||[];
 box.innerHTML=rows.length?'<div class="nf-request-heading">คำขอจองโต๊ะนี้ <b>'+rows.length+'</b></div>'+rows.map(function(r){return '<div class="nf-request"><b>'+esc(r.guest_name)+' · '+Number(r.party_size||0)+' คน</b><small>'+esc(r.date)+' '+esc(r.time)+' · '+esc(r.status)+'</small></div>';}).join(''):'';
}
function setQuickStatus(value){
 var form=detail&&detail.querySelector('[data-nf-quick-form]');if(!form)return;
 var input=form.querySelector('[data-nf-quick-status-input]');if(input)input.value=value;
 form.querySelectorAll('[data-nf-quick-status]').forEach(function(btn){btn.classList.toggle('active',btn.dataset.nfQuickStatus===value);});
 var disabled=value!=='occupied',pr=form.querySelector('[data-nf-quick-pr]'),sales=form.querySelector('[data-nf-quick-sales]');
 if(pr)pr.disabled=disabled;if(sales)sales.disabled=disabled;
}
function refreshPrChoices(currentPrId,currentCheckinId){
 var form=detail&&detail.querySelector('[data-nf-quick-form]'),select=form&&form.querySelector('[data-nf-quick-pr]');if(!select)return;
 Array.prototype.forEach.call(select.options,function(opt){
  var id=Number(opt.value||0),row=null;state.prs.some(function(p){if(Number(p.id)===id){row=p;return true;}return false;});
  var occupied=row&&Number(row.current_checkin_id||0)>0&&Number(row.current_checkin_id)!==Number(currentCheckinId||0);
  opt.disabled=!!occupied;
  if(id>0&&row){var suffix=occupied?' · กำลังลงโต๊ะอื่น':(row.status==='offline'?' · OFFLINE':' · '+String(row.status||'').toUpperCase());if(opt.text.indexOf(' · กำลังลงโต๊ะอื่น')<0&&opt.text.indexOf(' · OFFLINE')<0&&opt.text.indexOf(' · ONLINE')<0&&opt.text.indexOf(' · BREAK')<0&&opt.text.indexOf(' · BUSY')<0)opt.dataset.baseLabel=opt.text;opt.text=(opt.dataset.baseLabel||opt.text)+suffix;}
 });
 select.value=String(currentPrId||0);
}
function renderQuick(t,id){
 var form=detail&&detail.querySelector('[data-nf-quick-form]');if(!form)return;
 form.hidden=!(t&&t.active&&wrap.dataset.canQuick==='1');if(form.hidden)return;
 setValue(form,'[data-nf-quick-table]',id);
 var status=tableState(t);if(status==='requested')status='available';if(status==='mockup')status='available';
 setQuickStatus(status);
 var checkin=t&&t.checkin,prId=Number(checkin&&checkin.pr_id||t&&t.service&&t.service.pr_id||0),salesId=Number(checkin&&checkin.service_sales_employee_id||t&&t.service&&t.service.sales_employee_id||0);
 refreshPrChoices(prId,checkin&&checkin.id);
 setValue(form,'[data-nf-quick-sales]',salesId||'none');
 var current=form.querySelector('[data-nf-current-service]');
 if(current){
  var service=t&&t.service,parts=[];
  if(service&&service.pr_name)parts.push('PR '+service.pr_name);else if(prId&&form.querySelector('[data-nf-quick-pr]').selectedOptions.length)parts.push('PR '+form.querySelector('[data-nf-quick-pr]').selectedOptions[0].text.replace(/ · (ONLINE|OFFLINE|BREAK|BUSY)$/,''));
  if(service&&service.sales_name)parts.push('เซล '+service.sales_name);else if(salesId&&form.querySelector('[data-nf-quick-sales]').selectedOptions.length)parts.push('เซล '+form.querySelector('[data-nf-quick-sales]').selectedOptions[0].text);
  var serviceStarted=service&&service.started_at||checkin&&checkin.started_at;if(serviceStarted){var d=new Date(serviceStarted);if(!isNaN(d.getTime()))parts.push('เริ่ม '+d.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'}));}
  current.textContent=parts.length?'กำลังดูแล: '+parts.join(' · '):'ยังไม่มี PR หรือเซลผูกกับโต๊ะนี้';
  current.hidden=status!=='occupied';
 }
 var feedback=form.querySelector('[data-nf-quick-feedback]');if(feedback)feedback.hidden=true;
}
function openTable(el,shouldScroll){
 selected=el;wrap.querySelectorAll('[data-nf-table]').forEach(function(x){x.classList.toggle('is-selected',x===el);});
 var id=String(el.dataset.tableId||''),t=state.tables&&state.tables[id],st=tableState(t),checkin=t&&t.checkin,reqs=t&&t.requests||[];
 txt('[data-nf-kicker]','TABLE / '+((t&&t.code)||el.getAttribute('aria-label')||'—'));
 txt('[data-nf-title]',(t&&t.code)||el.getAttribute('aria-label')||'โต๊ะ');txt('[data-nf-state]',labels[st]||st);
 var stateEl=detail&&detail.querySelector('[data-nf-state]');if(stateEl)stateEl.className='nf-state state-'+st;
 txt('[data-nf-zone-label]',(t&&t.zone)||el.dataset.zone||'—');txt('[data-nf-capacity]',t&&t.capacity?String(t.capacity)+' คน':'—');
 txt('[data-nf-guest]',checkin?checkin.guest_name:'—');txt('[data-nf-ticket]',checkin?checkin.ticket:'—');fillRequests(t);renderQuick(t,id);
 var seatForm=detail.querySelector('[data-nf-seat-form]'),canSeat=!!seatForm&&wrap.dataset.canSeat==='1'&&t&&t.active&&t.status==='available'&&!checkin&&reqs.length>0;
 hide(seatForm,!canSeat);if(canSeat){setValue(seatForm,'[data-nf-seat-table]',id);var seatSelect=seatForm.querySelector('[data-nf-seat-select]');seatSelect.innerHTML=reqs.map(function(r){return '<option value="'+Number(r.id||0)+'">'+esc(r.guest_name)+' · '+Number(r.party_size||0)+' คน · '+esc(r.date)+' '+esc(r.time)+'</option>';}).join('');var seatId=seatForm.querySelector('[data-nf-seat-id]');seatId.value=seatSelect.value;seatSelect.onchange=function(){seatId.value=seatSelect.value;};}
 var walkin=detail.querySelector('[data-nf-walkin-form]'),canWalkin=!!walkin&&wrap.dataset.canSeat==='1'&&t&&t.active&&t.status==='available'&&!checkin;
 hide(walkin,!canWalkin);if(canWalkin)setValue(walkin,'[data-nf-walkin-table]',id);
 var statusForm=detail.querySelector('[data-nf-status-form]'),canStatus=!!statusForm&&wrap.dataset.canStatus==='1'&&t&&t.active&&!checkin;
 hide(statusForm,!canStatus);if(canStatus){setValue(statusForm,'[data-nf-status-table]',id);var statusSelect=statusForm.querySelector('[data-nf-status-select]');statusSelect.value=(t.status==='blocked'||st==='blocked'||st==='occupied')?'available':'blocked';var statusSubmit=statusForm.querySelector('[data-nf-status-submit]');if(statusSubmit)statusSubmit.textContent=statusSelect.value==='available'?'เปิดโต๊ะพร้อมรับลูกค้า':'ปิดรับลูกค้าชั่วคราว';statusSelect.onchange=function(){if(statusSubmit)statusSubmit.textContent=this.value==='available'?'เปิดโต๊ะพร้อมรับลูกค้า':'ปิดรับลูกค้าชั่วคราว';};}
 var moveForm=detail.querySelector('[data-nf-move-form]'),canMove=!!moveForm&&wrap.dataset.canMove==='1'&&st==='occupied'&&checkin;
 hide(moveForm,!canMove);if(canMove){setValue(moveForm,'[data-nf-move-checkin]',checkin.id);setValue(moveForm,'[data-nf-move-table]',id);var moveSelect=moveForm.querySelector('[data-nf-move-select]');Array.prototype.forEach.call(moveSelect.options,function(o){o.hidden=o.value===id;});moveSelect.value='';}
 var checkout=detail.querySelector('[data-nf-checkout]'),canCheckout=!!checkout&&st==='occupied'&&checkin&&(wrap.dataset.canComplete==='1'||wrap.dataset.canCancel==='1');
 hide(checkout,!canCheckout);if(canCheckout){setValue(checkout,'[data-nf-complete-table]',id);setValue(checkout,'[data-nf-complete-checkin]',checkin.id);setValue(checkout,'[data-nf-cancel-table]',id);setValue(checkout,'[data-nf-cancel-checkin]',checkin.id);}
 var none=detail.querySelector('[data-nf-no-action]'),hasQuick=wrap.dataset.canQuick==='1'&&t&&t.active,hasAction=hasQuick||canSeat||canWalkin||canStatus||canMove||canCheckout;
 hide(none,hasAction);if(none&&!hasAction)none.textContent=!t?'Hotspot นี้ยังไม่เชื่อมกับ Table Directory':(!t.active?'โต๊ะนี้ถูกปิดถาวรใน Table Directory กรุณาเปิดจากหน้าจัดการโต๊ะ':'บัญชีนี้ไม่มี Permission สำหรับคำสั่งของสถานะนี้');
 detail.hidden=false;if(shouldScroll&&window.innerWidth<1101)detail.scrollIntoView({behavior:'auto',block:'nearest'});
}
async function fetchState(showFeedback){
 if(busy||syncing)return;syncing=true;var refresh=wrap.querySelector('[data-nf-refresh]');if(refresh)refresh.disabled=true;
 try{var response=await fetch(endpoint+'?mode=state&_='+Date.now(),{credentials:'same-origin',headers:{'Accept':'application/json'}});var data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'โหลดสถานะไม่สำเร็จ');mergeState(data.state);if(selected)openTable(selected,false);if(showFeedback){var f=detail&&detail.querySelector('[data-nf-quick-feedback]');if(f){f.textContent='อัปเดตข้อมูลล่าสุดแล้ว';f.className='nf-quick-feedback ok';f.hidden=false;}}}
 catch(error){if(showFeedback)window.alert(error.message||'โหลดสถานะไม่สำเร็จ');}
 finally{syncing=false;if(refresh){refresh.disabled=false;refresh.textContent='↻ รีเฟรชข้อมูล';}}
}
wrap.querySelectorAll('[data-nf-table]').forEach(function(el){el.addEventListener('click',function(){openTable(el,true);});});
var close=wrap.querySelector('[data-nf-close]');if(close)close.addEventListener('click',function(){detail.hidden=true;selected=null;wrap.querySelectorAll('[data-nf-table]').forEach(function(x){x.classList.remove('is-selected');});});
wrap.querySelectorAll('[data-nf-zone]').forEach(function(btn){btn.addEventListener('click',function(){var z=btn.dataset.nfZone||'';wrap.querySelectorAll('[data-nf-zone]').forEach(function(x){x.classList.toggle('active',x===btn);});wrap.querySelectorAll('.nf-item').forEach(function(x){x.classList.toggle('is-zone-hidden',!!z&&(x.dataset.zone||'')!==z&&x.classList.contains('type-table'));});});});
var quick=detail&&detail.querySelector('[data-nf-quick-form]');
if(quick){
 quick.querySelectorAll('[data-nf-quick-status]').forEach(function(btn){btn.addEventListener('click',function(){setQuickStatus(btn.dataset.nfQuickStatus);});});
 var prSelect=quick.querySelector('[data-nf-quick-pr]');if(prSelect)prSelect.addEventListener('change',function(){if(Number(this.value||0)>0)setQuickStatus('occupied');});
 quick.addEventListener('submit',async function(e){
  e.preventDefault();if(busy)return;var status=quick.querySelector('[data-nf-quick-status-input]').value,pr=quick.querySelector('[data-nf-quick-pr]'),sales=quick.querySelector('[data-nf-quick-sales]');
  if(status!=='occupied'){if(pr)pr.value='0';if(sales)sales.value='none';}
  if((status==='available'||status==='blocked')&&selected){var t=state.tables[String(selected.dataset.tableId||'')];if(t&&t.checkin&&!window.confirm(status==='available'?'ยืนยันจบการดูแลและคืนโต๊ะเป็นว่าง?':'ยืนยันจบการดูแลและปิดโต๊ะชั่วคราว?'))return;}
  busy=true;var save=quick.querySelector('[data-nf-quick-save]'),feedback=quick.querySelector('[data-nf-quick-feedback]'),saveState=quick.querySelector('[data-nf-save-state]');if(save){save.disabled=true;save.textContent='กำลังบันทึก...';}if(saveState)saveState.textContent='SYNCING';
  try{var response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'fetch'},body:new FormData(quick)});var data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'บันทึกไม่สำเร็จ');mergeState(data.state);if(data.table)state.tables[String(data.table.id)]=Object.assign({},state.tables[String(data.table.id)]||{},data.table);if(selected){paintTable(selected.dataset.tableId);openTable(selected,false);}if(feedback){feedback.textContent='✓ '+(data.message||'บันทึกแล้ว')+' · '+new Date().toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});feedback.className='nf-quick-feedback ok';feedback.hidden=false;}if(saveState)saveState.textContent='บันทึกแล้ว';}
  catch(error){if(feedback){feedback.textContent='⚠ '+(error.message||'บันทึกไม่สำเร็จ');feedback.className='nf-quick-feedback err';feedback.hidden=false;}if(saveState)saveState.textContent='บันทึกไม่สำเร็จ';}
  finally{busy=false;if(save){save.disabled=false;save.textContent='บันทึกสถานะด่วน';}}
 });
}
wrap.querySelectorAll('[data-confirm]').forEach(function(btn){btn.addEventListener('click',function(e){var message=btn.dataset.confirm||'ยืนยันดำเนินการ?';if(!window.confirm(message))e.preventDefault();});});
wrap.querySelectorAll('form:not([data-nf-quick-form])').forEach(function(form){form.addEventListener('submit',function(e){if(e.defaultPrevented)return;var button=form.querySelector('button[type="submit"],button:not([type])');if(button){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent='กำลังบันทึก...';}});});
var refresh=wrap.querySelector('[data-nf-refresh]');if(refresh)refresh.addEventListener('click',function(){refresh.textContent='กำลังรีเฟรช...';fetchState(true);});
var clock=wrap.querySelector('[data-nf-clock]');if(clock)setInterval(function(){var d=new Date();clock.textContent=d.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});},1000);
var selectedId=String(wrap.dataset.selectedTable||'');if(selectedId&&selectedId!=='0'){var initial=tableElement(selectedId);if(initial)openTable(initial,false);}
setInterval(function(){if(!document.hidden)fetchState(false);},15000);
})();
