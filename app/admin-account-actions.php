<?php
declare(strict_types=1);

function admin_account_archive_attempt(array $raw,int $targetId,array $actor,int $branchId,string $pin,string &$error=''): array {
    $error='';$actorId=(int)($actor['id']??0);$actorIndex=null;$targetIndex=null;
    foreach($raw['users']??[] as $i=>$user){
        if((int)($user['id']??0)===$actorId)$actorIndex=$i;
        if((int)($user['id']??0)===$targetId)$targetIndex=$i;
    }
    if($actorIndex===null||empty($raw['users'][$actorIndex]['active'])||!empty($raw['users'][$actorIndex]['deleted_at']))throw new RuntimeException('ไม่พบบัญชีผู้ดูแลที่ใช้งานอยู่');
    $owner=$raw['users'][$actorIndex];
    if(empty($owner['super_admin'])&&($owner['role']??'')!=='admin')throw new RuntimeException('ต้องใช้บัญชี Admin หรือ Super Admin เพื่อยืนยันการลบ');
    if($targetIndex===null)throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
    $target=$raw['users'][$targetIndex];
    if(!empty($target['deleted_at']))throw new RuntimeException('บัญชีนี้ถูกนำออกจากรายการแล้ว');
    if($targetId===$actorId)throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่');
    if(!db_user_can_branch($target,$branchId))throw new RuntimeException('บัญชีนี้ไม่ได้อยู่ในสาขาปัจจุบัน');
    if(count(branch_user_related_branch_ids($target))>1&&empty($owner['super_admin']))throw new RuntimeException('บัญชีนี้ใช้หลายสาขา กรุณาให้ Super Admin จัดการ');
    if((!empty($target['super_admin'])||($target['role']??'')==='admin')&&empty($owner['super_admin']))throw new RuntimeException('บัญชี Admin ต้องให้ Super Admin เป็นผู้ดำเนินการ');
    if(!empty($target['super_admin'])){
        $activeSuperAdmins=array_filter($raw['users']??[],fn($user)=>!empty($user['super_admin'])&&!empty($user['active'])&&empty($user['deleted_at']));
        if(count($activeSuperAdmins)<=1)throw new RuntimeException('นำ Super Admin คนสุดท้ายออกไม่ได้');
    }

    $lockedUntil=strtotime((string)($owner['admin_archive_pin_locked_until']??''))?:0;
    if($lockedUntil>time())throw new RuntimeException('PIN ถูกล็อกชั่วคราว กรุณาลองใหม่หลัง '.date('H:i',$lockedUntil).' น.');
    $pinHashes=[];
    foreach($owner['trusted_devices']??[] as $device){$hash=(string)($device['pin_hash']??'');if($hash!=='')$pinHashes[]=$hash;}
    if(!$pinHashes)throw new RuntimeException('บัญชี Admin นี้ยังไม่มี PIN ที่ลงทะเบียนไว้ กรุณาตั้ง PIN บนอุปกรณ์ที่เชื่อถือก่อน');
    $valid=preg_match('/^\d{6}$/',$pin)===1;
    if($valid){$matched=false;foreach($pinHashes as $hash)if(password_verify($pin,$hash))$matched=true;$valid=$matched;}
    if(!$valid){
        $attempts=(int)($owner['admin_archive_pin_failed_attempts']??0)+1;
        $owner['admin_archive_pin_failed_attempts']=$attempts;
        if($attempts>=5){$owner['admin_archive_pin_failed_attempts']=0;$owner['admin_archive_pin_locked_until']=date('c',time()+900);$error='PIN ไม่ถูกต้องครบ 5 ครั้ง ระบบล็อกการยืนยัน 15 นาที';}
        else $error='Admin PIN ไม่ถูกต้อง (เหลือ '.(5-$attempts).' ครั้ง)';
        $raw['users'][$actorIndex]=$owner;
        audit_permission($raw,$actor,'user_card_archive_pin_failed',['user_id'=>$targetId,'branch_id'=>$branchId,'attempts'=>$attempts]);
        return $raw;
    }

    $owner['admin_archive_pin_failed_attempts']=0;$owner['admin_archive_pin_locked_until']=null;
    $raw['users'][$actorIndex]=$owner;
    $raw['users'][$targetIndex]['active']=0;
    $raw['users'][$targetIndex]['deleted_at']=date('c');
    $raw['users'][$targetIndex]['deleted_by']=$actorId;
    $raw['users'][$targetIndex]['deleted_branch_id']=$branchId;
    $raw['users'][$targetIndex]['trusted_devices']=[];
    $raw['users'][$targetIndex]['password_hash']=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
    if(is_array($raw['branch_data']??null)){
        foreach($raw['branch_data'] as &$branchData){
            foreach(['employees','prs'] as $bucket){
                if(!is_array($branchData[$bucket]??null))continue;
                foreach($branchData[$bucket] as &$row)if(is_array($row)&&(int)($row['user_id']??0)===$targetId)$row['user_id']=null;
                unset($row);
            }
        }
        unset($branchData);
    }
    audit_permission($raw,$actor,'user_card_archived',['user_id'=>$targetId,'username'=>(string)($target['username']??''),'branch_id'=>$branchId,'links_unlinked'=>true]);
    return $raw;
}
