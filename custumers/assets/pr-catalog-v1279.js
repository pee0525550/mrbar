(function(){
  const buttons=[...document.querySelectorAll('[data-pr-filter]')];
  const cards=[...document.querySelectorAll('.pr-catalog-card')];
  const empty=document.getElementById('prCatalogEmpty');
  if(!buttons.length||!cards.length)return;
  function apply(filter){
    let shown=0;
    cards.forEach(card=>{
      const present=card.dataset.prPresent==='1';
      const status=card.dataset.prStatus||'offline';
      let show=true;
      if(filter==='ready')show=present;
      else if(filter==='online')show=status==='online';
      else if(filter==='offline')show=!present;
      card.hidden=!show;if(show)shown++;
    });
    if(empty)empty.hidden=shown!==0;
    buttons.forEach(btn=>btn.classList.toggle('active',btn.dataset.prFilter===filter));
  }
  buttons.forEach(btn=>btn.addEventListener('click',()=>apply(btn.dataset.prFilter||'all')));
})();
