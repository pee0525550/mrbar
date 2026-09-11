<?php
declare(strict_types=1);
$checks=[];
$path=__DIR__.'/storage/data.php';
$checks[]=['PHP 8+','PHP_VERSION',version_compare(PHP_VERSION,'8.0.0','>=')];
$checks[]=['Sessions','',function_exists('session_start')];
$checks[]=['storage/ exists','',is_dir(__DIR__.'/storage')];
$checks[]=['data.php exists','',$path && is_file($path)];
$checks[]=['data.php readable','',is_readable($path)];
$checks[]=['data.php writable (needed for Check-in/Admin changes)','',is_writable($path)];
$checks[]=['storage/ directory writable (optional on this release)','',is_writable(__DIR__.'/storage')];
$db_ok=false;$db_error='';
if(is_file($path)&&is_readable($path)){
  try{$v=require $path;$db_ok=is_array($v);}catch(Throwable $e){$db_error=$e->getMessage();}
}
$checks[]=['Database file valid','',$db_ok];
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR Host Preflight</title><link rel="stylesheet" href="assets/typography.css?v=1.22.4"><style>
body{font-family:MRBAR Thai,Noto Sans Thai,Tahoma,Leelawadee UI,Thonburi,Segoe UI,Arial,sans-serif;background:#09090d;color:#fff;padding:20px}.box{max-width:820px;margin:auto;background:#15151d;padding:24px;border-radius:18px}li{padding:10px 0;border-bottom:1px solid #292936}.ok{color:#7dff9b}.bad{color:#ff7474}.note{background:#101018;padding:14px;border-radius:12px;margin-top:18px}code{background:#222;padding:2px 5px;border-radius:5px}
</style></head><body><div class="box"><h1>MR BAR Host Preflight</h1><p>StartUp Version 1.0.1 — Host Compatibility Fix</p><ul><?php foreach($checks as $c):?><li class="<?=$c[2]?'ok':'bad'?>"><?=$c[2]?'✓':'✗'?> <?=htmlspecialchars($c[0])?></li><?php endforeach;?></ul>
<div class="note"><b>ถ้า data.php writable เป็น ✓</b> ระบบ Check-in / Admin สามารถบันทึกข้อมูลได้ แม้ <code>storage/</code> จะไม่ writable เพราะรุ่นนี้เขียนลงไฟล์เดิมโดยตรง</div>
<?php if($db_error):?><div class="note bad">Database error: <?=htmlspecialchars($db_error)?></div><?php endif;?>
<div class="note">หลังตรวจสอบเสร็จ ให้ลบหรือเปลี่ยนชื่อ <code>preflight.php</code> เพื่อไม่เปิดข้อมูลระบบต่อสาธารณะ</div>
</div></body></html>