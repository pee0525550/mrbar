<?php
declare(strict_types=1);

function auth_credential_version(array $user): string {
    return hash('sha256',(string)($user['password_hash']??''));
}

function auth_session_account(array $data,array $session): ?array {
    $userId=(int)($session['id']??0);
    if($userId<=0)return null;
    foreach($data['users']??[] as $user){
        if((int)($user['id']??0)!==$userId)continue;
        if(empty($user['active'])||!empty($user['deleted_at']))return null;
        $version=auth_credential_version($user);
        if(isset($session['credential_version'])){
            if(!hash_equals($version,(string)$session['credential_version']))return null;
        }elseif(!empty($user['password_changed_at'])){
            // Legacy sessions cannot prove that they authenticated after a password reset.
            return null;
        }
        foreach(['username','role','display_name','super_admin','branch_ids','active','deleted_at'] as $key){
            $session[$key]=$user[$key]??(['super_admin'=>false,'branch_ids'=>[1],'deleted_at'=>null][$key]??'');
        }
        $session['credential_version']=$version;
        return $session;
    }
    return null;
}

function auth_csrf_valid(string $expected,string $provided): bool {
    return $expected!==''&&$provided!==''&&hash_equals($expected,$provided);
}

function auth_remembered_device(array $data,string $token,?int $now=null,?int $deviceDays=null): ?array {
    if(strlen($token)<32||strlen($token)>256)return null;
    $now=$now??time();$hash=hash('sha256',$token);
    $days=max(1,min(365,$deviceDays??(int)($data['settings']['trusted_device_days']??30)));
    foreach($data['users']??[] as $userIndex=>$user){
        if(empty($user['active'])||!empty($user['deleted_at']))continue;
        foreach($user['trusted_devices']??[] as $deviceIndex=>$device){
            if(!hash_equals((string)($device['token_hash']??''),$hash))continue;
            $created=strtotime((string)($device['created_at']??''));
            if($created===false||$created>$now||$created+$days*86400<=$now)return null;
            if(isset($device['expires_at'])){
                $expires=strtotime((string)$device['expires_at']);
                if($expires===false||$expires<=$now)return null;
            }
            return ['user'=>$user,'user_index'=>$userIndex,'device'=>$device,'device_index'=>$deviceIndex,'token_hash'=>$hash];
        }
    }
    return null;
}

function auth_pin_attempt(array &$data,string $token,string $pin,?int $now=null,?int $deviceDays=null): array {
    if(!preg_match('/^\d{6}$/D',$pin))return ['ok'=>false,'message'=>'กรุณากรอก PIN 6 หลัก'];
    $now=$now??time();$remembered=auth_remembered_device($data,$token,$now,$deviceDays);
    if(!$remembered)return ['ok'=>false,'message'=>'ไม่พบอุปกรณ์ที่จดจำ กรุณาเข้าสู่ระบบด้วยรหัสผ่าน'];
    $device=&$data['users'][$remembered['user_index']]['trusted_devices'][$remembered['device_index']];
    $lockedUntil=strtotime((string)($device['locked_until']??''))?:0;
    if($lockedUntil>$now){
        $minutes=max(1,(int)ceil(($lockedUntil-$now)/60));
        return ['ok'=>false,'message'=>'PIN ถูกล็อกชั่วคราว กรุณารอประมาณ '.$minutes.' นาที'];
    }
    if(!password_verify($pin,(string)($device['pin_hash']??''))){
        $attempts=(int)($device['failed_attempts']??0)+1;
        $device['failed_attempts']=$attempts;
        if($attempts>=5){$device['locked_until']=date('c',$now+900);$device['failed_attempts']=0;}
        return ['ok'=>false,'message'=>$attempts>=5?'PIN ไม่ถูกต้องครบ 5 ครั้ง กรุณารอ 15 นาที':'PIN ไม่ถูกต้อง'];
    }
    $device['failed_attempts']=0;$device['locked_until']=null;$device['last_used_at']=date('c',$now);
    return ['ok'=>true,'user'=>$data['users'][$remembered['user_index']]];
}
