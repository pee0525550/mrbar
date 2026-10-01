<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/customer-integrations.php';
$user=require_permission('settings.view');
$canManage=user_can($user,'settings.manage');
$error='';
$saved=isset($_GET['saved']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(!$canManage){http_response_code(403);exit(permission_denied_page('settings.manage'));}
    $action=(string)($_POST['action']??'save_config');
    if($action==='unlink_user'){
        $targetId=(int)($_POST['user_id']??0);
        if($targetId<=0)$error='ไม่พบบัญชีพนักงาน';
        else try{
            db_mutate_global(function(array $data)use($targetId,$user):array{
                $found=false;
                foreach($data['users'] as &$account){
                    if((int)($account['id']??0)!==$targetId)continue;
                    $account['line_user_id']='';$account['line_linked_at']=null;$found=true;break;
                }unset($account);
                if(!$found)throw new RuntimeException('User not found');
                $data['audit'][]=['at'=>date('c'),'action'=>'line_account_unlinked_by_admin','by'=>(int)$user['id'],'target_user_id'=>$targetId];
                return $data;
            });
            header('Location: line-settings.php?unlinked=1');exit;
        }catch(Throwable $exception){$error='ยกเลิกการเชื่อมไม่สำเร็จ';}
    }elseif($action==='save_config'){
        $liffId=trim((string)($_POST['liff_id']??''));
        $channelId=trim((string)($_POST['channel_id']??''));
        $customerOaInput=trim((string)($_POST['line_customer_oa_url']??''));
        $customerOa=$customerOaInput!==''?mrbar_line_oa_destination($customerOaInput):null;
        if($liffId!==''&&!preg_match('/^[0-9]{6,15}-[A-Za-z0-9]{4,40}$/',$liffId))$error='LIFF ID ไม่ถูกต้อง ตัวอย่าง 2011745152-yvbsYagI';
        elseif($channelId!==''&&!preg_match('/^[0-9]{6,15}$/',$channelId))$error='LINE Login Channel ID ต้องเป็นตัวเลข';
        elseif($customerOaInput!==''&&$customerOa===null)$error='LINE OA ต้องเป็น @Basic ID หรือ URL https://lin.ee/... / https://line.me/R/ti/p/@...';
        else{
        try{
            db_mutate_global(function(array $data)use($liffId,$channelId,$customerOa,$user):array{
                $data['portal_settings']['line_liff_id']=$liffId;
                $data['portal_settings']['line_login_channel_id']=$channelId;
                $data['portal_settings']['line_customer_oa_url']=$customerOa['url']??'';
                $data['audit'][]=['at'=>date('c'),'action'=>'line_login_settings_saved','by'=>(int)$user['id']];
                return $data;
            });
            header('Location: line-settings.php?saved=1');exit;
        }catch(Throwable $exception){$error='บันทึกไม่ได้ กรุณาตรวจสิทธิ์เขียน Storage ของเซิร์ฟเวอร์';}
        }
    }elseif($action==='save_templates'){
        $bookingTemplate=trim((string)($_POST['booking_template']??''));
        $confirmationTemplate=trim((string)($_POST['confirmation_template']??''));
        $confirmationEnabled=isset($_POST['confirmation_enabled'])?'1':'0';
        if(strlen($bookingTemplate)>4500||strlen($confirmationTemplate)>4500)$error='ข้อความต้องไม่เกิน 4,500 ไบต์ต่อรายการ';
        else try{
            db_mutate_global(function(array $data)use($bookingTemplate,$confirmationTemplate,$confirmationEnabled,$user):array{
                $data['portal_settings']['line_customer_booking_template']=$bookingTemplate;
                $data['portal_settings']['line_customer_confirmation_template']=$confirmationTemplate;
                $data['portal_settings']['line_customer_confirmation_enabled']=$confirmationEnabled;
                $data['audit'][]=['at'=>date('c'),'action'=>'line_customer_templates_saved','by'=>(int)$user['id']];
                return $data;
            });
            header('Location: line-settings.php?templates_saved=1');exit;
        }catch(Throwable $exception){$error='บันทึกข้อความไม่ได้ กรุณาตรวจสิทธิ์เขียน Storage ของเซิร์ฟเวอร์';}
    }else{
        $error='คำสั่งไม่ถูกต้อง';
    }
}
$data=db_load_global();
$config=mrbar_line_login_config($data);
$customerOa=mrbar_line_customer_oa_config($data);
$messageTemplates=mrbar_customer_line_message_templates($data);
$linked=0;$active=0;$linkedUsers=[];
foreach($data['users']??[] as $account){
    if(empty($account['active'])||!empty($account['deleted_at']))continue;
    $active++;
    if(trim((string)($account['line_user_id']??''))!==''){$linked++;$linkedUsers[]=$account;}
}
$messagingReady=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!=='';
$broadcastLastAttempt=(int)((is_array($data['portal_settings']??null)?$data['portal_settings']:[])['line_broadcast_last_attempt_at']??0);
$broadcastCooldownUntil=max(0,$broadcastLastAttempt+60);
$lineBroadcastReady=$canManage&&$messagingReady;
$currentLineUserId='';
foreach($data['users']??[] as $account)if((int)($account['id']??0)===(int)$user['id']){$currentLineUserId=trim((string)($account['line_user_id']??''));break;}
$currentLineLinked=(bool)preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$currentLineUserId);
$lineTestReady=$messagingReady&&$currentLineLinked;
$endpoint='https://'.(string)($_SERVER['HTTP_HOST']??'mrbarsupport.com').branch_public_base().'/line-link.php';
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#080d17"><title>LINE Login · MR BAR</title>
<link rel="stylesheet" href="assets/line-auth-theme-v14901.css?v=14901"><link rel="stylesheet" href="assets/line-customer-oa-v14910.css?v=14910"><link rel="stylesheet" href="assets/line-broadcast-v14913.css?v=14913">
<link rel="stylesheet" href="assets/admin.css?v=14901"><link rel="stylesheet" href="assets/admin-v14.css?v=14901"><link rel="stylesheet" href="assets/line-flow-v14901.css?v=14901"><link rel="stylesheet" href="assets/line-messages-v14909.css?v=14909"><style>.line-linked-section{margin-top:20px;border-top:1px solid #304153;padding-top:19px}.line-linked-section h2{font-size:18px}.line-linked-section p{color:#aabec8}.line-linked-list{border:1px solid #304153;border-radius:8px;overflow:hidden}.line-linked-row{display:flex;align-items:center;gap:14px;padding:12px 15px;border-bottom:1px solid #304153;background:#101a29}.line-linked-row:last-child{border-bottom:0}.line-linked-row>div{flex:1;min-width:0}.line-linked-row strong,.line-linked-row small{display:block}.line-linked-row small,.line-linked-row>span{font-size:12px;color:#aabec8}.line-linked-row form{margin:0}@media(max-width:650px){.line-linked-row{align-items:flex-start;flex-wrap:wrap}.line-linked-row>div{flex-basis:100%}}</style><style>.line-test-row{align-items:center;flex-wrap:wrap;margin-top:12px}.line-test-row [data-line-status]{flex:1 1 220px;min-width:180px;color:#aabec8;font-size:13px}.line-test-row [data-line-status][data-state="success"]{color:#48d6aa}.line-test-row [data-line-status][data-state="error"]{color:#ff879b}.line-test-row button:disabled{opacity:.55;cursor:not-allowed}.line-test-row a{white-space:nowrap}@media(max-width:650px){.line-test-row [data-line-status]{flex-basis:100%}}</style></head>
<body class="admin-v14-page line-admin-page"><?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('line_settings',$user);?>
<main class="line-admin-main"><header class="line-admin-header"><div><small>STORE & SYSTEM / LINE</small><h1>ตั้งค่า LINE Login</h1><p>ตั้งค่า LINE Login ของระบบและตรวจบัญชีที่เชื่อมแล้ว</p></div><a class="line-outline" href="settings.php">Settings Center</a></header>
<?php if($saved):?><div class="line-banner success" role="status">บันทึกการตั้งค่า LINE Login แล้ว</div><?php endif;?>
<?php if(isset($_GET['templates_saved'])):?><div class="line-banner success" role="status">บันทึกข้อความแจ้งลูกค้าแล้ว</div><?php endif;?>
<?php if(isset($_GET['unlinked'])):?><div class="line-banner success" role="status">ยกเลิกการเชื่อม LINE แล้ว</div><?php endif;?>
<?php if($error):?><div class="line-banner error" role="alert"><?=h($error)?></div><?php endif;?>
<div class="line-readiness"><div class="line-readiness-main"><span class="line-dot <?=$config['ready']?'ready':''?>"></span><div><small>สถานะ LINE Login</small><strong><?=$config['ready']?'พร้อมให้เชื่อมบัญชีและเข้าสู่ระบบ':'ยังตั้งค่าไม่ครบ'?></strong><p><?=$config['ready']?'LIFF ID และ LINE Login Channel ID พร้อมใช้งาน':'กรอกค่าที่ขาดในขั้นตอนที่ 1 แล้วบันทึก'?></p></div></div><div class="line-readiness-count"><strong><?=$linked?> / <?=$active?></strong><span>บัญชีพนักงานที่ผูก LINE แล้ว</span></div></div>
<div class="line-steps"><section class="line-step"><div class="line-step-head"><span>1</span><div><h2>ตั้งค่า LINE Login</h2><p>ใช้ LIFF ID และ Channel ID จากช่อง LINE Login เดียวกัน</p></div></div>
<div class="line-checks"><span class="<?=$config['liff_ready']?'ok':'missing'?>"><?=$config['liff_ready']?'พร้อม':'ยังขาด'?> · LIFF ID</span><span class="<?=$config['channel_ready']?'ok':'missing'?>"><?=$config['channel_ready']?'พร้อม':'ยังขาด'?> · Channel ID</span></div>
<form method="post" class="line-config-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_config"><label>LIFF ID<input name="liff_id" value="<?=h($config['liff_id'])?>" placeholder="2011745152-yvbsYagI" autocomplete="off" <?=$canManage?'':'readonly'?>></label><label>LINE Login Channel ID<input name="channel_id" value="<?=h($config['channel_id'])?>" placeholder="2011745152" inputmode="numeric" autocomplete="off" <?=$canManage?'':'readonly'?>></label><label class="line-oa-field">LINE OA สำหรับชวนลูกค้าเพิ่มเพื่อน<input name="line_customer_oa_url" value="<?=h($customerOa['value'])?>" placeholder="@117rttja หรือ https://lin.ee/..." autocomplete="url" <?=$canManage?'':'readonly'?>><small>ใช้เป็นลิงก์สำรอง และระบุ OA ที่ต้องการให้ลูกค้าเพิ่มก่อนรับข่าวการจอง</small></label><?php if($canManage):?><button class="line-primary" type="submit">บันทึกการตั้งค่า</button><?php endif;?></form>
<div class="line-oa-admin-note"><strong><?=$customerOa['configured']?'ตั้งค่า OA แล้ว':'ยังไม่ได้ตั้งค่า OA ลูกค้า'?></strong><span>หน้าต่างเพิ่มเพื่อนของ LIFF ใช้ OA ที่ผูกกับ LINE Login Channel ใน LINE Developers Console ซึ่งต้องเป็นบัญชีเดียวกับลิงก์นี้ และควรเป็น Provider เดียวกับ Messaging API; เปิด scope profile เพื่ออ่านสถานะเพื่อน หากหน้าต่าง LINE ใช้ไม่ได้ ลูกค้าจะเห็นลิงก์สำรองนี้</span><?php if($customerOa['url']!==''):?><a href="<?=h($customerOa['url'])?>" target="_blank" rel="noopener noreferrer">ตรวจลิงก์ OA</a><?php endif;?></div>
<p class="line-help">ค่าที่บันทึกที่นี่ใช้ทั้ง Login พนักงานและยืนยันลูกค้าก่อนจองโต๊ะ หากปล่อยว่าง ระบบจะใช้ค่าที่ผู้ดูแลเซิร์ฟเวอร์ตั้งไว้แทน</p><div class="line-endpoint"><span>Endpoint URL ใน LINE Developers</span><code><?=h($endpoint)?></code></div></section>
<section class="line-step"><div class="line-step-head"><span>2</span><div><h2>บัญชีที่เชื่อม LINE</h2><p>พนักงานแต่ละคนจัดการ LINE ของตนในหน้า “บัญชีของฉัน”</p></div></div><div class="line-action-row"><a class="line-outline" href="admin-manage.php">ดูบัญชีผู้ใช้</a></div><p class="line-help">LINE หนึ่งบัญชีผูกกับพนักงานได้หนึ่งคน ระบบไม่สร้างสิทธิ์ใหม่จาก LINE โดยอัตโนมัติ</p></section>
<section class="line-step"><div class="line-step-head"><span>3</span><div><h2>ทดสอบ LINE บนมือถือ</h2><p>ส่งข้อความจริงจาก OA ไปยัง LINE ที่เชื่อมกับบัญชีพนักงานที่กำลังใช้งาน</p></div></div><div class="line-action-row"><a class="line-outline" href="login.php">หน้า Login</a><a class="line-outline" href="custumers/reserve.php">หน้าจองโต๊ะลูกค้า</a></div><div class="line-banner muted">LINE Messaging API: <?=$messagingReady?'ตั้งค่า token แล้ว':'ยังไม่ได้ตั้งค่า token บน Server'?> · แยกจากการตั้งค่า LINE Login</div><div class="line-action-row line-test-row"><button type="button" class="line-primary" data-line-test data-csrf="<?=h(csrf_token())?>" <?=$lineTestReady?'':'disabled'?>>ส่งข้อความทดสอบ</button><span data-line-status role="status" aria-live="polite"><?=$lineTestReady?'ส่งข้อความไปยัง LINE ส่วนตัวของบัญชีนี้ได้':'ยังทดสอบไม่ได้: '.(!$messagingReady?'รอตั้งค่ Messaging API Token':(!$currentLineLinked?'กรุณาเชื่อม LINE ของบัญชีนี้ก่อน':'บัญชี LINE ไม่พร้อมใช้งาน'))?></span><?php if(!$currentLineLinked):?><a class="line-outline" href="account.php">ไปที่บัญชีของฉัน</a><?php endif;?></div></section></div>
<section class="line-step line-message-settings"><div class="line-step-head"><span>4</span><div><h2>ข้อความ LINE ถึงลูกค้า</h2><p>แก้ข้อความตอบรับคำขอ และข้อความอัตโนมัติเมื่อทีมงานยืนยันการจอง</p></div></div>
<form method="post" class="line-template-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_templates"><div class="line-template-grid">
<label>ข้อความเมื่อรับคำขอจอง<textarea name="booking_template" rows="7" maxlength="1500" <?=$canManage?'':'readonly'?>><?=h($messageTemplates['booking_template'])?></textarea></label>
<label>ข้อความเมื่อยืนยันการจอง<textarea name="confirmation_template" rows="7" maxlength="1500" <?=$canManage?'':'readonly'?>><?=h($messageTemplates['confirmation_template'])?></textarea></label>
</div><p class="line-help">ตัวแปรที่ใช้ได้: <code>{customer}</code> <code>{reservation_id}</code> <code>{date}</code> <code>{time}</code> <code>{party_size}</code> <code>{table}</code> <code>{zone}</code> <code>{shop}</code> · ลบข้อความในช่องเพื่อกลับไปใช้ค่าเริ่มต้น</p>
<label class="line-confirm-toggle"><input type="checkbox" name="confirmation_enabled" value="1" <?=$messageTemplates['confirmation_enabled']?'checked':''?> <?=$canManage?'':'disabled'?>> ส่งข้อความยืนยันให้ลูกค้าอัตโนมัติเมื่อทีมงานเปลี่ยนสถานะเป็น “จองแล้ว” หรือ “ยืนยันแล้ว”</label>
<?php if($canManage):?><button class="line-primary" type="submit">บันทึกข้อความ</button><?php endif;?></form></section>
<section class="line-step line-broadcast-step"><div class="line-step-head"><span>5</span><div><h2>ส่งข้อความ Broadcast ถึงผู้ติดตาม OA</h2><p>ส่งประกาศทั่วไปจาก LINE Official Account โดยไม่ต้องผูก LINE กับบัญชีพนักงานแต่ละคน</p></div></div>
<div class="line-broadcast-warning"><span aria-hidden="true">!</span><div><strong>ส่งถึงผู้ติดตาม OA ทุกคน</strong>การส่ง Broadcast ใช้โควตาข้อความของ LINE ตามจำนวนผู้รับ ผู้ที่บล็อก OA หรือรับข้อความไม่ได้อาจไม่ได้รับข้อความ และผลสำเร็จจาก API ไม่ได้ยืนยันว่าแต่ละคนอ่านข้อความ</div></div>
<?php if($canManage):?><form data-line-broadcast data-ready="<?=$lineBroadcastReady?'1':'0'?>" data-cooldown-until="<?=$broadcastCooldownUntil?>">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="line-broadcast-grid"><label>ข้อความ<textarea name="message" rows="8" maxlength="5000" placeholder="พิมพ์ประกาศที่ต้องการส่งให้ผู้ติดตาม OA ทั้งหมด" <?=$messagingReady?'':'disabled'?>></textarea><span class="line-broadcast-count" data-broadcast-count>0 / 5,000</span></label><div><label>ตัวอย่างข้อความ</label><div class="line-broadcast-preview" data-broadcast-preview aria-live="polite">ตัวอย่างข้อความจะแสดงที่นี่</div></div></div>
<div class="line-broadcast-confirm"><label><input type="checkbox" name="confirm_all" value="1" <?=$messagingReady?'':'disabled'?>>ฉันตรวจข้อความแล้ว และยืนยันส่งถึงผู้ติดตาม OA ทั้งหมด</label><label>พิมพ์ “ยืนยันส่ง” เพื่อปลดล็อกปุ่ม<input type="text" name="confirm_phrase" autocomplete="off" placeholder="ยืนยันส่ง" <?=$messagingReady?'':'disabled'?>></label></div>
<div class="line-broadcast-footer"><button type="submit" class="line-primary" data-broadcast-submit disabled>ส่ง Broadcast</button><span data-broadcast-status role="status" aria-live="polite"><?=$messagingReady?'เตรียมข้อความและยืนยันก่อนส่งได้ · ระบบเว้นช่วงอย่างน้อย 1 นาทีต่อครั้ง':'ยังส่งไม่ได้: ไม่พบ LINE Messaging API Token บน Server'?></span></div>
</form><?php else:?><div class="line-broadcast-permission">ต้องมีสิทธิ์ <code>settings.manage</code> เพื่อเขียนและส่ง Broadcast</div><?php endif;?></section>
<section class="line-linked-section"><h2>บัญชีที่เชื่อม LINE แล้ว</h2><?php if(!$linkedUsers):?><p>ยังไม่มีพนักงานเชื่อม LINE</p><?php else:?><div class="line-linked-list"><?php foreach($linkedUsers as $account):?><div class="line-linked-row"><div><strong><?=h((string)($account['display_name']??''))?></strong><small>@<?=h((string)($account['username']??''))?> · <?=h((string)($account['role']??''))?></small></div><span><?=h((string)($account['line_linked_at']??'เชื่อมแล้ว'))?></span><?php if($canManage):?><form method="post" onsubmit="return confirm('ยกเลิกการเชื่อม LINE ของบัญชีนี้?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="unlink_user"><input type="hidden" name="user_id" value="<?=(int)$account['id']?>"><button type="submit" class="line-outline">ยกเลิกการเชื่อม</button></form><?php endif;?></div><?php endforeach;?></div><?php endif;?></section>
</main><script src="assets/admin.js?v=14901"></script><script src="assets/line-test-v14906.js?v=14906" defer></script><script src="assets/line-broadcast-v14913.js?v=14913" defer></script></body></html>
