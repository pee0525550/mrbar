<?php
require __DIR__.'/bootstrap.php';
$u=require_permission('employees.manage');
$d=db_load();
require_once __DIR__.'/admin-nav.php';
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR — เลือกประเภทค่าดื่ม</title><link rel="stylesheet" href="assets/admin.css?v=1240"><link rel="stylesheet" href="assets/admin-v14.css?v=1240"><link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298"><link rel="stylesheet" href="assets/pos-suite.css?v=14814"></head><body class="admin-v14-page posi-page"><?php echo admin_sidebar('incentive',$u);?><main class="posi-shell">
<header class="posi-head"><div><p>PEOPLE / POS INCENTIVE</p><h1>POS Incentive & Commission</h1><span>เลือกประเภทการคำนวณค่าดื่มก่อนเข้าหน้าสูตร</span></div><div><a href="employees.php">Employee Center</a><a href="payroll-attendance.php">Attendance & Payroll</a></div></header>
<nav class="pos-suite-nav"><a href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>อัปโหลดไฟล์</small></div></a><a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>ระบบเดิม / Mapping / Sort</small></div></a><a class="active" href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a><a href="pos-commission.php"><span>4</span><div><b>ค่าคอม Sales</b><small>รายละเอียดบิล</small></div></a><a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a></nav>
<section class="pos-drink-landing"><div><p class="pos-drink-kicker">DRINK PAYOUT</p><h2>เลือกประเภทค่าดื่มที่จะคำนวณ</h2><p>แยกทางเข้าของ PR และ Sales ไว้ก่อน เพื่อรองรับสูตรคำนวณคนละแบบในขั้นถัดไป</p></div><div class="pos-drink-landing-grid"><a class="pos-drink-choice pr" href="pos-drinks.php?drink_mode=pr"><small>PR DRINK</small><b>คำนวณค่าดื่มของ PR</b><span>ใช้สำหรับรอบค่าดื่ม PR จาก Report ยอดขายตามเมนู</span></a><a class="pos-drink-choice sales" href="pos-drinks.php?drink_mode=sales"><small>SALES DRINK</small><b>คำนวณค่าดื่มของ Sales</b><span>เตรียมแยกสูตร Sales จาก PR ในรอบถัดไป</span></a></div></section>
<p class="note">ขั้นตอนนี้เป็นหน้า Landing สำหรับเลือกประเภทค่าดื่มเท่านั้น · สูตรคำนวณ Sales จะแยกต่อในรอบถัดไป</p>
</main></body></html>
