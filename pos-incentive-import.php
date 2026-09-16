<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';
require_once __DIR__.'/app/pos-incentive.php';
$u=require_permission('employees.manage');$msg='';$err='';$uid=(int)($u['id']??0);
$validDate=fn(string $date)=>(bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',$date);
$dateFrom=(string)($_POST['date_from']??date('Y-m-01'));$dateTo=(string)($_POST['date_to']??date('Y-m-t'));
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();try{
    if(!$validDate($dateFrom)||!$validDate($dateTo)||$dateTo<$dateFrom)throw new RuntimeException('กรุณาระบุวันที่เริ่มต้นและวันที่สิ้นสุดของ Report ให้ถูกต้อง');
    $stored=posi_inbox_store_upload($_FILES['pos_file']??[]);
    $title=trim((string)($_POST['report_title']??''));if($title==='')throw new RuntimeException('กรุณาใส่ชื่อรายการ Report');
    $detail=trim((string)($_POST['report_detail']??''));
    try{db_mutate(function($d)use($stored,$title,$detail,$dateFrom,$dateTo,$uid){
        if(!isset($d['pos_upload_inbox'])||!is_array($d['pos_upload_inbox']))$d['pos_upload_inbox']=[];
        foreach($d['pos_upload_inbox'] as $existing)if(($existing['sha256']??'')===$stored['sha256']&&(string)($existing['period_from']??'')===$dateFrom&&(string)($existing['period_to']??'')===$dateTo&&in_array((string)($existing['status']??''),['uploaded','processing','completed'],true))throw new RuntimeException('ไฟล์เดียวกันและรอบวันที่นี้มีอยู่ในระบบแล้ว');
        $entry=array_replace($stored,['id'=>next_id($d['pos_upload_inbox']),'title'=>$title,'detail'=>$detail,'period_mode'=>'custom','period_from'=>$dateFrom,'period_to'=>$dateTo,'status'=>'uploaded','uploaded_at'=>date('c'),'uploaded_by'=>$uid,'processed_at'=>'','processed_by'=>null,'batch_id'=>null,'error'=>'']);
        $d['pos_upload_inbox'][]=$entry;$d['audit'][]=['at'=>date('c'),'action'=>'pos_file_uploaded_to_inbox','inbox_id'=>$entry['id'],'period_from'=>$dateFrom,'period_to'=>$dateTo,'by'=>$uid];return $d;
    });}catch(Throwable $uploadError){$path=posi_inbox_dir().'/'.basename((string)$stored['stored_name']);if(is_file($path))@unlink($path);throw $uploadError;}
    $msg='Import ไฟล์ POS เข้า Server สำเร็จแล้ว';
}catch(Throwable $e){$err=$e->getMessage();}}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR — Import File จาก POS</title><link rel="stylesheet" href="assets/admin.css?v=1240"><link rel="stylesheet" href="assets/admin-v14.css?v=1240"><link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298"><link rel="stylesheet" href="assets/pos-upload-inbox-v1390.css?v=1437"><link rel="stylesheet" href="assets/pos-wizard-strict-v1410.css?v=1410"><link rel="stylesheet" href="assets/pos-suite.css?v=1463"></head><body class="admin-v14-page posi-page"><?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('incentive',$u);?>
<main class="posi-shell"><header class="posi-head"><div><p>PEOPLE / POS INCENTIVE</p><h1>Import File จาก POS</h1><span>บันทึกไฟล์รายงานและช่วงวันที่เข้า Server เท่านั้น · ยังไม่เริ่มคำนวณ</span></div><div><a href="pos-incentive.php">เลือกไฟล์ไปคำนวณ →</a></div></header>
<nav class="pos-suite-nav"><a class="active" href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>อัปโหลดไฟล์</small></div></a><a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>ระบบเดิม / Mapping / Sort</small></div></a><a href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a><a href="pos-commission.php"><span>4</span><div><b>ค่าคอม</b><small>PR ในทีม Sales</small></div></a><a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a></nav>
<?php if($msg):?><div class="posi-notice ok">✓ <?=h($msg)?></div><?php endif;?><?php if($err):?><div class="posi-notice err">⚠ <?=h($err)?></div><?php endif;?>
<section class="posi-card posi-import-card"><div class="posi-card-head row"><div><small>IMPORT POS REPORT</small><h2>อัปโหลดรายงานจาก POS</h2><p>กรอกข้อมูลของ Report ให้ครบ แล้วเก็บไฟล์ต้นฉบับไว้สำหรับทีมคำนวณเลือกใช้ภายหลัง</p></div><span class="posi-format-badge">XLSX / CSV</span></div>
<form method="post" enctype="multipart/form-data" class="posi-inbox-upload"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="posi-inbox-fields"><label><span>ชื่อรายการ Report *</span><input name="report_title" value="<?=h((string)($_POST['report_title']??''))?>" placeholder="เช่น ยอดขายตามสินค้า รอบเดือนกันยายน" required></label><label><span>รายละเอียด Report</span><input name="report_detail" value="<?=h((string)($_POST['report_detail']??''))?>" placeholder="เช่น ไฟล์ปิดรอบจาก POS เครื่องหลัก"></label></div>
<div class="posi-inbox-fields"><label><span>วันที่เริ่มต้นของ Report *</span><input type="date" name="date_from" value="<?=h($dateFrom)?>" required></label><label><span>วันที่สิ้นสุดของ Report *</span><input type="date" name="date_to" value="<?=h($dateTo)?>" required></label></div>
<label class="posi-inbox-file"><span class="upload-icon">↑</span><div><b>เลือกไฟล์ Excel / CSV</b><small>สูงสุด 20MB · ระบบเก็บไฟล์ต้นฉบับไว้ตรวจย้อนหลัง</small></div><input type="file" name="pos_file" accept=".xlsx,.csv" required></label>
<button>Import ไฟล์เข้า Server</button></form></section>
<section class="posi-flow-actions"><span></span><div><b>เสร็จสิ้นขั้นตอน Import</b><small>ไฟล์นี้ยังไม่ถูก Process หรือคำนวณ</small></div><a class="next" href="pos-incentive.php">ไปหน้าเลือกไฟล์เพื่อคำนวณ →</a></section>
<footer>MR BAR POS Import · <?=h((string)(app_config()['pack']??'MR BAR'))?></footer></main></body></html>
