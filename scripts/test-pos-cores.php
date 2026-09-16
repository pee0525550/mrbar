<?php
require __DIR__.'/test-role-team-commission.php';
require_once __DIR__.'/../app/pos-cores.php';
function next_id(array $rows):int{return count($rows)+1;}
$data['pos_import_batches']=[['id'=>1,'status'=>'active','period_start'=>'2026-08-01','period_end'=>'2026-08-31']];
foreach($data['pos_sales_rows'] as &$r)$r['batch_id']=1;unset($r);
$drinks=pc_calculate($data,'drinks',1,$rule);
$comm=pc_calculate($data,'commission',1,$rule);
role_check($drinks['total']===94.0,'drink core excludes team commission');
role_check($comm['total']===32.0,'commission core excludes personal drinks');
role_check(count($comm['rows'])===1,'commission only includes team Sales recipients');
role_check($drinks['rule']['sales_team_rate_d']===0.0,'cross-core posted team rate ignored');
role_check($comm['rule']['sales_own_rate_d']===0.0,'cross-core posted own rate ignored');
// Fully map fixture for final save.
foreach($data['pos_sales_rows'] as &$r)if(strpos($r['item_category'],'VIP')!==false)$r['employee_id']=1;unset($r);
$saved=pc_save($data,'drinks',1,$rule,7);
$saved=pc_save($saved,'commission',1,$rule,7);
role_check(count($saved['drink_payout_rounds'])===1&&count($saved['commission_payout_rounds'])===1,'same report can finalize once in each independent core');
try{pc_save($saved,'drinks',1,$rule,7);throw new LogicException('duplicate allowed');}catch(RuntimeException $e){echo "PASS: duplicate core payout blocked\n";}
role_check(!isset($saved['role_commission_rules'])&&!isset($saved['pos_incentive_closings']),'legacy settings and finalizations untouched');
