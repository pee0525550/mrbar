<?php
require_once __DIR__.'/pos-incentive.php';
function pc_bucket(string $core): string {
    if(!in_array($core,['drinks','commission'],true))throw new RuntimeException('ไม่พบประเภทการคำนวณ');
    return $core==='drinks'?'drink_payout_rounds':'commission_payout_rounds';
}
function pc_calculate(array $d,string $core,int $batchId,array $input): array {
    pc_bucket($core);$batch=null;
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
    $result=posi_role_commission_results($d,$rule,$from,$to);$rows=[];
    foreach($result['rows'] as $r){
        if($core==='commission'&&empty($r['team_lead']))continue;
        $rows[]=['employee_id'=>$r['employee_id'],'name'=>$r['name'],'role'=>$r['role'],'teams'=>$core==='commission'?$r['team_code']:'',
            'd'=>$core==='drinks'?$r['own_d']:$r['team_pr_d'],'m'=>$core==='drinks'?$r['own_m']:$r['team_pr_m'],
            'amount'=>$core==='drinks'?$r['own_commission']:$r['team_commission']];
    }
    return ['core'=>$core,'batch_id'=>$batchId,'from'=>$from,'to'=>$to,'rule'=>$rule,'rows'=>$rows,'unmapped'=>$result['unmapped'],'teams'=>$result['team_pr_units'],'total'=>array_sum(array_column($rows,'amount'))];
}
function pc_save(array $d,string $core,int $batchId,array $input,int $uid): array {
    $bucket=pc_bucket($core);$result=pc_calculate($d,$core,$batchId,$input);
    if(!$result['rows'])throw new RuntimeException('ยังไม่มีรายการสำหรับบันทึก');
    if($result['unmapped'])throw new RuntimeException('ผูกเมนูกับ Employee Card ให้ครบก่อนบันทึก');
    foreach($d[$bucket]??[] as $old)if((int)$old['batch_id']===$batchId&&($old['status']??'saved')!=='void')throw new RuntimeException('Report นี้บันทึกในหัวข้อนี้แล้ว');
    $result['id']=next_id($d[$bucket]??[]);$result['status']='saved';$result['saved_at']=date('c');$result['saved_by']=$uid;
    $d[$bucket][]=$result;$d['audit'][]=['at'=>date('c'),'action'=>$core.'_payout_saved','by'=>$uid,'batch_id'=>$batchId,'round_id'=>$result['id']];
    return $d;
}
