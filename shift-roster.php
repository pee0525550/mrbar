<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';

$u=require_any_permission(['shifts.view','employees.view']);
$d=db_load();
$canManage=user_can($u,'shifts.manage',$d);
$msg='';$err='';$preview=null;

function sr_team_label(string $team): string { return workforce_roster_team_label($team); }
function sr_type_label(string $type): string { return ['regular'=>'Regular','half'=>'Half Shift','event'=>'Event','standby'=>'Standby'][$type]??$type; }
function sr_branch_label(array $d,$id): string {
    $id=(int)$id;if($id<=0)return 'ตามสาขาประจำพนักงาน';
    $b=workforce_branch($d,$id);return $b?((string)$b['code'].' · '.(string)$b['name']):'ไม่พบสาขา';
}
function sr_day_labels(array $days): string {
    $map=[0=>'อา',1=>'จ',2=>'อ',3=>'พ',4=>'พฤ',5=>'ศ',6=>'ส'];$out=[];
    foreach($days as $v){$v=(int)$v;if(isset($map[$v]))$out[]=$map[$v];}
    return $out?implode(' · ',$out):'ทุกวัน';
}
function sr_position_label(string $p): string { return workforce_position_label($p); }

$employees=array_values(array_filter($d['employees']??[],function($e){return !empty($e['active']);}));
usort($employees,function($a,$b){
    $ta=['day'=>0,'night'=>1,'flex'=>2];$xa=$ta[workforce_roster_team((string)($a['roster_team']??'flex'))]??2;$xb=$ta[workforce_roster_team((string)($b['roster_team']??'flex'))]??2;
    if($xa!==$xb)return $xa<=>$xb;
    return strcasecmp((string)($a['code']??''),(string)($b['code']??''));
});
$branches=array_values(array_filter($d['branches']??[],function($b){return !empty($b['active']);}));
$templates=array_values(array_filter($d['shift_templates']??[],function($t){return !empty($t['active']);}));
usort($templates,function($a,$b){return strcasecmp((string)($a['name']??''),(string)($b['name']??''));});

$selectedTemplateId=(int)($_POST['template_id']??$_GET['template_id']??($templates[0]['id']??0));
$dateStart=(string)($_POST['date_start']??$_GET['date_start']??date('Y-m-d'));
$dateEnd=(string)($_POST['date_end']??$_GET['date_end']??date('Y-m-d',strtotime('+6 days')));
$selectedEmployeeIds=array_values(array_unique(array_map('intval',(array)($_POST['employee_ids']??[]))));

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action=(string)($_POST['action']??'');
    try{
        if(!$canManage && $action!=='preview_roster')throw new RuntimeException('ไม่มี Permission: shifts.manage');
        if($action==='save_template'){
            $id=(int)($_POST['template_edit_id']??0);
            $name=trim((string)($_POST['name']??''));$team=workforce_roster_team((string)($_POST['team']??'flex'));
            $start=trim((string)($_POST['start']??''));$end=trim((string)($_POST['end']??''));$type=(string)($_POST['type']??'regular');
            $branchId=(int)($_POST['branch_id']??0);$days=array_values(array_unique(array_map('intval',(array)($_POST['days']??[]))));
            $note=trim((string)($_POST['note']??''));
            if($name==='')throw new RuntimeException('กรุณาตั้งชื่อ Template');
            if(!preg_match('/^\d{2}:\d{2}$/',$start)||!preg_match('/^\d{2}:\d{2}$/',$end))throw new RuntimeException('เวลา Template ไม่ถูกต้อง');
            if(!in_array($type,['regular','half','event','standby'],true))throw new RuntimeException('ประเภทกะไม่ถูกต้อง');
            $days=array_values(array_filter($days,function($v){return $v>=0&&$v<=6;}));if(!$days)throw new RuntimeException('กรุณาเลือกวันทำงานอย่างน้อย 1 วัน');
            if($branchId>0){$b=workforce_branch($d,$branchId);if(!$b||empty($b['active']))throw new RuntimeException('สาขาที่เลือกไม่พร้อมใช้งาน');}
            db_mutate(function($x)use($id,$name,$team,$start,$end,$type,$branchId,$days,$note,$u){
                if($id>0){
                    foreach($x['shift_templates'] as &$t){if((int)($t['id']??0)!==$id)continue;
                        $t['name']=$name;$t['team']=$team;$t['start']=$start;$t['end']=$end;$t['type']=$type;$t['branch_id']=$branchId?:null;$t['days']=$days;$t['note']=$note;$t['active']=1;$t['updated_at']=date('c');
                        audit_permission($x,$u,'shift_template_updated',['template_id'=>$id,'name'=>$name]);return $x;
                    }unset($t);throw new RuntimeException('ไม่พบ Template');
                }
                $nid=next_id($x['shift_templates']??[]);
                $x['shift_templates'][]=['id'=>$nid,'name'=>$name,'team'=>$team,'start'=>$start,'end'=>$end,'type'=>$type,'branch_id'=>$branchId?:null,'days'=>$days,'note'=>$note,'active'=>1,'created_by'=>(int)$u['id'],'created_at'=>date('c'),'updated_at'=>date('c')];
                audit_permission($x,$u,'shift_template_created',['template_id'=>$nid,'name'=>$name]);return $x;
            });
            header('Location: shift-roster.php?template_saved=1');exit;
        }elseif($action==='archive_template'){
            $id=(int)($_POST['template_id']??0);
            db_mutate(function($x)use($id,$u){foreach($x['shift_templates'] as &$t){if((int)($t['id']??0)!==$id)continue;$t['active']=0;$t['updated_at']=date('c');audit_permission($x,$u,'shift_template_archived',['template_id'=>$id]);return $x;}unset($t);throw new RuntimeException('ไม่พบ Template');});
            header('Location: shift-roster.php?template_archived=1');exit;
        }elseif($action==='save_roster_teams'){
            $teams=(array)($_POST['roster_team']??[]);
            db_mutate(function($x)use($teams,$u){
                $changed=0;
                foreach($x['employees'] as &$e){
                    $id=(int)($e['id']??0);
                    if(!array_key_exists((string)$id,$teams)&&!array_key_exists($id,$teams))continue;
                    $raw=array_key_exists((string)$id,$teams)?$teams[(string)$id]:$teams[$id];
                    $team=workforce_roster_team((string)$raw);
                    if((string)($e['roster_team']??'flex')!==$team){$e['roster_team']=$team;$e['updated_at']=date('c');$changed++;}
                }unset($e);
                audit_permission($x,$u,'roster_team_assignments_updated',['changed'=>$changed]);return $x;
            });
            header('Location: shift-roster.php?team_saved=1');exit;
        }elseif($action==='preview_roster'){
            $template=workforce_shift_template_by_id($d,$selectedTemplateId);if(!$template||empty($template['active']))throw new RuntimeException('กรุณาเลือก Template ที่ใช้งานอยู่');
            $preview=workforce_roster_preview($d,$template,$selectedEmployeeIds,$dateStart,$dateEnd);
        }elseif($action==='apply_roster'){
            if(!$canManage)throw new RuntimeException('ไม่มี Permission: shifts.manage');
            $templateId=(int)($_POST['template_id']??0);$dateStart=(string)($_POST['date_start']??'');$dateEnd=(string)($_POST['date_end']??'');$ids=array_values(array_unique(array_map('intval',(array)($_POST['employee_ids']??[]))));
            $result=db_mutate(function($x)use($templateId,$dateStart,$dateEnd,$ids,$u){
                $template=workforce_shift_template_by_id($x,$templateId);if(!$template||empty($template['active']))throw new RuntimeException('Template ถูกปิดหรือไม่พบ');
                $pv=workforce_roster_preview($x,$template,$ids,$dateStart,$dateEnd);$ready=(int)($pv['stats']['ready']??0);if($ready<=0)throw new RuntimeException('ไม่มีรายการที่พร้อมสร้างกะ');
                $batchId=next_id($x['roster_batches']??[]);$created=[];$now=date('c');$nextShiftId=next_id($x['shifts']??[]);
                foreach($pv['rows'] as $row){
                    if(($row['status']??'')!=='ready')continue;
                    $eid=(int)$row['employee_id'];$emp=workforce_employee_by_id($x,$eid);if(!$emp||empty($emp['active']))continue;
                    if(workforce_employee_has_approved_leave($x,$eid,(string)$row['date']))continue;
                    if(workforce_roster_duplicate($x,$eid,(string)$row['date'],$template))continue;
                    if(workforce_roster_conflict($x,$eid,(string)$row['date'],(string)$template['start'],(string)$template['end']))continue;
                    $sid=$nextShiftId++;$branchId=(int)($template['branch_id']??0);if($branchId<=0)$branchId=(int)($emp['branch_id']??0);
                    $x['shifts'][]=['id'=>$sid,'employee_id'=>$eid,'pr_id'=>(int)($emp['pr_id']??0)?:null,'date'=>(string)$row['date'],'start'=>(string)$template['start'],'end'=>(string)$template['end'],'status'=>'scheduled','type'=>(string)$template['type'],'branch_id'=>$branchId?:null,'note'=>trim((string)($template['note']??'')),'created_by'=>(int)$u['id'],'created_at'=>$now,'updated_at'=>$now,'substitution_id'=>0,'replaces_shift_id'=>0,'external_substitute_name'=>'','external_substitute_phone'=>'','shift_template_id'=>$templateId,'roster_batch_id'=>$batchId];
                    $created[]=$sid;
                }
                if(!$created)throw new RuntimeException('ข้อมูลเปลี่ยนระหว่าง Preview และยืนยัน จึงยังไม่ได้สร้างกะ');
                $stats=$pv['stats'];$x['roster_batches'][]=['id'=>$batchId,'template_id'=>$templateId,'template_name'=>(string)$template['name'],'date_start'=>$dateStart,'date_end'=>$dateEnd,'employee_ids'=>$ids,'created_shift_ids'=>$created,'created_count'=>count($created),'skipped_conflict'=>(int)($stats['conflict']??0),'skipped_leave'=>(int)($stats['leave']??0),'skipped_duplicate'=>(int)($stats['duplicate']??0),'status'=>'active','created_by'=>(int)$u['id'],'created_at'=>$now,'undone_by'=>null,'undone_at'=>null,'undo_count'=>0,'undo_skipped'=>0];
                audit_permission($x,$u,'auto_roster_applied',['batch_id'=>$batchId,'template_id'=>$templateId,'created_count'=>count($created),'date_start'=>$dateStart,'date_end'=>$dateEnd]);
                return $x;
            });
            $last=end($result['roster_batches']);$count=(int)($last['created_count']??0);header('Location: shift-roster.php?batch_created='.$count);exit;
        }elseif($action==='undo_batch'){
            $batchId=(int)($_POST['batch_id']??0);
            $result=db_mutate(function($x)use($batchId,$u){
                $bi=null;foreach($x['roster_batches'] as $i=>$b)if((int)($b['id']??0)===$batchId){$bi=$i;break;}if($bi===null)throw new RuntimeException('ไม่พบ Batch');
                $batch=&$x['roster_batches'][$bi];if(($batch['status']??'active')!=='active')throw new RuntimeException('Batch นี้ถูกย้อนกลับแล้ว');
                $ids=array_fill_keys(array_map('intval',(array)($batch['created_shift_ids']??[])),true);$undone=0;$skipped=0;$today=date('Y-m-d');
                foreach($x['shifts'] as &$s){
                    $sid=(int)($s['id']??0);if(!isset($ids[$sid])||(int)($s['roster_batch_id']??0)!==$batchId)continue;
                    if((string)($s['status']??'')!=='scheduled'||!empty($s['substitution_id'])||(string)($s['date']??'')<$today){$skipped++;continue;}
                    $hasAttendance=false;foreach($x['attendance']??[] as $a){if((int)($a['employee_id']??0)===(int)($s['employee_id']??0)&&substr((string)($a['check_in']??''),0,10)===(string)$s['date']){$hasAttendance=true;break;}}
                    if($hasAttendance){$skipped++;continue;}
                    $s['status']='cancelled';$s['updated_at']=date('c');$s['note']=trim((string)($s['note']??'').' · Auto Roster Undo');$undone++;
                }unset($s);
                $batch['status']=$skipped>0?'partial_undo':'undone';$batch['undone_by']=(int)$u['id'];$batch['undone_at']=date('c');$batch['undo_count']=$undone;$batch['undo_skipped']=$skipped;
                audit_permission($x,$u,'auto_roster_undone',['batch_id'=>$batchId,'undo_count'=>$undone,'undo_skipped'=>$skipped]);unset($batch);return $x;
            });
            $b=null;foreach($result['roster_batches'] as $x)if((int)($x['id']??0)===$batchId){$b=$x;break;}$undone=(int)($b['undo_count']??0);$skipped=(int)($b['undo_skipped']??0);
            header('Location: shift-roster.php?batch_undone='.$undone.'&batch_skipped='.$skipped);exit;
        }
    }catch(Throwable $e){$err=$e->getMessage();}
    $d=db_load();
    $employees=array_values(array_filter($d['employees']??[],function($e){return !empty($e['active']);}));
    usort($employees,function($a,$b){return strcasecmp((string)($a['code']??''),(string)($b['code']??''));});
    $templates=array_values(array_filter($d['shift_templates']??[],function($t){return !empty($t['active']);}));
}

if(isset($_GET['template_saved']))$msg='บันทึก Shift Template แล้ว';
if(isset($_GET['template_archived']))$msg='ปิดใช้งาน Template แล้ว';
if(isset($_GET['team_saved']))$msg='บันทึก Roster Team แล้ว';
if(isset($_GET['batch_created']))$msg='Auto Roster สร้างกะแล้ว '.(int)$_GET['batch_created'].' รายการ';
if(isset($_GET['batch_undone']))$msg='ย้อนกลับกะจาก Batch แล้ว '.(int)$_GET['batch_undone'].' รายการ'.((int)($_GET['batch_skipped']??0)>0?' · ข้าม '.(int)$_GET['batch_skipped'].' รายการที่ไม่ปลอดภัยต่อการยกเลิก':'');
$d=db_load();
$templates=array_values(array_filter($d['shift_templates']??[],function($t){return !empty($t['active']);}));
usort($templates,function($a,$b){return strcasecmp((string)($a['name']??''),(string)($b['name']??''));});
$batches=array_values($d['roster_batches']??[]);usort($batches,function($a,$b){return strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});$batches=array_slice($batches,0,12);
$teamCounts=['day'=>0,'night'=>0,'flex'=>0];foreach($employees as $e)$teamCounts[workforce_roster_team((string)($e['roster_team']??'flex'))]++;
$activeShiftCount=0;foreach($d['shifts']??[] as $s)if(!in_array((string)($s['status']??'scheduled'),['cancelled','off','substituted'],true)&&((string)($s['date']??'')>=date('Y-m-d')))$activeShiftCount++;
?><!doctype html>
<html lang="th" data-mr-page="shift-roster"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MR BAR — Shift Template & Auto Roster</title>
<link rel="stylesheet" href="assets/admin.css?v=1210">
<link rel="stylesheet" href="assets/admin-v14.css?v=1210">
<link rel="stylesheet" href="assets/shift-roster-v121.css?v=1210">
</head><body class="admin-v14-page roster-page">
<div class="ambient"><i></i><i></i><i></i></div>
<?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('roster',$u);?>
<main class="roster-shell">
<header class="roster-head"><div><p class="eyebrow">WORKFORCE / SHIFT AUTOMATION</p><h1>Shift Template & Auto Roster</h1><p>สร้างแม่แบบกะ · แบ่งทีมกลางวัน/กลางคืน · Preview กะซ้อนและวันลาก่อนลงตารางจริง</p></div><div class="roster-head-actions"><a href="workforce-schedule.php">▦ เปิดปฏิทินกะ</a><a href="employees.php">♙ Employees</a></div></header>
<?php if($msg):?><div class="roster-notice ok">✓ <?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="roster-notice err">⚠ <?=h($err)?></div><?php endif;?>

<section class="roster-kpis">
<article><small>ACTIVE EMPLOYEES</small><b><?=count($employees)?></b><span>Day <?=$teamCounts['day']?> · Night <?=$teamCounts['night']?> · Flex <?=$teamCounts['flex']?></span></article>
<article><small>SHIFT TEMPLATES</small><b><?=count($templates)?></b><span>แม่แบบที่เปิดใช้งาน</span></article>
<article><small>FUTURE SHIFTS</small><b><?=$activeShiftCount?></b><span>กะตั้งแต่วันนี้เป็นต้นไป</span></article>
<article class="gold"><small>SAFE AUTO ROSTER</small><b>PREVIEW</b><span>Leave / Duplicate / Conflict จะถูก Skip</span></article>
</section>

<section class="roster-grid">
<div class="roster-main">
<section class="roster-card builder-card">
<div class="section-head"><div><small>STEP 01 · BUILD ROSTER</small><h2>เลือก Template + ช่วงวันที่ + พนักงาน</h2><p>ระบบสร้างเฉพาะรายการสถานะ “พร้อมสร้าง” เท่านั้น</p></div><span class="safe-chip">SAFE MODE</span></div>
<?php if(!$templates):?><div class="empty-panel"><b>ยังไม่มี Shift Template</b><span>สร้าง Template ด้านขวาก่อน แล้วกลับมาเลือกพนักงานเพื่อ Preview</span></div><?php else:?>
<form method="post" id="rosterBuilder" class="builder-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="preview_roster">
<div class="builder-fields">
<label>Shift Template<select name="template_id" id="builderTemplate" required><?php foreach($templates as $t):?><option value="<?=(int)$t['id']?>" <?=(int)$t['id']===$selectedTemplateId?'selected':''?>><?=h((string)$t['name'].' · '.(string)$t['start'].'–'.(string)$t['end'].' · '.sr_team_label((string)$t['team']))?></option><?php endforeach;?></select></label>
<label>เริ่มวันที่<input type="date" name="date_start" value="<?=h($dateStart)?>" required></label>
<label>ถึงวันที่<input type="date" name="date_end" value="<?=h($dateEnd)?>" required></label>
</div>
<div class="employee-picker-head"><div><h3>เลือกพนักงาน</h3><p>ใช้ตัวกรองเพื่อเลือกทั้งทีม แล้วปรับรายคนได้</p></div><div class="employee-search"><input type="search" id="rosterEmployeeSearch" placeholder="ค้นหารหัส / ชื่อ / ตำแหน่ง"></div></div>
<div class="picker-actions">
<button type="button" data-pick="all">ทั้งหมด</button><button type="button" class="day" data-pick="day">☀ ทีมกลางวัน</button><button type="button" class="night" data-pick="night">☾ ทีมกลางคืน</button><button type="button" data-pick="flex">◇ Flex</button><button type="button" data-pick-position="pr">PR</button><button type="button" data-pick-position="staff">Staff</button><button type="button" data-clear>ล้างเลือก</button>
</div>
<div class="employee-picker" id="rosterEmployeePicker">
<?php foreach($employees as $e):$team=workforce_roster_team((string)($e['roster_team']??'flex'));$checked=in_array((int)$e['id'],$selectedEmployeeIds,true);?>
<label class="employee-option team-<?=h($team)?>" data-team="<?=h($team)?>" data-position="<?=h((string)$e['position'])?>" data-search="<?=h(strtolower((string)$e['code'].' '.(string)$e['name'].' '.(string)$e['position'].' '.(string)$e['department']))?>">
<input type="checkbox" name="employee_ids[]" value="<?=(int)$e['id']?>" <?=$checked?'checked':''?>>
<span class="emp-dot"><?=h(workforce_initial((string)$e['name']))?></span><span class="emp-copy"><b><?=h((string)$e['code'].' · '.(string)$e['name'])?></b><small><?=h(sr_position_label((string)$e['position']))?> · <?=h(sr_team_label($team))?> · <?=h(sr_branch_label($d,$e['branch_id']??0))?></small></span><em><?=h(strtoupper($team))?></em>
</label><?php endforeach;?>
</div>
<div class="builder-submit"><div><small id="selectedEmployeeCount">0 คนถูกเลือก</small><b>Preview ก่อนสร้างจริงเสมอ</b></div><button <?=$canManage?'':'disabled'?>>Preview Auto Roster →</button></div>
</form><?php endif;?>
</section>

<?php if($preview!==null):$pst=$preview['stats'];$template=workforce_shift_template_by_id($d,$selectedTemplateId);?>
<section class="roster-card preview-card" id="previewResults">
<div class="section-head"><div><small>STEP 02 · PREVIEW</small><h2><?=h((string)($template['name']??'Template'))?></h2><p><?=h($dateStart)?> → <?=h($dateEnd)?> · <?=count($preview['dates'])?> วันตาม Template</p></div><span class="preview-total"><?=h((string)$pst['total'])?> รายการ</span></div>
<div class="preview-stats"><article class="ready"><small>พร้อมสร้าง</small><b><?=$pst['ready']?></b></article><article class="leave"><small>วันลา</small><b><?=$pst['leave']?></b></article><article class="duplicate"><small>มีอยู่แล้ว</small><b><?=$pst['duplicate']?></b></article><article class="conflict"><small>กะซ้อน</small><b><?=$pst['conflict']?></b></article></div>
<div class="preview-table-wrap"><table class="preview-table"><thead><tr><th>วันที่</th><th>พนักงาน</th><th>ทีม</th><th>เวลา</th><th>สาขา</th><th>ผลตรวจ</th></tr></thead><tbody>
<?php foreach($preview['rows'] as $r):?><tr class="state-<?=h((string)$r['status'])?>"><td><?=h(date('d/m',strtotime((string)$r['date'])))?></td><td><b><?=h((string)$r['code'].' · '.(string)$r['name'])?></b><small><?=h(sr_position_label((string)$r['position']))?></small></td><td><span class="team-pill <?=h((string)$r['team'])?>"><?=h(sr_team_label((string)$r['team']))?></span></td><td><strong><?=h((string)$r['start'].'–'.(string)$r['end'])?></strong></td><td><?=h(sr_branch_label($d,$r['branch_id']))?></td><td><span class="state-pill <?=h((string)$r['status'])?>"><?=h((string)$r['detail'])?></span></td></tr><?php endforeach;?>
</tbody></table></div>
<?php if($canManage&&$pst['ready']>0):?><form method="post" class="apply-form" onsubmit="return confirm('ยืนยันสร้าง <?=h((string)$pst['ready'])?> กะจาก Preview นี้?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="apply_roster"><input type="hidden" name="template_id" value="<?=$selectedTemplateId?>"><input type="hidden" name="date_start" value="<?=h($dateStart)?>"><input type="hidden" name="date_end" value="<?=h($dateEnd)?>"><?php foreach($selectedEmployeeIds as $eid):?><input type="hidden" name="employee_ids[]" value="<?=$eid?>"><?php endforeach;?><div><small>SERVER จะตรวจ Conflict / Leave ซ้ำอีกครั้งก่อน Commit</small><b>พร้อมสร้าง <?=$pst['ready']?> กะ</b></div><button>ยืนยันสร้าง Auto Roster →</button></form><?php endif;?>
</section>
<?php endif;?>

<section class="roster-card team-card">
<div class="section-head"><div><small>TEAM SETUP</small><h2>ทีมกลางวัน / กลางคืน / Flex</h2><p>ใช้สำหรับปุ่มเลือกทีมใน Auto Roster เท่านั้น ไม่กระทบ Role หรือ Payroll</p></div></div>
<form method="post" id="teamAssignmentForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_roster_teams">
<div class="team-assignment-grid"><?php foreach($employees as $e):$team=workforce_roster_team((string)($e['roster_team']??'flex'));?><label><span><b><?=h((string)$e['code'].' · '.(string)$e['name'])?></b><small><?=h(sr_position_label((string)$e['position']))?></small></span><select name="roster_team[<?=(int)$e['id']?>]"><option value="day" <?=$team==='day'?'selected':''?>>☀ ทีมกลางวัน</option><option value="night" <?=$team==='night'?'selected':''?>>☾ ทีมกลางคืน</option><option value="flex" <?=$team==='flex'?'selected':''?>>◇ Flex / ไม่ล็อกทีม</option></select></label><?php endforeach;?></div>
<?php if($canManage):?><button class="team-save">บันทึก Team Assignment →</button><?php endif;?></form>
</section>
</div>

<aside class="roster-side">
<section class="roster-card template-editor">
<div class="section-head"><div><small>SHIFT TEMPLATE</small><h2>สร้างแม่แบบกะ</h2><p>กำหนดเวลา วันทำงาน ทีม และสาขา</p></div></div>
<?php if($canManage):?><form method="post" id="templateForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_template"><input type="hidden" name="template_edit_id" id="templateEditId" value="0">
<label>ชื่อ Template<input name="name" id="templateName" placeholder="เช่น กะดึก PR ศุกร์–เสาร์" required></label>
<div class="two"><label>ทีม<select name="team" id="templateTeam"><option value="night">☾ ทีมกลางคืน</option><option value="day">☀ ทีมกลางวัน</option><option value="flex">◇ Flex</option></select></label><label>ประเภท<select name="type" id="templateType"><option value="regular">Regular</option><option value="half">Half Shift</option><option value="event">Event</option><option value="standby">Standby</option></select></label></div>
<div class="two"><label>เริ่ม<input type="time" name="start" id="templateStart" value="<?=h((string)($d['settings']['shop_open_time']??'18:00'))?>" required></label><label>จบ<input type="time" name="end" id="templateEnd" value="<?=h((string)($d['settings']['shop_close_time']??'02:00'))?>" required></label></div>
<label>สาขา<select name="branch_id" id="templateBranch"><option value="0">ตามสาขาประจำพนักงาน</option><?php foreach($branches as $b):?><option value="<?=(int)$b['id']?>"><?=h((string)$b['code'].' · '.(string)$b['name'])?></option><?php endforeach;?></select></label>
<div class="day-select"><small>วันที่ใช้ Template</small><div><?php foreach([0=>'อา',1=>'จ',2=>'อ',3=>'พ',4=>'พฤ',5=>'ศ',6=>'ส'] as $n=>$label):?><label><input type="checkbox" name="days[]" value="<?=$n?>" checked><span><?=$label?></span></label><?php endforeach;?></div></div>
<label>หมายเหตุ<input name="note" id="templateNote" maxlength="200" placeholder="Event / VIP / รอบปกติ"></label>
<div class="template-buttons"><button>บันทึก Template</button><button type="button" class="ghost" id="templateReset">ล้างฟอร์ม</button></div></form><?php else:?><div class="empty-panel">View only</div><?php endif;?>
</section>

<section class="roster-card template-list"><div class="section-head"><div><small>ACTIVE TEMPLATES</small><h2><?=count($templates)?> แม่แบบ</h2></div></div>
<?php if(!$templates):?><div class="empty-panel">ยังไม่มี Template</div><?php endif;?>
<?php foreach($templates as $t):?><article class="template-item team-<?=h((string)$t['team'])?>" data-template='<?=h(json_encode(['id'=>(int)$t['id'],'name'=>(string)$t['name'],'team'=>(string)$t['team'],'start'=>(string)$t['start'],'end'=>(string)$t['end'],'type'=>(string)$t['type'],'branch_id'=>(int)($t['branch_id']??0),'days'=>array_values((array)$t['days']),'note'=>(string)($t['note']??'')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><div><span class="template-team"><?=h(sr_team_label((string)$t['team']))?></span><h3><?=h((string)$t['name'])?></h3><b><?=h((string)$t['start'].' – '.(string)$t['end'])?></b><small><?=h(sr_day_labels((array)$t['days']))?> · <?=h(sr_branch_label($d,$t['branch_id']??0))?></small></div><?php if($canManage):?><div class="template-actions"><button type="button" data-edit-template>แก้ไข</button><form method="post" onsubmit="return confirm('ปิดใช้งาน Template นี้? กะที่สร้างไปแล้วจะไม่ถูกลบ')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="archive_template"><input type="hidden" name="template_id" value="<?=(int)$t['id']?>"><button class="archive">ปิด</button></form></div><?php endif;?></article><?php endforeach;?>
</section>

<section class="roster-card batch-list"><div class="section-head"><div><small>RECENT AUTO ROSTER</small><h2>Batch History</h2><p>Undo ใช้ได้เฉพาะกะที่ยังไม่เริ่มและไม่มี Attendance</p></div></div>
<?php if(!$batches):?><div class="empty-panel">ยังไม่มี Auto Roster Batch</div><?php endif;?>
<?php foreach($batches as $b):?><article class="batch-item status-<?=h((string)$b['status'])?>"><div class="batch-top"><span>#<?=h((string)$b['id'])?></span><b><?=h((string)$b['template_name'])?></b><em><?=h(strtoupper((string)$b['status']))?></em></div><p><?=h(date('d/m/Y',strtotime((string)$b['date_start'])))?> → <?=h(date('d/m/Y',strtotime((string)$b['date_end'])))?></p><div class="batch-stats"><span>สร้าง <b><?=(int)$b['created_count']?></b></span><span>ชน <b><?=(int)$b['skipped_conflict']?></b></span><span>ลา <b><?=(int)$b['skipped_leave']?></b></span><span>ซ้ำ <b><?=(int)$b['skipped_duplicate']?></b></span></div><?php if($canManage&&($b['status']??'active')==='active'):?><form method="post" onsubmit="return confirm('Undo Batch #<?=h((string)$b['id'])?> ? ระบบจะไม่ยกเลิกกะที่เริ่มงาน/มี Attendance แล้ว')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="undo_batch"><input type="hidden" name="batch_id" value="<?=(int)$b['id']?>"><button>Undo Future Shifts</button></form><?php elseif(($b['status']??'')!=='active'):?><small class="undo-note">Undo <?=(int)($b['undo_count']??0)?> · Skip <?=(int)($b['undo_skipped']??0)?></small><?php endif;?></article><?php endforeach;?>
</section>
</aside>
</section>
<footer>MR BAR Shift Template & Auto Roster · v1.21.0 · Schema v<?=h((string)($d['meta']['schema']??20))?></footer>
</main>
<script src="assets/shift-roster-v121.js?v=1210"></script>
</body></html>
