<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';

$root=realpath(__DIR__.'/storage/customer-web-media/system-branding');
$rel='';
$fileParam=trim((string)($_GET['file']??''));
if($fileParam!==''){
    $u=current_user();
    if(!$u){http_response_code(403);exit;}
    $d=db_load();
    if((($u['role']??'')!=='admin')&&!user_can($u,'settings.view',$d)){http_response_code(403);exit;}
    $base=basename($fileParam);
    if($base!==$fileParam||!preg_match('/^[A-Za-z0-9._-]+$/',$base)){http_response_code(404);exit;}
    $candidate=mr_branding_media_prefix().$base;
    $file=realpath(__DIR__.'/'.$candidate);
    if(!$root||!$file||!is_file($file)||strpos($file,$root.DIRECTORY_SEPARATOR)!==0){http_response_code(404);exit;}
    $rel=$candidate;
}else{
    $type=(string)($_GET['type']??'');
    if(!in_array($type,['sidebar','favicon','favicon_customer','favicon_admin','favicon_checkin'],true)){http_response_code(404);exit;}
    $d=db_load();$rel=mr_branding_asset_path($type,$d);
    if($rel===''){http_response_code(404);exit;}
    $file=realpath(__DIR__.'/'.$rel);
    if(!$root||!$file||!is_file($file)||strpos($file,$root.DIRECTORY_SEPARATOR)!==0){http_response_code(404);exit;}
}

$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($file);
$allowed=['image/jpeg','image/png','image/webp','image/x-icon','image/vnd.microsoft.icon'];
if(!in_array($mime,$allowed,true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($file));
header('Cache-Control: public, max-age=86400, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);
