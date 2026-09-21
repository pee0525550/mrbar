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
function st_row_in_branch(array $d,array $row): bool {$bid=(int)($d['_branch_context']['id']??0);$rowBid=(int)($row['branch_id']??0);return $bid<=0||$rowBid<=0||$rowBid===$bid;}
function st_sales(array $d,int $id): array {
    foreach($d['employees']??[] as $e) if((int)$e['id']===$id&&!empty($e['active'])&&($e['position']??'')==='sales'&&st_row_in_branch($d,$e))return $e;
    throw new RuntimeException('กรุณาเลือก Sales ที่ยังใช้งานในร้านนี้');
}
function st_label(array $e): string { return trim((string)($e['code']??'').' · '.(string)($e['name']??$e['display_name']??'')); }
function st_public_secret(array $d): string {return trim((string)($d['settings']['sales_table_qr_secret']??''));}
function st_public_token(array $d,string $slug,int $tableId): string {$secret=st_public_secret($d);if($secret===''||$tableId<1)return '';return hash_hmac('sha256',strtolower(trim($slug)).'|'.$tableId,$secret);}
function st_public_token_valid(array $d,string $slug,int $tableId,string $token): bool {$expected=st_public_token($d,$slug,$tableId);return $expected!==''&&$token!==''&&hash_equals($expected,$token);}
function st_apply_public(array $d,array $input,string $actorLabel='QR Public'): array {
    $action=(string)($input['action']??'');if(!in_array($action,['open','close'],true))throw new RuntimeException('QR สาธารณะใช้ได้เฉพาะเปิด/ปิดโต๊ะ');
    $tableId=(int)($input['table_id']??0);$table=null;foreach($d['tables']??[] as $t)if((int)$t['id']===$tableId){$table=$t;break;}if(!$table)throw new RuntimeException('ไม่พบโต๊ะในร้านนี้');
    $now=date('c');$before=null;
    if($action==='open'){
        if(empty($table['active'])||($table['status']??'')==='blocked')throw new RuntimeException('โต๊ะปิดใช้งาน');
        if(st_open_session($d,$tableId))throw new RuntimeException('โต๊ะนี้มี Sales เปิดรอบอยู่แล้ว กรุณาโหลดหน้าใหม่');
        $e=st_sales($d,(int)($input['sales_id']??0));
        $s=['id'=>next_id($d['sales_table_sessions']??[]),'branch_id'=>(int)$d['_branch_context']['id'],'table_id'=>$tableId,'table_code'=>(string)$table['code'],'sales_id'=>(int)$e['id'],'sales_label'=>st_label($e),'status'=>'open','opened_at'=>$now,'opened_by'=>0,'opened_by_label'=>$actorLabel,'closed_at'=>null,'closed_by'=>null,'receipts'=>[],'match_status'=>'pending','matched_net_sales'=>null,'revision'=>1];
        $d['sales_table_sessions'][]=$s;
    }else{
        $index=null;foreach($d['sales_table_sessions']??[] as $i=>$row)if(st_row_in_branch($d,$row)&&(int)$row['id']===(int)($input['session_id']??0)&&(int)$row['table_id']===$tableId){$index=$i;break;}if($index===null)throw new RuntimeException('ไม่พบรอบโต๊ะในสาขานี้');
        $s=$d['sales_table_sessions'][$index];$before=$s;if((int)($input['revision']??0)!==(int)$s['revision'])throw new RuntimeException('รายการเปลี่ยนแปลงแล้ว กรุณาโหลดหน้าใหม่');if($s['status']!=='open')throw new RuntimeException('รายการนี้ปิดแล้ว');
        $s['receipts']=st_receipts($d,(string)($input['receipts']??''),(int)$s['id']);$s['status']='closed';$s['closed_at']=$now;$s['closed_by']=0;$s['closed_by_label']=$actorLabel;$s['revision']++;$d['sales_table_sessions'][$index]=$s;
    }
    $otherUse=false;foreach($d['checkins']??[] as $c)if(st_row_in_branch($d,$c)&&(int)($c['table_id']??0)===$tableId&&!in_array((string)($c['status']??''),['completed','cancelled'],true))$otherUse=true;foreach($d['floor_service_sessions']??[] as $c)if(st_row_in_branch($d,$c)&&(int)($c['table_id']??0)===$tableId&&empty($c['ended_at']))$otherUse=true;
    foreach($d['tables'] as &$t){if((int)$t['id']!==$tableId)continue;if(!empty($t['active'])&&($t['status']??'')!=='blocked'){if($action==='open')$t['status']='occupied';elseif(($t['status']??'')==='occupied'&&!$otherUse&&!st_open_session($d,$tableId))$t['status']='available';$t['updated_at']=$now;}}unset($t);
    $d['audit'][]=['at'=>$now,'action'=>'sales_table_'.$action.'_public_qr','by'=>0,'session_id'=>$s['id'],'table_id'=>$tableId,'reason'=>'public_qr','before'=>$before,'after'=>$s];return $d;
}
function st_open_session(array $d,int $tableId): ?array {
    foreach($d['sales_table_sessions']??[] as $s)if(st_row_in_branch($d,$s)&&(int)$s['table_id']===$tableId&&$s['status']==='open')return $s;
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
        if(!st_row_in_branch($d,$s)||(int)$s['id']===$except)continue;
        foreach($s['receipts']??[] as $receipt)if(isset($seen[mb_strtoupper(trim($receipt),'UTF-8')])){$where=trim((string)($s['table_code']??''));$owner=trim((string)($s['sales_label']??''));$detail=trim(($where!==''?'โต๊ะ '.$where:'').($owner!==''?' · '.$owner:''));throw new RuntimeException('ใบเสร็จ '.trim((string)$receipt).' ถูกใช้แล้วในสาขานี้'.($detail!==''?' ('.$detail.')':''));}
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
        $index=null;foreach($d['sales_table_sessions']??[] as $i=>$row)if(st_row_in_branch($d,$row)&&(int)$row['id']===(int)($input['session_id']??0)&&(int)$row['table_id']===$tableId){$index=$i;break;}
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
    if(in_array($action,['open','close'],true)){
        $otherUse=false;
        foreach($d['checkins']??[] as $c)if(st_row_in_branch($d,$c)&&(int)($c['table_id']??0)===$tableId&&!in_array((string)($c['status']??''),['completed','cancelled'],true))$otherUse=true;
        foreach($d['floor_service_sessions']??[] as $c)if(st_row_in_branch($d,$c)&&(int)($c['table_id']??0)===$tableId&&empty($c['ended_at']))$otherUse=true;
        foreach($d['tables'] as &$t){
            if((int)$t['id']!==$tableId)continue;
            if(!empty($t['active'])&&($t['status']??'')!=='blocked'){
                if($action==='open')$t['status']='occupied';
                elseif(($t['status']??'')==='occupied'&&!$otherUse&&!st_open_session($d,$tableId))$t['status']='available';
                $t['updated_at']=$now;
            }
        }unset($t);
    }
    $d['audit'][]=['at'=>$now,'action'=>'sales_table_'.$action,'by'=>$actorId,'session_id'=>$s['id'],'table_id'=>$tableId,'reason'=>trim((string)($input['reason']??'')),'before'=>$before,'after'=>$s];
    return $d;
}
