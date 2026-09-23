(function(){
 'use strict';
 var cards=[].slice.call(document.querySelectorAll('[data-portal-card]'));
 var search=document.querySelector('[data-portal-search]');
 var clear=document.querySelector('[data-portal-clear]');
 var filters=[].slice.call(document.querySelectorAll('[data-portal-filter]'));
 var count=document.querySelector('[data-portal-visible]');
 var status=document.querySelector('[data-portal-status]');
 var empty=document.querySelector('[data-portal-empty]');
 var active='all';
 if(!cards.length||!search)return;
 function update(){
  var query=search.value.trim().toLocaleLowerCase();
  var visible=0;
  cards.forEach(function(card){
   var matchesText=(card.getAttribute('data-search')||'').toLocaleLowerCase().indexOf(query)!==-1;
   var matchesFilter=active==='all'||(active==='open'&&card.getAttribute('data-open')==='1')||(active==='featured'&&card.getAttribute('data-featured')==='1');
   var show=matchesText&&matchesFilter;
   card.hidden=!show;
   if(show)visible++;
  });
  if(count)count.textContent=String(visible);
  if(status)status.textContent='แสดง '+visible+' จาก '+cards.length+' สาขา';
  if(empty)empty.hidden=visible!==0;
  if(clear)clear.hidden=search.value.length===0;
 }
 filters.forEach(function(button){button.addEventListener('click',function(){active=button.getAttribute('data-portal-filter')||'all';filters.forEach(function(item){item.setAttribute('aria-pressed',item===button?'true':'false')});update()})});
 search.addEventListener('input',update);
 if(clear)clear.addEventListener('click',function(){search.value='';search.focus();update()});
 var reset=document.querySelector('[data-portal-reset]');
 if(reset)reset.addEventListener('click',function(){active='all';search.value='';filters.forEach(function(button){var selected=button.getAttribute('data-portal-filter')==='all';button.setAttribute('aria-pressed',selected?'true':'false')});update();search.focus()});
 update();
})();
