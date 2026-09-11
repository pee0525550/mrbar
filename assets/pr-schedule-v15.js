(function(){
  var n=document.getElementById('newShiftBtn'),card=document.getElementById('newShiftCard');if(n&&card)n.addEventListener('click',function(){card.hidden=!card.hidden;n.textContent=card.hidden?'＋ เพิ่มกะ':'ปิดฟอร์ม';});
  document.addEventListener('click',function(e){var b=e.target.closest('[data-toggle-shift]');if(!b)return;var c=b.closest('.shift-card');var d=c&&c.querySelector('.shift-detail');if(!d)return;d.hidden=!d.hidden;b.textContent=d.hidden?'แก้ไข ▾':'ปิด ▴';});
  var m=document.getElementById('menuBtn'),s=document.getElementById('sidebar');if(m&&s)m.addEventListener('click',function(){s.classList.toggle('open');});
})();
