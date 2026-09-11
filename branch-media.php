<?php
declare(strict_types=1);
$name=basename((string)($_GET['file']??''));
if($name===''||!preg_match('/^branch-(?:logo|cover)-[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)$/i',$name)){http_response_code(404);exit;}
$dir=__DIR__.'/storage/branch-media';$root=realpath($dir);$path=realpath($dir.'/'.$name);
if(!$root||!$path||!is_file($path)||strpos($path,$root.DIRECTORY_SEPARATOR)!==0){http_response_code(404);exit;}
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));$types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];$type=$types[$ext]??'';
if($type===''){http_response_code(415);exit;}
$mtime=(int)filemtime($path);$size=(int)filesize($path);$etag='"'.sha1($name.'|'.$mtime.'|'.$size).'"';
header('Content-Type: '.$type);header('Content-Length: '.$size);header('Content-Disposition: inline; filename="'.$name.'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');header('ETag: '.$etag);
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
readfile($path);
