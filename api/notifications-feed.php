<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$user=current_user();
if(!$user){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['error'=>'method_not_allowed']);exit;}

$data=db_load();
session_write_close();
$settings=$data['settings']??[];
$enabled=(string)($settings['reservation_staff_notifications']??'1')==='1';
$desktop=(string)($settings['notification_desktop']??'0')==='1';
$sound=(string)($settings['notification_sound']??'0')==='1';
$afterId=max(0,(int)($_GET['after_id']??0));
$items=[];
if($enabled){
    foreach(op_unread_notifications($data,$user) as $row){
        $id=(int)($row['id']??0);
        if($id<=$afterId)continue;
        $meta=is_array($row['meta']??null)?$row['meta']:[];
        $reservationEvent=($meta['event']??'')==='reservation.created';
        $canOpenReservation=$reservationEvent&&(user_can($user,'reservations.view',$data)||user_can($user,'reservations.manage',$data));
        $items[]=[
            'id'=>$id,
            'type'=>(string)($row['type']??'notice'),
            'message'=>(string)($row['message']??'มีการแจ้งเตือนใหม่'),
            'created_at'=>(string)($row['created_at']??''),
            'href'=>$canOpenReservation?'reservations.php':'',
        ];
    }
    usort($items,static fn(array $a,array $b):int=>$a['id']<=>$b['id']);
}
$items=array_slice($items,0,40);
echo json_encode(['enabled'=>$enabled,'desktop'=>$desktop,'sound'=>$sound,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
