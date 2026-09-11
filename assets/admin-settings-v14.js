(function(){
'use strict';
var tabs=[].slice.call(document.querySelectorAll('.settings-tab[data-target]'));
var panels=[].slice.call(document.querySelectorAll('.setting-section[id]'));
if(!tabs.length||!panels.length)return;
var saved=(document.body.getAttribute('data-saved-section')||'').trim();
function valid(id){return panels.some(function(p){return p.id===id;});}
function activate(id,push){if(!valid(id))id='shop';tabs.forEach(function(t){var on=t.getAttribute('data-target')===id;t.classList.toggle('active',on);t.setAttribute('aria-selected',on?'true':'false');});panels.forEach(function(p){p.classList.toggle('active',p.id===id);});if(push&&location.hash!=='#'+id){history.replaceState(null,'','#'+id);}var active=document.getElementById(id);if(active){setTimeout(function(){active.querySelectorAll('.leaflet-container').forEach(function(el){try{if(el._leaflet_id&&window.L){var map=Object.values(window).find(function(){return false;});}}catch(e){}});},80);}}
tabs.forEach(function(t){t.addEventListener('click',function(){activate(t.getAttribute('data-target'),true);});});
window.addEventListener('hashchange',function(){activate(location.hash.slice(1),false);});
panels.forEach(function(panel){var formEls=panel.querySelectorAll('input,select,textarea');formEls.forEach(function(el){el.addEventListener('change',function(){panel.classList.add('is-dirty');});el.addEventListener('input',function(){panel.classList.add('is-dirty');});});panel.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(e){var label=(form.getAttribute('data-confirm-delete')||'').trim();if(label){var ok=window.confirm('ยืนยันลบสาขา “'+label+'” ?\n\nPR ที่ผูกกับสาขานี้จะกลับเป็น AUTO และสาขาจะไม่ถูกใช้กับ GPS อีก แต่ประวัติ Attendance เดิมจะยังคงอยู่');if(!ok){e.preventDefault();return;}}var btn=form.querySelector('button[type="submit"],button:not([type])');if(btn){btn.dataset.oldText=btn.textContent;btn.textContent=label?'กำลังลบ…':'กำลังบันทึก…';btn.disabled=true;}});});});
var initial=saved&&valid(saved)?saved:(location.hash.slice(1)||'shop');activate(initial,false);
})();
/* v1.14.3: keep Branch/GPS editing focused — one branch card open at a time */
(function(){
'use strict';
var cards=[].slice.call(document.querySelectorAll('#branchgps .branch-manage-card'));
cards.forEach(function(card){
 card.addEventListener('toggle',function(){
  if(!card.open)return;
  cards.forEach(function(other){if(other!==card&&other.open)other.open=false;});
 });
});
})();
