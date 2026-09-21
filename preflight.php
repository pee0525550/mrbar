<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$u=require_roles('admin');
if(empty($u['super_admin'])){http_response_code(403);exit('Super Admin only');}

$checks=[];$storage=__DIR__.'/storage';$primary=db_primary_path();$runtime=db_runtime_path();$active=db_path();
$perm=function(string $path): string {$p=@fileperms($path);return $p===false?'unknown':substr(sprintf('%o',$p),-4);};
$checks[]=['PHP 8+',PHP_VERSION,version_compare(PHP_VERSION,'8.0.0','>=')];
$checks[]=['Sessions',session_save_path()?:'PHP default',function_exists('session_start')];
$checks[]=['storage/ exists',$storage.' · permission '.$perm($storage),is_dir($storage)];
$checks[]=['storage/ reported writable','PHP is_writable() result',is_dir($storage)&&is_writable($storage)];
$checks[]=['data.php exists',$primary.' · permission '.$perm($primary),is_file($primary)];
$checks[]=['data.php readable','PHP is_readable() result',is_file($primary)&&is_readable($primary)];
$checks[]=['data.php writable','PHP is_writable() result',is_file($primary)&&is_writable($primary)];
$checks[]=['runtime-data.php state',(is_file($runtime)?'exists':'not present').' · permission '.$perm($runtime),!is_file($runtime)||is_readable($runtime)];
$checks[]=['runtime-data.php writable','Required when this is the active database',!is_file($runtime)||is_writable($runtime)];
$checks[]=['Active database',basename($active).' · permission '.$perm($active),is_file($active)&&is_readable($active)];
$checks[]=['Active database writable',$active,is_file($active)&&is_writable($active)];

$probeOk=false;$probeError='';
if(is_dir($storage)){
    $probe=$storage.'/.mrbar-write-probe-'.bin2hex(random_bytes(5)).'.tmp';
    try{$written=@file_put_contents($probe,'MRBAR '.date('c'),LOCK_EX);$probeOk=$written!==false&&is_file($probe);if(!$probeOk)$probeError=error_get_last()['message']??'file_put_contents returned false';}catch(Throwable $e){$probeError=$e->getMessage();}
    if(is_file($probe)&&!@unlink($probe))$probeError=trim($probeError.'; probe file could not be deleted','; ');
}
$checks[]=['Real storage write/delete probe',$probeError?:'temporary file created and removed',$probeOk&&$probeError===''];

$dbOk=false;$dbError='';$schema=0;
if(is_file($active)&&is_readable($active)){
    try{$value=require $active;$dbOk=is_array($value);$schema=(int)($value['meta']['schema']??0);}catch(Throwable $e){$dbError=$e->getMessage();}
}
$checks[]=['Database file valid','Schema '.$schema,$dbOk];
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>MR BAR Host Preflight</title><link rel="stylesheet" href="assets/typography.css?v=1.22.4"><style>
body{font-family:MRBAR Thai,Noto Sans Thai,Tahoma,Leelawadee UI,Thonburi,Segoe UI,Arial,sans-serif;background:#09090d;color:#fff;padding:20px}.box{max-width:980px;margin:auto;background:#15151d;padding:24px;border-radius:18px}li{padding:10px 0;border-bottom:1px solid #292936}.ok{color:#7dff9b}.bad{color:#ff7474}.detail{display:block;color:#a8afc2;font-size:12px;margin:5px 0 0 25px;overflow-wrap:anywhere}.note{background:#101018;padding:14px;border-radius:12px;margin-top:18px}code{background:#222;padding:2px 5px;border-radius:5px}
</style></head><body><div class="box"><h1>MR BAR Host Preflight</h1><p>Storage Diagnostics · <?=htmlspecialchars((string)(app_config()['version']??''))?></p><ul><?php foreach($checks as $check):?><li class="<?=$check[2]?'ok':'bad'?>"><?=$check[2]?'✓':'✗'?> <?=htmlspecialchars((string)$check[0])?><?php if((string)$check[1]!==''):?><small class="detail"><?=htmlspecialchars((string)$check[1])?></small><?php endif;?></li><?php endforeach;?></ul>
<div class="note"><b>เกณฑ์ผ่าน:</b> Active database writable หรือ storage write/delete probe ต้องผ่านอย่างน้อยหนึ่งทาง หากมี <code>runtime-data.php</code> ระบบจะเลือกไฟล์นี้ก่อน <code>data.php</code> เสมอ</div>
<?php if($dbError):?><div class="note bad">Database error: <?=htmlspecialchars($dbError)?></div><?php endif;?>
<div class="note">หน้านี้เปิดได้เฉพาะ Super Admin การทดสอบจะสร้างและลบไฟล์ชั่วคราวขนาดเล็กใน <code>storage</code> โดยไม่แก้ข้อมูลธุรกิจ</div>
</div></body></html>
