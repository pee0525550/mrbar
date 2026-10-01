<?php
declare(strict_types=1);
require __DIR__.'/../app/auth-state.php';

function expect_auth(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
}
$now=time();$token=str_repeat('a',64);
$user=['id'=>7,'username'=>'staff','display_name'=>'Staff','role'=>'staff','active'=>1,'password_hash'=>password_hash('test-password',PASSWORD_DEFAULT),'trusted_devices'=>[
    ['token_hash'=>hash('sha256',$token),'pin_hash'=>password_hash('123456',PASSWORD_DEFAULT),'created_at'=>date('c',$now-60),'failed_attempts'=>0,'locked_until'=>null],
]];
$data=['settings'=>['trusted_device_days'=>'30'],'users'=>[$user]];
for($attempt=1;$attempt<=5;$attempt++){
    $result=auth_pin_attempt($data,$token,'999999',$now);
    expect_auth(!$result['ok'],'Wrong PIN must fail');
    expect_auth($data['users'][0]['trusted_devices'][0]['failed_attempts']===($attempt===5?0:$attempt),'Failed attempts must persist in the original device record');
}
expect_auth(strtotime($data['users'][0]['trusted_devices'][0]['locked_until'])===$now+900,'Five failures must lock the device for 15 minutes');
expect_auth(!auth_pin_attempt($data,$token,'123456',$now+899)['ok'],'Correct PIN cannot bypass lockout');
expect_auth(auth_pin_attempt($data,$token,'123456',$now+900)['ok'],'Correct PIN succeeds when lockout expires');
expect_auth($data['users'][0]['trusted_devices'][0]['locked_until']===null,'Successful PIN clears the lock');
expect_auth(strtotime($data['users'][0]['trusted_devices'][0]['last_used_at'])===$now+900,'Successful PIN updates the persisted last-used time');
$data['users'][0]['trusted_devices']=[];
expect_auth(!auth_pin_attempt($data,$token,'123456',$now)['ok'],'Revoked device must not authenticate from a stale snapshot');
$data['users'][0]=$user;$data['users'][0]['trusted_devices'][0]['created_at']=date('c',$now-30*86400);
expect_auth(auth_remembered_device($data,$token,$now)===null,'Legacy device expires at the configured age');
$data['users'][0]=$user;$data['users'][0]['trusted_devices'][0]['expires_at']=date('c',$now);
expect_auth(auth_remembered_device($data,$token,$now)===null,'Explicit device expiration is enforced');
$data['users'][0]=$user;$data['settings']['trusted_device_days']='1';$data['users'][0]['trusted_devices'][0]['created_at']=date('c',$now-86400);
expect_auth(auth_remembered_device($data,$token,$now)===null,'A shorter device policy takes effect on old devices');
unset($data['settings']);
expect_auth(!auth_pin_attempt($data,$token,'123456',$now,1)['ok'],'Global PIN transaction must honor the active branch policy');
$data['users'][0]=$user;
$session=auth_session_account($data,['id'=>7,'active'=>1,'role'=>'admin','super_admin'=>true]);
expect_auth($session!==null&&$session['role']==='staff','Session refresh uses the current account role');
expect_auth($session['super_admin']===false,'Session must not retain a removed super-admin flag');
expect_auth(!isset($session['password_hash']),'Session refresh does not expose the password hash');
$data['users'][0]['password_hash']=password_hash('changed-password',PASSWORD_DEFAULT);
expect_auth(auth_session_account($data,$session)===null,'Password changes revoke established sessions');
$data['users'][0]['password_changed_at']=date('c',$now);
expect_auth(auth_session_account($data,['id'=>7,'active'=>1])===null,'Legacy sessions cannot bypass a password reset');
$data['users'][0]=$user;$data['users'][0]['active']=0;
expect_auth(auth_session_account($data,$session)===null,'Disabled account session is rejected');
$data['users'][0]=$user;$data['users'][0]['deleted_at']=date('c',$now);
expect_auth(auth_session_account($data,$session)===null,'Archived account session is rejected');
$data['users']=[];
expect_auth(auth_session_account($data,$session)===null,'Deleted account cannot reuse a session');
expect_auth(!auth_csrf_valid('',''),'Missing CSRF tokens must not compare as valid');
expect_auth(!auth_csrf_valid('expected','wrong'),'Mismatched CSRF token is rejected');
expect_auth(auth_csrf_valid('expected','expected'),'Matching nonempty CSRF tokens are accepted');
echo "Auth session and PIN regression passed.\n";
