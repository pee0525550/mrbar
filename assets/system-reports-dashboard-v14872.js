(function(){
  'use strict';
  var body=document.getElementById('srRows');
  var button=document.getElementById('srLoadMore');
  var state=document.getElementById('sr-load-state');
  var count=document.querySelector('[data-row-count]');
  if(!body||!button)return;
  var busy=false;

  function cellText(value){
    if(value===null||value===undefined||value==='')return '—';
    return String(value);
  }

  function appendRow(row){
    var tr=document.createElement('tr');
    if(Number(row.risk)>0)tr.className='is-risk';
    var values=[
      {text:row.module,badge:'sr-module'},
      {text:row.type},
      {text:row.date||'-'},
      {text:row.title,bold:true},
      {text:row.status,badge:'sr-status'},
      {text:row.amount===null?'-':Number(row.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),className:'sr-money'},
      {text:Number(row.count||0).toLocaleString()+' '+(row.count_unit||'รายการ')},
      {text:row.ref},
      {text:row.detail}
    ];
    values.forEach(function(item){
      var td=document.createElement('td');
      if(item.className)td.className=item.className;
      var node=document.createElement(item.badge?'span':item.bold?'b':'span');
      if(item.badge)node.className=item.badge;
      node.textContent=cellText(item.text);
      td.appendChild(node);
      tr.appendChild(td);
    });
    body.appendChild(tr);
  }

  button.addEventListener('click',function(){
    if(busy)return;
    busy=true;
    button.disabled=true;
    button.textContent='กำลังโหลด...';
    if(state)state.textContent='กำลังดึงรายการถัดไป';
    var params=new URLSearchParams(location.search);
    params.set('ajax','rows');
    params.set('offset',body.dataset.offset||'0');
    fetch(location.pathname+'?'+params.toString(),{
      credentials:'same-origin',
      headers:{'Accept':'application/json'},
      mrbarLoadingLabel:'กำลังโหลดรายงาน...'
    }).then(function(response){
      if(!response.ok)throw new Error('โหลดข้อมูลไม่สำเร็จ ('+response.status+')');
      return response.json();
    }).then(function(data){
      if(!Array.isArray(data.rows))throw new Error('รูปแบบข้อมูลไม่ถูกต้อง');
      if(!data.rows.length&&Number(data.offset)<Number(data.total))throw new Error('ไม่พบข้อมูลชุดถัดไป');
      data.rows.forEach(appendRow);
      body.dataset.offset=String(data.offset+data.rows.length);
      if(count)count.textContent=Number(body.dataset.offset).toLocaleString();
      button.hidden=!data.has_more;
      if(state)state.textContent=data.has_more?'แสดงแล้ว '+Number(body.dataset.offset).toLocaleString()+' จาก '+Number(data.total).toLocaleString()+' รายการ':'แสดงครบ '+Number(data.total).toLocaleString()+' รายการ';
    }).catch(function(error){
      if(state)state.textContent=error.message||'เกิดข้อผิดพลาด กรุณาลองอีกครั้ง';
      button.textContent='ลองโหลดอีกครั้ง';
    }).finally(function(){
      busy=false;
      button.disabled=false;
      if(!button.hidden&&button.textContent==='กำลังโหลด...')button.textContent='โหลดเพิ่มอีก '+(body.dataset.pageSize||'20')+' รายการ';
    });
  });
})();
