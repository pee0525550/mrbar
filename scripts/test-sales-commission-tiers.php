<?php
require __DIR__.'/../app/pos-incentive.php';
function check_sales_tier($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}

$rule=['tiers'=>[
 ['store_minimum'=>0,'personal_minimum'=>500000,'minimum_units'=>20,'rate_per_unit'=>70],
 ['store_minimum'=>4500000,'personal_minimum'=>600000,'minimum_units'=>20,'rate_per_unit'=>80],
]];
$tiers=posi_sales_commission_tiers($rule);
check_sales_tier($tiers[0]['store_minimum']===4500000.0,'sorts highest store target first');

$data=[
 'pos_sales_rows'=>[
  ['active'=>1,'sale_date'=>'2026-08-10','item_group'=>'Sales','item_name'=>'Sales A','employee_id'=>7,'qty'=>30,'net_sales'=>1000],
  ['active'=>1,'sale_date'=>'2026-08-10','item_group'=>'Food','item_name'=>'Other','employee_id'=>0,'qty'=>1,'net_sales'=>4599000],
 ],
 'pos_r4_employee_inputs'=>[
  ['period_from'=>'2026-08-01','period_to'=>'2026-08-31','employee_id'=>7,'personal_sales'=>650000],
 ],
];
$result=posi_sales_commission_results($data,'2026-08',$rule,'2026-08-01','2026-08-31')[0];
check_sales_tier($result['store_sales']===4600000.0,'uses total POS store sales for target');
check_sales_tier($result['personal_sales']===650000.0,'uses employee personal sales input');
check_sales_tier($result['rate_per_unit']===80.0&&$result['commission']===2400.0,'uses highest fully matched tier');

$data['pos_sales_rows'][1]['net_sales']=3999000;
$result=posi_sales_commission_results($data,'2026-08',$rule,'2026-08-01','2026-08-31')[0];
check_sales_tier($result['rate_per_unit']===70.0&&$result['commission']===2100.0,'falls through to lower tier when store misses target');

$data['pos_r4_employee_inputs'][0]['personal_sales']=400000;
$result=posi_sales_commission_results($data,'2026-08',$rule,'2026-08-01','2026-08-31')[0];
check_sales_tier(!$result['eligible']&&$result['commission']===0.0,'pays zero when no tier is fully matched');

$unmapped=[
 'pos_sales_rows'=>[
  ['active'=>1,'sale_date'=>'2026-08-10','item_group'=>'Sales','item_name'=>'Sales B','employee_id'=>0,'qty'=>25,'net_sales'=>1000],
  ['active'=>1,'sale_date'=>'2026-08-10','item_group'=>'Food','item_name'=>'Other','employee_id'=>0,'qty'=>1,'net_sales'=>4599000],
 ],
];
$manualRule=$rule;$manualRule['personal_sales_by_key']=['name:'.posi_norm('Sales B')=>620000];
$result=posi_sales_commission_results($unmapped,'2026-08',$manualRule,'2026-08-01','2026-08-31')[0];
check_sales_tier($result['personal_sales']===620000.0,'uses manual personal sales for an unmapped POS menu name');
check_sales_tier($result['rate_per_unit']===80.0&&$result['commission']===2000.0,'calculates an unmapped Sales menu after manual sales entry');

echo "Sales commission tier regression passed.\n";
