(()=>{
'use strict';
const fmt=(value,digits=2)=>new Intl.NumberFormat('th-TH',{minimumFractionDigits:digits,maximumFractionDigits:digits}).format(value);
const norm=(value)=>String(value||'').trim().toLocaleLowerCase('th');
const number=(value)=>{const cleaned=String(value||'').replace(/,/g,'').replace(/[^0-9.\-]/g,'');return Number(cleaned)||0;};
document.querySelectorAll('[data-posi-product-sort]').forEach(root=>{
 const wrap=root.nextElementSibling;
 const table=wrap?.querySelector('[data-product-table]');
 if(!table)return;
 const form=root.closest('.posi-import-card')?.querySelector('.posi-map-form')||null;
 const rows=Array.from(table.tBodies[0]?.rows||[]);
 const valueSelect=root.querySelector('[data-sort-value]');
 const searchInput=root.querySelector('[data-sort-search]');
 const status=root.querySelector('[data-sort-status]');
 const axisButtons=Array.from(root.querySelectorAll('[data-sort-axis]'));
 let axis='group';
 const mapped=(name)=>{
   const el=form?.querySelector('[name="map_'+name+'"]');
   if(el)return Number(el.value);
   return Number(table.dataset[name+'Index']??-1);
 };
 const cell=(row,index)=>index>=0?(row.cells[index]?.textContent||'').trim():'';
 const usableRows=()=>rows.filter(row=>norm(cell(row,0))!=='total'&&Array.from(row.cells).some(td=>td.textContent.trim()!==''));
 const apply=()=>{
   const axisIndex=mapped(axis),qtyIndex=mapped('qty'),salesIndex=mapped('sales');
   const selected=norm(valueSelect.value),query=norm(searchInput.value);
   let shown=0,qty=0,sales=0;
   usableRows().forEach(row=>{
     const visible=(!selected||(axisIndex>=0&&norm(cell(row,axisIndex))===selected))&&(!query||norm(row.textContent).includes(query));
     row.hidden=!visible;
     if(visible){shown++;qty+=number(cell(row,qtyIndex));sales+=number(cell(row,salesIndex));}
   });
   root.querySelector('[data-sort-rows]').textContent=fmt(shown,0);
   root.querySelector('[data-sort-qty]').textContent=fmt(qty,2).replace(/\.00$/,'');
   root.querySelector('[data-sort-sales]').textContent=fmt(sales);
   root.querySelector('[data-sort-average]').textContent=fmt(shown?sales/shown:0);
   status.textContent='แสดง '+fmt(shown,0)+' รายการ · '+(axis==='group'?'กลุ่มสินค้า':'หมวดหมู่สินค้า')+': '+(valueSelect.value||'ทั้งหมด');
   const salesLink=root.querySelector('[data-sales-commission-link]');if(salesLink)salesLink.hidden=true;
 };
 const rebuild=()=>{
   const index=mapped(axis),current=valueSelect.value;
   const values=[...new Set(usableRows().map(row=>cell(row,index)).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'th',{numeric:true}));
   valueSelect.innerHTML='<option value="">ทั้งหมด</option>';
   values.forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=value;valueSelect.appendChild(option);});
   if(values.includes(current))valueSelect.value=current;
   if(index<0)status.textContent='ยังไม่ได้ Mapping คอลัมน์ '+(axis==='group'?'กลุ่มสินค้า':'หมวดหมู่สินค้า');
   apply();
 };
 axisButtons.forEach(button=>button.addEventListener('click',()=>{axis=button.dataset.sortAxis;axisButtons.forEach(item=>item.classList.toggle('active',item===button));rebuild();}));
 valueSelect.addEventListener('change',apply);
 searchInput.addEventListener('input',apply);
 root.querySelector('[data-sort-calculate]').addEventListener('click',()=>{apply();const salesLink=root.querySelector('[data-sales-commission-link]');if(salesLink&&axis==='group'&&norm(valueSelect.value)==='sales')window.location.href=salesLink.href;});
 ['group','category','qty','sales'].forEach(name=>form?.querySelector('[name="map_'+name+'"]')?.addEventListener('change',()=>name===axis?rebuild():apply()));
 rebuild();
});
const copyButton=document.querySelector('[data-copy-sales-table]');
copyButton?.addEventListener('click',async()=>{
 const table=document.querySelector('[data-sales-payout-table]');if(!table)return;
 const text=Array.from(table.rows).map(row=>Array.from(row.cells).map(cell=>cell.textContent.trim()).join('\t')).join('\n');
 const feedback=document.querySelector('[data-copy-feedback]');
 try{await navigator.clipboard.writeText(text);if(feedback)feedback.textContent='คัดลอกตารางแล้ว · สามารถวางใน Excel, Google Sheets หรือแชตได้ทันที';}
 catch(error){const area=document.createElement('textarea');area.value=text;document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();if(feedback)feedback.textContent='คัดลอกตารางแล้ว';}
});
})();