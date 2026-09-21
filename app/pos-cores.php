<?php
require_once __DIR__.'/pos-incentive.php';
function pc_bucket(string $core): string {
    if(!in_array($core,['drinks','commission'],true))throw new RuntimeException('ไม่พบประเภทการคำนวณ');
    return $core==='drinks'?'drink_payout_rounds':'commission_payout_rounds';
}
function pc_commission_default_tiers(): array {
    return [['limit'=>200000,'multiplier'=>50],['limit'=>300000,'multiplier'=>60],['limit'=>400000,'multiplier'=>70],['limit'=>500000,'multiplier'=>80],['limit'=>600000,'multiplier'=>90]];
}
function pc_commission_tiers(array $input): array {
    if(isset($input['sales_bill_tiers'])&&is_array($input['sales_bill_tiers'])){
        $tiers=[];foreach($input['sales_bill_tiers'] as $tier){if(!is_array($tier))continue;$limit=$tier['limit']??null;$mult=$tier['multiplier']??null;if(!is_numeric($limit)||!is_numeric($mult))continue;$limit=(float)$limit;$mult=(float)$mult;if($limit<=0||$mult<0)continue;$tiers[]=['limit'=>$limit,'multiplier'=>$mult];}
        if($tiers){usort($tiers,fn($a,$b)=>(float)$a['limit']<=>(float)$b['limit']);return $tiers;}
    }
    $limits=(array)($input['tier_limit']??[]);$multipliers=(array)($input['tier_multiplier']??[]);
    if(!$limits&&!$multipliers)return pc_commission_default_tiers();
    $tiers=[];$count=max(count($limits),count($multipliers));
    for($i=0;$i<$count;$i++){
        $limit=$limits[$i]??null;$mult=$multipliers[$i]??null;
        if(!is_numeric($limit)||!is_numeric($mult))continue;
        $limit=(float)$limit;$mult=(float)$mult;
        if($limit<=0||$mult<0)continue;
        $tiers[]=['limit'=>$limit,'multiplier'=>$mult];
    }
    if(!$tiers)throw new RuntimeException('กรุณาระบุเงื่อนไขยอดขายและตัวคูณอย่างน้อย 1 ระดับ');
    usort($tiers,fn($a,$b)=>(float)$a['limit']<=>(float)$b['limit']);return $tiers;
}
function pc_commission_multiplier(float $sales,array $tiers): array {
    foreach($tiers as $tier)if($sales<(float)$tier['limit'])return ['multiplier'=>(float)$tier['multiplier'],'label'=>'ยอดขายไม่ถึง '.number_format((float)$tier['limit'],0)];
    $last=end($tiers)?:['limit'=>0,'multiplier'=>0];return ['multiplier'=>(float)$last['multiplier'],'label'=>'ยอดขายตั้งแต่ '.number_format((float)$last['limit'],0).' ขึ้นไป'];
}
function pc_sales_drink_multiplier_map(array $d,string $from,string $to): array {
    $map=[];
    foreach($d['pos_sales_drink_multipliers']??[] as $row){
        if(($row['status']??'active')!=='active')continue;
        if((string)($row['period_from']??'')!==$from||(string)($row['period_to']??'')!==$to)continue;
        $eid=(int)($row['employee_id']??0);if($eid>0)$map[$eid]=(float)($row['multiplier']??0);
    }
    if($map)return $map;
    foreach(array_reverse($d['commission_payout_rounds']??[]) as $round){
        if(($round['status']??'saved')==='void'||($round['basis']??'')!=='bill_detail')continue;
        if((string)($round['from']??'')!==$from||(string)($round['to']??'')!==$to)continue;
        foreach($round['rows']??[] as $row){$eid=(int)($row['employee_id']??0);if($eid>0)$map[$eid]=(float)($row['sales_drink_multiplier']??$row['amount']??0);}
        if($map)break;
    }
    return $map;
}
function pc_calculate(array $d,string $core,int $batchId,array $input): array {
    pc_bucket($core);$batch=null;
    if($core==='commission'){
        foreach($d['pos_bill_batches']??[] as $b)if((int)$b['id']===$batchId&&($b['status']??'active')==='active'){$batch=$b;break;}
        if(!$batch)throw new RuntimeException('เลือก Report รายละเอียดบิลที่ Process สำเร็จ');
        $from=(string)$batch['period_start'];$to=(string)$batch['period_end'];$tiers=pc_commission_tiers($input);
        $rule=['period_from'=>$from,'period_to'=>$to,'sales_bill_tiers'=>$tiers];
        $people=[];
        foreach($d['sales_table_sessions']??[] as $s){
            if((int)($s['matched_bill_batch_id']??0)!==$batchId)continue;
            if(!in_array((string)($s['match_status']??''),['matched','partial'],true))continue;
            $sales=max(0,(float)($s['matched_net_sales']??0));if($sales<=0)continue;
            $sid=(int)($s['sales_id']??0);$key=$sid>0?'employee:'.$sid:'name:'.posi_norm((string)($s['sales_label']??'ไม่ระบุ Sales'));
            if(!isset($people[$key]))$people[$key]=['employee_id'=>$sid,'name'=>(string)($s['sales_label']??($sid>0?'#'.$sid:'ไม่ระบุ Sales')),'role'=>'sales','teams'=>'','d'=>0.0,'m'=>0.0,'sales_total'=>0.0,'session_count'=>0,'receipt_count'=>0,'amount'=>0.0,'sales_drink_multiplier'=>0.0,'tier_label'=>''];
            $people[$key]['sales_total']+=$sales;$people[$key]['session_count']++;$people[$key]['receipt_count']+=count((array)($s['receipts']??[]));$people[$key]['m']=$people[$key]['sales_total'];
        }
        foreach($people as &$row){$tier=pc_commission_multiplier((float)$row['sales_total'],$tiers);$row['sales_drink_multiplier']=(float)$tier['multiplier'];$row['amount']=(float)$tier['multiplier'];$row['tier_label']=(string)$tier['label'];}unset($row);
        $rows=array_values($people);usort($rows,fn($a,$b)=>(float)$b['sales_total']<=>(float)$a['sales_total']);
        return ['core'=>$core,'batch_id'=>$batchId,'from'=>$from,'to'=>$to,'rule'=>$rule,'rows'=>$rows,'unmapped'=>[],'teams'=>[],'total'=>array_sum(array_column($rows,'amount')),'basis'=>'bill_detail','source_total'=>array_sum(array_column($rows,'sales_total'))];
    }
    foreach($d['pos_import_batches']??[] as $b)if((int)$b['id']===$batchId&&($b['status']??'active')==='active'){$batch=$b;break;}
    if(!$batch)throw new RuntimeException('เลือก Report ที่ Process แล้วและยังใช้งาน');
    $from=(string)$batch['period_start'];$to=(string)$batch['period_end'];
    $d['pos_sales_rows']=array_values(array_filter($d['pos_sales_rows']??[],fn($r)=>(int)($r['batch_id']??0)===$batchId));
    $rule=posi_role_commission_rule([], $from,$to);
    $keys=$core==='drinks'?['pr_rate_d','pr_rate_m','sales_own_rate_d','sales_own_rate_m']:['sales_team_rate_d','sales_team_rate_m'];
    foreach($keys as $key){$v=$input[$key]??null;if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0)throw new RuntimeException('ระบุอัตราเป็นเลขตั้งแต่ 0 ขึ้นไป');$rule[$key]=(float)$v;}
    $discovered=posi_role_commission_results($d,$rule,$from,$to);
    if($core==='commission'){
        foreach($discovered['team_pr_units'] as $team=>$units){
            $id=(int)($input['team_sales_map'][$team]??0);$valid=false;
            foreach($d['employees']??[] as $e)if((int)$e['id']===$id&&!empty($e['active'])&&($e['position']??'')==='sales')$valid=true;
            if(!$valid)throw new RuntimeException('เลือก Sales ผู้ดูแลทีม '.$team);
            $rule['team_sales_map'][$team]=$id;
        }
    }
    $result=posi_role_commission_results($d,$rule,$from,$to);$rows=[];$salesMultiplierMap=$core==='drinks'?pc_sales_drink_multiplier_map($d,$from,$to):[];
    foreach($result['rows'] as $r){
        if($core==='commission'&&empty($r['team_lead']))continue;
        $amount=$core==='drinks'?$r['own_commission']:$r['team_commission'];$salesMultiplier=null;
        if($core==='drinks'&&($r['role']??'')==='sales'&&isset($salesMultiplierMap[(int)$r['employee_id']])){
            $salesMultiplier=(float)$salesMultiplierMap[(int)$r['employee_id']];
            $amount=((float)$r['own_d']+(float)$r['own_m'])*$salesMultiplier;
        }
        $rows[]=['employee_id'=>$r['employee_id'],'name'=>$r['name'],'role'=>$r['role'],'teams'=>$core==='commission'?$r['team_code']:'',
            'd'=>$core==='drinks'?$r['own_d']:$r['team_pr_d'],'m'=>$core==='drinks'?$r['own_m']:$r['team_pr_m'],
            'amount'=>$amount,'sales_drink_multiplier'=>$salesMultiplier];
    }
    return ['core'=>$core,'batch_id'=>$batchId,'from'=>$from,'to'=>$to,'rule'=>$rule,'rows'=>$rows,'unmapped'=>$result['unmapped'],'teams'=>$result['team_pr_units'],'total'=>array_sum(array_column($rows,'amount'))];
}
function pc_sales_direct_context(array $d,int $batchId,array $map=[]): array {
    $batch=null;foreach($d['pos_import_batches']??[] as $b)if((int)($b['id']??0)===$batchId&&($b['status']??'active')==='active'){$batch=$b;break;}
    if(!$batch)throw new RuntimeException('เลือก Report ยอดขายตามเมนูที่ Process สำเร็จ');
    $from=(string)($batch['period_start']??'');$to=(string)($batch['period_end']??'');$multipliers=pc_sales_drink_multiplier_map($d,$from,$to);$groups=[];
    foreach($d['pos_sales_rows']??[] as $row){
        if((int)($row['batch_id']??0)!==$batchId||empty($row['active'])||!posi_row_is_candidate($row))continue;
        $employee=posi_employee_by_id($d,(int)($row['employee_id']??0));$role=posi_row_role($row,$employee);
        if($role!=='sales')continue;
        $name=posi_sales_display_name($d,$row);$key='menu:'.substr(hash('sha256',posi_norm($name)),0,16);
        if(!isset($groups[$key]))$groups[$key]=['key'=>$key,'menu_name'=>$name,'employee_id'=>(int)($row['employee_id']??0),'d'=>0.0,'m'=>0.0,'qty'=>0.0,'net_sales'=>0.0,'source_rows'=>0,'source_items'=>[],'selected_employee_id'=>0,'selected_employee_name'=>'','has_multiplier'=>false,'multiplier'=>0.0,'amount'=>0.0];
        $drink=in_array((string)($row['drink_code']??''),['D','M'],true)?(string)$row['drink_code']:'D';$qty=max(0,(float)($row['qty']??0));
        $groups[$key][strtolower($drink)]+=$qty;$groups[$key]['qty']+=$qty;$groups[$key]['net_sales']+=max(0,(float)($row['net_sales']??0));$groups[$key]['source_rows']++;
        $item=(string)($row['item_name']??'');if($item!==''&&!in_array($item,$groups[$key]['source_items'],true))$groups[$key]['source_items'][]=$item;
        if((int)$groups[$key]['employee_id']<=0&&(int)($row['employee_id']??0)>0)$groups[$key]['employee_id']=(int)$row['employee_id'];
    }
    $sales=[];foreach($d['employees']??[] as $e)if(!empty($e['active'])&&($e['position']??'')==='sales')$sales[]=$e;
    usort($sales,fn($a,$b)=>strcmp((string)($a['name']??$a['code']??''),(string)($b['name']??$b['code']??'')));
    foreach($groups as &$group){
        $selected=(int)($map[$group['key']]??0);if($selected<=0)$selected=(int)$group['employee_id'];$group['selected_employee_id']=$selected;
        if($selected>0){$employee=posi_employee_by_id($d,$selected);$group['selected_employee_name']=$employee?(string)($employee['name']??$employee['code']??('#'.$selected)):'#'.$selected;$group['has_multiplier']=array_key_exists($selected,$multipliers);$group['multiplier']=(float)($multipliers[$selected]??0);}
        $group['amount']=(float)$group['qty']*(float)$group['multiplier'];
    }unset($group);
    $rows=array_values($groups);usort($rows,fn($a,$b)=>[(float)$b['qty'],(float)$b['net_sales'],(string)$a['menu_name']]<=>[(float)$a['qty'],(float)$a['net_sales'],(string)$b['menu_name']]);
    return ['batch'=>$batch,'from'=>$from,'to'=>$to,'rows'=>$rows,'sales'=>$sales,'multipliers'=>$multipliers,'total_units'=>array_sum(array_column($rows,'qty')),'total_sales'=>array_sum(array_column($rows,'net_sales')),'total_amount'=>array_sum(array_column($rows,'amount'))];
}
function pc_sales_direct_result(array $d,int $batchId,array $map): array {
    $ctx=pc_sales_direct_context($d,$batchId,$map);$rows=[];
    foreach($ctx['rows'] as $row){
        if((int)($row['selected_employee_id']??0)<=0)throw new RuntimeException('กรุณาจับคู่เมนู Sales ให้ครบก่อนบันทึก');
        if(empty($row['has_multiplier']))throw new RuntimeException('ยังไม่มีตัวคูณค่าคอมของ '.(string)($row['selected_employee_name']??$row['menu_name']).' ในรอบนี้ กรุณาบันทึกค่าคอม Sales ก่อน');
        $rows[]=['employee_id'=>(int)$row['selected_employee_id'],'name'=>(string)$row['selected_employee_name'],'role'=>'sales','teams'=>'ดื่มตรง','menu_name'=>(string)$row['menu_name'],'d'=>(float)$row['d'],'m'=>(float)$row['m'],'qty'=>(float)$row['qty'],'net_sales'=>(float)$row['net_sales'],'sales_drink_multiplier'=>(float)$row['multiplier'],'amount'=>(float)$row['amount']];
    }
    if(!$rows)throw new RuntimeException('ยังไม่มีรายการ Sales ดื่มตรงสำหรับบันทึก');
    return ['core'=>'drinks','batch_id'=>$batchId,'from'=>$ctx['from'],'to'=>$ctx['to'],'basis'=>'sales_direct_drink','rule'=>['sales_direct_map'=>$map],'rows'=>$rows,'unmapped'=>[],'teams'=>[],'total'=>array_sum(array_column($rows,'amount')),'source_total'=>(float)$ctx['total_sales'],'total_units'=>(float)$ctx['total_units']];
}
function pc_save_sales_direct(array $d,int $batchId,array $map,int $uid): array {
    $result=pc_sales_direct_result($d,$batchId,$map);$bucket='drink_payout_rounds';
    if(!isset($d[$bucket])||!is_array($d[$bucket]))$d[$bucket]=[];
    foreach($d[$bucket] as $old){$oldBasis=(string)($old['basis']??'role_drinks');if((int)($old['batch_id']??0)===$batchId&&$oldBasis==='sales_direct_drink'&&($old['status']??'saved')!=='void')throw new RuntimeException('Report นี้บันทึกค่าดื่ม Sales ดื่มตรงแล้ว');}
    $result['id']=next_id($d[$bucket]);$result['status']='saved';$result['saved_at']=date('c');$result['saved_by']=$uid;$d[$bucket][]=$result;
    $d['audit'][]=['at'=>date('c'),'action'=>'sales_direct_drink_payout_saved','by'=>$uid,'batch_id'=>$batchId,'round_id'=>$result['id']];
    return $d;
}
function pc_save(array $d,string $core,int $batchId,array $input,int $uid): array {
    $bucket=pc_bucket($core);$result=pc_calculate($d,$core,$batchId,$input);
    if(!$result['rows'])throw new RuntimeException('ยังไม่มีรายการสำหรับบันทึก');
    if($result['unmapped'])throw new RuntimeException('ผูกเมนูกับ Employee Card ให้ครบก่อนบันทึก');
    if($core==='drinks'&&!isset($result['basis']))$result['basis']='role_drinks';
    foreach($d[$bucket]??[] as $old){$oldBasis=(string)($old['basis']??($core==='drinks'?'role_drinks':''));$newBasis=(string)($result['basis']??'');if((int)$old['batch_id']===$batchId&&$oldBasis===$newBasis&&($old['status']??'saved')!=='void')throw new RuntimeException('Report นี้บันทึกในหัวข้อนี้แล้ว');}
    $result['id']=next_id($d[$bucket]??[]);$result['status']='saved';$result['saved_at']=date('c');$result['saved_by']=$uid;
    $d[$bucket][]=$result;
    if($core==='commission'&&($result['basis']??'')==='bill_detail'){
        if(!isset($d['pos_sales_drink_multipliers'])||!is_array($d['pos_sales_drink_multipliers']))$d['pos_sales_drink_multipliers']=[];
        foreach($d['pos_sales_drink_multipliers'] as &$old)if((string)($old['period_from']??'')===(string)$result['from']&&(string)($old['period_to']??'')===(string)$result['to'])$old['status']='superseded';unset($old);
        $next=next_id($d['pos_sales_drink_multipliers']);
        foreach($result['rows'] as $row){$eid=(int)($row['employee_id']??0);if($eid<=0)continue;$d['pos_sales_drink_multipliers'][]=['id'=>$next++,'employee_id'=>$eid,'employee_name'=>(string)($row['name']??''),'period_from'=>(string)$result['from'],'period_to'=>(string)$result['to'],'source_batch_id'=>$batchId,'source_round_id'=>(int)$result['id'],'sales_total'=>(float)($row['sales_total']??0),'receipt_count'=>(int)($row['receipt_count']??0),'multiplier'=>(float)($row['sales_drink_multiplier']??$row['amount']??0),'status'=>'active','saved_at'=>date('c'),'saved_by'=>$uid];}
    }
    $d['audit'][]=['at'=>date('c'),'action'=>$core.'_payout_saved','by'=>$uid,'batch_id'=>$batchId,'round_id'=>$result['id']];
    return $d;
}
