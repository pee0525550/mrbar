<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function customer_line_auth_json(int $status,array $payload): void {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Allow: POST');
    customer_line_auth_json(405,['error'=>'method_not_allowed']);
}
if(!auth_csrf_valid((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??'')))customer_line_auth_json(419,['error'=>'csrf_expired']);

try{
    $verification=mrbar_line_verify_liff_identity_result(trim((string)($_POST['id_token']??'')));
}catch(Throwable $error){
    error_log('Customer LINE token verification failed: '.get_class($error));
    customer_line_auth_json(500,['error'=>'line_server_error']);
}
$identity=$verification['identity'];
if($identity===null)customer_line_auth_json(422,['error'=>$verification['error']]);

$returnUrl=mrbar_customer_reservation_return_url((string)($_POST['return_to']??''));
if(!mrbar_line_customer_oa_config()['configured'])customer_line_auth_json(409,['error'=>'line_oa_not_configured']);
$friendship=mrbar_line_customer_friendship_result(trim((string)($_POST['access_token']??'')),(string)$identity['sub']);
if(($friendship['friend']??null)!==true){
    $error=(string)($friendship['error']??'line_friendship_unavailable');
    $status=$error==='line_oa_friend_required'?403:($error==='line_access_token_missing'||$error==='line_access_token_invalid'?401:($error==='line_access_token_channel_mismatch'||$error==='line_token_subject_mismatch'?403:503));
    customer_line_auth_json($status,['error'=>$error]);
}
session_regenerate_id(true);
$_SESSION['customer_line_auth']=[
    'line_user_id'=>$identity['sub'],
    'display_name'=>$identity['name'],
    'friend_status'=>'friend',
    'friend_checked_at'=>time(),
    'authenticated_at'=>time(),
];
customer_line_auth_json(200,['ok'=>true,'redirect'=>$returnUrl]);
