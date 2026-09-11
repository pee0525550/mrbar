<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/app.php';date_default_timezone_set((string)($config['timezone']??'Asia/Bangkok'));
require_once __DIR__.'/../app/db.php';require_once __DIR__.'/../app/customer-hero-media.php';
try{$d=db_load();}catch(Throwable $e){http_response_code(500);exit;}
$id=(int)($_GET['id']??0);$field=(string)($_GET['field']??'source');if(!in_array($field,['source','poster'],true)){http_response_code(400);exit;}
$item=chm_find($d,$id);if(!$item){http_response_code(404);exit;}
$rel=(string)($item[$field]??'');if($rel===''){http_response_code(404);exit;}$abs=chm_absolute_path($rel);if(!$abs||!is_file($abs)){http_response_code(404);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($abs);$allowed=['image/jpeg','image/png','image/webp','video/mp4','video/webm'];if(!in_array($mime,$allowed,true)){http_response_code(415);exit;}
$size=(int)filesize($abs);header('Content-Type: '.$mime);header('Accept-Ranges: bytes');header('Cache-Control: public, max-age=900');header('X-Content-Type-Options: nosniff');
if(strpos($mime,'video/')===0&&isset($_SERVER['HTTP_RANGE'])&&preg_match('/bytes=(\d*)-(\d*)/',(string)$_SERVER['HTTP_RANGE'],$m)){
    $start=$m[1]===''?0:(int)$m[1];$end=$m[2]===''?$size-1:min($size-1,(int)$m[2]);if($start>$end||$start>=$size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
    $length=$end-$start+1;http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);header('Content-Length: '.$length);$fh=fopen($abs,'rb');if(!$fh)exit;fseek($fh,$start);$remaining=$length;while($remaining>0&&!feof($fh)){$chunk=fread($fh,min(8192,$remaining));if($chunk===false)break;echo $chunk;$remaining-=strlen($chunk);}fclose($fh);exit;
}
header('Content-Length: '.$size);readfile($abs);
