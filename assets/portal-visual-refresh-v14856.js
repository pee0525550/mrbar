(function(){
 var clock=document.querySelector('[data-portal-clock]');
 if(!clock||!window.Intl)return;
 var dateFormat=new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{timeZone:'Asia/Bangkok',weekday:'short',day:'numeric',month:'short',year:'numeric'});
 var timeFormat=new Intl.DateTimeFormat('th-TH',{timeZone:'Asia/Bangkok',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
 function tick(){var now=new Date();clock.textContent=dateFormat.format(now)+' · '+timeFormat.format(now);clock.dateTime=now.toISOString();}
 tick();window.setInterval(tick,60000);
})();
