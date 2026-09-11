<?php
require __DIR__.'/app/bootstrap.php';
$u=require_roles('admin','staff','pr');header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');$d=db_load();$scope=(string)($_GET['scope']??'staff');
if($scope==='pr'){
    if($u['role']!=='pr'){http_response_code(403);echo json_encode(['error'=>'forbidden']);exit;}
    $pr=user_pr($d,(int)$u['id']);if(!$pr){echo json_encode(['signature'=>'none','jobs'=>0,'notifications'=>0]);exit;}
    $jobs=array_values(array_filter($d['checkins'],fn($c)=>(int)($c['pr_id']??0)===(int)$pr['id']&&in_array((string)$c['status'],['assigned','accepted','in_service'],true)));
    $not=op_unread_notifications($d,$u);$sig=sha1(json_encode(array_map(fn($c)=>[$c['id'],$c['status'],$c['updated_at']??''],$jobs)).'|'.count($not));
    echo json_encode(['signature'=>$sig,'jobs'=>count($jobs),'notifications'=>count($not),'status'=>$pr['status']],JSON_UNESCAPED_UNICODE);exit;
}
if(!in_array($u['role'],['staff','admin'],true)){http_response_code(403);echo json_encode(['error'=>'forbidden']);exit;}
$queue=array_values(array_filter($d['checkins'],fn($c)=>in_array((string)$c['status'],['pending','rejected','assigned','accepted','in_service'],true)));
$online=count(array_filter($d['prs'],fn($p)=>($p['status']??'')==='online'));$occupied=count(array_filter($d['tables'],fn($t)=>($t['status']??'')==='occupied'));$service=count(array_filter($d['checkins'],fn($c)=>($c['status']??'')==='in_service'));
$sig=sha1(json_encode(array_map(fn($c)=>[$c['id'],$c['status'],$c['pr_id']??0,$c['updated_at']??''],$queue)).'|'.$online.'|'.$occupied);
echo json_encode(['signature'=>$sig,'queue'=>count($queue),'online'=>$online,'occupied'=>$occupied,'service'=>$service],JSON_UNESCAPED_UNICODE);
