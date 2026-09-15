<?php
require __DIR__.'/../app/pos-r4.php';
function check_r4($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$rule=posi_r4_rule_defaults('2026-04','2026-04-01','2026-04-30');
check_r4(posi_r4_tier_rate($rule,650000)===100.0,'600k+ uses normal 100 rate');
check_r4(posi_r4_tier_rate($rule,550000)===90.0,'500k-599k uses 90 rate');
check_r4(posi_r4_tier_rate($rule,450000)===80.0,'400k-499k uses 80 rate');
check_r4(posi_r4_tier_rate($rule,350000)===70.0,'300k-399k uses 70 rate');
check_r4(posi_r4_tier_rate($rule,250000)===60.0,'200k-299k uses 60 rate');
check_r4(posi_r4_tier_rate($rule,150000)===50.0,'under 200k uses 50 rate');
$rule['online_exception_enabled']=1;$rule['online_required_days']=0;$rule['online_required_tables']=4;
$input=['period_from'=>'2026-05-01','period_to'=>'2026-05-31','online_required_days'=>0,'online_post_days'=>31,'online_tables'=>4,'exception_approved'=>1];
check_r4(posi_r4_online_exception($rule,$input),'online exception requires every day, four tables and approval');
$input['online_post_days']=30;check_r4(!posi_r4_online_exception($rule,$input),'online exception rejects missing daily post');
$input['online_post_days']=31;$input['exception_approved']=0;check_r4(!posi_r4_online_exception($rule,$input),'online exception requires manager approval');
function posi_active_rows_period(array $d,string $from,string $to): array{return array_values(array_filter($GLOBALS['r4_test_rows']??[],fn($row)=>(string)$row['sale_date']>=$from&&(string)$row['sale_date']<=$to));}
function posi_sales_units(array $d,string $month,string $from='',string $to=''): array{return $GLOBALS['r4_test_sales_units']??[];}
function posi_month_bounds(string $month): array{return [$month.'-01',date('Y-m-t',strtotime($month.'-01'))];}
$GLOBALS['r4_test_rows']=[['sale_date'=>'2026-04-10','net_sales'=>2000000]];
$GLOBALS['r4_test_sales_units']=[['employee_id'=>7,'sales_name'=>'Sales A','units'=>10,'source_rows'=>2]];
$rule['enabled']=1;$rule['store_target']=2400000;$rule['sales_normal_rate']=100;$rule['sales_rate_mode']='amount_per_unit';$rule['hold_enabled']=1;$rule['release_month']='2026-05';$rule['release_store_target']=2800000;$rule['release_personal_target']=600000;
$data=['pos_r4_employee_inputs'=>[['period_from'=>'2026-04-01','period_to'=>'2026-04-30','employee_id'=>7,'personal_sales'=>550000]],'pos_sales_rows'=>[]];
$result=posi_r4_sales_results($data,'2026-04',$rule,'2026-04-01','2026-04-30')[0];
check_r4($result['applied_rate']===90.0&&$result['commission']===900.0,'R4 applies personal tier to payable commission');
check_r4($result['held_amount']===100.0,'R4 records difference as Hold');
$rule['sales_rate_mode']='percent_normal';$result=posi_r4_sales_results($data,'2026-04',$rule,'2026-04-01','2026-04-30')[0];
check_r4($result['commission']===900.0,'percentage mode applies 90 percent of normal commission');
echo "R4 commission rules regression passed.\n";
