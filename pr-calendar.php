<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/pr-preview.php';
$previewCtx=pr_preview_context();$isAdminPreview=(bool)$previewCtx;
if($previewCtx){$u=$previewCtx['user'];$d=$previewCtx['data'];$pr=$previewCtx['pr'];}
else{$u=require_roles('pr');$d=db_load();$pr=user_pr($d,(int)$u['id']);$employee=workforce_employee_by_pr($d,(int)$pr['id'])?:$employee;$employeeId=(int)($employee['id']??($pr['employee_id']??0));}
if(!$pr){http_response_code(403);exit('PR profile is not linked.');}
$employee=workforce_employee_by_pr($d,(int)$pr['id']);$employeeId=(int)($employee['id']??($pr['employee_id']??0));

function pc_leave_label(string $type): string {
    return [
        'sick'=>'ลาป่วย','personal'=>'ลากิจ','vacation'=>'ลาพักร้อน','unpaid'=>'ลาไม่รับค่าจ้าง','other'=>'ลาอื่น ๆ'
    ][$type]??$type;
}
function pc_leave_status(string $status): string {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิกแล้ว'][$status]??$status;
}
function pc_sub_status(string $status): string {
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','cancelled'=>'ยกเลิกแล้ว'][$status]??$status;
}
function pc_portion_label(string $portion): string {
    return ['full'=>'เต็มวัน','am'=>'ครึ่งวันเช้า','pm'=>'ครึ่งวันบ่าย'][$portion]??$portion;
}
function pc_branch_name(array $d,int $id): string {
    foreach($d['branches']??[] as $b) if((int)($b['id']??0)===$id) return (string)($b['name']??$b['code']??'-');
    return '-';
}
function pc_minutes_between(string $in,?string $out): int {
    if($out===null||$out==='')return 0;
    $a=strtotime($in);$b=strtotime($out);return ($a&&$b&&$b>$a)?(int)floor(($b-$a)/60):0;
}
function pc_hm(int $min): string {return floor($min/60).' ชม. '.($min%60).' นาที';}
function pc_late_minutes(string $checkIn,string $date,string $start,int $grace): int {
    $actual=strtotime($checkIn);$target=strtotime($date.' '.$start);if(!$actual||!$target)return 0;return max(0,(int)floor(($actual-$target)/60)-$grace);
}
function pc_dates_between(string $from,string $to): array {
    $out=[];$s=strtotime($from);$e=strtotime($to);if(!$s||!$e||$e<$s)return $out;
    for($t=$s;$t<=$e;$t=strtotime('+1 day',$t)){$out[]=date('Y-m-d',$t);if(count($out)>370)break;}
    return $out;
}
function pc_thai_month(int $month): string {
    $m=[1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return $m[$month]??'';
}

$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    pr_preview_block_post();
    csrf_check();$action=(string)($_POST['action']??'');
    try{
        if($action==='request_leave'){
            if(($d['settings']['leave_request_enabled']??'1')!=='1')throw new RuntimeException('ระบบขอลาถูกปิดใช้งานชั่วคราว');
            $type=(string)($_POST['type']??'personal');$portion=(string)($_POST['portion']??'full');
            $start=(string)($_POST['start_date']??'');$end=(string)($_POST['end_date']??'');$reason=trim((string)($_POST['reason']??''));
            if(!in_array($type,['sick','personal','vacation','unpaid','other'],true))throw new RuntimeException('ประเภทลาไม่ถูกต้อง');
            if(!in_array($portion,['full','am','pm'],true))throw new RuntimeException('ช่วงเวลาไม่ถูกต้อง');
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||strtotime($end)<strtotime($start))throw new RuntimeException('ช่วงวันที่ลาไม่ถูกต้อง');
            $days=(int)floor((strtotime($end)-strtotime($start))/86400)+1;$maxDays=max(1,(int)($d['settings']['leave_max_days_per_request']??31));
            if($days>$maxDays)throw new RuntimeException('คำขอลาหนึ่งครั้งได้สูงสุด '.$maxDays.' วัน');
            if($days>1&&$portion!=='full')throw new RuntimeException('ลาหลายวันต้องเลือก “เต็มวัน”');
            if($reason==='')throw new RuntimeException('กรุณาระบุเหตุผลการลา');
            foreach($d['leave_requests']??[] as $l){
                if((int)($l['pr_id']??0)!==(int)$pr['id']||!in_array((string)($l['status']??''),['pending','approved'],true))continue;
                if($start<=(string)$l['end_date'] && $end>=(string)$l['start_date'])throw new RuntimeException('ช่วงวันที่นี้มีคำขอลาที่กำลังใช้งานอยู่แล้ว');
            }
            db_mutate(function($x)use($pr,$u,$employeeId,$type,$portion,$start,$end,$reason){
                $id=next_id($x['leave_requests']??[]);
                $x['leave_requests'][]=['id'=>$id,'employee_id'=>$employeeId?:null,'pr_id'=>(int)$pr['id'],'user_id'=>(int)$u['id'],'type'=>$type,'start_date'=>$start,'end_date'=>$end,'portion'=>$portion,'reason'=>$reason,'status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>''];
                $x['audit'][]=['at'=>date('c'),'action'=>'leave_requested','user_id'=>(int)$u['id'],'employee_id'=>$employeeId?:null,'pr_id'=>(int)$pr['id'],'leave_id'=>$id,'type'=>$type,'start_date'=>$start,'end_date'=>$end];
                op_notify($x,0,'admin','leave_request','มีคำขอลาใหม่จาก '.(string)$pr['code'].' · '.pc_leave_label($type),['leave_id'=>$id,'employee_id'=>$employeeId?:null,'pr_id'=>(int)$pr['id']]);
                return $x;
            });
            header('Location: pr-calendar.php?month='.substr($start,0,7).'&date='.$start.'&saved=1');exit;
        }
        if($action==='cancel_leave'){
            $id=(int)($_POST['leave_id']??0);$found=false;
            db_mutate(function($x)use($id,$pr,$u,&$found){
                foreach($x['leave_requests'] as &$l){
                    if((int)($l['id']??0)!==$id||(int)($l['pr_id']??0)!==(int)$pr['id'])continue;
                    if(($l['status']??'')!=='pending')throw new RuntimeException('ยกเลิกได้เฉพาะคำขอที่ยังรออนุมัติ');
                    $l['status']='cancelled';$l['updated_at']=date('c');$found=true;
                    $x['audit'][]=['at'=>date('c'),'action'=>'leave_cancelled_by_pr','user_id'=>(int)$u['id'],'pr_id'=>(int)$pr['id'],'leave_id'=>$id];
                    break;
                }unset($l);return $x;
            });
            if(!$found)throw new RuntimeException('ไม่พบคำขอลา');
            $msg='ยกเลิกคำขอลาแล้ว';
        }
        if($action==='request_time_correction'){
            if(($d['settings']['attendance_correction_request_enabled']??'1')!=='1')throw new RuntimeException('ระบบขอแก้เวลาถูกปิดใช้งาน');
            $aid=(int)($_POST['attendance_id']??0);$requested=trim((string)($_POST['requested_checkout']??''));$reason=trim((string)($_POST['reason']??''));
            if($requested===''||$reason==='')throw new RuntimeException('กรุณาระบุเวลาออกจริงและเหตุผล');
            $target=null;foreach($d['attendance']??[] as $a){$same=((int)($a['pr_id']??0)===(int)$pr['id'])||($employeeId>0&&(int)($a['employee_id']??0)===$employeeId);if((int)($a['id']??0)===$aid&&$same){$target=$a;break;}}
            if(!$target)throw new RuntimeException('ไม่พบ Attendance');if(!in_array(workforce_attendance_state($target),['missing_checkout','provisional'],true))throw new RuntimeException('รายการนี้ไม่ต้องแก้เวลาออก');
            $ts=strtotime($requested);$in=strtotime((string)$target['check_in']);if(!$ts||!$in||$ts<=$in||$ts>time()+300)throw new RuntimeException('เวลาออกที่ขอแก้ไม่ถูกต้อง');
            if(workforce_correction_pending($d,$aid))throw new RuntimeException('รายการนี้มีคำขอแก้เวลาที่กำลังรออยู่แล้ว');
            db_mutate(function($x)use($pr,$u,$employeeId,$aid,$ts,$reason){$id=next_id($x['time_correction_requests']??[]);$x['time_correction_requests'][]=['id'=>$id,'attendance_id'=>$aid,'employee_id'=>$employeeId?:0,'pr_id'=>(int)$pr['id'],'user_id'=>(int)$u['id'],'requested_checkout'=>date('c',$ts),'reason'=>$reason,'status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>''];$x['audit'][]=['at'=>date('c'),'action'=>'time_correction_requested','request_id'=>$id,'attendance_id'=>$aid,'employee_id'=>$employeeId?:null,'pr_id'=>(int)$pr['id'],'by'=>(int)$u['id']];op_notify($x,0,'admin','time_correction','PR '.(string)$pr['code'].' ขอแก้เวลา Check-out',['request_id'=>$id,'attendance_id'=>$aid]);return $x;});$msg='ส่งคำขอแก้เวลาออกแล้ว';
        }
        if($action==='request_substitute'){
            if(($d['settings']['substitute_request_enabled']??'1')!=='1')throw new RuntimeException('ระบบขอ PR มาแทนถูกปิดใช้งาน');
            $shiftId=(int)($_POST['shift_id']??0);$type=(string)($_POST['substitute_type']??'internal');$reason=trim((string)($_POST['reason']??''));$subEid=(int)($_POST['substitute_employee_id']??0);$extName=trim((string)($_POST['external_name']??''));$extPhone=trim((string)($_POST['external_phone']??''));$extNote=trim((string)($_POST['external_note']??''));
            if(!in_array($type,['internal','external'],true)||$reason==='')throw new RuntimeException('กรุณาระบุข้อมูลคำขอคนมาแทน');
            $shift=workforce_shift_for_id($d,$shiftId);if(!$shift)throw new RuntimeException('ไม่พบกะที่ต้องการหาคนแทน');$sameShift=((int)($shift['pr_id']??0)===(int)$pr['id'])||($employeeId>0&&(int)($shift['employee_id']??0)===$employeeId);if(!$sameShift)throw new RuntimeException('กะนี้ไม่ใช่กะของคุณ');if(in_array((string)($shift['status']??''),['cancelled','off','substituted'],true))throw new RuntimeException('กะนี้ไม่พร้อมขอคนแทน');
            foreach($d['substitute_requests']??[] as $r)if((int)($r['shift_id']??0)===$shiftId&&in_array((string)($r['status']??''),['pending','approved'],true))throw new RuntimeException('กะนี้มีคำขอคนมาแทนอยู่แล้ว');
            $subPrId=0;if($type==='internal'){$sub=workforce_employee_by_id($d,$subEid);if(!$sub||empty($sub['active'])||($sub['position']??'')!=='pr'||$subEid===$employeeId)throw new RuntimeException('PR ที่เลือกไม่พร้อม');$subPrId=(int)($sub['pr_id']??0);if(!$subPrId)throw new RuntimeException('PR ที่เลือกยังไม่มี PR Profile');}else{if(($d['settings']['substitute_external_allowed']??'1')!=='1')throw new RuntimeException('ร้านไม่อนุญาต PR คนนอก');if($extName==='')throw new RuntimeException('กรุณาระบุชื่อ PR คนนอก');}
            db_mutate(function($x)use($pr,$u,$employeeId,$shift,$shiftId,$type,$subEid,$subPrId,$extName,$extPhone,$extNote,$reason){$id=next_id($x['substitute_requests']??[]);$x['substitute_requests'][]=['id'=>$id,'requester_employee_id'=>$employeeId?:0,'requester_pr_id'=>(int)$pr['id'],'user_id'=>(int)$u['id'],'shift_id'=>$shiftId,'date'=>(string)$shift['date'],'original_employee_id'=>$employeeId?:0,'original_pr_id'=>(int)$pr['id'],'substitute_type'=>$type,'substitute_employee_id'=>$type==='internal'?$subEid:0,'substitute_pr_id'=>$type==='internal'?$subPrId:0,'external_name'=>$type==='external'?$extName:'','external_phone'=>$type==='external'?$extPhone:'','external_note'=>$type==='external'?$extNote:'','reason'=>$reason,'status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>'','created_shift_id'=>0,'external_check_in'=>null,'external_check_out'=>null,'external_attendance_note'=>''];$x['audit'][]=['at'=>date('c'),'action'=>'substitute_requested','request_id'=>$id,'shift_id'=>$shiftId,'employee_id'=>$employeeId?:null,'pr_id'=>(int)$pr['id'],'substitute_type'=>$type,'by'=>(int)$u['id']];op_notify($x,0,'admin','substitute_request','PR '.(string)$pr['code'].' ขอ PR มาแทน '.(string)$shift['date'],['request_id'=>$id,'shift_id'=>$shiftId]);return $x;});$msg='ส่งคำขอ PR มาแทนแล้ว';
        }
        if($action==='cancel_substitute'){
            $rid=(int)($_POST['request_id']??0);db_mutate(function($x)use($rid,$pr,$u){foreach($x['substitute_requests'] as &$r){if((int)($r['id']??0)!==$rid||(int)($r['requester_pr_id']??0)!==(int)$pr['id'])continue;if(($r['status']??'')!=='pending')throw new RuntimeException('ยกเลิกได้เฉพาะคำขอที่ยังรออนุมัติ');$r['status']='cancelled';$r['updated_at']=date('c');$x['audit'][]=['at'=>date('c'),'action'=>'substitute_cancelled','request_id'=>$rid,'pr_id'=>(int)$pr['id'],'by'=>(int)$u['id']];return $x;}unset($r);throw new RuntimeException('ไม่พบคำขอ');});$msg='ยกเลิกคำขอคนมาแทนแล้ว';
        }
    }catch(Throwable $e){$err=$e->getMessage();}
    $d=db_load();$pr=user_pr($d,(int)$u['id']);
}
if(isset($_GET['saved']))$msg='ส่งคำขอลาเรียบร้อย รอ Admin อนุมัติ';

$month=(string)($_GET['month']??date('Y-m'));if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$monthStart=$month.'-01';$monthTs=strtotime($monthStart);if(!$monthTs){$month=date('Y-m');$monthStart=$month.'-01';$monthTs=strtotime($monthStart);}
$daysInMonth=(int)date('t',$monthTs);$year=(int)date('Y',$monthTs);$mon=(int)date('n',$monthTs);
$prev=date('Y-m',strtotime('-1 month',$monthTs));$next=date('Y-m',strtotime('+1 month',$monthTs));
$selected=(string)($_GET['date']??date('Y-m-d'));if(substr($selected,0,7)!==$month)$selected=$monthStart;
$today=date('Y-m-d');$grace=(int)($d['settings']['attendance_late_grace_minutes']??15);

$attendance=[];foreach($d['attendance']??[] as $a){if((int)($a['pr_id']??0)!==(int)$pr['id']||empty($a['check_in']))continue;$date=substr((string)$a['check_in'],0,10);if(substr($date,0,7)!==$month)continue;$attendance[$date][]=$a;}
$shifts=[];foreach($d['shifts']??[] as $s){if((int)($s['pr_id']??0)!==(int)$pr['id']||in_array((string)($s['status']??'scheduled'),['cancelled','off'],true))continue;$date=(string)($s['date']??'');if(substr($date,0,7)===$month)$shifts[$date][]=$s;}
$leaves=[];foreach($d['leave_requests']??[] as $l){if((int)($l['pr_id']??0)!==(int)$pr['id'])continue;foreach(pc_dates_between((string)$l['start_date'],(string)$l['end_date']) as $date){if(substr($date,0,7)===$month)$leaves[$date][]=$l;}}

$calendarData=[];
for($day=1;$day<=$daysInMonth;$day++){
    $date=sprintf('%04d-%02d-%02d',$year,$mon,$day);$rows=$attendance[$date]??[];$dayShifts=$shifts[$date]??[];$dayLeaves=$leaves[$date]??[];
    $approved=array_values(array_filter($dayLeaves,function($l){return ($l['status']??'')==='approved';}));
    $pending=array_values(array_filter($dayLeaves,function($l){return ($l['status']??'')==='pending';}));
    $status='none';$late=0;
    if($rows){$status='worked';$hasMissing=false;foreach($rows as $a){if(in_array(workforce_attendance_state($a),['missing_checkout','provisional'],true))$hasMissing=true;$start=$dayShifts?(string)($dayShifts[0]['start']??'18:00'):(string)($pr['profile']['payroll_start_time']??'18:00');$late=max($late,pc_late_minutes((string)$a['check_in'],$date,$start,$grace));}if($hasMissing)$status='missing_checkout';elseif($late>0)$status='late';}
    elseif($approved)$status='leave';elseif($pending)$status='leave_pending';elseif($dayShifts&&$date<$today)$status='absent';elseif($dayShifts)$status='scheduled';
    $calendarData[$date]=['status'=>$status,'late'=>$late,'attendance_count'=>count($rows),'leave_count'=>count($dayLeaves),'shift_count'=>count($dayShifts)];
}

$detailData=[];
foreach($calendarData as $date=>$meta){
    $rows=[];foreach($attendance[$date]??[] as $a){
        $worked=pc_minutes_between((string)$a['check_in'],empty($a['check_out'])?null:(string)$a['check_out']);$dayShifts=$shifts[$date]??[];$start=$dayShifts?(string)($dayShifts[0]['start']??'18:00'):(string)($pr['profile']['payroll_start_time']??'18:00');
        $rows[]=['id'=>(int)$a['id'],'in'=>date('H:i:s',strtotime((string)$a['check_in'])),'out'=>empty($a['check_out'])?'—':date('H:i:s',strtotime((string)$a['check_out'])),'worked'=>$worked,'worked_label'=>pc_hm($worked),'late'=>pc_late_minutes((string)$a['check_in'],$date,$start,$grace),'branch'=>pc_branch_name($d,(int)($a['branch_id']??0)),'gps_in'=>isset($a['checkin_distance_m'])?round((float)$a['checkin_distance_m']):null,'gps_out'=>isset($a['checkout_distance_m'])?round((float)$a['checkout_distance_m']):null,'in_photo'=>!empty($a['checkin_evidence'])?'pr-attendance-evidence.php?id='.(int)$a['id'].'&type=in':'','out_photo'=>!empty($a['checkout_evidence'])?'pr-attendance-evidence.php?id='.(int)$a['id'].'&type=out':'','state'=>workforce_attendance_state($a),'payroll_status'=>(string)($a['payroll_status']??'pending')];
    }
    $lv=[];foreach($leaves[$date]??[] as $l)$lv[]=['id'=>(int)$l['id'],'type'=>(string)$l['type'],'type_label'=>pc_leave_label((string)$l['type']),'portion'=>(string)$l['portion'],'portion_label'=>pc_portion_label((string)$l['portion']),'reason'=>(string)$l['reason'],'status'=>(string)$l['status'],'status_label'=>pc_leave_status((string)$l['status']),'start_date'=>(string)$l['start_date'],'end_date'=>(string)$l['end_date'],'admin_note'=>(string)($l['admin_note']??'')];
    $sf=[];foreach($shifts[$date]??[] as $s)$sf[]=['start'=>(string)($s['start']??''),'end'=>(string)($s['end']??''),'status'=>(string)($s['status']??'scheduled')];
    $detailData[$date]=['meta'=>$meta,'attendance'=>$rows,'leaves'=>$lv,'shifts'=>$sf];
}

$firstWeekday=(int)date('w',$monthTs); // Sunday=0
$avatar=(string)($pr['profile']['profile_photo']??'');
if($avatar!=='' && strpos($avatar,'storage/pr-media/')===0)$avatar='pr-media.php?pr_id='.(int)$pr['id'].'&type=profile';
$ownLeaves=array_values(array_filter($d['leave_requests']??[],function($l)use($pr){return (int)($l['pr_id']??0)===(int)$pr['id'];}));
usort($ownLeaves,function($a,$b){return strcmp((string)($b['requested_at']??''),(string)($a['requested_at']??''));});
$ownSubs=array_values(array_filter($d['substitute_requests']??[],function($r)use($pr){return (int)($r['requester_pr_id']??0)===(int)$pr['id'];}));usort($ownSubs,function($a,$b){return strcmp((string)($b['requested_at']??''),(string)($a['requested_at']??''));});
$subCandidates=[];foreach($d['employees']??[] as $e)if(!empty($e['active'])&&($e['position']??'')==='pr'&&(int)($e['id']??0)!==$employeeId&&!empty($e['pr_id']))$subCandidates[]=$e;
$missingOwn=[];foreach($d['attendance']??[] as $a){$same=((int)($a['pr_id']??0)===(int)$pr['id'])||($employeeId>0&&(int)($a['employee_id']??0)===$employeeId);if($same&&in_array(workforce_attendance_state($a),['missing_checkout','provisional'],true))$missingOwn[]=$a;}usort($missingOwn,function($a,$b){return strcmp((string)$b['check_in'],(string)$a['check_in']);});
$subShiftOptions=[];foreach($d['shifts']??[] as $sh){$same=((int)($sh['pr_id']??0)===(int)$pr['id'])||($employeeId>0&&(int)($sh['employee_id']??0)===$employeeId);if(!$same||in_array((string)($sh['status']??''),['cancelled','off','substituted'],true))continue;$subShiftOptions[]=$sh;}usort($subShiftOptions,function($a,$b){return strcmp((string)$a['date'].(string)$a['start'],(string)$b['date'].(string)$b['start']);});
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#080713"><title>MR BAR — ปฏิทินลงเวลา</title><link rel="stylesheet" href="assets/pr.css?v=1250"><link rel="stylesheet" href="assets/pr-calendar.css?v=1250"><?php if($isAdminPreview):?><link rel="stylesheet" href="assets/pr-preview-v1173.css?v=1173"><?php endif;?></head><body class="calendar-body"><?php if($isAdminPreview)echo pr_preview_banner();?>
<div id="calDrawerBackdrop" class="pr-drawer-backdrop" hidden></div><aside id="calDrawer" class="pr-drawer" aria-hidden="true"><div class="drawer-head"><div class="drawer-avatar"><?php if($avatar!==''):?><img src="<?=h($avatar)?>" alt=""><?php else:?><span>♛</span><?php endif;?></div><div><small>MR BAR / PR</small><strong><?=h((string)($pr['profile']['nickname']?:$pr['name']))?></strong><span><?=h((string)$pr['code'])?></span></div><button type="button" id="calDrawerClose">×</button></div><nav class="drawer-nav"><a href="pr.php">◷ <span>ลงเวลาเข้า / ออกงาน</span></a><a class="drawer-active" href="pr-calendar.php">▦ <span>ปฏิทิน / ประวัติ</span></a><a href="employee-income.php">฿ <span>รายได้ของฉัน</span></a><a href="pr-jobs.php">✦ <span>งานของฉัน</span></a><a href="pr.php?profile=1">♙ <span>โปรไฟล์ส่วนตัว</span></a></nav><a class="drawer-logout" href="logout.php">ออกจากระบบ ↗</a></aside>
<main class="cal-shell"><header class="cal-appbar"><button type="button" class="hamburger" id="calDrawerOpen"><span></span><span></span><span></span></button><div><small>PR ATTENDANCE</small><strong>ปฏิทินของฉัน</strong></div><a class="today-link" href="pr-calendar.php?month=<?=date('Y-m')?>&date=<?=$today?>">วันนี้</a></header>
<?php if($msg):?><div class="cal-notice ok">✓ <?=h($msg)?></div><?php endif;?><?php if($err):?><div class="cal-notice err">⚠ <?=h($err)?></div><?php endif;?>
<section class="calendar-card"><div class="month-nav"><a href="?month=<?=$prev?>">‹</a><div><small>MONTHLY ATTENDANCE</small><h1><?=h(pc_thai_month($mon))?> <span><?=$year+543?></span></h1></div><a href="?month=<?=$next?>">›</a></div><div class="week-head"><?php foreach(['อา','จ','อ','พ','พฤ','ศ','ส'] as $i=>$w):?><span class="<?=$i===0?'sun':''?>"><?=$w?></span><?php endforeach;?></div><div class="calendar-grid">
<?php for($i=0;$i<$firstWeekday;$i++):?><span class="day-cell blank"></span><?php endfor;?>
<?php for($day=1;$day<=$daysInMonth;$day++):$date=sprintf('%04d-%02d-%02d',$year,$mon,$day);$m=$calendarData[$date];$dow=(int)date('w',strtotime($date));?><button type="button" class="day-cell status-<?=h($m['status'])?> <?=$date===$today?'is-today':''?> <?=$date===$selected?'is-selected':''?> <?=$dow===0?'sun':''?>" data-date="<?=$date?>"><span class="num"><?=$day?></span><span class="dots"><?php if($m['status']==='worked'):?><i class="green"></i><?php elseif($m['status']==='missing_checkout'):?><i class="orange"></i><?php elseif($m['status']==='late'):?><i class="yellow"></i><?php elseif($m['status']==='leave'):?><i class="cyan"></i><?php elseif($m['status']==='leave_pending'):?><i class="purple"></i><?php elseif($m['status']==='absent'):?><i class="red"></i><?php elseif($m['status']==='scheduled'):?><i class="blue"></i><?php endif;?></span></button><?php endfor;?></div>
<div class="legend"><span><i class="green"></i>ลงเวลาปกติ</span><span><i class="yellow"></i>สาย</span><span><i class="orange"></i>ขาด Check-out</span><span><i class="red"></i>ขาดงาน</span><span><i class="cyan"></i>ลาอนุมัติ</span><span><i class="purple"></i>ลารออนุมัติ</span><span><i class="blue"></i>มีกะงาน</span><span class="today-legend">Aa วันนี้</span><span class="selected-legend">Aa วันที่เลือก</span></div></section>
<section class="day-detail" id="dayDetail"><div class="detail-head"><div><small>SELECTED DATE</small><h2 id="detailDate">—</h2></div><div class="detail-actions"><button type="button" class="sub-plus" id="subOpen" aria-label="ขอ PR มาแทน">⇄</button><button type="button" class="leave-plus" id="leaveOpen" aria-label="ขอลา">＋</button></div></div><div id="detailStatus" class="detail-status"></div><div id="attendanceDetail" class="attendance-detail"></div><div id="leaveDetail" class="leave-detail"></div></section>
<section class="my-leaves"><div class="section-title-row"><div><small>LEAVE REQUESTS</small><h2>คำขอลาของฉัน</h2></div><button type="button" class="small-plus" id="leaveOpen2">＋ ขอลา</button></div><div class="leave-history"><?php if(!$ownLeaves):?><div class="empty mini">ยังไม่มีคำขอลา</div><?php endif;?><?php foreach(array_slice($ownLeaves,0,12) as $l):?><article><div><b><?=h(pc_leave_label((string)$l['type']))?></b><span><?=h(date('d/m/Y',strtotime((string)$l['start_date'])))?><?=($l['start_date']??'')!==($l['end_date']??'')?' → '.h(date('d/m/Y',strtotime((string)$l['end_date']))):''?> · <?=h(pc_portion_label((string)$l['portion']))?></span><small><?=h((string)$l['reason'])?></small></div><div><em class="leave-status <?=h((string)$l['status'])?>"><?=h(pc_leave_status((string)$l['status']))?></em><?php if(($l['status']??'')==='pending'):?><form method="post" onsubmit="return confirm('ยกเลิกคำขอลานี้?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="cancel_leave"><input type="hidden" name="leave_id" value="<?=$l['id']?>"><button class="cancel-leave">ยกเลิก</button></form><?php endif;?></div></article><?php endforeach;?></div></section>
<section class="my-leaves exception-self"><div class="section-title-row"><div><small>TIME EXCEPTIONS</small><h2>รายการที่ขาด Check-out</h2></div><a class="exception-admin-link" href="javascript:void(0)">ส่งให้ Manager ตรวจ</a></div><div class="leave-history"><?php if(!$missingOwn):?><div class="empty mini">ไม่มีรายการที่ต้องแก้เวลาออก</div><?php endif;?><?php foreach(array_slice($missingOwn,0,8) as $a):$pendingCorr=workforce_correction_pending($d,(int)$a['id']);?><article><div><b><?=h(date('d/m/Y',strtotime((string)$a['check_in'])))?> · IN <?=h(date('H:i',strtotime((string)$a['check_in'])))?> → <?=empty($a['check_out'])?'?':h(date('H:i',strtotime((string)$a['check_out'])))?></b><span class="exception-state"><?=h(workforce_attendance_state($a)==='provisional'?'เวลาชั่วคราว · รอตรวจ':'ขาด Check-out')?></span><small>Payroll จะยังไม่ Final จนกว่าจะได้รับการยืนยัน</small></div><div><?php if($pendingCorr):?><em class="leave-status pending">รออนุมัติแก้เวลา</em><?php else:?><form method="post" class="correction-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="request_time_correction"><input type="hidden" name="attendance_id" value="<?=(int)$a['id']?>"><input type="datetime-local" name="requested_checkout" value="<?=h(!empty($a['check_out'])?date('Y-m-d\TH:i',strtotime((string)$a['check_out'])):date('Y-m-d\TH:i',strtotime((string)$a['check_in'].' +8 hours')))?>" required><input name="reason" placeholder="เหตุผล เช่น ลืมกด / มือถือดับ" required><button>ขอแก้เวลาออก</button></form><?php endif;?></div></article><?php endforeach;?></div></section>
<section class="my-leaves substitute-history"><div class="section-title-row"><div><small>PR SUBSTITUTE</small><h2>คำขอคนมาแทนของฉัน</h2></div><button type="button" class="small-plus" id="subOpen2">⇄ ขอคนมาแทน</button></div><div class="leave-history"><?php if(!$ownSubs):?><div class="empty mini">ยังไม่มีคำขอคนมาแทน</div><?php endif;?><?php foreach(array_slice($ownSubs,0,12) as $r):?><article><div><b><?=h(date('d/m/Y',strtotime((string)$r['date'])))?> · <?=h(workforce_substitute_label($d,$r))?></b><span><?=($r['substitute_type']??'')==='external'?'PR คนนอก':'PR ในระบบ'?> · <?=h((string)$r['reason'])?></span><?php if(!empty($r['admin_note'])):?><small>Admin: <?=h((string)$r['admin_note'])?></small><?php endif;?></div><div><em class="leave-status <?=h((string)$r['status'])?>"><?=h(pc_sub_status((string)$r['status']))?></em><?php if(($r['status']??'')==='pending'):?><form method="post" onsubmit="return confirm('ยกเลิกคำขอคนมาแทน?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="cancel_substitute"><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><button class="cancel-leave">ยกเลิก</button></form><?php endif;?></div></article><?php endforeach;?></div></section>
<footer>MR BAR PR Calendar · v1.20.0 · Schema v<?=h((string)($d['meta']['schema']??19))?></footer></main>
<nav class="mobile-dock calendar-dock income-dock"><a href="pr.php"><span>◷</span><small>ลงเวลา</small></a><a class="active" href="pr-calendar.php"><span>▦</span><small>ปฏิทิน</small></a><a href="employee-income.php"><span>฿</span><small>รายได้</small></a><a href="pr-jobs.php"><span>✦</span><small>งาน</small></a></nav>
<div class="leave-modal" id="leaveModal" hidden><div class="leave-dialog"><button type="button" class="modal-x" id="leaveClose">×</button><div class="modal-kicker">LEAVE REQUEST</div><h2>＋ บันทึกการลา</h2><p>ส่งคำขอให้ Admin ตรวจสอบและอนุมัติ</p><form method="post" id="leaveForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="request_leave"><label>ประเภทลา<select name="type" required><option value="sick">ลาป่วย</option><option value="personal">ลากิจ</option><option value="vacation">ลาพักร้อน</option><option value="unpaid">ลาไม่รับค่าจ้าง</option><option value="other">ลาอื่น ๆ</option></select></label><div class="date-pair"><label>ตั้งแต่<input type="date" name="start_date" id="leaveStart" value="<?=h($selected)?>" required></label><label>ถึง<input type="date" name="end_date" id="leaveEnd" value="<?=h($selected)?>" required></label></div><label>ช่วงเวลา<select name="portion"><option value="full">เต็มวัน</option><option value="am">ครึ่งวันเช้า</option><option value="pm">ครึ่งวันบ่าย</option></select></label><label>เหตุผล<textarea name="reason" rows="4" maxlength="500" placeholder="เช่น มีไข้ / มีธุระครอบครัว" required></textarea></label><button class="submit-leave">ส่งคำขอลา →</button></form></div></div>
<div class="leave-modal" id="subModal" hidden><div class="leave-dialog substitute-dialog"><button type="button" class="modal-x" id="subClose">×</button><div class="modal-kicker">PR SUBSTITUTE REQUEST</div><h2>⇄ ขอ PR มาแทน</h2><p>เลือกกะที่มาไม่ได้ แล้วเสนอ PR ในระบบหรือ PR คนนอกให้ Admin อนุมัติ</p><form method="post" id="subForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="request_substitute"><label>กะที่ต้องการคนแทน<select name="shift_id" required><?php foreach($subShiftOptions as $sh):?><option value="<?=(int)$sh['id']?>" data-date="<?=h((string)$sh['date'])?>" <?=((string)$sh['date']===$selected)?'selected':''?>><?=h(date('d/m/Y',strtotime((string)$sh['date'])).' · '.(string)$sh['start'].'–'.(string)$sh['end'])?></option><?php endforeach;?></select></label><label>ประเภทคนมาแทน<select name="substitute_type" id="subType"><option value="internal">PR ในระบบ</option><?php if(($d['settings']['substitute_external_allowed']??'1')==='1'):?><option value="external">PR คนนอก</option><?php endif;?></select></label><div id="subInternal"><label>เลือก PR<select name="substitute_employee_id"><option value="0">เลือก PR</option><?php foreach($subCandidates as $e):?><option value="<?=(int)$e['id']?>"><?=h((string)$e['code'].' · '.(string)$e['name'])?></option><?php endforeach;?></select></label></div><div id="subExternal" hidden><label>ชื่อ PR คนนอก<input name="external_name" maxlength="100" placeholder="ชื่อ / ชื่อเล่น"></label><label>เบอร์ติดต่อ (ถ้ามี)<input name="external_phone" maxlength="40" placeholder="08x-xxx-xxxx"></label><label>ข้อมูลเพิ่มเติม<input name="external_note" maxlength="180" placeholder="เช่น ติดต่อผ่านใคร / ประสบการณ์"></label></div><label>เหตุผล<textarea name="reason" rows="3" maxlength="500" placeholder="เช่น ป่วย / ติดธุระ / มาไม่ได้" required></textarea></label><button class="submit-leave">ส่งคำขอคนมาแทน →</button></form></div></div>
<div class="photo-viewer" id="photoViewer" hidden><button type="button" id="photoClose">×</button><img id="photoImage" alt="หลักฐานลงเวลา"><p id="photoCaption"></p></div>
<script id="calendarData" type="application/json"><?=json_encode($detailData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script>window.MRBAR_CAL_SELECTED=<?=json_encode($selected,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="assets/pr-calendar.js?v=1200"></script><script>
(function(){const modal=document.getElementById('subModal'),open1=document.getElementById('subOpen'),open2=document.getElementById('subOpen2'),close=document.getElementById('subClose'),type=document.getElementById('subType'),inside=document.getElementById('subInternal'),outside=document.getElementById('subExternal');function show(){if(modal)modal.hidden=false}function hide(){if(modal)modal.hidden=true}if(open1)open1.onclick=show;if(open2)open2.onclick=show;if(close)close.onclick=hide;if(modal)modal.addEventListener('click',e=>{if(e.target===modal)hide()});if(type)type.addEventListener('change',()=>{const ext=type.value==='external';if(inside)inside.hidden=ext;if(outside)outside.hidden=!ext;});})();
</script><?php if($isAdminPreview):?><script src="assets/pr-preview-v1173.js?v=1173"></script><?php endif;?></body></html>
