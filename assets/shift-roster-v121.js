
(function(){
  'use strict';
  function qs(s,c){return (c||document).querySelector(s);}
  function qsa(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s));}

  var picker=qs('#rosterEmployeePicker');
  var search=qs('#rosterEmployeeSearch');
  var countEl=qs('#selectedEmployeeCount');

  function checkboxes(){return picker?qsa('input[type="checkbox"][name="employee_ids[]"]',picker):[];}
  function updateCount(){
    if(!countEl)return;
    var n=checkboxes().filter(function(x){return x.checked;}).length;
    countEl.textContent=n+' คนถูกเลือก';
  }
  function visibleOptions(){return picker?qsa('.employee-option:not(.is-hidden)',picker):[];}
  function selectWhere(fn){
    visibleOptions().forEach(function(row){
      var cb=qs('input[type="checkbox"]',row);if(!cb)return;
      cb.checked=!!fn(row);
    });updateCount();
  }
  qsa('[data-pick]').forEach(function(btn){
    btn.addEventListener('click',function(){
      var v=btn.getAttribute('data-pick');
      selectWhere(function(row){return v==='all'||row.getAttribute('data-team')===v;});
    });
  });
  qsa('[data-pick-position]').forEach(function(btn){
    btn.addEventListener('click',function(){
      var v=btn.getAttribute('data-pick-position');
      selectWhere(function(row){return row.getAttribute('data-position')===v;});
    });
  });
  var clear=qs('[data-clear]');if(clear)clear.addEventListener('click',function(){checkboxes().forEach(function(cb){cb.checked=false;});updateCount();});
  if(picker)picker.addEventListener('change',updateCount);
  if(search)search.addEventListener('input',function(){
    var q=(search.value||'').trim().toLowerCase();
    qsa('.employee-option',picker).forEach(function(row){
      var hay=(row.getAttribute('data-search')||'').toLowerCase();
      row.classList.toggle('is-hidden',q!==''&&hay.indexOf(q)===-1);
    });
  });
  updateCount();

  var templateForm=qs('#templateForm');
  var reset=qs('#templateReset');
  function clearTemplateForm(){
    if(!templateForm)return;
    templateForm.reset();
    var id=qs('#templateEditId');if(id)id.value='0';
    var start=qs('#templateStart'),end=qs('#templateEnd');
    if(start&&!start.value)start.value='18:00';
    if(end&&!end.value)end.value='02:00';
    templateForm.scrollIntoView({behavior:'smooth',block:'start'});
  }
  if(reset)reset.addEventListener('click',clearTemplateForm);
  qsa('[data-edit-template]').forEach(function(btn){
    btn.addEventListener('click',function(){
      if(!templateForm)return;
      var item=btn.closest('.template-item');if(!item)return;
      var data={};try{data=JSON.parse(item.getAttribute('data-template')||'{}');}catch(e){return;}
      qs('#templateEditId').value=data.id||0;
      qs('#templateName').value=data.name||'';
      qs('#templateTeam').value=data.team||'flex';
      qs('#templateStart').value=data.start||'18:00';
      qs('#templateEnd').value=data.end||'02:00';
      qs('#templateType').value=data.type||'regular';
      qs('#templateBranch').value=String(data.branch_id||0);
      qs('#templateNote').value=data.note||'';
      var days=(data.days||[]).map(function(x){return Number(x);});
      qsa('input[name="days[]"]',templateForm).forEach(function(cb){cb.checked=days.indexOf(Number(cb.value))!==-1;});
      templateForm.scrollIntoView({behavior:'smooth',block:'start'});
      qs('#templateName').focus();
    });
  });

  var preview=qs('#previewResults');
  if(preview){window.setTimeout(function(){preview.scrollIntoView({behavior:'smooth',block:'start'});},120);}

  var builder=qs('#rosterBuilder');
  if(builder)builder.addEventListener('submit',function(ev){
    if(checkboxes().filter(function(cb){return cb.checked;}).length===0){
      ev.preventDefault();window.alert('กรุณาเลือกพนักงานอย่างน้อย 1 คน');
    }
  });
})();
