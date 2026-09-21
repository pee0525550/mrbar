<?php
require __DIR__.'/app/bootstrap.php';
$u=require_roles('admin');
if(empty($u['super_admin'])){http_response_code(403);exit('Super Admin only');}
header('Content-Type: text/html; charset=utf-8');
$ok=false;$msg='พร้อมตรวจสอบ';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 try{$d=db_load();db_save($d);$d=db_load();$ok=true;$msg='Schema '.(int)($d['meta']['schema']??0).' ready. Active DB: '.basename(db_path());}catch(Throwable $e){$msg=$e->getMessage();}
}
$cfg=app_config();
?><!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>MR BAR Auto Migrate</title><link rel="stylesheet" href="assets/typography.css?v=1.22.4"><body style="font-family:MRBAR Thai,Noto Sans Thai,Tahoma,Leelawadee UI,Thonburi,Segoe UI,Arial,sans-serif;background:#070812;color:#fff;padding:40px"><h1>MR BAR Auto Migrate</h1><p style="color:<?=$ok?'#67f5a2':'#ffcc76'?>"><?=htmlspecialchars($ok?'✓ '.$msg:$msg,ENT_QUOTES,'UTF-8')?></p><p>Version <?=htmlspecialchars((string)($cfg['version']??''),ENT_QUOTES,'UTF-8')?> · Schema <?=MRBAR_SCHEMA_VERSION?></p><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8')?>"><button style="padding:12px 18px">ตรวจและอัปเดต Schema</button></form><p><a style="color:#8dbbff" href="admin.php">กลับหน้า Admin</a></p></body></html>
