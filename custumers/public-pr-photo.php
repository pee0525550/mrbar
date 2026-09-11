<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/app.php';date_default_timezone_set((string)($config['timezone']??'Asia/Bangkok'));
require_once __DIR__.'/../app/db.php';
try{$d=db_load();}catch(Throwable $e){http_response_code(500);exit;}
if((string)($d['settings']['customer_web_show_pr']??'1')!=='1'){http_response_code(404);exit;}
$id=(int)($_GET['id']??0);$mode=(string)($_GET['mode']??'profile');if(!in_array($mode,['profile','showcase'],true))$mode='profile';$pr=null;foreach($d['prs']??[] as $p)if((int)($p['id']??0)===$id&&!empty($p['active'])){$pr=$p;break;}if(!$pr){http_response_code(404);exit;}
$profile=is_array($pr['profile']??null)?$pr['profile']:[];$rel=$mode==='showcase'?trim((string)($profile['showcase_photo']??'')):'';if($rel==='')$rel=trim((string)($profile['profile_photo']??''));if($rel===''){http_response_code(404);exit;}
$root=realpath(__DIR__.'/..');if(!$root){http_response_code(404);exit;}$file=realpath($root.'/'.$rel);if(!$file||!is_file($file)){http_response_code(404);exit;}
$allowed=[];foreach([$root.'/storage/pr-media/'.$id,$root.'/uploads/pr/'.$id] as $r){$x=realpath($r);if($x)$allowed[]=$x;}
$ok=false;foreach($allowed as $base){if(strpos($file,$base.DIRECTORY_SEPARATOR)===0){$ok=true;break;}}if(!$ok){http_response_code(403);exit;}
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($file);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: public, max-age=600');header('X-Content-Type-Options: nosniff');readfile($file);
