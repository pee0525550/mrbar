<?php
require_once __DIR__.'/app/secure-context.php';
mrbar_require_https('/time.php');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: same-origin');
require __DIR__.'/app/bootstrap.php';
$u=current_user();
if(!$u){header('Location:login.php?return_to=time&pwa=1');exit;}
if((string)($u['role']??'')==='pr'){header('Location:pr.php?source=pwa');exit;}
header('Location:employee-time.php?source=pwa');exit;
