<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';

$u=current_user();
if(!$u){header('Location:login.php');exit;}
$d=db_load();

$canLeaveManage=(($u['role']??'')==='admin')||user_can($u,'leave.manage',$d);
$canLeave=$canLeaveManage||user_can($u,'leave.view',$d);
$canExceptions=(($u['role']??'')==='admin')||user_can($u,'workforce.exceptions.view',$d)||user_can($u,'workforce.exceptions.manage',$d)||user_can($u,'attendance.view',$d)||user_can($u,'attendance.manage',$d)||user_can($u,'substitute.manage',$d);
$canPayroll=(($u['role']??'')==='admin')||user_can($u,'payroll.view',$d)||user_can($u,'payroll.manage',$d);
if(!$canLeave&&!$canExceptions&&!$canPayroll){http_response_code(403);exit('Permission denied');}

function ha_emp(array $d,int $id): array {
    $e=workforce_employee_by_id($d,$id);
    return $e?:['id'=>$id,'code'=>'EMP'.$id,'name'=>'Unknown','position'=>'other','branch_id'=>0];
}
function ha_status(string $s): string {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิก','confirmed'=>'ยืนยันหัก','waived'=>'ยกเว้น'][$s]??$s;
}
function ha_leave_type(string $x): string {
    return ['sick'=>'ลาป่วย','personal'=>'ลากิจ','vacation'=>'ลาพักร้อน','unpaid'=>'ลาไม่รับค่าจ้าง','other'=>'ลาอื่น ๆ'][$x]??$x;
}
function ha_when(string $v): string {
    $t=strtotime($v); return $t?date('d/m/Y H:i',$t):'—';
}
function ha_view_for_type(string $type): string {
    return ['leave'=>'leave','correction'=>'correction','substitute'=>'substitute','exception'=>'exception','penalty'=>'penalty'][$type]??'overview';
}
function ha_late_minutes(array $d,array $employee,string $checkIn): int {
    $pay=$employee['payroll']??[];$start=(string)($pay['start_time']??($d['settings']['shop_open_time']??'18:00'));$grace=max(0,(int)($d['settings']['attendance_late_grace_minutes']??15));
    $date=substr($checkIn,0,10);$in=strtotime($checkIn);$st=strtotime($date.' '.$start);if(!$in||!$st)return 0;$diff=(int)floor(($in-$st)/60)-$grace;return max(0,$diff);
}
function ha_thai_date(string $date): string {
    $months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $t=strtotime($date);if(!$t)return $date;return 'วันที่ '.date('j',$t).' '.$months[(int)date('n',$t)].' '.((int)date('Y',$t)+543);
}

$missing=[];foreach($d['attendance']??[] as $a){$st=workforce_attendance_state($a);if(in_array($st,['missing_checkout','provisional'],true))$missing[]=$a;}
$corrections=array_values(array_filter($d['time_correction_requests']??[],fn($r)=>(string)($r['status']??'')==='pending'));
$subs=array_values(array_filter($d['substitute_requests']??[],fn($r)=>(string)($r['status']??'')==='pending'));
$leaves=array_values(array_filter($d['leave_requests']??[],fn($r)=>(string)($r['status']??'')==='pending'));
$penalties=array_values(array_filter($d['attendance_penalties']??[],fn($r)=>(string)($r['status']??'pending')==='pending'));

$queue=[];
if($canLeave)foreach($leaves as $r){
    $eid=(int)($r['employee_id']??0);if(!$eid&&!empty($r['pr_id']))$eid=(int)(workforce_employee_by_pr($d,(int)$r['pr_id'])['id']??0);
    $e=ha_emp($d,$eid);$avatar=workforce_employee_avatar_url($d,$e);$queue[]=['avatar'=>$avatar,'type'=>'leave','priority'=>2,'at'=>(string)($r['requested_at']??''),'title'=>ha_leave_type((string)($r['type']??'')),'name'=>(string)$e['name'],'code'=>(string)$e['code'],'meta'=>(string)($r['start_date']??'').' → '.(string)($r['end_date']??''),'href'=>'admin-leaves.php?status=pending&employee_id='.$eid,'badge'=>'การลา'];
}
if($canExceptions)foreach($corrections as $r){$e=ha_emp($d,(int)($r['employee_id']??0));$avatar=workforce_employee_avatar_url($d,$e);$queue[]=['avatar'=>$avatar,'type'=>'correction','priority'=>3,'at'=>(string)($r['requested_at']??''),'title'=>'ขอแก้เวลาออก','name'=>(string)$e['name'],'code'=>(string)$e['code'],'meta'=>ha_when((string)($r['requested_checkout']??'')),'href'=>'workforce-exceptions.php','badge'=>'แก้เวลา'];}
if($canExceptions)foreach($subs as $r){$e=ha_emp($d,(int)($r['original_employee_id']??0));$avatar=workforce_employee_avatar_url($d,$e);$queue[]=['avatar'=>$avatar,'type'=>'substitute','priority'=>4,'at'=>(string)($r['requested_at']??''),'title'=>'คำขอ PR มาแทน','name'=>(string)$e['name'],'code'=>(string)$e['code'],'meta'=>(string)($r['date']??'').' · '.workforce_substitute_label($d,$r),'href'=>'workforce-exceptions.php','badge'=>'PR มาแทน'];}
if($canExceptions)foreach($missing as $a){$e=ha_emp($d,(int)($a['employee_id']??0));$avatar=workforce_employee_avatar_url($d,$e);$queue[]=['avatar'=>$avatar,'type'=>'exception','priority'=>5,'at'=>(string)($a['check_in']??''),'title'=>workforce_attendance_state($a)==='provisional'?'เวลา Provisional รอตรวจ':'Missing Check-out','name'=>(string)$e['name'],'code'=>(string)$e['code'],'meta'=>ha_when((string)($a['check_in']??'')),'href'=>'workforce-exceptions.php','badge'=>'Exception'];}
if($canPayroll)foreach($penalties as $p){$e=ha_emp($d,(int)($p['employee_id']??0));$avatar=workforce_employee_avatar_url($d,$e);$queue[]=['avatar'=>$avatar,'type'=>'penalty','priority'=>5,'at'=>(string)($p['created_at']??''),'title'=>'No-show รอยืนยันหัก','name'=>(string)$e['name'],'code'=>(string)$e['code'],'meta'=>(string)($p['date']??'').' · '.number_format((float)($p['amount']??0),2).' '.(string)($d['settings']['currency']??'THB'),'href'=>'payroll-attendance.php','badge'=>'No-show'];}
usort($queue,function($a,$b){$p=((int)$b['priority']<=> (int)$a['priority']);return $p!==0?$p:strcmp((string)$b['at'],(string)$a['at']);});

$view=(string)($_GET['view']??'overview');if(!in_array($view,['overview','leave','correction','substitute','exception','penalty'],true))$view='overview';
$viewUrls=['leave'=>'admin-leaves.php?embed=1&status=pending','correction'=>'workforce-exceptions.php?embed=1#time-correction','substitute'=>'workforce-exceptions.php?embed=1#pr-substitute','exception'=>'workforce-exceptions.php?embed=1#attendance-exception','penalty'=>'payroll-attendance.php?embed=1'];
$total=count($queue);$today=date('Y-m-d');$selectedDate=trim((string)($_GET['date']??$today));if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$selectedDate)||!strtotime($selectedDate))$selectedDate=$today;$prevDate=date('Y-m-d',strtotime($selectedDate.' -1 day'));$nextDate=date('Y-m-d',strtotime($selectedDate.' +1 day'));
$todayCount=count(array_filter($queue,fn($q)=>substr((string)$q['at'],0,10)===$today));
$leavesForDate=array_values(array_filter($leaves,function($r)use($selectedDate){$start=(string)($r['start_date']??'');$end=(string)($r['end_date']??$start);return $start!==''&&$selectedDate>=$start&&$selectedDate<=($end!==''?$end:$start);}));
$urgent=array_slice($queue,0,6);
$attendanceRows=[];$attendanceCounts=['normal'=>0,'late'=>0,'missing'=>0,'all'=>0];
foreach($d['attendance']??[] as $a){$in=(string)($a['check_in']??'');if($in===''||substr($in,0,10)!==$selectedDate)continue;$eid=(int)($a['employee_id']??0);if(!$eid&&!empty($a['pr_id']))$eid=(int)(workforce_employee_by_pr($d,(int)$a['pr_id'])['id']??0);$e=ha_emp($d,$eid);$avatar=workforce_employee_avatar_url($d,$e);$late=ha_late_minutes($d,$e,$in);$state=workforce_attendance_state($a);$bucket=in_array($state,['missing_checkout','provisional'],true)?'missing':($late>0?'late':'normal');$attendanceCounts[$bucket]++;$attendanceCounts['all']++;$attendanceRows[]=['a'=>$a,'employee'=>$e,'avatar'=>$avatar,'late'=>$late,'bucket'=>$bucket];}
usort($attendanceRows,fn($x,$y)=>strcmp((string)($y['a']['check_in']??''),(string)($x['a']['check_in']??'')));
$attFilter=(string)($_GET['att']??'all');if(!in_array($attFilter,['all','normal','late','missing'],true))$attFilter='all';$attendanceShown=$attFilter==='all'?$attendanceRows:array_values(array_filter($attendanceRows,fn($r)=>(string)$r['bucket']===$attFilter));
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MR BAR — HR Approval Center</title>
<link rel="stylesheet" href="assets/admin.css?v=1220">
<link rel="stylesheet" href="assets/admin-v14.css?v=1220">
<link rel="stylesheet" href="assets/hr-approval-center-v14822.css?v=14822"><link rel="stylesheet" href="assets/hr-approval-center-v14823.css?v=14823"><link rel="stylesheet" href="assets/hr-approval-center-v14824.css?v=14824"><link rel="stylesheet" href="assets/hr-approval-date-nav-v14842.css?v=14842">
</head>
<body class="admin-v14-page">
<?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('approvals',$u);?>
<main class="ha-shell">
<header class="ha-head">
  <div><p>WORKFORCE / HR OPERATIONS</p><h1>ศูนย์อนุมัติพนักงาน</h1><span>รวมงานลา · แก้เวลา · PR มาแทน · Attendance Exception · No-show ไว้ในจุดเดียว</span></div>
  <div class="ha-head-actions"><a href="workforce-schedule.php">ปฏิทินกะ</a><a href="payroll-attendance.php">Payroll</a></div>
</header>

<section class="ha-kpis">
  <article><small>รอดำเนินการทั้งหมด</small><b><?=$total?></b><span>ทุกหมวดที่คุณมีสิทธิ์ดู</span></article>
  <article><small>คำขอลา</small><b><?=$canLeave?count($leaves):0?></b><span>รออนุมัติ</span></article>
  <article><small>แก้เวลา</small><b><?=$canExceptions?count($corrections):0?></b><span>รอหัวหน้างานตรวจ</span></article>
  <article><small>PR มาแทน</small><b><?=$canExceptions?count($subs):0?></b><span>รออนุมัติ</span></article>
  <article><small>Exception</small><b><?=$canExceptions?count($missing):0?></b><span>Missing / Provisional</span></article>
  <article><small>No-show / Penalty</small><b><?=$canPayroll?count($penalties):0?></b><span>รอยืนยันหรือยกเว้น</span></article>
</section>

<section class="ha-layout">
  <aside class="ha-local-nav">
    <div class="ha-local-title">เมนูงานอนุมัติ</div>
    <a class="<?=$view==='overview'?'active':''?>" href="hr-approval-center.php"><span>⌂</span><div><b>ภาพรวม</b><small><?=$total?> รายการ</small></div></a>
    <?php if($canLeave):?><a class="<?=$view==='leave'?'active':''?>" href="hr-approval-center.php?view=leave"><span>☂</span><div><b>การลา</b><small><?=count($leaves)?> รอ</small></div></a><?php endif;?>
    <?php if($canExceptions):?><a class="<?=$view==='correction'?'active':''?>" href="hr-approval-center.php?view=correction"><span>◷</span><div><b>แก้ไขเวลา</b><small><?=count($corrections)?> รอ</small></div></a>
    <a class="<?=$view==='substitute'?'active':''?>" href="hr-approval-center.php?view=substitute"><span>⇄</span><div><b>PR มาแทน</b><small><?=count($subs)?> รอ</small></div></a>
    <a class="<?=$view==='exception'?'active':''?>" href="hr-approval-center.php?view=exception"><span>⚠</span><div><b>Attendance Exception</b><small><?=count($missing)?> รายการ</small></div></a><?php endif;?>
    <?php if($canPayroll):?><a class="<?=$view==='penalty'?'active':''?>" href="hr-approval-center.php?view=penalty"><span>−</span><div><b>No-show / Penalty</b><small><?=count($penalties)?> รอ</small></div></a><?php endif;?>
  </aside>

  <section class="ha-work <?=$view!=='overview'?'is-detail':''?>" id="overview">
    <?php if($view==='overview'):?>
      <div class="ha-live-head">
        <div><small>DAILY WORKFORCE</small><form class="ha-calendar-sort" method="get"><input type="hidden" name="att" value="<?=h($attFilter)?>"><label><span><?=h(ha_thai_date($selectedDate))?></span><input type="date" name="date" value="<?=h($selectedDate)?>" onchange="this.form.submit()" aria-label="เลือกวันที่เพื่อกรองข้อมูล"></label></form><p>ภาพรวมการลงเวลาและคำขอของวันที่เลือก · คลิกวันที่เพื่อเลือกจากปฏิทิน</p></div>
        <div class="ha-live-actions"><a class="<?=$selectedDate===$today?'active':''?>" href="hr-approval-center.php?date=<?=h($today)?>&att=<?=h($attFilter)?>">วันนี้</a><a href="workforce-schedule.php?date=<?=h($selectedDate)?>">ปฏิทินพนักงาน</a></div>
      </div>
      <div class="ha-attendance-tabs">
        <a class="<?=$attFilter==='normal'?'active':''?>" href="?date=<?=h($selectedDate)?>&att=normal">ปกติ <b><?=$attendanceCounts['normal']?></b></a>
        <a class="<?=$attFilter==='late'?'active':''?>" href="?date=<?=h($selectedDate)?>&att=late">สาย <b><?=$attendanceCounts['late']?></b></a>
        <a class="<?=$attFilter==='missing'?'active':''?>" href="?date=<?=h($selectedDate)?>&att=missing">ขาด/ไม่สมบูรณ์ <b><?=$attendanceCounts['missing']?></b></a>
        <a class="<?=$attFilter==='all'?'active':''?>" href="?date=<?=h($selectedDate)?>&att=all">ทั้งหมด <b><?=$attendanceCounts['all']?></b></a>
      </div>
      <div class="ha-attendance-table">
        <div class="ha-att-head"><span>พนักงาน</span><span>เวลางาน</span><span>เข้างาน</span><span>ออกงาน</span><span>สถานะ</span></div>
        <?php if(!$attendanceShown):?><div class="ha-empty">ยังไม่มีข้อมูลลงเวลาตามตัวกรองนี้</div><?php endif;?>
        <?php foreach($attendanceShown as $r):$a=$r['a'];$e=$r['employee'];$pay=$e['payroll']??[];$start=(string)($pay['start_time']??($d['settings']['shop_open_time']??'18:00'));$end=(string)($pay['end_time']??($d['settings']['shop_close_time']??'02:00'));?>
          <article class="ha-att-row">
            <div class="ha-att-person">
              <span class="ha-avatar"><?php if(!empty($r['avatar'])):?><img src="<?=h((string)$r['avatar'])?>" alt=""><?php else:?><i><?=h(substr((string)$e['code'],0,2))?></i><?php endif;?></span>
              <div><b><?=h((string)$e['name'])?></b><small><?=h((string)$e['code'].' · '.workforce_position_label((string)$e['position']))?></small></div>
            </div>
            <div><b><?=h($start.' - '.$end)?></b><small>กะมาตรฐาน</small></div>
            <div><strong class="<?=$r['late']>0?'is-late':'is-ok'?>"><?=h(date('H:i',strtotime((string)$a['check_in'])))?></strong><small><?=h((string)($a['branch_name']??''))?></small></div>
            <div><strong class="<?=empty($a['check_out'])?'is-wait':'is-ok'?>"><?=empty($a['check_out'])?'—':h(date('H:i',strtotime((string)$a['check_out'])))?></strong><small><?=empty($a['check_out'])?'ยังไม่ลงเวลาออก':'ลงเวลาแล้ว'?></small></div>
            <div><span class="ha-att-status <?=$r['bucket']?>"><?=$r['bucket']==='normal'?'ปกติ':($r['bucket']==='late'?'สาย '.$r['late'].' นาที':'ต้องตรวจสอบ')?></span></div>
          </article>
        <?php endforeach;?>
      </div>
    <?php else:$detailTitle=['leave'=>'การลา','correction'=>'แก้ไขเวลา','substitute'=>'PR มาแทน','exception'=>'Attendance Exception','penalty'=>'No-show / Penalty'][$view]??'รายละเอียด';?>
      <div class="ha-detail-head"><div><small>HR APPROVAL CENTER / <?=h(strtoupper($view))?></small><h2><?=h($detailTitle)?></h2><p>รายละเอียดและประวัติคำขออยู่ในหัวข้อนี้ โดยยังใช้ระบบอนุมัติเดิมทั้งหมด</p></div><a href="hr-approval-center.php">← กลับภาพรวม</a></div>
      <div class="ha-frame-wrap"><iframe class="ha-workframe" name="ha-workframe" src="<?=h((string)($viewUrls[$view]??''))?>" title="<?=h($detailTitle)?>"></iframe></div>
    <?php endif;?>
  </section>

  <?php if($view==='overview'):?>
  <aside class="ha-side ha-quick-leave">
    <div class="ha-section-head"><div><small>QUICK APPROVAL</small><h2>การลา</h2><p>แสดงคำขอลาที่ครอบคลุมวันที่เลือก · อนุมัติได้ทันทีจากตรงนี้</p></div><div class="ha-leave-date-nav"><a href="?date=<?=h($prevDate)?>&att=<?=h($attFilter)?>" aria-label="วันก่อนหน้า">‹</a><span><?=h(date('d/m/Y',strtotime($selectedDate)))?></span><a href="?date=<?=h($nextDate)?>&att=<?=h($attFilter)?>" aria-label="วันถัดไป">›</a></div></div>
    <div class="ha-quick-list">
      <?php if(!$leavesForDate):?><div class="ha-empty compact">ไม่มีคำขอลารออนุมัติในวันที่เลือก</div><?php endif;?>
      <?php foreach(array_slice($leavesForDate,0,8) as $l):$eid=(int)($l['employee_id']??0);if(!$eid&&!empty($l['pr_id']))$eid=(int)(workforce_employee_by_pr($d,(int)$l['pr_id'])['id']??0);$e=ha_emp($d,$eid);$av=workforce_employee_avatar_url($d,$e);?>
      <article class="ha-leave-card">
        <span class="ha-avatar"><?php if($av):?><img src="<?=h($av)?>" alt=""><?php else:?><i><?=h(substr((string)$e['code'],0,2))?></i><?php endif;?></span>
        <div class="ha-leave-info"><b><?=h((string)$e['name'].' ('.(string)$e['code'].')')?></b><small>ประเภท: <?=h(ha_leave_type((string)($l['type']??'')))?></small><em><?=h(date('d/m/Y',strtotime((string)$l['start_date'])))?><?=($l['start_date']??'')!==($l['end_date']??'')?' - '.h(date('d/m/Y',strtotime((string)$l['end_date']))):''?></em><?php if(!empty($l['reason'])):?><p><?=h((string)$l['reason'])?></p><?php endif;?></div>
        <div class="ha-leave-action">
          <?php if($canLeaveManage):?><form method="post" action="admin-leaves.php"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="leave_id" value="<?=(int)$l['id']?>"><input type="hidden" name="decision" value="approved"><input type="hidden" name="admin_note" value=""><input type="hidden" name="return_to" value="hr-approval-center.php?date=<?=h($selectedDate)?>&att=<?=h($attFilter)?>"><button>อนุมัติ</button></form><?php endif;?>
          <a href="hr-approval-center.php?view=leave">รายละเอียด</a>
        </div>
      </article>
      <?php endforeach;?>
    </div>
  </aside>
  <?php else:?>
  <aside class="ha-side">
    <div class="ha-section-head"><div><small>SECTION</small><h2>หัวข้ออื่น</h2></div></div>
    <div class="ha-urgent">
      <a href="hr-approval-center.php"><span>⌂</span><div><b>กลับภาพรวม</b><small>ดูการลงเวลาและ Quick Approval</small></div></a>
      <?php if($canLeave):?><a href="hr-approval-center.php?view=leave"><span>☂</span><div><b>การลา</b><small><?=count($leaves)?> รอ</small></div></a><?php endif;?>
      <?php if($canExceptions):?><a href="hr-approval-center.php?view=exception"><span>⚠</span><div><b>Attendance Exception</b><small><?=count($missing)?> รายการ</small></div></a><?php endif;?>
    </div>
  </aside>
  <?php endif;?>
</section>

<footer>MR BAR HR Approval Center · <?=h((string)(app_config()['pack']??'MR BAR'))?> · Schema v<?=h((string)($d['meta']['schema']??28))?></footer>
</main>
</body></html>