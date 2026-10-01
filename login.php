<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: same-origin');
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/customer-integrations.php';
$returnKey=login_return_key();
$pwaMode=$returnKey==='time'||(string)($_GET['pwa']??'')==='1';
$linkAfterLogin=(string)($_POST['line_link']??$_GET['line_link']??'')==='1';
$lineQuery=['flow'=>'staff_login'];if($returnKey!=='')$lineQuery['return_to']=$returnKey;
$lineLoginUrl='line-link.php?'.http_build_query($lineQuery);
$lineReady=mrbar_line_login_config()['ready'];
if($current=current_user()){header('Location: '.($linkAfterLogin?'line-link.php?flow=link':login_success_target($current,$returnKey)));exit;}
$error='';$action='';$message=isset($_GET['forgot'])?'ลืมอุปกรณ์นี้แล้ว กรุณาเข้าสู่ระบบใหม่':'';
$data=db_load();$remembered=remembered_device($data);
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();$action=(string)($_POST['action']??'password');
    if($action==='forget_device'){
        forget_current_device();header('Location: login.php'.login_return_query($returnKey,['forgot'=>1]));exit;
    }
    if($action==='pin'){
        $result=pin_login(trim((string)($_POST['pin']??'')));
        if(!empty($result['ok'])){header('Location: '.($linkAfterLogin?'line-link.php?flow=link':login_success_target((array)$result['user'],$returnKey)));exit;}
        $error=(string)$result['message'];$data=db_load();$remembered=remembered_device($data);
    }else{
        $username=trim((string)($_POST['username']??''));$account=null;
        foreach($data['users'] as $row)if(strtolower((string)$row['username'])===strtolower($username)&&!empty($row['active'])&&empty($row['deleted_at'])){$account=$row;break;}
        if($account&&password_verify((string)($_POST['password']??''),(string)$account['password_hash'])){
            establish_session($account);
            if(!empty($_POST['remember_device'])&&!$linkAfterLogin){$_SESSION['needs_pin_setup']=1;header('Location: setup-pin.php'.login_return_query($returnKey));exit;}
            header('Location: '.($linkAfterLogin?'line-link.php?flow=link':login_success_target($account,$returnKey)));exit;
        }
        $error='ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
$passwordVisible=!$remembered||$action==='password'||$linkAfterLogin;
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0a1119"><title>เข้าสู่ระบบ · MR BAR</title><link rel="stylesheet" href="assets/line-login-v14901.css?v=14901"><link rel="stylesheet" href="assets/line-auth-theme-v14901.css?v=14901"><link rel="stylesheet" href="assets/login-experience-v14904.css?v=14904"><link rel="stylesheet" href="assets/login-pin-v14905.css?v=14905"></head>
<body><div class="auth-shell"><section class="auth-story"><header class="auth-brand"><span class="brand-mark">M</span><div><strong>MR BAR</strong><small><?=$pwaMode?'TIME STAFF':'STAFF PORTAL'?></small></div></header><div class="auth-story-content"><small class="auth-story-kicker">STAFF OPERATIONS</small><h2>พร้อมทำงาน<br>ในทุกกะ</h2><p>ระบบจัดการงานและเวลาสำหรับทีม MR BAR</p><div class="auth-depth-scene" aria-hidden="true"><div class="auth-depth-grid"></div><div class="auth-depth-rail rail-one"></div><div class="auth-depth-rail rail-two"></div><div class="auth-depth-panel depth-back"><span>TEAM SCHEDULE</span><b>ปฏิทินพนักงาน</b><i>▦</i></div><div class="auth-depth-panel depth-front"><span>MR BAR TIME</span><b>ลงเวลาเข้า-ออก</b><i>◷</i></div><div class="auth-depth-chip">SECURE STAFF PORTAL</div></div><div class="auth-story-footer"><span>TIME</span><i></i><span>PEOPLE</span><i></i><span>OPERATIONS</span></div></div></section>
<main class="auth-main"><div class="auth-heading"><span>STAFF ACCESS</span><h1><?=$linkAfterLogin?'เข้าสู่ระบบเพื่อเชื่อม LINE':'เข้าสู่ระบบ'?></h1><p><?=$linkAfterLogin?'ใช้บัญชีพนักงานเดิมก่อนเชื่อม LINE ของคุณ':'เลือกวิธีเข้าใช้งานที่สะดวกสำหรับคุณ'?></p></div>
<?php if($error):?><div class="auth-notice error" role="alert"><?=h($error)?></div><?php endif;?>
<?php if($message):?><div class="auth-notice success" role="status"><?=h($message)?></div><?php endif;?>
<?php if(!$linkAfterLogin):?>
<?php if($lineReady):?><a class="auth-line-button" href="<?=h($lineLoginUrl)?>"><span class="auth-line-symbol" aria-hidden="true">L</span><span>เข้าสู่ระบบด้วย LINE</span><span aria-hidden="true">→</span></a>
<?php else:?><div class="auth-line-unavailable">LINE Login ยังไม่พร้อมใช้งาน ใช้รหัสผ่านหรือ PIN ได้ตามปกติ</div><?php endif;?>
<?php endif;?>
<div class="auth-method"><span><?=$remembered&&!$linkAfterLogin?'หรือใช้ PIN บนอุปกรณ์นี้':'หรือใช้บัญชีพนักงาน'?></span></div>
<?php if($remembered&&!$linkAfterLogin):$known=$remembered['user'];?><section class="auth-pane <?=$passwordVisible?'is-hidden':''?>" id="pinPane"><div class="auth-account"><span class="auth-avatar">M</span><div><strong><?=h((string)$known['display_name'])?></strong><small>@<?=h((string)$known['username'])?></small></div><em>อุปกรณ์ที่ไว้ใจ</em></div><form method="post" id="pinForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="pin"><?php if($returnKey!==''):?><input type="hidden" name="return_to" value="<?=h($returnKey)?>"><?php endif;?><label id="pinLabel" for="pin">PIN 6 หลัก</label><div class="pin-entry" id="pinEntry" data-invalid="<?=$action==='pin'&&$error?'1':'0'?>"><input id="pin" name="pin" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" required aria-labelledby="pinLabel" aria-describedby="pinFeedback"><div class="pin-slots" aria-hidden="true"><?php for($slot=0;$slot<6;$slot++):?><span class="pin-slot"><i></i></span><?php endfor;?></div></div><div class="pin-feedback" id="pinFeedback" role="status" aria-live="polite"><?=$action==='pin'&&$error?'รหัสไม่ถูกต้อง กรุณาลองอีกครั้ง':'กรอก PIN 6 หลักเพื่อเข้าสู่ระบบ'?></div><button class="auth-submit pin-submit" type="submit">เข้าใช้งาน</button></form><div class="auth-small-actions"><button type="button" data-show-password>ใช้รหัสผ่าน</button><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="forget_device"><button type="submit">ลืมอุปกรณ์นี้</button></form></div></section><?php endif;?>
<section class="auth-pane <?=$passwordVisible?'':'is-hidden'?>" id="passwordPane"><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="password"><?php if($linkAfterLogin):?><input type="hidden" name="line_link" value="1"><?php endif;?><?php if($returnKey!==''):?><input type="hidden" name="return_to" value="<?=h($returnKey)?>"><?php endif;?><label for="username">ชื่อผู้ใช้</label><input id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="Username"><label for="password">รหัสผ่าน</label><div class="auth-password"><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Password"><button type="button" data-password-toggle aria-label="แสดงรหัสผ่าน">ดู</button></div><?php if(!$linkAfterLogin):?><label class="auth-remember"><input type="checkbox" name="remember_device" value="1"><span>จำอุปกรณ์นี้เพื่อตั้ง PIN หลังเข้าสู่ระบบ</span></label><?php endif;?><button class="auth-submit" type="submit"><?=$linkAfterLogin?'เข้าสู่ระบบและไปเชื่อม LINE':'เข้าสู่ระบบด้วยรหัสผ่าน'?></button></form><?php if($remembered&&!$linkAfterLogin):?><button type="button" class="auth-switch" data-show-pin>กลับไปใช้ PIN</button><?php endif;?></section>
<p class="auth-help">ยังไม่ได้ผูก LINE? เข้าด้วยรหัสผ่านก่อน แล้วเลือก “เชื่อม LINE ของฉัน” ในหน้าพนักงาน</p></main>
<footer class="auth-footer"><a href="custumers/">หน้าลูกค้า</a><span>MR BAR · v<?=h((string)(app_config()['version']??''))?></span></footer></div><script src="assets/line-login-v14901.js?v=14901" defer></script><script src="assets/login-pin-v14905.js?v=14905" defer></script></body></html>
