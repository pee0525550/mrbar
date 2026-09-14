<?php
require __DIR__.'/app/bootstrap.php';
$name=basename((string)($_GET['file']??''));
if($name===''||!preg_match('/^portal-(?:logo|favicon)-[a-zA-Z0-9._-]+$/',$name)){http_response_code(404);exit;}
$path=__DIR__.'/storage/portal-media/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
$info=@getimagesize($path);$allowed=['image/jpeg','image/png','image/webp'];$mime=(string)($info['mime']??'');
if(!in_array($mime,$allowed,true)){http_response_code(404);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: public, max-age=31536000, immutable');header('X-Content-Type-Options: nosniff');readfile($path);
