<?php
require __DIR__.'/../app/dashboard-sales.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
$data=['pos_bill_batches'=>[['id'=>1,'status'=>'active','imported_at'=>'2026-10-01','total_sales'=>150],['id'=>2,'status'=>'void','imported_at'=>'2026-10-02']],'pos_bill_rows'=>[['batch_id'=>1,'active'=>1,'sale_date'=>'2026-10-01','net_sales'=>200],['batch_id'=>1,'active'=>1,'sale_date'=>'2026-10-01','net_sales'=>-50],['batch_id'=>2,'active'=>1,'sale_date'=>'2026-10-01','net_sales'=>999],['batch_id'=>1,'active'=>0,'net_sales'=>999]],'pos_import_batches'=>[['id'=>1,'status'=>'active','imported_at'=>'2026-10-02','report_type'=>'product_summary','total_sales'=>300]],'pos_sales_rows'=>[['batch_id'=>1,'active'=>1,'sale_date'=>'2026-10-02','item_name'=>'Menu A','net_sales'=>100,'qty'=>2],['batch_id'=>1,'active'=>1,'sale_date'=>'2026-10-02','item_name'=>'Menu A','net_sales'=>200,'qty'=>4]]];
$bill=dash_sales($data,'bills');check($bill['total']===150.0&&$bill['count']===2,'exclude void/inactive and preserve negative sales');
check($bill['average']===75.0&&$bill['series'][0]['value']===150.0,'bill average and daily grouping');
$menu=dash_sales($data,'menu');check($menu['total']===300.0&&$menu['qty']===6.0,'menu total remains separate from bills');
check($menu['summary']&&$menu['series']===[],'period summary is never a fabricated daily trend');
check(count($menu['items'])===1&&$menu['items'][0]['amount']===300.0,'rank aggregated menu sales');
check(dash_sales($data,'bills',99)['total']===null,'unknown batch does not silently show a different report');
check(dash_sales([],'bills')['total']===null,'empty data is not a fabricated zero');
$data['pos_bill_rows'][]=['batch_id'=>1,'active'=>1,'sale_date'=>'','net_sales'=>20];
$bill=dash_sales($data,'bills');check($bill['undated']===1&&$bill['total']===170.0&&$bill['difference']===20.0,'undated totals and import reconciliation warning');
echo "Dashboard sales tests passed.\n";
