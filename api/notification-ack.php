<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$user=current_user();
if(!$user){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'method_not_allowed']);exit;}
csrf_check();

$notificationId=(int)($_POST['notification_id']??0);
if($notificationId<=0){http_response_code(400);echo json_encode(['error'=>'invalid_notification']);exit;}
$acknowledged=false;
db_mutate(function(array $data)use($notificationId,$user,&$acknowledged):array{
    $acknowledged=op_acknowledge_notification($data,$user,$notificationId);
    return $data;
});
if(!$acknowledged){http_response_code(404);echo json_encode(['error'=>'notification_not_found']);exit;}
echo json_encode(['ok'=>true,'notification_id'=>$notificationId]);
