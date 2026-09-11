/* MR BAR v1.29.16 — Complete Live Floor Operations controls */
(function(){
'use strict';
var wrap=document.querySelector('[data-night-floor]');if(!wrap)return;
var raw=document.getElementById('nightFloorState'),state={tables:{}};try{state=JSON.parse(raw?raw.textContent:'{}')||state;}catch(e){}
var detail=wrap.querySelector('[data-nf-detail]'),selected=null;
var labels={available:'ว่าง / พร้อมรับลูกค้า',requested:'มีคำขอโต๊ะรอยืนยัน',occupied:'มีลูกค้าใช้งาน',blocked:'ปิดรับลูกค้าชั่วคราว',mockup:'ยังไม่เชื่อม Table Directory'};
function txt(sel,v){var n=detail&&detail.querySelector(sel);if(n)n.textContent=(v===undefined||v===null||v==='')?'—':v;}
function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function tableState(t){if(!t)return'mockup';if(!t.active||t.status==='blocked')return'blocked';if(t.checkin||t.status==='occupied')return'occupied';if((t.requests||[]).length)return'requested';return'available';}
function hide(el,value){if(el)el.hidden=value;}
function setValue(root,sel,value){var el=root&&root.querySelector(sel);if(el)el.value=value==null?'':String(value);}
function fillRequests(t){var box=detail&&detail.querySelector('[data-nf-requests]');if(!box)return;var rows=(t&&t.requests)||[];box.innerHTML=rows.length?'<div class="nf-request-heading">คำขอจองโต๊ะนี้ <b>'+rows.length+'</b></div>'+rows.map(function(r){return '<div class="nf-request"><b>'+esc(r.guest_name)+' · '+Number(r.party_size||0)+' คน</b><small>'+esc(r.date)+' '+esc(r.time)+' · '+esc(r.status)+'</small></div>';}).join(''):'';}
function openTable(el){
 selected=el;wrap.querySelectorAll('[data-nf-table]').forEach(function(x){x.classList.toggle('is-selected',x===el);});
 var id=String(el.dataset.tableId||''),t=state.tables&&state.tables[id],st=tableState(t),checkin=t&&t.checkin,reqs=t&&t.requests||[];
 txt('[data-nf-kicker]','TABLE / '+((t&&t.code)||el.getAttribute('aria-label')||'—'));
 txt('[data-nf-title]',(t&&t.code)||el.getAttribute('aria-label')||'โต๊ะ');
 txt('[data-nf-state]',labels[st]||st);
 var stateEl=detail&&detail.querySelector('[data-nf-state]');if(stateEl)stateEl.className='nf-state state-'+st;
 txt('[data-nf-zone-label]',(t&&t.zone)||el.dataset.zone||'—');
 txt('[data-nf-capacity]',t&&t.capacity?String(t.capacity)+' คน':'—');
 txt('[data-nf-guest]',checkin?checkin.guest_name:'—');
 txt('[data-nf-ticket]',checkin?checkin.ticket:'—');
 fillRequests(t);
 var seatForm=detail.querySelector('[data-nf-seat-form]');
 var canSeat=!!seatForm&&wrap.dataset.canSeat==='1'&&t&&t.active&&t.status==='available'&&!checkin&&reqs.length>0;
 hide(seatForm,!canSeat);
 if(canSeat){setValue(seatForm,'[data-nf-seat-table]',id);var seatSelect=seatForm.querySelector('[data-nf-seat-select]');seatSelect.innerHTML=reqs.map(function(r){return '<option value="'+Number(r.id||0)+'">'+esc(r.guest_name)+' · '+Number(r.party_size||0)+' คน · '+esc(r.date)+' '+esc(r.time)+'</option>';}).join('');var seatId=seatForm.querySelector('[data-nf-seat-id]');seatId.value=seatSelect.value;seatSelect.onchange=function(){seatId.value=seatSelect.value;};}
 var walkin=detail.querySelector('[data-nf-walkin-form]');
 var canWalkin=!!walkin&&wrap.dataset.canSeat==='1'&&t&&t.active&&t.status==='available'&&!checkin;
 hide(walkin,!canWalkin);if(canWalkin)setValue(walkin,'[data-nf-walkin-table]',id);
 var statusForm=detail.querySelector('[data-nf-status-form]');
 var canStatus=!!statusForm&&wrap.dataset.canStatus==='1'&&t&&t.active&&!checkin;
 hide(statusForm,!canStatus);
 if(canStatus){setValue(statusForm,'[data-nf-status-table]',id);var statusSelect=statusForm.querySelector('[data-nf-status-select]');statusSelect.value=(t.status==='blocked'||st==='blocked'||st==='occupied')?'available':'blocked';var submit=statusForm.querySelector('[data-nf-status-submit]');if(submit)submit.textContent=statusSelect.value==='available'?'เปิดโต๊ะพร้อมรับลูกค้า':'ปิดรับลูกค้าชั่วคราว';statusSelect.onchange=function(){if(submit)submit.textContent=this.value==='available'?'เปิดโต๊ะพร้อมรับลูกค้า':'ปิดรับลูกค้าชั่วคราว';};}
 var moveForm=detail.querySelector('[data-nf-move-form]');
 var canMove=!!moveForm&&wrap.dataset.canMove==='1'&&st==='occupied'&&checkin;
 hide(moveForm,!canMove);
 if(canMove){setValue(moveForm,'[data-nf-move-checkin]',checkin.id);setValue(moveForm,'[data-nf-move-table]',id);var moveSelect=moveForm.querySelector('[data-nf-move-select]');Array.prototype.forEach.call(moveSelect.options,function(o){o.hidden=o.value===id;});moveSelect.value='';}
 var checkout=detail.querySelector('[data-nf-checkout]');
 var canCheckout=!!checkout&&st==='occupied'&&checkin&&(wrap.dataset.canComplete==='1'||wrap.dataset.canCancel==='1');
 hide(checkout,!canCheckout);
 if(canCheckout){setValue(checkout,'[data-nf-complete-table]',id);setValue(checkout,'[data-nf-complete-checkin]',checkin.id);setValue(checkout,'[data-nf-cancel-table]',id);setValue(checkout,'[data-nf-cancel-checkin]',checkin.id);}
 var none=detail.querySelector('[data-nf-no-action]');
 var hasAction=canSeat||canWalkin||canStatus||canMove||canCheckout;
 hide(none,hasAction);
 if(none&&!hasAction){none.textContent=!t?'Hotspot นี้ยังไม่เชื่อมกับ Table Directory':(!t.active?'โต๊ะนี้ถูกปิดถาวรใน Table Directory กรุณาเปิดจากหน้าจัดการโต๊ะ':(st==='occupied'&&!checkin?'โต๊ะถูกระบุว่ามีลูกค้า แต่ไม่พบ Check-in ที่กำลังทำงาน ผู้มีสิทธิ์ tables.manage สามารถคืนโต๊ะเป็นว่างได้':'บัญชีนี้ไม่มี Permission สำหรับคำสั่งของสถานะนี้'));}
 detail.hidden=false;if(window.innerWidth<1101)detail.scrollIntoView({behavior:'smooth',block:'nearest'});
}
wrap.querySelectorAll('[data-nf-table]').forEach(function(el){el.addEventListener('click',function(){openTable(el);});});
var close=wrap.querySelector('[data-nf-close]');if(close)close.addEventListener('click',function(){detail.hidden=true;selected=null;wrap.querySelectorAll('[data-nf-table]').forEach(function(x){x.classList.remove('is-selected');});});
wrap.querySelectorAll('[data-nf-zone]').forEach(function(btn){btn.addEventListener('click',function(){var z=btn.dataset.nfZone||'';wrap.querySelectorAll('[data-nf-zone]').forEach(function(x){x.classList.toggle('active',x===btn);});wrap.querySelectorAll('.nf-item').forEach(function(x){x.classList.toggle('is-zone-hidden',!!z&&(x.dataset.zone||'')!==z&&x.classList.contains('type-table'));});});});
wrap.querySelectorAll('[data-confirm]').forEach(function(btn){btn.addEventListener('click',function(e){var message=btn.dataset.confirm||'ยืนยันดำเนินการ?';if(!window.confirm(message))e.preventDefault();});});
wrap.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(e){if(e.defaultPrevented)return;var button=form.querySelector('button[type="submit"],button:not([type])');if(button){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent='กำลังบันทึก...';}});});
var refresh=wrap.querySelector('[data-nf-refresh]');if(refresh)refresh.addEventListener('click',function(){refresh.disabled=true;refresh.textContent='กำลังรีเฟรช...';window.location.reload();});
var clock=wrap.querySelector('[data-nf-clock]');if(clock)setInterval(function(){var d=new Date();clock.textContent=d.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});},1000);
var selectedId=String(wrap.dataset.selectedTable||'');if(selectedId&&selectedId!=='0'){var initial=wrap.querySelector('[data-nf-table][data-table-id="'+selectedId.replace(/"/g,'')+'"]');if(initial)openTable(initial);}
})();
