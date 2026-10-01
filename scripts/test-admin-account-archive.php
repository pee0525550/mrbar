<?php
declare(strict_types=1);

require __DIR__.'/../app/db.php';
require __DIR__.'/../app/branches.php';
require __DIR__.'/../app/admin-account-actions.php';

function expect_archive(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
    fwrite(STDOUT,"PASS: {$message}\n");
}
function archive_fixture(): array {
    return [
        'users'=>[
            ['id'=>1,'username'=>'admin','role'=>'admin','active'=>1,'super_admin'=>1,'branch_ids'=>[1],'trusted_devices'=>[['pin_hash'=>password_hash('123456',PASSWORD_DEFAULT)]],'admin_archive_pin_failed_attempts'=>0],
            ['id'=>2,'username'=>'staff','role'=>'staff','active'=>1,'branch_ids'=>[1,2],'trusted_devices'=>[['token_hash'=>'keep-me']]],
        ],
        'branch_data'=>[
            '1'=>['employees'=>[['id'=>10,'user_id'=>2]],'prs'=>[['id'=>20,'user_id'=>2]]],
            '2'=>['employees'=>[['id'=>11,'user_id'=>2]],'prs'=>[['id'=>21,'user_id'=>2]]],
        ],
        'audit'=>[],
    ];
}
$actor=['id'=>1,'role'=>'admin','super_admin'=>1,'active'=>1];
$error='';$result=admin_account_archive_attempt(archive_fixture(),2,$actor,1,'123456',$error);
expect_archive($error===''&&count($result['users'])===2,'archive keeps account row and ID reserved');
expect_archive(!empty($result['users'][1]['deleted_at'])&&empty($result['users'][1]['active']),'valid admin PIN archives and disables account');
expect_archive($result['users'][1]['trusted_devices']===[],'archive revokes trusted devices');
expect_archive(empty($result['branch_data']['1']['employees'][0]['user_id'])&&empty($result['branch_data']['2']['employees'][0]['user_id']),'employee login links are removed from every branch');
expect_archive(empty($result['branch_data']['1']['prs'][0]['user_id'])&&empty($result['branch_data']['2']['prs'][0]['user_id']),'PR login links are removed from every branch');
expect_archive(($result['audit'][0]['action']??'')==='user_card_archived','archive is recorded in audit');

$error='';$failed=admin_account_archive_attempt(archive_fixture(),2,$actor,1,'000000',$error);
expect_archive($error==='Admin PIN ไม่ถูกต้อง (เหลือ 4 ครั้ง)'&&(int)$failed['users'][0]['admin_archive_pin_failed_attempts']===1,'wrong PIN is rejected and attempt counter persists');
$locked=archive_fixture();
for($i=0;$i<5;$i++)$locked=admin_account_archive_attempt($locked,2,$actor,1,'000000',$error);
expect_archive(str_contains($error,'ล็อกการยืนยัน 15 นาที')&&!empty($locked['users'][0]['admin_archive_pin_locked_until']),'five wrong PIN attempts trigger a lockout');

$error='';
try{admin_account_archive_attempt(archive_fixture(),1,$actor,1,'123456',$error);expect_archive(false,'self archive is rejected');}
catch(RuntimeException $e){expect_archive(str_contains($e->getMessage(),'บัญชีที่กำลังใช้งาน'),'self archive is rejected');}

$last=archive_fixture();$last['users'][1]['super_admin']=1;$last['users'][1]['role']='admin';
$regularAdmin=['id'=>3,'role'=>'admin','active'=>1,'super_admin'=>0,'branch_ids'=>[1],'trusted_devices'=>[['pin_hash'=>password_hash('123456',PASSWORD_DEFAULT)]]];
$last['users'][]=$regularAdmin;
$error='';
try{admin_account_archive_attempt($last,2,$regularAdmin,1,'123456',$error);expect_archive(false,'regular admin cannot archive a super admin');}
catch(RuntimeException $e){expect_archive(str_contains($e->getMessage(),'Super Admin'),'regular admin cannot archive a super admin');}

echo "Admin account archive tests passed\n";
