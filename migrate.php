<?php
require __DIR__.'/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
$ok=false;$msg='';
try{$d=db_load();db_save($d);$d=db_load();$ok=true;$msg='Schema '.(int)($d['meta']['schema']??0).' ready. Active DB: '.basename(db_path());}catch(Throwable $e){$msg=$e->getMessage();}
?><!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR Auto Migrate</title><link rel="stylesheet" href="assets/typography.css?v=1.22.4"><body style="font-family:MRBAR Thai,Noto Sans Thai,Tahoma,Leelawadee UI,Thonburi,Segoe UI,Arial,sans-serif;background:#070812;color:#fff;padding:40px"><h1>MR BAR Auto Migrate</h1><p style="color:<?=$ok?'#67f5a2':'#ff7d9e'?>"><?=htmlspecialchars($ok?'✓ '.$msg:'✕ '.$msg,ENT_QUOTES,'UTF-8')?></p><p>Version 1.2.2 · Schema 4</p><a style="color:#8dbbff" href="login.php">ไปหน้า Login</a></body></html>
