<?php
/**
 * R4 commission policy engine.
 * All amounts are numeric THB. Rules are stored per branch and exact calculation period.
 */
function posi_r4_rule_defaults(string $month='', string $from='', string $to=''): array {
    return [
        'enabled'=>0,'month'=>$month,'period_from'=>$from,'period_to'=>$to,
        'store_target'=>2400000.0,'waive_reduction'=>0,
        'sales_normal_rate'=>100.0,'sales_rate_mode'=>'amount_per_unit',
        'sales_tiers'=>[
            ['min_sales'=>600000.0,'rate'=>100.0],
            ['min_sales'=>500000.0,'rate'=>90.0],
            ['min_sales'=>400000.0,'rate'=>80.0],
            ['min_sales'=>300000.0,'rate'=>70.0],
            ['min_sales'=>200000.0,'rate'=>60.0],
            ['min_sales'=>0.0,'rate'=>50.0],
        ],
        'pr_normal_rate'=>5.0,'pr_reduced_rate'=>4.0,
        'hold_enabled'=>0,'release_month'=>'','release_store_target'=>2800000.0,
        'release_personal_target'=>600000.0,'release_deadline'=>'',
        'online_exception_enabled'=>0,'online_required_days'=>0,'online_required_tables'=>4,
        'note'=>'','updated_at'=>'','updated_by'=>null,
    ];
}
function posi_r4_rule(array $d,string $month,string $from='',string $to=''): array {
    $base=posi_r4_rule_defaults($month,$from,$to);
    foreach(array_reverse($d['pos_r4_rules']??[]) as $rule){
        if($from!==''&&$to!==''&&(string)($rule['period_from']??'')===$from&&(string)($rule['period_to']??'')===$to)return array_replace($base,$rule);
        if($from===''&&(string)($rule['month']??'')===$month)return array_replace($base,$rule);
    }
    return $base;
}
function posi_r4_store_sales(array $d,string $from,string $to): float {
    $sum=0.0;foreach(posi_active_rows_period($d,$from,$to) as $row)$sum+=(float)($row['net_sales']??0);return $sum;
}
function posi_r4_employee_input(array $d,string $from,string $to,int $employeeId): array {
    $base=['period_from'=>$from,'period_to'=>$to,'employee_id'=>$employeeId,'personal_sales'=>0.0,'online_post_days'=>0,'online_required_days'=>0,'online_tables'=>0,'exception_approved'=>0,'exception_approved_by'=>null,'note'=>''];
    foreach(array_reverse($d['pos_r4_employee_inputs']??[]) as $input)if((int)($input['employee_id']??0)===$employeeId&&(string)($input['period_from']??'')===$from&&(string)($input['period_to']??'')===$to)return array_replace($base,$input);
    return $base;
}
function posi_r4_tier_rate(array $rule,float $personalSales): float {
    $tiers=is_array($rule['sales_tiers']??null)?$rule['sales_tiers']:[];
    usort($tiers,fn($a,$b)=>(float)($b['min_sales']??0)<=>(float)($a['min_sales']??0));
    foreach($tiers as $tier)if($personalSales>=(float)($tier['min_sales']??0))return max(0,(float)($tier['rate']??0));
    return 0.0;
}
function posi_r4_online_exception(array $rule,array $input): bool {
    if(empty($rule['online_exception_enabled'])||empty($input['exception_approved']))return false;
    $requiredDays=max(0,(int)($input['online_required_days']?:($rule['online_required_days']??0)));if($requiredDays===0){$from=(string)($input['period_from']??'');$to=(string)($input['period_to']??'');$requiredDays=($from!==''&&$to>=$from)?((int)floor((strtotime($to)-strtotime($from))/86400)+1):1;}
    return (int)($input['online_post_days']??0)>=$requiredDays&&(int)($input['online_tables']??0)>=(int)($rule['online_required_tables']??4);
}
function posi_r4_release_state(array $d,array $rule,int $employeeId): array {
    if(empty($rule['hold_enabled']))return ['eligible'=>false,'reason'=>'ไม่ได้เปิด Hold'];
    $releaseMonth=(string)($rule['release_month']??'');if(!preg_match('/^\d{4}-\d{2}$/',$releaseMonth))return ['eligible'=>false,'reason'=>'ยังไม่กำหนดเดือนตรวจคืน'];
    [$from,$to]=posi_month_bounds($releaseMonth);$store=posi_r4_store_sales($d,$from,$to);$deadline=(string)($rule['release_deadline']??'');$personalTo=preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$deadline)&&$deadline>$to?$deadline:$to;$personal=0.0;
    foreach($d['pos_r4_employee_inputs']??[] as $input){if((int)($input['employee_id']??0)!==$employeeId)continue;$inputFrom=(string)($input['period_from']??'');$inputTo=(string)($input['period_to']??'');if($inputFrom<$from||$inputTo>$personalTo)continue;$personal+=(float)($input['personal_sales']??0);}
    if($store>=(float)($rule['release_store_target']??0))return ['eligible'=>true,'reason'=>'ยอดรวมเดือนคืนถึงเป้า','store_sales'=>$store,'personal_sales'=>$personal];
    if($personal>=(float)($rule['release_personal_target']??0))return ['eligible'=>true,'reason'=>'ยอดส่วนตัวสะสมถึงเป้าก่อนกำหนดคืน','store_sales'=>$store,'personal_sales'=>$personal];
    return ['eligible'=>false,'reason'=>'รอตรวจเป้าคืนเงิน','store_sales'=>$store,'personal_sales'=>$personal];
}
function posi_r4_sales_results(array $d,string $month,array $rule,string $from,string $to): array {
    $storeSales=posi_r4_store_sales($d,$from,$to);$storeBelow=$storeSales<(float)($rule['store_target']??0);$out=[];
    foreach(posi_sales_units($d,$month,$from,$to) as $sales){
        $eid=(int)($sales['employee_id']??0);$input=$eid>0?posi_r4_employee_input($d,$from,$to,$eid):posi_r4_employee_input([], $from,$to,0);
        $normalRate=max(0,(float)($rule['sales_normal_rate']??100));$appliedRate=$normalRate;$reason='อัตราปกติ';
        $onlineException=$eid>0&&posi_r4_online_exception($rule,$input);
        if($storeBelow&&empty($rule['waive_reduction'])&&!$onlineException){$appliedRate=posi_r4_tier_rate($rule,(float)$input['personal_sales']);$reason='ขั้นบันไดยอดส่วนตัว';}
        elseif($storeBelow&&!empty($rule['waive_reduction']))$reason='ร้านยกเว้นการลด';
        elseif($onlineException)$reason='อนุมัติข้อยกเว้นออนไลน์';
        $units=(float)($sales['units']??0);$mode=(string)($rule['sales_rate_mode']??'amount_per_unit');
        $normalCommission=$units*$normalRate;
        $payable=$mode==='percent_normal'?$normalCommission*($appliedRate/100):$units*$appliedRate;
        $held=max(0,$normalCommission-$payable);$release=$eid>0?posi_r4_release_state($d,$rule,$eid):['eligible'=>false,'reason'=>'ยังไม่ผูกพนักงาน'];
        $out[]=array_replace($sales,['employee_input'=>$input,'store_sales'=>$storeSales,'store_below_target'=>$storeBelow,'normal_rate'=>$normalRate,'applied_rate'=>$appliedRate,'normal_commission'=>$normalCommission,'commission'=>$payable,'held_amount'=>!empty($rule['hold_enabled'])?$held:0.0,'deducted_amount'=>$held,'online_exception'=>$onlineException,'reason'=>$reason,'release'=>$release,'mapping_status'=>$eid>0?'mapped':'unmapped']);
    }
    usort($out,fn($a,$b)=>[(int)($a['employee_id']>0),(float)$a['commission']]<=>[(int)($b['employee_id']>0),(float)$b['commission']]);return array_reverse($out);
}
function posi_r4_pr_rate(array $d,string $month,float $storeSales): float {
    [$from,$to]=posi_month_bounds($month);$rule=posi_r4_rule($d,$month,$from,$to);if(empty($rule['enabled']))return -1.0;
    if($storeSales>=(float)$rule['store_target']||!empty($rule['waive_reduction']))return (float)$rule['pr_normal_rate'];
    return (float)$rule['pr_reduced_rate'];
}
function posi_remap_rows_period(array &$d,string $from,string $to): int {
    $changed=0;if(!isset($d['pos_sales_rows'])||!is_array($d['pos_sales_rows']))return 0;
    foreach($d['pos_sales_rows'] as &$row){if(empty($row['active']))continue;$date=(string)($row['sale_date']??'');if($date<$from||$date>$to||!posi_row_is_candidate($row))continue;$match=posi_match_employee($d,(string)($row['item_name']??''));$new=$match['employee_id'];if((int)($row['employee_id']??0)!==(int)($new??0)||($row['match_method']??'')!==$match['method']){$row['employee_id']=$new;$row['match_method']=$match['method'];$row['matched_alias']=$match['alias'];$changed++;}}
    unset($row);return $changed;
}
