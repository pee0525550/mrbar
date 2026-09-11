<?php
$https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
if(!$https){$host=(string)($_SERVER['HTTP_HOST']??'nigiwaigroup.com');$uri=(string)($_SERVER['REQUEST_URI']??'/it/time.php');header('Location: https://'.$host.$uri,true,302);exit;}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: same-origin');
require __DIR__.'/app/bootstrap.php';
$u=current_user();
if(!$u){header('Location:login.php?return_to=time&pwa=1');exit;}
if((string)($u['role']??'')==='pr'){header('Location:pr.php?source=pwa');exit;}
header('Location:employee-time.php?source=pwa');exit;
