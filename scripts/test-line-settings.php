<?php
declare(strict_types=1);
$_SERVER['SCRIPT_NAME']='/line-settings.php';
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['HTTP_HOST']='mrbarsupport.com';
session_start();
require __DIR__.'/../app/db.php';
$_SESSION['user']=['id'=>999999,'username'=>'preview','display_name'=>'Preview Admin','role'=>'admin','super_admin'=>1,'active'=>1,'branch_ids'=>[1]];
ob_start();
require __DIR__.'/../line-settings.php';
$html=ob_get_clean();
foreach(['ตั้งค่า LINE Login','name="liff_id"','name="channel_id"','name="line_customer_oa_url"','LINE OA สำหรับชวนลูกค้าเพิ่มเพื่อน','login.php','custumers/reserve.php','ทดสอบ LINE บนมือถือ','data-line-test','data-line-status','assets/line-test-v14906.js','assets/line-customer-oa-v14910.css','บัญชีที่เชื่อม LINE แล้ว','ข้อความ LINE ถึงลูกค้า','name="booking_template"','name="confirmation_template"','confirmation_enabled','{reservation_id}','{party_size}','assets/line-messages-v14909.css','ส่งข้อความ Broadcast ถึงผู้ติดตาม OA','data-line-broadcast','data-broadcast-preview','data-broadcast-submit','ยืนยันส่ง','assets/line-broadcast-v14913.js','assets/line-broadcast-v14913.css'] as $fragment){
    if(!str_contains($html,$fragment)){fwrite(STDERR,"Missing LINE setup UI: $fragment\n");exit(1);}
}
if(str_contains($html,'MRBAR_LINE_CHANNEL_ACCESS_TOKEN')){fwrite(STDERR,"Secret variable leaked into setup page\n");exit(1);}
if(str_contains($html,'href="line-link.php?flow=link"')){fwrite(STDERR,"Personal LINE action remains in system settings\n");exit(1);}
echo "LINE settings page smoke test passed.\n";
