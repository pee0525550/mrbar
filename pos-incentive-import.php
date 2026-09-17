<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';
require_once __DIR__.'/app/pos-incentive.php';
$u=require_permission('employees.manage');$msg='';$err='';$uid=(int)($u['id']??0);
$validDate=fn(string $date)=>(bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',$date);
$dateFrom=(string)($_POST['date_from']??date('Y-m-01'));$dateTo=(string)($_POST['date_to']??date('Y-m-t'));$postedKind=(string)($_POST['report_kind']??'');
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();try{
    if(!in_array($postedKind,['bill_detail','menu_sales'],true))throw new RuntimeException('กรุณาเลือกประเภท Report ที่ต้องการ Import');
    if(!$validDate($dateFrom)||!$validDate($dateTo)||$dateTo<$dateFrom)throw new RuntimeException('กรุณาระบุวันที่เริ่มต้นและวันที่สิ้นสุดของ Report ให้ถูกต้อง');
    $stored=posi_inbox_store_upload($_FILES['pos_file']??[]);$title=trim((string)($_POST['report_title']??''));if($title==='')$title=pathinfo((string)$stored['original_name'],PATHINFO_FILENAME);$detail=trim((string)($_POST['report_detail']??''));
    try{
        $parsed=posi_parse_file(posi_inbox_dir().'/'.$stored['stored_name'],$stored['ext'],20);$map=posi_guess_columns($parsed['headers']);
        if($postedKind==='bill_detail'&&(($map['ref']??-1)<0||($map['sales']??-1)<0||($map['date']??-1)<0))throw new RuntimeException('Report รายละเอียดบิลต้องมีเลขบิล วันที่/เวลา และยอดขาย');
        if($postedKind==='menu_sales'&&(($map['item']??-1)<0||($map['qty']??-1)<0||($map['sales']??-1)<0))throw new RuntimeException('Report การขายตามเมนูต้องมีชื่อเมนู จำนวนขาย และยอดขาย');
        db_mutate(function($d)use($stored,$postedKind,$title,$detail,$dateFrom,$dateTo,$uid){
            if(!isset($d['pos_upload_inbox'])||!is_array($d['pos_upload_inbox']))$d['pos_upload_inbox']=[];
            foreach($d['pos_upload_inbox'] as $existing)if(($existing['sha256']??'')===$stored['sha256']&&(string)($existing['period_from']??'')===$dateFrom&&(string)($existing['period_to']??'')===$dateTo&&in_array((string)($existing['status']??''),['uploaded','processing','completed'],true))throw new RuntimeException(posi_bill_report_label($postedKind).' ไฟล์เดียวกันในรอบวันที่นี้มีอยู่ในระบบแล้ว');
            $entry=array_replace($stored,['id'=>next_id($d['pos_upload_inbox']),'report_kind'=>$postedKind,'title'=>$title,'detail'=>$detail,'period_mode'=>'custom','period_from'=>$dateFrom,'period_to'=>$dateTo,'status'=>'uploaded','uploaded_at'=>date('c'),'uploaded_by'=>$uid,'processed_at'=>'','processed_by'=>null,'batch_id'=>null,'error'=>'']);
            $d['pos_upload_inbox'][]=$entry;$d['audit'][]=['at'=>date('c'),'action'=>'pos_file_uploaded_to_inbox','inbox_id'=>$entry['id'],'report_kind'=>$postedKind,'period_from'=>$dateFrom,'period_to'=>$dateTo,'by'=>$uid];return $d;
        });
    }catch(Throwable $uploadError){$path=posi_inbox_dir().'/'.basename((string)$stored['stored_name']);if(is_file($path))@unlink($path);throw $uploadError;}
    $msg='Import '.posi_bill_report_label($postedKind).' เข้า Server สำเร็จแล้ว · รอทีมทำเงินเดือนเลือกไป Process';
}catch(Throwable $e){$err=$e->getMessage();}}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR — Import File จาก POS</title><link rel="stylesheet" href="assets/admin.css?v=1240"><link rel="stylesheet" href="assets/admin-v14.css?v=1240"><link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298"><link rel="stylesheet" href="assets/pos-upload-inbox-v1390.css?v=1437"><link rel="stylesheet" href="assets/pos-wizard-strict-v1410.css?v=1410"><link rel="stylesheet" href="assets/pos-suite.css?v=1482"></head><body class="admin-v14-page posi-page"><?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('incentive',$u);?>
<main class="posi-shell"><header class="posi-head"><div><p>PEOPLE / POS INCENTIVE</p><h1>Import File จาก POS</h1><span>บันทึกไฟล์รายงานและช่วงวันที่เข้า Server เท่านั้น · ยังไม่เริ่มคำนวณ</span></div><div><a href="pos-incentive.php">เลือกไฟล์ไปคำนวณ →</a></div></header>
<nav class="pos-suite-nav"><a class="active" href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>อัปโหลดไฟล์</small></div></a><a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>ระบบเดิม / Mapping / Sort</small></div></a><a href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a><a href="pos-commission.php"><span>4</span><div><b>ค่าคอม</b><small>PR ในทีม Sales</small></div></a><a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a></nav>
<?php if($msg):?><div class="posi-notice ok">✓ <?=h($msg)?></div><?php endif;?><?php if($err):?><div class="posi-notice err">⚠ <?=h($err)?></div><?php endif;?>
<section class="posi-import-intro"><div><small>IT FILE DROP</small><h2>อัปโหลดไฟล์ POS แยกตามประเภท</h2><p>แต่ละไฟล์ Import แยกกันได้ทันที ไม่ต้องรออีกไฟล์ · ระบบเพียงเก็บไฟล์ไว้ให้ทีมทำเงินเดือนเลือกใช้ในขั้นตอนที่ 2</p></div><span class="posi-format-badge">XLSX / CSV</span></section>
<div class="posi-import-type-grid">
<section class="posi-card posi-import-card posi-import-type bill"><div class="posi-card-head"><div><small>REPORT TYPE 1</small><h2>รายละเอียดบิล</h2><p>เลขบิล · วันที่/เวลา · ยอดขาย สำหรับเทียบกับรอบเปิดโต๊ะของ Sales</p></div></div>
<form method="post" enctype="multipart/form-data" class="posi-inbox-upload"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="report_kind" value="bill_detail">
<div class="posi-inbox-fields"><label><span>ชื่อรายการ Report</span><input name="report_title" value="<?=h($postedKind==='bill_detail'?(string)($_POST['report_title']??''):'')?>" placeholder="เช่น รายละเอียดบิล รอบเดือนกันยายน"></label><label><span>รายละเอียด Report</span><input name="report_detail" value="<?=h($postedKind==='bill_detail'?(string)($_POST['report_detail']??''):'')?>" placeholder="เช่น ไฟล์จาก POS เครื่องหลัก"></label></div>
<div class="posi-inbox-fields"><label><span>วันที่เริ่มต้นของ Report *</span><input type="date" name="date_from" value="<?=h($dateFrom)?>" required></label><label><span>วันที่สิ้นสุดของ Report *</span><input type="date" name="date_to" value="<?=h($dateTo)?>" required></label></div>
<label class="posi-inbox-file"><span class="upload-icon">1</span><div><b>เลือกไฟล์รายละเอียดบิล *</b><small>ระบบจะตรวจหาเลขบิล วันที่/เวลา และยอดขายก่อนรับไฟล์</small></div><input type="file" name="pos_file" accept=".xlsx,.csv" required></label>
<button>Import รายละเอียดบิลเข้า Server →</button></form></section>
<section class="posi-card posi-import-card posi-import-type menu"><div class="posi-card-head"><div><small>REPORT TYPE 2</small><h2>ยอดขายตามเมนู</h2><p>ชื่อเมนู · จำนวนขาย · ยอดขาย สำหรับค่าดื่มและค่าคอม</p></div></div>
<form method="post" enctype="multipart/form-data" class="posi-inbox-upload"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="report_kind" value="menu_sales">
<div class="posi-inbox-fields"><label><span>ชื่อรายการ Report</span><input name="report_title" value="<?=h($postedKind==='menu_sales'?(string)($_POST['report_title']??''):'')?>" placeholder="เช่น ยอดขายตามเมนู รอบเดือนกันยายน"></label><label><span>รายละเอียด Report</span><input name="report_detail" value="<?=h($postedKind==='menu_sales'?(string)($_POST['report_detail']??''):'')?>" placeholder="เช่น ไฟล์สรุปเมนูจาก POS"></label></div>
<div class="posi-inbox-fields"><label><span>วันที่เริ่มต้นของ Report *</span><input type="date" name="date_from" value="<?=h($dateFrom)?>" required></label><label><span>วันที่สิ้นสุดของ Report *</span><input type="date" name="date_to" value="<?=h($dateTo)?>" required></label></div>
<label class="posi-inbox-file"><span class="upload-icon">2</span><div><b>เลือกไฟล์ยอดขายตามเมนู *</b><small>ระบบจะตรวจหาชื่อเมนู จำนวนขาย และยอดขายก่อนรับไฟล์</small></div><input type="file" name="pos_file" accept=".xlsx,.csv" required></label>
<button>Import ยอดขายตามเมนูเข้า Server →</button></form></section>
</div>
<section class="posi-flow-actions"><span></span><div><b>เสร็จสิ้นขั้นตอน Import</b><small>ไฟล์นี้ยังไม่ถูก Process หรือคำนวณ</small></div><a class="next" href="pos-incentive.php">ไปหน้าเลือกไฟล์เพื่อคำนวณ →</a></section>
<footer>MR BAR POS Import · <?=h((string)(app_config()['pack']??'MR BAR'))?></footer></main></body></html>
