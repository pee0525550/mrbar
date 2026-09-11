<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){http_response_code(401);exit;}
$d=db_load();$employeeId=(int)($_GET['employee_id']??0);$employee=workforce_employee_by_id($d,$employeeId);if(!$employee){http_response_code(404);exit;}
$owner=((int)($employee['user_id']??0)===(int)($u['id']??0));
$allowed=(($u['role']??'')==='admin')||$owner||user_can($u,'employees.view',$d)||user_can($u,'workforce.view',$d)||user_can($u,'payroll.view',$d);
if(!$allowed){http_response_code(403);exit;}
$rel=(string)($employee['profile']['profile_photo']??'');
if($rel===''||strpos($rel,'storage/employee-media/')!==0){http_response_code(404);exit;}
$base=realpath(__DIR__.'/storage/employee-media');$file=realpath(__DIR__.'/'.$rel);
if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');readfile($file);
