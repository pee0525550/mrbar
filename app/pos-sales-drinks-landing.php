<?php
require __DIR__.'/bootstrap.php';
$u=require_permission('employees.manage');
$d=db_load();
require_once __DIR__.'/admin-nav.php';
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR — เลือกค่าดื่ม Sales</title><link rel="stylesheet" href="assets/admin.css?v=1240"><link rel="stylesheet" href="assets/admin-v14.css?v=1240"><link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298"><link rel="stylesheet" href="assets/pos-suite.css?v=14814"></head><body class="admin-v14-page posi-page"><?php echo admin_sidebar('incentive',$u);?><main class="posi-shell">
<header class="posi-head"><div><p>PEOPLE / POS INCENTIVE</p><h1>ค่าดื่ม Sales</h1><span>เลือกประเภทค่าดื่มฝั่ง Sales ก่อนเข้าหน้าสูตร</span></div><div><a href="employees.php">Employee Center</a><a href="payroll-attendance.php">Attendance & Payroll</a></div></header>
<nav class="pos-suite-nav"><a href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>อัปโหลดไฟล์</small></div></a><a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>ระบบเดิม / Mapping / Sort</small></div></a><a class="active" href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a><a href="pos-commission.php"><span>4</span><div><b>ค่าคอม Sales</b><small>รายละเอียดบิล</small></div></a><a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a></nav>
<section class="pos-drink-landing"><div><p class="pos-drink-kicker">SALES DRINK PAYOUT</p><h2>เลือกประเภทค่าดื่มของ Sales</h2><p>แยกไว้ก่อนตามโครงสร้างงานจริง: Sales ได้ดื่มตรง และ Sales ได้จากน้อง PR ในทีม ซึ่งสูตรจะไม่เหมือนกัน</p></div><div class="pos-drink-landing-grid"><a class="pos-drink-choice sales-direct" href="pos-drinks.php?drink_mode=sales&amp;sales_drink_type=direct"><small>DIRECT DRINK</small><b>ดื่มตรงของ Sales</b><span>สำหรับยอดดื่มที่ผูกกับ Sales โดยตรง</span></a><a class="pos-drink-choice sales-team" href="pos-drinks.php?drink_mode=sales&amp;sales_drink_type=team_pr"><small>TEAM PR DRINK</small><b>ดื่มจากน้อง PR ในทีม</b><span>สำหรับยอดดื่มของ PR ที่ต้องคิดต่อให้ Sales ตามทีม</span></a></div></section>
<p class="note"><a href="pos-drinks.php">← กลับไปเลือก PR / Sales</a> · หน้านี้เป็นจุดแยกประเภทก่อน สูตรคำนวณจะต่อในรอบถัดไป</p>
</main></body></html>
