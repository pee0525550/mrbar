<?php
require __DIR__.'/../app/pos-incentive.php';
function role_check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$data=['meta'=>['active_branch_id'=>2],'_branch_context'=>['id'=>2],'branches'=>[['id'=>2,'name'=>'Priest Exclusive Club & KTV','slug'=>'Priest','code'=>'BR02','active'=>1]],'employees'=>[
 ['id'=>1,'code'=>'PR01','name'=>'PR A','position'=>'pr','active'=>1,'pos_incentive'=>['enabled'=>1,'aliases'=>['ฟ้า']]],
 ['id'=>2,'code'=>'S01','name'=>'Sale A','position'=>'sales','active'=>1,'pos_incentive'=>['enabled'=>1,'aliases'=>['เซลเอ']]],
],'pos_sales_rows'=>[
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D ฟ้า','item_group'=>'PR','item_category'=>'PR D Priest','employee_id'=>1,'drink_code'=>'D','qty'=>10,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'M ฟ้า','item_group'=>'PR','item_category'=>'PR M Priest','employee_id'=>1,'drink_code'=>'M','qty'=>5,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D เซลเอ','item_group'=>'Sales','item_category'=>'Sales D Priest','employee_id'=>2,'drink_code'=>'D','qty'=>4,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D คนร้านอื่น','item_group'=>'PR','item_category'=>'PR D MW','employee_id'=>null,'drink_code'=>'D','qty'=>99,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'M คนร้านอื่น','item_group'=>'PR','item_category'=>'PR M R4','employee_id'=>null,'drink_code'=>'M','qty'=>88,'incentive_candidate'=>1],
]];
$rule=['pr_rate_d'=>5,'pr_rate_m'=>4,'sales_own_rate_d'=>6,'sales_own_rate_m'=>5,'sales_team_rate_d'=>2,'sales_team_rate_m'=>1,'team_sales_map'=>['D PRIEST'=>2,'M PRIEST'=>2]];
$result=posi_role_commission_results($data,$rule,'2026-08-01','2026-08-31');$byId=[];foreach($result['rows'] as $row)$byId[$row['employee_id']]=$row;
role_check(isset($result['team_pr_units']['D PRIEST'])&&isset($result['team_pr_units']['M PRIEST']),'keeps D and M POS category codes as separate teams');
role_check(!isset($result['team_pr_units']['D MW'])&&!isset($result['team_pr_units']['M R4']),'filters teams from other shops using current branch');
role_check($byId[1]['own_commission']===70.0,'PR receives own D/M commission');
role_check($byId[2]['own_commission']===24.0,'Sales receives own commission');
role_check($byId[2]['team_pr_d']===10.0&&$byId[2]['team_pr_m']===5.0,'Sales receives both separately mapped Priest team codes');
role_check($byId[2]['team_commission']===25.0&&$byId[2]['total_commission']===49.0,'Sales total combines own and team commission');
role_check(posi_category_team_code('PR D Priest')==='D PRIEST','shows D as part of the real POS category code');
role_check(posi_category_team_code('PR M Priest')==='M PRIEST','shows M as a separate real POS category code');
echo "Branch-scoped POS team regression passed.\n";
