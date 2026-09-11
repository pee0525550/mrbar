<?php
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){http_response_code(401);exit;}
$d=db_load();$id=(int)($_GET['id']??0);$type=($_GET['type']??'in')==='out'?'out':'in';$row=null;
foreach($d['attendance']??[] as $a)if((int)($a['id']??0)===$id){$row=$a;break;}
if(!$row){http_response_code(404);exit;}
$allowed=(($u['role']??'')==='admin')||user_can($u,'attendance.view',$d)||user_can($u,'attendance.manage',$d)||user_can($u,'audit.view',$d)||user_can($u,'payroll.view',$d);
if(!$allowed){
    $employee=workforce_user_employee($d,$u);$ownEmployee=$employee&&((int)($row['employee_id']??0)===(int)$employee['id']);
    $pr=user_pr($d,(int)$u['id']);$ownPr=$pr&&((int)($row['pr_id']??0)===(int)$pr['id']);
    $allowed=$ownEmployee||$ownPr;
}
if(!$allowed){http_response_code(403);exit;}
$rel=(string)($type==='out'?($row['checkout_evidence']??''):($row['checkin_evidence']??''));
if($rel===''||strpos($rel,'storage/attendance-evidence/')!==0){http_response_code(404);exit;}
$base=realpath(__DIR__.'/storage/attendance-evidence');$file=realpath(__DIR__.'/'.$rel);
if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=300');readfile($file);
