<?php
declare(strict_types=1);

/* Called inside db_mutate's exclusive lock. No POS sales or commission is inferred here. */
function st_actor(array $d,int $actorId,bool $manage=false): array {
    foreach($d['users']??[] as $u) if((int)($u['id']??0)===$actorId) {
        $bid=(int)($d['_branch_context']['id']??0);
        if(empty($u['active'])||!db_user_can_branch($u,$bid)||!user_can($u,$manage?'sales_sessions.manage':'sales_sessions.record',$d))
            throw new RuntimeException('ไม่มีสิทธิ์ทำรายการในสาขานี้');
        return $u;
    }
    throw new RuntimeException('ไม่พบบัญชีผู้ทำรายการ');
}
function st_sales(array $d,int $id): array {
    foreach($d['employees']??[] as $e) if((int)$e['id']===$id&&!empty($e['active'])&&($e['position']??'')==='sales')return $e;
    throw new RuntimeException('กรุณาเลือก Sales ที่ยังใช้งานในร้านนี้');
}
function st_label(array $e): string { return trim((string)($e['code']??'').' · '.(string)($e['name']??$e['display_name']??'')); }
function st_open_session(array $d,int $tableId): ?array {
    foreach($d['sales_table_sessions']??[] as $s)if((int)$s['table_id']===$tableId&&$s['status']==='open')return $s;
    return null;
}
function st_receipts(array $d,string $text,int $except=0): array {
    $lines=preg_split('/\R/u',$text)?:[];$out=[];$seen=[];
    foreach($lines as $line){
        $line=trim($line);if($line==='')continue;
        if(mb_strlen($line)>80||preg_match('/[\x00-\x1f\x7f]/u',$line))throw new RuntimeException('เลขใบเสร็จไม่ถูกต้อง');
        $key=mb_strtoupper($line,'UTF-8');
        if(isset($seen[$key]))throw new RuntimeException('เลขใบเสร็จซ้ำในรายการนี้');
        $seen[$key]=true;$out[]=$line;
    }
    if(!$out||count($out)>30)throw new RuntimeException('ใส่ใบเสร็จ 1–30 ใบ แยกบรรทัด');
    foreach($d['sales_table_sessions']??[] as $s){
        if((int)$s['id']===$except)continue;
        foreach($s['receipts']??[] as $receipt)if(isset($seen[mb_strtoupper(trim($receipt),'UTF-8')]))throw new RuntimeException('ใบเสร็จนี้ถูกผูกกับรายการอื่นในสาขาแล้ว');
    }
    return $out;
}
function st_apply(array $d,array $input,int $actorId): array {
    $action=(string)($input['action']??'');$manage=in_array($action,['transfer','edit_receipts'],true);
    $actor=st_actor($d,$actorId,$manage);$tableId=(int)($input['table_id']??0);$table=null;
    foreach($d['tables']??[] as $t)if((int)$t['id']===$tableId){$table=$t;break;}
    if(!$table)throw new RuntimeException('ไม่พบโต๊ะในร้านนี้');
    $now=date('c');$before=null;
    if($action==='open'){
        if(empty($table['active'])||($table['status']??'')==='blocked')throw new RuntimeException('โต๊ะปิดใช้งาน');
        if(st_open_session($d,$tableId))throw new RuntimeException('โต๊ะนี้มี Sales เปิดรอบอยู่แล้ว กรุณาโหลดหน้าใหม่');
        $e=st_sales($d,(int)($input['sales_id']??0));
        $s=['id'=>next_id($d['sales_table_sessions']??[]),'branch_id'=>(int)$d['_branch_context']['id'],'table_id'=>$tableId,'table_code'=>(string)$table['code'],'sales_id'=>(int)$e['id'],'sales_label'=>st_label($e),'status'=>'open','opened_at'=>$now,'opened_by'=>$actorId,'opened_by_label'=>(string)($actor['display_name']??$actor['username']??$actorId),'closed_at'=>null,'closed_by'=>null,'receipts'=>[],'match_status'=>'pending','matched_net_sales'=>null,'revision'=>1];
        $d['sales_table_sessions'][]=$s;
    }else{
        $index=null;foreach($d['sales_table_sessions']??[] as $i=>$row)if((int)$row['id']===(int)($input['session_id']??0)&&(int)$row['table_id']===$tableId){$index=$i;break;}
        if($index===null)throw new RuntimeException('ไม่พบรอบโต๊ะ');
        $s=$d['sales_table_sessions'][$index];$before=$s;
        if((int)($input['revision']??0)!==(int)$s['revision'])throw new RuntimeException('รายการเปลี่ยนแปลงแล้ว กรุณาโหลดหน้าใหม่');
        if($action==='close'){
            if($s['status']!=='open')throw new RuntimeException('รายการนี้ปิดแล้ว');
            $s['receipts']=st_receipts($d,(string)($input['receipts']??''),(int)$s['id']);
            $s['status']='closed';$s['closed_at']=$now;$s['closed_by']=$actorId;
            $s['closed_by_label']=(string)($actor['display_name']??$actor['username']??$actorId);
        }elseif($manage){
            if(trim((string)($input['reason']??''))==='')throw new RuntimeException('ระบุเหตุผลการแก้ไข');
            if(($s['match_status']??'pending')!=='pending')throw new RuntimeException('รายการจับคู่ยอดแล้ว ต้องตรวจสอบกับผู้ดูแลก่อนแก้ไข');
            if($action==='transfer'){
                $e=st_sales($d,(int)($input['sales_id']??0));$s['sales_id']=(int)$e['id'];$s['sales_label']=st_label($e);
            }else{
                if($s['status']!=='closed')throw new RuntimeException('แก้ใบเสร็จได้เฉพาะรายการปิดแล้ว');
                $s['receipts']=st_receipts($d,(string)($input['receipts']??''),(int)$s['id']);
            }
        }else throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        $s['revision']++;$d['sales_table_sessions'][$index]=$s;
    }
    $d['audit'][]=['at'=>$now,'action'=>'sales_table_'.$action,'by'=>$actorId,'session_id'=>$s['id'],'table_id'=>$tableId,'reason'=>trim((string)($input['reason']??'')),'before'=>$before,'after'=>$s];
    return $d;
}
