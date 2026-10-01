<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: same-origin');
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/customer-integrations.php';
$liffStateParams=[];
$liffStateValue=(string)($_GET['liff.state']??$_GET['liff_state']??'');
if($liffStateValue!==''){
    $liffState=$liffStateValue;
    $stateQuery=parse_url($liffState,PHP_URL_QUERY);
    if(is_string($stateQuery))parse_str($stateQuery,$liffStateParams);
}
$requested=(string)($_GET['flow']??$liffStateParams['flow']??'');
$callback=isset($_GET['code'])||$liffStateValue!==''||isset($_GET['state']);
$flow=$requested!==''?$requested:($callback?(string)($_SESSION['mrbar_line_flow']??'link'):'link');
if(!in_array($flow,['link','staff_login','customer_booking'],true))$flow='link';
$_SESSION['mrbar_line_flow']=$flow;
if(array_key_exists('return_to',$_GET)||array_key_exists('return_to',$liffStateParams)||($requested!==''&&!$callback))$_SESSION['mrbar_line_return']=(string)($_GET['return_to']??$liffStateParams['return_to']??'');
$returnCandidate=(string)($_GET['return_to']??$liffStateParams['return_to']??($callback?($_SESSION['mrbar_line_return']??''):''));
$user=current_user();
if($flow==='link'&&!$user){header('Location: login.php?line_link=1');exit;}
$config=mrbar_line_login_config();
$customerOa=mrbar_line_customer_oa_config();
$lineLinked=false;
if($flow==='link'&&$user){
    foreach(db_load_global()['users']??[] as $account){
        if((int)($account['id']??0)===(int)$user['id']){$lineLinked=(string)($account['line_user_id']??'')!=='';break;}
    }
}
$returnKey=in_array($returnCandidate,['time','income','sales_table'],true)?$returnCandidate:'';
$reservationReturn=mrbar_customer_reservation_return_url($returnCandidate);
$titles=['staff_login'=>'เข้าสู่ระบบด้วย LINE','customer_booking'=>'ยืนยัน LINE ก่อนจองโต๊ะ','link'=>'เชื่อม LINE ของฉัน'];
$descriptions=['staff_login'=>'ใช้ LINE ที่เคยผูกกับบัญชีพนักงานของคุณ','customer_booking'=>'ยืนยันบัญชี LINE เพื่อดำเนินการจองโต๊ะ ระบบจะพากลับไปยังแบบฟอร์มจอง','link'=>'เชื่อม LINE กับบัญชีพนักงานที่กำลังใช้งาน เพื่อเข้าระบบได้สะดวกขึ้น'];
$endpoints=['staff_login'=>'api/line-login.php','customer_booking'=>'api/customer-line-auth.php','link'=>'api/line-link.php'];
$title=$titles[$flow];
$description=$descriptions[$flow];
$endpoint=$endpoints[$flow];
$returnValue=$flow==='staff_login'?$returnKey:($flow==='customer_booking'?$reservationReturn:'');
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#080d17">
<base href="<?=h(branch_public_base())?>/">
<title><?=h($title)?> · MR BAR</title>
<link rel="stylesheet" href="assets/line-flow-v14901.css?v=14901">
<link rel="stylesheet" href="assets/line-auth-theme-v14901.css?v=14901">
<link rel="stylesheet" href="assets/line-link-experience-v14904.css?v=14904">
<link rel="stylesheet" href="assets/line-customer-oa-v14910.css?v=14915">
</head>
<body><main class="line-link-shell line-flow-page flow-<?=h($flow)?>">
<?php if($flow==='link'):?><aside class="line-link-story" aria-label="ขั้นตอนเชื่อมบัญชี"><a class="line-story-brand" href="account.php"><span class="line-brand-mark">M</span><span>MR BAR<small>STAFF ACCOUNT</small></span></a><div class="line-story-copy"><small>ONE ACCOUNT, CONNECTED</small><h2>บัญชีงานของคุณ<br>พร้อมไปกับ LINE</h2><p>เชื่อม LINE ส่วนตัวกับบัญชีพนักงานที่กำลังใช้งาน เพื่อเข้าสู่ระบบได้สะดวกขึ้น</p></div><div class="line-connection-art" aria-hidden="true"><div class="line-art-orbit orbit-one"></div><div class="line-art-orbit orbit-two"></div><div class="line-art-card card-account"><span class="line-art-avatar"><?=h(mb_substr((string)($user['display_name']??'U'),0,1))?></span><span><small>MR BAR ACCOUNT</small><b>@<?=h((string)($user['username']??''))?></b></span><i>✓</i></div><div class="line-art-link"><span></span><b>SECURE LINK</b><span></span></div><div class="line-art-card card-line"><span class="line-symbol">L</span><span><small>LINE IDENTITY</small><b><?=$lineLinked?'CONNECTED':'READY TO CONNECT'?></b></span></div></div><ol class="line-steps-mini"><li class="done"><span>01</span><div><b>บัญชีพนักงาน</b><small>กำลังใช้งานในชื่อคุณ</small></div></li><li class="<?=$lineLinked?'done':'current'?>"><span>02</span><div><b>ยืนยัน LINE</b><small><?=$lineLinked?'เชื่อมบัญชีเรียบร้อย':'ยืนยันผ่าน LINE อย่างปลอดภัย'?></small></div></li><li class="<?=$lineLinked?'current':''?>"><span>03</span><div><b>พร้อมเข้าสู่ระบบ</b><small>ใช้ LINE ในครั้งถัดไป</small></div></li></ol><div class="line-story-foot"><span class="secure-dot"></span> การเชื่อมต่อปลอดภัย · สิทธิ์การใช้งานยังยึดตามบัญชีพนักงาน</div></aside><?php endif;?>
<section class="line-link-panel" data-line-flow="<?=h($flow)?>" data-line-liff-id="<?=h($config['liff_id'])?>" data-line-channel-id="<?=h($config['channel_id'])?>" data-line-csrf="<?=h(csrf_token())?>" data-line-endpoint="<?=h($endpoint)?>" data-line-return="<?=h($returnValue)?>" data-line-oa-configured="<?=$customerOa['configured']?'1':'0'?>">
<div class="line-panel-top"><small><?=h($flow==='customer_booking'?'CUSTOMER BOOKING':($flow==='staff_login'?'STAFF LOGIN':'MY ACCOUNT'))?> / LINE</small><?php if($flow==='link'&&$lineLinked):?><span class="line-connected-badge"><i></i> เชื่อมแล้ว</span><?php endif;?></div><h1><?=h($title)?></h1><p class="line-panel-description"><?=h($description)?></p>
<?php if($flow==='link'):?><div class="line-link-account"><span class="line-account-avatar"><?=h(mb_substr((string)($user['display_name']??'U'),0,1))?></span><span><small>บัญชีพนักงานที่กำลังใช้งาน</small><strong><?=h((string)($user['display_name']??''))?> <em>@<?=h((string)($user['username']??''))?></em></strong></span><b class="account-check">✓</b></div><?php endif;?>
<?php if(!$config['ready']):?><p class="line-link-status" data-state="error">LINE Login ยังไม่พร้อมใช้งาน กรุณาให้ผู้ดูแลเปิดหน้าตั้งค่า LINE Login</p>
<?php elseif($flow==='customer_booking'&&!$customerOa['configured']):?><p class="line-link-status" data-line-status data-state="error">ร้านยังไม่ได้ตั้งค่าลิงก์ LINE OA จึงยังยืนยันเพื่อนและส่งคำขอจองไม่ได้ กรุณาติดต่อร้าน</p>
<?php elseif($flow==='link'&&$lineLinked):?><div class="line-connected-state"><span class="connected-check">✓</span><div><b>บัญชีนี้เชื่อม LINE แล้ว</b><small>คุณสามารถกลับไปใช้งานระบบ หรือยกเลิกการเชื่อมได้</small></div></div><p class="line-link-status" data-line-status data-state="success">เชื่อมบัญชีเรียบร้อย</p><div class="line-link-actions"><button type="button" class="line-unlink-action" data-line-unlink>ยกเลิกการเชื่อม LINE</button><a class="line-primary-action" href="account.php">กลับบัญชีของฉัน <span>→</span></a></div>
<?php else:?><div class="line-action-intro"><span class="line-action-icon">L</span><span><b><?=$flow==='customer_booking'?'ยืนยันบัญชี LINE':'พร้อมเชื่อมบัญชีของคุณ'?></b><small><?=$flow==='customer_booking'?'LINE จะยืนยันตัวตนก่อนกลับไปหน้าจอง':'กดปุ่มด้านล่างเพื่อเปิดหน้าต่างยืนยันจาก LINE'?></small></span></div><div class="line-link-actions"><button type="button" class="primary" data-line-action><?=h($flow==='link'?'เชื่อม LINE ของฉัน':($flow==='staff_login'?'ดำเนินการด้วย LINE':'ยืนยัน LINE และกลับไปจองโต๊ะ'))?><span aria-hidden="true">→</span></button></div><p class="line-link-status" data-line-status data-state="">กดปุ่มเพื่อเริ่มยืนยันกับ LINE</p><?php endif;?>
<?php if($flow==='staff_login'):?><a class="line-link-back" href="login.php<?=h(login_return_query($returnKey))?>">กลับไปใช้รหัสผ่านหรือ PIN</a><br><a class="line-link-back" href="login.php?line_link=1">ยังไม่เคยผูก LINE? เข้าระบบเพื่อเชื่อม</a>
<?php elseif($flow==='customer_booking'):?><?php if($customerOa['configured']):?><div class="line-customer-oa-note"><strong>เพิ่มเพื่อน OA ก่อนจอง</strong><span>ต้องเพิ่มและยืนยันสถานะเพื่อนกับ OA ที่ผูกกับ LINE Login Channel ก่อน ระบบจึงจะเปิดแบบฟอร์มจอง</span><a href="<?=h($customerOa['url'])?>" target="_blank" rel="noopener noreferrer">เปิด LINE OA สำรอง ↗</a></div><?php else:?><div class="line-customer-oa-note"><strong>ยังไม่ได้ตั้งค่าลิงก์ LINE OA</strong><span>ระบบยังยืนยันสถานะเพื่อน OA ไม่ได้ กรุณาติดต่อร้านก่อนจอง</span></div><?php endif;?><a class="line-link-back" href="<?=h($reservationReturn)?>">กลับไปหน้าจองโต๊ะ</a>
<?php else:?><a class="line-link-back" href="account.php">← กลับไปบัญชีของฉัน</a><?php endif;?>
</section></main><?php if($config['ready']):?><script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script><script src="assets/line-flow-v14901.js?v=14915" defer></script><?php endif;?></body></html>
