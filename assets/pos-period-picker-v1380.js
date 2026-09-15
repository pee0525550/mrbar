(()=>{
'use strict';
const root=document.querySelector('[data-period-picker]');if(!root)return;
const form=root.querySelector('.posi-period-form'),mode=form.querySelector('[data-period-mode]'),from=form.querySelector('[data-period-from]'),to=form.querySelector('[data-period-to]');
const pad=n=>String(n).padStart(2,'0'),iso=d=>d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()),parse=v=>{const [y,m,d]=v.split('-').map(Number);return new Date(y,m-1,d);};
const monthRange=anchor=>[new Date(anchor.getFullYear(),anchor.getMonth(),1),new Date(anchor.getFullYear(),anchor.getMonth()+1,0)];
const weekRange=anchor=>{const start=new Date(anchor);start.setDate(start.getDate()-((start.getDay()+6)%7));const end=new Date(start);end.setDate(end.getDate()+6);return[start,end];};
const cycleRange=anchor=>{let end=new Date(anchor.getFullYear(),anchor.getMonth(),22);if(anchor.getDate()>22)end=new Date(anchor.getFullYear(),anchor.getMonth()+1,22);const start=new Date(end.getFullYear(),end.getMonth()-1,22);return[start,end];};
function setRange(kind,partial=false){const today=new Date(),anchor=to.value?parse(to.value):today;let range;if(kind==='week')range=weekRange(anchor);else if(kind==='cycle')range=cycleRange(anchor);else range=monthRange(anchor);from.value=iso(range[0]);to.value=iso(partial&&today>=range[0]&&today<=range[1]?today:range[1]);mode.value=kind==='today'?'custom':kind;}
root.querySelectorAll('[data-period-shortcut]').forEach(button=>button.addEventListener('click',()=>{const kind=button.dataset.periodShortcut;if(kind==='today'){const selected=mode.value==='week'?'week':mode.value==='cycle'?'cycle':'month';setRange(selected,true);mode.value='custom';}else setRange(kind);form.requestSubmit();}));
mode.addEventListener('change',()=>{if(mode.value!=='custom')setRange(mode.value);});
})();