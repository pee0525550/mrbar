<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/customer-integrations.php';
$user=current_user();
if(!$user){header('Location: login.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if((string)($_POST['action']??'')==='change_password'){
        $current=(string)($_POST['current_password']??'');
        $new=(string)($_POST['new_password']??'');
        $repeat=(string)($_POST['repeat_password']??'');
        if(strlen($new)<10)$error='รหัสผ่านใหม่ต้องมีอย่างน้อย 10 ตัวอักษร';
        elseif($new!==$repeat)$error='ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
        elseif(hash_equals($current,$new))$error='รหัสผ่านใหม่ต้องต่างจากรหัสผ่านเดิม';
        else{
            try{
                db_mutate_global(function(array $data)use($user,$current,$new):array{
                    foreach($data['users'] as &$account){
                        if((int)($account['id']??0)!==(int)$user['id'])continue;
                        if(!password_verify($current,(string)($account['password_hash']??'')))throw new RuntimeException('invalid_password');
                        $account['password_hash']=password_hash($new,PASSWORD_DEFAULT);
                        $account['password_changed_at']=date('c');
                        $account['trusted_devices']=[];
                        $data['audit'][]=['at'=>date('c'),'action'=>'own_password_changed','by'=>(int)$user['id']];
                        return $data;
                    }unset($account);
                    throw new RuntimeException('account_not_found');
                });
                header('Location: account.php?password_changed=1');exit;
            }catch(Throwable $exception){$error=$exception->getMessage()==='invalid_password'?'รหัสผ่านปัจจุบันไม่ถูกต้อง':'เปลี่ยนรหัสผ่านไม่สำเร็จ กรุณาลองอีกครั้ง';}
        }
    }else{$error='คำสั่งไม่ถูกต้อง';}
}
$data=db_load_global();$account=null;
foreach($data['users']??[] as $row)if((int)($row['id']??0)===(int)$user['id']){$account=$row;break;}
if(!$account||empty($account['active'])||!empty($account['deleted_at'])){header('Location: logout.php');exit;}
$employee=workforce_employee_by_user($data,(int)$user['id']);
$profile=is_array($employee['profile']??null)?$employee['profile']:[];
$lineLinked=trim((string)($account['line_user_id']??''))!=='';
$lineReady=mrbar_line_login_config($data)['ready'];
$home=role_home((string)($user['role']??''));
$isAdmin=($user['role']??'')==='admin';
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#080d17"><title>บัญชีของฉัน · MR BAR</title><link rel="stylesheet" href="assets/admin.css?v=14902"><link rel="stylesheet" href="assets/admin-v14.css?v=14902"><link rel="stylesheet" href="assets/account-v14902.css?v=14902"></head><body class="admin-v14-page account-page">
<?php if($isAdmin){require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('account',$user);}?>
<main class="account-main"><header class="account-header"><div><small>MY ACCOUNT</small><h1>บัญชีของฉัน</h1><p>ข้อมูลเข้าสู่ระบบและการเชื่อมต่อส่วนตัว</p></div><a href="<?=h($home)?>">กลับหน้าหลัก</a></header>
<?php if($error!==''):?><p class="account-alert error" role="alert"><?=h($error)?></p><?php endif;?><?php if(isset($_GET['password_changed'])):?><p class="account-alert success" role="status">เปลี่ยนรหัสผ่านแล้ว อุปกรณ์ที่เคยตั้ง PIN ต้องตั้งใหม่เมื่อเข้าสู่ระบบครั้งถัดไป</p><?php endif;?>
<div class="account-layout"><section class="account-panel account-identity"><div class="account-avatar" aria-hidden="true"><?=h(mb_substr((string)($account['display_name']??$account['username']??'U'),0,1))?></div><div><small>บัญชีผู้ใช้</small><h2><?=h((string)($account['display_name']??''))?></h2><p>@<?=h((string)($account['username']??''))?></p></div><dl><div><dt>Role</dt><dd><?=h(strtoupper((string)($account['role']??'')))?></dd></div><div><dt>สถานะ</dt><dd><?=!empty($account['active'])?'ใช้งาน':'ปิดใช้งาน'?></dd></div><?php if($employee):?><div><dt>รหัสพนักงาน</dt><dd><?=h((string)($employee['code']??''))?></dd></div><div><dt>ตำแหน่ง</dt><dd><?=h((string)($employee['position']??''))?></dd></div><?php endif;?></dl></section>
<section class="account-panel"><small>CONNECTED ACCOUNTS</small><h2>LINE ของฉัน</h2><p><?=$lineLinked?'เชื่อม LINE กับบัญชีนี้แล้ว':'ยังไม่ได้เชื่อม LINE กับบัญชีนี้'?></p><?php if($lineLinked):?><p class="account-meta">เชื่อมเมื่อ <?=h((string)($account['line_linked_at']??'-'))?></p><?php endif;?><?php if($lineReady):?><a class="account-action" href="line-link.php?flow=link"><?=$lineLinked?'จัดการการเชื่อม LINE':'เชื่อม LINE ของฉัน'?></a><?php else:?><p class="account-alert error">LINE Login ยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ</p><?php endif;?></section>
<section class="account-panel"><small>SECURITY</small><h2>เปลี่ยนรหัสผ่าน</h2><form method="post" class="account-password-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="change_password"><label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" autocomplete="current-password" required></label><label>รหัสผ่านใหม่<input type="password" name="new_password" autocomplete="new-password" minlength="10" required></label><label>ยืนยันรหัสผ่านใหม่<input type="password" name="repeat_password" autocomplete="new-password" minlength="10" required></label><button type="submit">บันทึกรหัสผ่านใหม่</button></form><p class="account-meta">หลังเปลี่ยนรหัสผ่าน ระบบจะยกเลิกอุปกรณ์ที่เคยตั้ง PIN</p></section>
<?php if($employee):?><section class="account-panel"><small>EMPLOYEE RECORD</small><h2>ข้อมูลพนักงาน</h2><dl><div><dt>ชื่อที่แสดง</dt><dd><?=h((string)($profile['display_name']??$employee['name']??''))?></dd></div><div><dt>โทรศัพท์</dt><dd><?=h((string)($profile['phone']??'-'))?></dd></div><div><dt>อีเมล</dt><dd><?=h((string)($profile['email']??'-'))?></dd></div><div><dt>สาขา</dt><dd><?=h((string)($employee['branch_id']??'-'))?></dd></div></dl><p class="account-meta">ข้อมูลการจ้างงานและสิทธิ์แก้ไขโดยผู้ดูแลใน Employee Center</p><?php if($isAdmin):?><a class="account-secondary" href="employees.php">เปิด Employee Center</a><?php endif;?></section><?php endif;?></div></main><?php if($isAdmin):?><script src="assets/admin.js?v=14902"></script><?php endif;?></body></html>
