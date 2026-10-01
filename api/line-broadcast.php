<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function line_broadcast_json(int $status,array $payload): void {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Allow: POST');
    line_broadcast_json(405,['error'=>'method_not_allowed']);
}
$user=current_user();
if(!$user)line_broadcast_json(401,['error'=>'unauthorized']);
if(!auth_csrf_valid((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??'')))line_broadcast_json(419,['error'=>'csrf_expired']);
$data=db_load_global();
if(!user_can($user,'settings.manage',$data))line_broadcast_json(403,['error'=>'forbidden']);
if((string)($_POST['confirm_all']??'')!=='1'||trim((string)($_POST['confirm_phrase']??''))!=='ยืนยันส่ง'){
    line_broadcast_json(400,['error'=>'confirmation_required']);
}
$payload=mrbar_line_broadcast_payload((string)($_POST['message']??''));
if($payload===null)line_broadcast_json(422,['error'=>'invalid_message']);
$retryKey=trim((string)($_POST['retry_key']??''));
if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$retryKey)){
    line_broadcast_json(422,['error'=>'invalid_retry_key']);
}
if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')line_broadcast_json(503,['error'=>'line_messaging_not_configured']);

$now=time();$retryAfter=0;$reserved=false;
try{
    db_mutate_global(function(array $global)use($now,$user,$payload,&$retryAfter,&$reserved):array{
        if(!user_can($user,'settings.manage',$global))throw new RuntimeException('permission_denied');
        if(!is_array($global['portal_settings']??null))$global['portal_settings']=[];
        $last=(int)($global['portal_settings']['line_broadcast_last_attempt_at']??0);
        $retryAfter=max(0,60-($now-$last));
        if($retryAfter>0)return $global;
        $global['portal_settings']['line_broadcast_last_attempt_at']=$now;
        $length=function_exists('mb_strlen')?mb_strlen($payload['messages'][0]['text'],'UTF-8'):preg_match_all('/./us',$payload['messages'][0]['text']);
        $global['audit'][]=['at'=>date('c'),'action'=>'line_broadcast_attempted','by'=>(int)($user['id']??0),'message_length'=>(int)$length];
        $reserved=true;
        return $global;
    });
}catch(Throwable $exception){
    line_broadcast_json(503,['error'=>$exception->getMessage()==='permission_denied'?'forbidden':'storage_unavailable']);
}
if(!$reserved)line_broadcast_json(429,['error'=>'rate_limited','retry_after'=>$retryAfter]);

$result=mrbar_line_post('/v2/bot/message/broadcast',$payload,$retryKey);
$accepted=!empty($result['ok'])||(int)($result['status']??0)===409;
try{
    db_mutate_global(function(array $global)use($user,$result,$accepted):array{
        $global['audit'][]=['at'=>date('c'),'action'=>$accepted?'line_broadcast_accepted':'line_broadcast_failed','by'=>(int)($user['id']??0),'http_status'=>(int)($result['status']??0)];
        return $global;
    });
}catch(Throwable $exception){
    // Keep the LINE result authoritative if audit storage is temporarily unavailable.
}
if($accepted)line_broadcast_json(200,['ok'=>true,'duplicate'=>(int)($result['status']??0)===409,'cooldown_seconds'=>60]);

$status=(int)($result['status']??0);
$error=$status===401||$status===403?'line_credentials_rejected':($status===429?'line_quota_or_rate_limit':($status===400?'line_request_rejected':'line_broadcast_failed'));
line_broadcast_json(502,['error'=>$error,'http_status'=>$status,'retry_after'=>60]);
