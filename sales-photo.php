<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$d=db_load();$employeeId=(int)($_GET['employee_id']??0);if($employeeId<=0){http_response_code(404);exit;}
$employee=workforce_employee_by_id($d,$employeeId);if(!$employee||empty($employee['active'])||(string)($employee['position']??'')!=='sales'){http_response_code(404);exit;}
$rel=(string)($employee['profile']['profile_photo']??'');$prefix='storage/employee-media/'.$employeeId.'/';
if($rel===''||strpos(str_replace('\\','/',$rel),$prefix)!==0){http_response_code(404);exit;}
$root=realpath(__DIR__.'/storage/employee-media/'.$employeeId);$file=realpath(__DIR__.'/'.$rel);
if(!$root||!$file||!is_file($file)||strpos($file,$root.DIRECTORY_SEPARATOR)!==0){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($file));header('Cache-Control: public, max-age=300');header('X-Content-Type-Options: nosniff');readfile($file);
