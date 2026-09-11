(function(){
  'use strict';
  var sidebar=document.getElementById('sidebar');
  if(!sidebar)return;
  var groups=Array.prototype.slice.call(sidebar.querySelectorAll('.nav-group'));
  if(!groups.length)return;
  var key='mrbar_admin_nav_open_group';

  function store(value){
    try{
      if(value===null){localStorage.removeItem(key);}else{localStorage.setItem(key,String(value));}
    }catch(e){}
  }

  function setOpen(group,open){
    if(!group)return;
    group.classList.toggle('is-open',!!open);
    var button=group.querySelector('.nav-group-title');
    var items=group.querySelector('.nav-group-items');
    if(button)button.setAttribute('aria-expanded',open?'true':'false');
    if(items)items.setAttribute('aria-hidden',open?'false':'true');
  }

  function openOnly(target,persist){
    groups.forEach(function(group){setOpen(group,group===target);});
    if(persist&&target)store(target.getAttribute('data-nav-group'));
  }

  var active=null;
  groups.forEach(function(group){if(group.classList.contains('has-active'))active=group;});
  if(active){
    openOnly(active,false);
  }else{
    var saved=null;
    try{saved=localStorage.getItem(key);}catch(e){}
    if(saved!==null){
      groups.forEach(function(group){
        if(group.getAttribute('data-nav-group')===saved)active=group;
      });
      if(active)openOnly(active,false);
    }
  }

  groups.forEach(function(group){
    var button=group.querySelector('.nav-group-title');
    if(!button)return;
    button.addEventListener('click',function(){
      if(group.classList.contains('is-open')){
        setOpen(group,false);
        store(null);
      }else{
        openOnly(group,true);
      }
    });
  });
})();
