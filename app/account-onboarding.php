<?php
declare(strict_types=1);

function account_employee_role(array $employee): string {
    return function_exists('workforce_recommended_account_role')?workforce_recommended_account_role($employee):(((string)($employee['position']??'')==='sales')?'sales':((($employee['position']??'')==='pr'||!empty($employee['pr_id']))?'pr':'staff'));
}
function account_username_valid(string $username): bool {
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username);
}
function account_invite_token(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
}
function account_invite_hash(string $token): string { return hash('sha256',$token); }
function account_find_invite(array $d,string $token): ?array {
    if($token===''||strlen($token)<32)return null;$hash=account_invite_hash($token);
    foreach($d['account_invites']??[] as $row)if(hash_equals((string)($row['token_hash']??''),$hash))return $row;
    return null;
}
function account_invite_is_valid(array $invite): bool {
    if(!empty($invite['used_at'])||!empty($invite['revoked_at']))return false;
    $exp=strtotime((string)($invite['expires_at']??''));return $exp!==false&&$exp>=time();
}
function account_active_invite_for_employee(array $d,int $employeeId): ?array {
    $rows=[];foreach($d['account_invites']??[] as $r)if((int)($r['employee_id']??0)===$employeeId&&account_invite_is_valid($r))$rows[]=$r;
    if(!$rows)return null;usort($rows,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return $rows[0];
}
function account_request_base_url(): string {
    $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
    $host=(string)($_SERVER['HTTP_HOST']??'');$host=preg_replace('/[^A-Za-z0-9.\-:\[\]]/','',$host)?:'localhost';
    $script=(string)($_SERVER['SCRIPT_NAME']??'/it/employees.php');$dir=rtrim(str_replace('\\','/',dirname($script)),'/');if($dir==='.'||$dir==='/')$dir='';
    return ($https?'https':'http').'://'.$host.$dir;
}
function account_invite_url(string $token): string { return account_request_base_url().'/employee-activate.php?token='.rawurlencode($token); }
function account_time_url(): string { return account_request_base_url().'/time.php'; }
function account_create_and_link(array &$d,int $employeeId,string $username,string $password): array {
    $username=trim($username);if(!account_username_valid($username))throw new RuntimeException('Username ใช้ A-Z, 0-9, จุด, ขีดกลาง หรือ _ จำนวน 3-40 ตัว');
    if(strlen($password)<8)throw new RuntimeException('Password ต้องมีอย่างน้อย 8 ตัวอักษร');
    $idx=null;foreach($d['employees']??[] as $i=>$e)if((int)($e['id']??0)===$employeeId){$idx=$i;break;}if($idx===null)throw new RuntimeException('ไม่พบพนักงาน');
    $employee=$d['employees'][$idx];if(empty($employee['active']))throw new RuntimeException('พนักงานคนนี้ถูกปิดใช้งาน');if(!empty($employee['user_id']))throw new RuntimeException('พนักงานคนนี้มีบัญชีเข้าสู่ระบบแล้ว');
    foreach($d['users']??[] as $x)if(strcasecmp((string)($x['username']??''),$username)===0)throw new RuntimeException('Username นี้ถูกใช้แล้ว');
    $role=account_employee_role($employee);$uid=next_id($d['users']??[]);
    $user=['id'=>$uid,'username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'display_name'=>(string)($employee['name']??$username),'active'=>1,'created_at'=>date('c'),'trusted_devices'=>[],'permission_overrides'=>[],'super_admin'=>0];
    $d['users'][]=$user;$d['employees'][$idx]['user_id']=$uid;$d['employees'][$idx]['updated_at']=date('c');
    $prId=(int)($employee['pr_id']??0);if($prId>0){foreach($d['prs'] as &$p)if((int)($p['id']??0)===$prId){$p['user_id']=$uid;break;}unset($p);}
    return $user;
}
function account_reset_password(array &$d,int $userId,string $password): void {
    if(strlen($password)<8)throw new RuntimeException('Password ต้องมีอย่างน้อย 8 ตัวอักษร');
    foreach($d['users'] as &$x)if((int)($x['id']??0)===$userId){$x['password_hash']=password_hash($password,PASSWORD_DEFAULT);$x['trusted_devices']=[];$x['password_changed_at']=date('c');unset($x);return;}unset($x);throw new RuntimeException('ไม่พบ User Account');
}
