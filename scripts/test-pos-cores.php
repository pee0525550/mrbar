<?php
require __DIR__.'/test-role-team-commission.php';
require_once __DIR__.'/../app/pos-cores.php';
function next_id(array $rows):int{return count($rows)+1;}
$data['pos_import_batches']=[['id'=>1,'status'=>'active','period_start'=>'2026-08-01','period_end'=>'2026-08-31']];
foreach($data['pos_sales_rows'] as &$r)$r['batch_id']=1;unset($r);
$drinks=pc_calculate($data,'drinks',1,$rule);
$data['pos_bill_batches']=[['id'=>1,'status'=>'active','period_start'=>'2026-08-01','period_end'=>'2026-08-31']];
$data['sales_table_sessions']=[['id'=>1,'sales_id'=>2,'sales_label'=>'Sale A','receipts'=>['INV-001'],'match_status'=>'matched','matched_bill_batch_id'=>1,'matched_net_sales'=>252000]];
$comm=pc_calculate($data,'commission',1,['tier_limit'=>[200000,300000,400000,500000,600000],'tier_multiplier'=>[50,60,70,80,90]]);
role_check($drinks['total']===94.0,'drink core excludes team commission');
role_check($comm['total']===60.0,'commission core stores Sales drink multiplier from bill sales');
role_check(count($comm['rows'])===1,'commission includes matched Sales recipients only');
role_check($drinks['rule']['sales_team_rate_d']===0.0,'cross-core posted team rate ignored');
role_check(!isset($comm['rule']['sales_own_rate_d']),'legacy drink rate is excluded from bill commission');
// Fully map fixture for final save.
foreach($data['pos_sales_rows'] as &$r)if(strpos($r['item_category'],'VIP')!==false)$r['employee_id']=1;unset($r);
$saved=pc_save($data,'drinks',1,$rule,7);
$saved=pc_save($saved,'commission',1,$rule,7);
role_check(count($saved['drink_payout_rounds'])===1&&count($saved['commission_payout_rounds'])===1,'same report can finalize once in each independent core');
try{pc_save($saved,'drinks',1,$rule,7);throw new LogicException('duplicate allowed');}catch(RuntimeException $e){echo "PASS: duplicate core payout blocked\n";}
role_check(!isset($saved['role_commission_rules'])&&!isset($saved['pos_incentive_closings']),'legacy settings and finalizations untouched');
require_once __DIR__.'/../app/pos-reports.php';
$summary=posr_employee_summary(posr_rounds($saved),['core'=>'all','status'=>'saved','sort'=>'saved_at','dir'=>'desc']);
role_check(count($summary)===2,'report aggregates employees across separated cores');
$filtered=posr_filter_sort(posr_rounds($saved),['core'=>'commission','status'=>'saved','sort'=>'total','dir'=>'desc']);
role_check(count($filtered)===1&&$filtered[0]['core']==='commission','report filters independent core');
try{posr_clear_batch($saved,1,7,'test');throw new LogicException('clear with payouts allowed');}catch(RuntimeException $e){echo "PASS: report clear blocked while payout rounds active\n";}
$voided=posr_void_round($saved,'drinks',1,7,'test reset');
$voided=posr_void_round($voided,'commission',1,7,'test reset');
$cleared=posr_clear_batch($voided,1,7,'test reset');
role_check(($cleared['pos_import_batches'][0]['status']??'')==='void','report clear soft-voids processed source');
role_check(count(array_filter($cleared['audit'],fn($a)=>($a['action']??'')==='pos_report_cleared'))===1,'report clear writes audit trail');
$billData=$data;$billData['sales_table_sessions']=[['id'=>1,'receipts'=>['INV-001','INV-002'],'match_status'=>'pending','matched_net_sales'=>null]];
$billEntry=['sha256'=>'bill-sha','original_name'=>'bills.xlsx','period_from'=>'2026-08-01','period_to'=>'2026-08-31'];
$billParsed=['headers'=>['เลขบิล','วันที่','โต๊ะ','ยอดขายสุทธิ'],'rows'=>[['INV-001','2026-08-10 21:00','T01','1000'],['INV-002','2026-08-10 22:00','T01','500']]];
$billMap=posi_guess_columns($billParsed['headers']);[$billData,$billBatch]=posi_commit_bill_import($billData,$billEntry,$billParsed,$billMap,7);
role_check($billBatch['bill_count']===2&&$billBatch['total_sales']===1500.0,'bill report stores receipt totals separately from menu sales');
role_check($billData['sales_table_sessions'][0]['match_status']==='matched'&&$billData['sales_table_sessions'][0]['matched_net_sales']===1500.0,'bill report matches Sales table receipts');
posi_void_bill_batch($billData,(int)$billBatch['id'],7);
role_check($billData['sales_table_sessions'][0]['match_status']==='pending'&&$billData['sales_table_sessions'][0]['matched_net_sales']===null,'clearing bill report reverses receipt match');
