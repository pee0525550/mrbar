<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function line_test_json(int $status,array $payload): void {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

$user=current_user();
if(!$user)line_test_json(401,['error'=>'unauthorized']);
if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Allow: POST');
    line_test_json(405,['error'=>'method_not_allowed']);
}
csrf_check();

$userId=(int)($user['id']??0);
$lineUserId='';
foreach(db_load_global()['users']??[] as $account){
    if((int)($account['id']??0)===$userId){
        $lineUserId=(string)($account['line_user_id']??'');
        break;
    }
}
if($lineUserId==='')line_test_json(409,['error'=>'line_not_linked']);
if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')===''){
    line_test_json(503,['error'=>'line_messaging_not_configured']);
}

$lastSent=(int)($_SESSION['line_test_last_sent_at']??0);
$wait=max(0,60-(time()-$lastSent));
if($wait>0)line_test_json(429,['error'=>'rate_limited','retry_after'=>$wait]);

$message='ทดสอบแจ้งเตือนจาก MR BAR สำเร็จ บัญชีพนักงานของคุณพร้อมรับการแจ้งเตือนผ่าน LINE · '.date('d/m/Y H:i');
$result=mrbar_line_push_text($lineUserId,$message);
if(!$result['ok'])line_test_json(502,['error'=>'line_push_failed']);

$_SESSION['line_test_last_sent_at']=time();
line_test_json(200,['ok'=>true]);
