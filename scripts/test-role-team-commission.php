<?php
require __DIR__.'/../app/pos-incentive.php';
function role_check($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$data=['employees'=>[
 ['id'=>1,'code'=>'PR01','name'=>'PR A','position'=>'pr','active'=>1,'pos_incentive'=>['enabled'=>1,'aliases'=>['ฟ้า']]],
 ['id'=>2,'code'=>'S01','name'=>'Sale A','position'=>'sales','active'=>1,'pos_incentive'=>['enabled'=>1,'aliases'=>['เซลเอ']]],
 ['id'=>3,'code'=>'S02','name'=>'Sale B','position'=>'sales','active'=>1,'pos_incentive'=>['enabled'=>1,'aliases'=>['เซลบี']]],
],'pos_sales_rows'=>[
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D ฟ้า','item_group'=>'PR','item_category'=>'PR D ทีมอะไรก็ได้','employee_id'=>1,'drink_code'=>'D','qty'=>10,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'M ฟ้า','item_group'=>'PR','item_category'=>'PR M ทีมอะไรก็ได้','employee_id'=>1,'drink_code'=>'M','qty'=>5,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D เซลเอ','item_group'=>'Sales','item_category'=>'Sales D ทีมอะไรก็ได้','employee_id'=>2,'drink_code'=>'D','qty'=>4,'incentive_candidate'=>1],
 ['active'=>1,'sale_date'=>'2026-08-10','item_name'=>'D เซลบี','item_group'=>'Sales','item_category'=>'Sales D ร้านอื่น','employee_id'=>3,'drink_code'=>'D','qty'=>2,'incentive_candidate'=>1],
]];
$rule=['pr_rate_d'=>5,'pr_rate_m'=>4,'sales_own_rate_d'=>6,'sales_own_rate_m'=>5,'sales_team_rate_d'=>2,'sales_team_rate_m'=>1,'team_sales_map'=>['ทีมอะไรก็ได้'=>2]];
$result=posi_role_commission_results($data,$rule,'2026-08-01','2026-08-31');$byId=[];foreach($result['rows'] as $row)$byId[$row['employee_id']]=$row;
role_check($byId[1]['own_commission']===70.0,'PR receives own D/M commission');
role_check($byId[1]['team_commission']===0.0,'PR receives no team commission');
role_check($byId[2]['own_commission']===24.0,'Sales receives own commission');
role_check($byId[2]['team_pr_d']===10.0&&$byId[2]['team_pr_m']===5.0,'selected Sales receives mapped PR team units');
role_check($byId[2]['team_commission']===25.0&&$byId[2]['total_commission']===49.0,'Sales total combines own and team commission');
role_check($byId[3]['team_commission']===0.0,'unmapped Sales does not receive another team payout');
role_check(posi_category_team_code('PR D ทีมอะไรก็ได้')==='ทีมอะไรก็ได้','discovers arbitrary Thai team name');
role_check(posi_category_team_code('Sales M Custom-Code 99')==='CUSTOM-CODE 99','discovers arbitrary shop-specific code');
echo "Dynamic team commission regression passed.\n";
