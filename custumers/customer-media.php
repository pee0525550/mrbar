<?php
require __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-media.php';
$d=db_load();$id=(int)($_GET['id']??0);$m=cm_find($d,$id);if(!$m){http_response_code(404);exit;}
$p=cm_absolute_path((string)$m['path']);if(!$p||!is_file($p)||!is_readable($p)){http_response_code(404);exit;}
$mime=(string)($m['mime']??'image/jpeg');if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){$mime='application/octet-stream';}
$mtime=(int)@filemtime($p);$etag='"'.sha1($id.'|'.$mtime.'|'.(int)@filesize($p)).'"';
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($p));header('Cache-Control: public, max-age=86400');header('ETag: '.$etag);header('X-Content-Type-Options: nosniff');
if(isset($_SERVER['HTTP_IF_NONE_MATCH'])&&trim((string)$_SERVER['HTTP_IF_NONE_MATCH'])===$etag){http_response_code(304);exit;}
readfile($p);
