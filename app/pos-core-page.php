<?php
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/pos-cores.php';
$u=require_permission('employees.manage');$d=db_load();$bucket=pc_bucket($posCore);
$title=$posCore==='drinks'?'ค่าดื่ม':'ค่าคอม';$url=$posCore==='drinks'?'pos-drinks.php':'pos-commission.php';
$key='pos_core_'.(int)$d['_branch_context']['id'].'_'.(int)$u['id'].'_'.$posCore;
$state=$_SESSION[$key]??['step'=>1];$err='';$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();try{
  $action=(string)($_POST['action']??'');
  if($action==='select'){
   $id=(int)($_POST['batch_id']??0);$found=false;
   foreach($d['pos_import_batches']??[] as $b)if((int)$b['id']===$id&&($b['status']??'active')==='active')$found=true;
   if(!$found)throw new RuntimeException('เลือก Report ที่ Process สำเร็จ');
   $state=['step'=>2,'batch_id'=>$id];
  }elseif($action==='back'){
   $state['step']=max(1,(int)$state['step']-1);unset($state['preview_hash']);
  }elseif($action==='preview'){
   if(($state['step']??1)!==2)throw new RuntimeException('เลือก Report ก่อน');
   $result=pc_calculate($d,$posCore,(int)$state['batch_id'],$_POST);
   $state['input']=$result['rule'];$state['preview_hash']=hash('sha256',serialize($result));$state['step']=3;
  }elseif($action==='save'){
   if(($state['step']??1)!==3||empty($state['preview_hash']))throw new RuntimeException('ตรวจผลคำนวณก่อนบันทึก');
   db_mutate(function($d)use($state,$posCore,$u){
    $r=pc_calculate($d,$posCore,(int)$state['batch_id'],$state['input']);
    if(!hash_equals($state['preview_hash'],hash('sha256',serialize($r))))throw new RuntimeException('ข้อมูลเปลี่ยนแล้ว กรุณาย้อนกลับคำนวณใหม่');
    return pc_save($d,$posCore,(int)$state['batch_id'],$state['input'],(int)$u['id']);
   });
   $state=['step'=>1];$msg='บันทึกรอบ'.$title.'แล้ว';$d=db_load();
  }else throw new RuntimeException('คำสั่งไม่ถูกต้อง');
  $_SESSION[$key]=$state;
 }catch(Throwable $e){$err=$e->getMessage();}
}
$step=(int)$state['step'];$result=null;
$defaults=posi_role_commission_rule([],'','');
if($step>1)try{$result=pc_calculate($d,$posCore,(int)$state['batch_id'],$posCore==='drinks'?($state['input']??$defaults):array_replace($defaults,$state['input']??[]));}catch(Throwable $e){
 if($step===3){$err=$e->getMessage();$step=2;$state['step']=2;unset($state['preview_hash']);$_SESSION[$key]=$state;}
 // Team selection is mandatory only when submitting calculation. Discover the source using direct-drink mode.
 try{$result=pc_calculate($d,'drinks',(int)$state['batch_id'],$defaults);}catch(Throwable $e){$err=$e->getMessage();$step=1;$state=['step'=>1];$_SESSION[$key]=$state;}
}
$rule=$state['input']??$defaults;
$sales=array_values(array_filter($d['employees']??[],fn($e)=>!empty($e['active'])&&($e['position']??'')==='sales'));
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MR BAR — <?=h($title)?></title><link rel="stylesheet" href="assets/admin.css?v=1240"><link rel="stylesheet" href="assets/admin-v14.css?v=1240"><link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298"><link rel="stylesheet" href="assets/pos-suite.css?v=1463"></head><body class="admin-v14-page posi-page"><?php require_once __DIR__.'/admin-nav.php';echo admin_sidebar('incentive',$u);?><main class="posi-shell">
<header class="posi-head"><div><p>PEOPLE / POS INCENTIVE</p><h1>POS Incentive & Commission</h1><span>ระบบเดิมและการคำนวณใหม่อยู่ในชุดงานเดียวกัน</span></div><div><a href="employees.php">Employee Center</a><a href="payroll-attendance.php">Attendance & Payroll</a></div></header>
<nav class="pos-suite-nav"><a href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>อัปโหลดไฟล์</small></div></a><a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>ระบบเดิม / Mapping / Sort</small></div></a><a class="<?=$posCore==='drinks'?'active':''?>" href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a><a class="<?=$posCore==='commission'?'active':''?>" href="pos-commission.php"><span>4</span><div><b>ค่าคอม</b><small>PR ในทีม Sales</small></div></a><a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a></nav>
<?php if($err):?><p class="error" role="alert"><?=h($err)?></p><?php endif;?><?php if($msg):?><p class="success"><?=h($msg)?></p><?php endif;?>
<?php if($posCore==='commission'):?><section><h2>ยอดเชียร์ลูกค้าของ Sales</h2><p>อ้างอิงรอบเปิด–ปิดโต๊ะและใบเสร็จ ระบบยังรอรายงานใบเสร็จ POS เพื่อจับคู่ยอด จึงยังไม่รวมยอดส่วนนี้เป็นค่าคอม</p><a href="sales-table.php?public_branch=<?=h(rawurlencode((string)(branch_current($d)['slug']??'')))?>">ตรวจรอบโต๊ะและใบเสร็จ →</a></section><?php endif;?>
<p class="note">ขั้นตอน <?=$step?> จาก 3 · <?=$step===1?'เลือก Report':($step===2?'กำหนดอัตรา':'ตรวจผลและบันทึก')?> · รอบบันทึกของค่าดื่มและค่าคอมแยกจากกัน</p>
<?php if($step===1):?><section><h2>เลือก Report ที่ Process แล้ว</h2><p><a href="pos-incentive-import.php">Import File จาก POS</a> · <a href="pos-incentive.php?step=1">เตรียม / Process ไฟล์จากระบบเดิม</a></p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="select"><label>Report<select name="batch_id" required><option value="">เลือก Report</option><?php foreach(array_reverse($d['pos_import_batches']??[]) as $b):if(($b['status']??'active')!=='active')continue;?><option value="<?=(int)$b['id']?>">#<?=(int)$b['id']?> <?=h((string)($b['source_name']??''))?> · <?=h((string)$b['period_start'].' – '.(string)$b['period_end'])?></option><?php endforeach;?></select></label><button>ขั้นตอนต่อไป →</button></form></section>
<?php elseif($step===2):?><section><h2>กำหนดอัตรา<?=h($title)?></h2><p>รอบ <?=h($result['from'].' – '.$result['to'])?> · ใช้วันที่จาก Report</p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="preview">
<?php $fields=$posCore==='drinks'?['pr_rate_d'=>'PR · D','pr_rate_m'=>'PR · M','sales_own_rate_d'=>'Sales · D','sales_own_rate_m'=>'Sales · M']:['sales_team_rate_d'=>'ค่าคอมต่อดื่ม PR · D','sales_team_rate_m'=>'ค่าคอมต่อดื่ม PR · M'];foreach($fields as $k=>$label):?><label><?=h($label)?> (บาท/ดื่ม)<input type="number" step="0.01" min="0" name="<?=h($k)?>" value="<?=h((string)$rule[$k])?>" required></label><?php endforeach;?>
<p>D / M เป็นรหัสในรายงาน POS</p>
<?php if($posCore==='commission'):?><?php foreach($result['teams'] as $team=>$units):?><label><?=h((string)$team)?> · D <?=number_format($units['D']??0)?> / M <?=number_format($units['M']??0)?><select name="team_sales_map[<?=h((string)$team)?>]" required><option value="">เลือก Sales ผู้ดูแล</option><?php foreach($sales as $e):?><option value="<?=(int)$e['id']?>" <?=((int)($rule['team_sales_map'][$team]??0)===(int)$e['id'])?'selected':''?>><?=h((string)$e['code'].' · '.(string)$e['name'])?></option><?php endforeach;?></select></label><?php endforeach;?><?php endif;?>
<button>ขั้นตอนต่อไป: ตรวจผลคำนวณ →</button></form></section>
<?php else:?><section><h2>ตรวจผล<?=h($title)?></h2><p>รอบ <?=h($result['from'].' – '.$result['to'])?></p>
<?php if($result['unmapped']):?><p class="error">ยังมี <?=count($result['unmapped'])?> เมนูที่ไม่ผูก Employee Card กรุณาจัดการ Alias ก่อนบันทึก</p><?php endif;?>
<div class="scroll"><table><thead><tr><th>พนักงาน</th><th>ตำแหน่ง / ทีม</th><th>D</th><th>M</th><th><?=h($title)?> (บาท)</th></tr></thead><tbody><?php foreach($result['rows'] as $r):?><tr><td><?=h($r['name'])?></td><td><?=h($r['role'].' '.$r['teams'])?></td><td><?=number_format($r['d'],2)?></td><td><?=number_format($r['m'],2)?></td><td><?=number_format($r['amount'],2)?></td></tr><?php endforeach;?></tbody><tfoot><tr><th colspan="4">รวมเฉพาะ<?=h($title)?></th><th><?=number_format($result['total'],2)?></th></tr></tfoot></table></div>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save"><button <?=(!$result['rows']||$result['unmapped'])?'disabled':''?>>บันทึกรอบ<?=h($title)?></button></form></section><?php endif;?>
<?php if($step>1):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><button name="action" value="back">← ขั้นตอนก่อนหน้า</button></form><?php endif;?>
<section><h2>รอบ<?=h($title)?>ที่บันทึกแล้ว</h2><?php foreach(array_reverse($d[$bucket]??[]) as $saved):if(($saved['status']??'saved')==='void')continue;?><details><summary><?=h($saved['from'].' – '.$saved['to'])?> · <?=number_format($saved['total'],2)?> บาท</summary><div class="scroll"><table><tr><th>พนักงาน</th><th>D</th><th>M</th><th>ยอดจ่าย</th></tr><?php foreach($saved['rows'] as $r):?><tr><td><?=h($r['name'])?></td><td><?=number_format($r['d'],2)?></td><td><?=number_format($r['m'],2)?></td><td><?=number_format($r['amount'],2)?></td></tr><?php endforeach;?></table></div></details><?php endforeach;?></section>
</main></body></html>
