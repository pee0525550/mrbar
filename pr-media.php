<?php
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){http_response_code(401);exit;}
$d=db_load();$prId=(int)($_GET['pr_id']??0);$field=(string)($_GET['field']??'');
if(!in_array($field,['profile_photo','cover_photo','showcase_photo'],true)){http_response_code(400);exit;}
$pr=null;foreach($d['prs'] as $p)if((int)$p['id']===$prId){$pr=$p;break;}if(!$pr){http_response_code(404);exit;}
$owner=((int)($pr['user_id']??0)===(int)$u['id']);
$admin=(($u['role']??'')==='admin');
$canManage=function_exists('user_can')&&(user_can($u,'pr.manage',$d)||user_can($u,'audit.view',$d));
if(!$owner&&!$admin&&!$canManage){http_response_code(403);exit;}
$rel=(string)($pr['profile'][$field]??'');
if($rel===''||strpos($rel,'storage/pr-media/')!==0){http_response_code(404);exit;}
$base=realpath(__DIR__.'/storage/pr-media');$file=realpath(__DIR__.'/'.$rel);
if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');readfile($file);
