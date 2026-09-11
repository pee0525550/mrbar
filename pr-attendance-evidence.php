<?php
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){http_response_code(401);exit;}
$d=db_load();$isAdmin=(($u['role']??'')==='admin')||(function_exists('user_can')&&user_can($u,'attendance.view',$d));$pr=$isAdmin?null:user_pr($d,(int)$u['id']);if(!$isAdmin&&!$pr){http_response_code(403);exit;}
$id=(int)($_GET['id']??0);$type=(($_GET['type']??'in')==='out')?'out':'in';$row=null;
foreach($d['attendance']??[] as $a){if((int)($a['id']??0)!==$id)continue;if(!$isAdmin&&(int)($a['pr_id']??0)!==(int)$pr['id'])continue;$row=$a;break;}
if(!$row){http_response_code(404);exit;}
$rel=(string)($type==='out'?($row['checkout_evidence']??''):($row['checkin_evidence']??''));$prefix='storage/attendance-evidence/';
if($rel===''||strpos($rel,$prefix)!==0){http_response_code(404);exit;}
$base=realpath(__DIR__.'/storage/attendance-evidence');$file=realpath(__DIR__.'/'.$rel);
if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=120');readfile($file);
