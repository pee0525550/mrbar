<?php
require __DIR__.'/app/bootstrap.php';
$u=require_permission('dashboard.view');
if($u['role']==='pr'){header('Location:pr.php');exit;}
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();$action=(string)($_POST['action']??'');
    try{
        if($action==='assign'){if(!user_can($u,'operations.assign_pr'))throw new RuntimeException('ไม่มี Permission: operations.assign_pr');
            $cid=(int)($_POST['checkin_id']??0);$prId=(int)($_POST['pr_id']??0);$uid=(int)$u['id'];
            if(!$prId) throw new RuntimeException('กรุณาเลือก PR ก่อนมอบหมายงาน');
            db_mutate(function($d)use($cid,$prId,$uid){
                $prIndex=null;foreach($d['prs'] as $i=>$p)if((int)$p['id']===$prId){$prIndex=$i;break;}
                if($prIndex===null)throw new RuntimeException('ไม่พบ PR');
                if(($d['prs'][$prIndex]['status']??'offline')!=='online')throw new RuntimeException('PR ต้องอยู่สถานะ ONLINE จึงจะรับงานใหม่ได้');
                $ci=null;foreach($d['checkins'] as $i=>$c)if((int)$c['id']===$cid){$ci=$i;break;}
                if($ci===null)throw new RuntimeException('ไม่พบ Check-in');
                if(!in_array((string)$d['checkins'][$ci]['status'],['pending','rejected'],true))throw new RuntimeException('รายการนี้ไม่อยู่ในคิวที่สามารถมอบหมายได้');
                $d['checkins'][$ci]['status']='assigned';$d['checkins'][$ci]['pr_id']=$prId;$d['checkins'][$ci]['assigned_at']=date('c');$d['checkins'][$ci]['assigned_by']=$uid;$d['checkins'][$ci]['updated_at']=date('c');
                $d['prs'][$prIndex]['status']='busy';$d['prs'][$prIndex]['current_checkin_id']=$cid;
                $target=(int)($d['prs'][$prIndex]['user_id']??0);
                op_notify($d,$target?:null,'pr','job_assigned','มีงานใหม่รอการตอบรับ: '.$d['checkins'][$ci]['ticket'],['checkin_id'=>$cid]);
                $d['audit'][]=['at'=>date('c'),'action'=>'staff_assign_pr','checkin_id'=>$cid,'pr_id'=>$prId,'by'=>$uid];
                return $d;
            });$msg='มอบหมายงานให้ PR แล้ว — รอ PR กดรับงาน';
        } elseif($action==='cancel'){if(!user_can($u,'operations.cancel'))throw new RuntimeException('ไม่มี Permission: operations.cancel');
            $cid=(int)($_POST['checkin_id']??0);$uid=(int)$u['id'];
            db_mutate(function($d)use($cid,$uid){
                $ci=null;foreach($d['checkins'] as $i=>$c)if((int)$c['id']===$cid){$ci=$i;break;}if($ci===null)throw new RuntimeException('ไม่พบรายการ');
                $prId=(int)($d['checkins'][$ci]['pr_id']??0);$tableId=(int)($d['checkins'][$ci]['table_id']??0);
                $d['checkins'][$ci]['status']='cancelled';$d['checkins'][$ci]['updated_at']=date('c');$d['checkins'][$ci]['completed_at']=date('c');
                foreach($d['prs'] as &$p)if((int)$p['id']===$prId){$p['status']=attendance_open($d,$prId)?'online':'offline';$p['current_checkin_id']=null;}unset($p);
                foreach($d['tables'] as &$t)if((int)$t['id']===$tableId)$t['status']='available';unset($t);
                $d['audit'][]=['at'=>date('c'),'action'=>'staff_cancel_job','checkin_id'=>$cid,'by'=>$uid];return $d;
            });$msg='ยกเลิกรายการและคืนโต๊ะแล้ว';
        } elseif($action==='force_complete'){if(!user_can($u,'operations.complete'))throw new RuntimeException('ไม่มี Permission: operations.complete');
            $cid=(int)($_POST['checkin_id']??0);$uid=(int)$u['id'];
            db_mutate(function($d)use($cid,$uid){
                $ci=null;foreach($d['checkins'] as $i=>$c)if((int)$c['id']===$cid){$ci=$i;break;}if($ci===null)throw new RuntimeException('ไม่พบรายการ');
                $prId=(int)($d['checkins'][$ci]['pr_id']??0);$tableId=(int)($d['checkins'][$ci]['table_id']??0);
                $d['checkins'][$ci]['status']='completed';$d['checkins'][$ci]['completed_at']=date('c');$d['checkins'][$ci]['updated_at']=date('c');
                foreach($d['prs'] as &$p)if((int)$p['id']===$prId){$p['status']=attendance_open($d,$prId)?'online':'offline';$p['current_checkin_id']=null;}unset($p);
                foreach($d['tables'] as &$t)if((int)$t['id']===$tableId)$t['status']='available';unset($t);
                $d['audit'][]=['at'=>date('c'),'action'=>'staff_force_complete','checkin_id'=>$cid,'by'=>$uid];return $d;
            });$msg='ปิดงานและคืนโต๊ะเรียบร้อย';
        } elseif($action==='read_notifications'){if(!user_can($u,'notifications.manage'))throw new RuntimeException('ไม่มี Permission: notifications.manage');
            $uid=(int)$u['id'];db_mutate(function($d)use($uid){foreach($d['notifications'] as &$n){if(empty($n['read_at'])&&(((int)($n['user_id']??0)===$uid)||((int)($n['user_id']??0)===0&&($n['role']??'')==='staff')))$n['read_at']=date('c');}unset($n);return $d;});$msg='อ่านการแจ้งเตือนทั้งหมดแล้ว';
        }
    }catch(Throwable $e){$err=$e->getMessage();}
}
$d=db_load();$notifications=op_unread_notifications($d,$u);
$queue=array_values(array_filter($d['checkins'],fn($c)=>in_array((string)$c['status'],['pending','rejected','assigned','accepted','in_service'],true)));
usort($queue,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
$onlinePr=array_values(array_filter($d['prs'],fn($p)=>($p['status']??'')==='online'));
$occupied=count(array_filter($d['tables'],fn($t)=>($t['status']??'')==='occupied'));
$inService=count(array_filter($d['checkins'],fn($c)=>($c['status']??'')==='in_service'));
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#080515"><title>MR BAR — Staff Operations</title><link rel="stylesheet" href="assets/staff.css?v=140"></head><body data-auto-refresh="<?=h((string)($d['settings']['staff_auto_refresh']??'1'))?>">
<div class="fx"><i></i><i></i><i></i><b></b></div>
<aside class="side"><a class="brand" href="#top"><span>✦</span><div><b>MR BAR</b><small>STAFF OPS</small></div></a><nav><a href="employee-time.php">◷ <span>ลงเวลาของฉัน</span></a><a href="employee-calendar.php">▦ <span>ปฏิทินของฉัน</span></a><a href="employee-income.php">฿ <span>รายได้ของฉัน</span></a><a class="active" href="#queue">⌁ <span>คิวลูกค้า</span></a><a href="#prs">♛ <span>PR Live Board</span></a><a href="#tables">▦ <span>โต๊ะ</span></a><a href="#alerts">◉ <span>แจ้งเตือน</span></a><a href="night-ops.php">✦ <span>จองโต๊ะ / Night Ops</span></a><a href="custumers/" target="_blank">↗ <span>หน้าลูกค้า</span></a></nav><div class="side-foot"><span class="pulse"></span> SYSTEM LIVE<small>Update Pack v1.18.0</small></div></aside>
<main id="top"><header class="top"><div><p class="eyebrow">MR BAR / STAFF OPERATIONS</p><h1>ศูนย์ควบคุมงานหน้าร้าน</h1><p>ยินดีต้อนรับ <b><?=h((string)$u['display_name'])?></b> · <span id="liveClock"><?=date('H:i:s')?></span></p></div><div class="head-actions"><span class="new-data" id="newData" hidden>● มีข้อมูลใหม่</span><a href="logout.php">ออกจากระบบ ↗</a></div></header>
<?php if($msg):?><div class="alert ok">✓ <?=h($msg)?></div><?php endif;?><?php if($err):?><div class="alert error">! <?=h($err)?></div><?php endif;?>
<section class="kpis"><article class="pink"><small>คิวที่ต้องดูแล</small><b id="kQueue"><?=count($queue)?></b><span>รายการ</span><i>⌁</i></article><article class="blue"><small>PR พร้อมรับงาน</small><b id="kOnline"><?=count($onlinePr)?></b><span>คน</span><i>♛</i></article><article class="green"><small>โต๊ะใช้งานอยู่</small><b id="kOccupied"><?=$occupied?></b><span>โต๊ะ</span><i>▦</i></article><article class="gold"><small>กำลังให้บริการ</small><b id="kService"><?=$inService?></b><span>งาน</span><i>⚡</i></article></section>
<section class="layout"><article class="panel queue-panel" id="queue"><div class="panel-head"><div><span class="num">1</span><h2>คิวลูกค้าและงาน</h2><p>เลือก PR → รอรับงาน → เริ่มบริการ → จบงาน</p></div><span class="live-pill">● LIVE QUEUE</span></div>
<div class="queue-list"><?php if(!$queue):?><div class="empty"><b>คืนนี้คิวว่าง ✦</b><span>เมื่อมีลูกค้า Check-in รายการจะขึ้นตรงนี้อัตโนมัติ</span></div><?php endif;?><?php foreach($queue as $c):$t=op_find_table($d,(int)$c['table_id']);$p=op_find_pr($d,(int)($c['pr_id']??0));?><div class="job status-<?=h((string)$c['status'])?>"><div class="job-main"><div class="ticket"><?=h((string)$c['ticket'])?></div><h3><?=h((string)$c['guest_name'])?></h3><p>โต๊ะ <b><?=h((string)($t['code']??'-'))?></b> · <?=h(op_status_label((string)$c['status']))?></p><small><?=h(date('H:i',strtotime((string)$c['created_at'])))?><?php if($p):?> · PR <?=h((string)$p['name'])?><?php endif;?></small></div><div class="job-actions">
<?php if(in_array((string)$c['status'],['pending','rejected'],true)):?><form method="post" class="assign-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="assign"><input type="hidden" name="checkin_id" value="<?=$c['id']?>"><select name="pr_id" required><option value="">เลือก PR ที่ ONLINE</option><?php foreach($onlinePr as $op):?><option value="<?=$op['id']?>"><?=h((string)$op['name'])?> · <?=h((string)$op['code'])?></option><?php endforeach;?></select><button class="primary">มอบหมายงาน →</button></form><?php else:?><div class="workflow"><span class="wf <?=in_array((string)$c['status'],['assigned','accepted','in_service'],true)?'on':''?>">ส่งงาน</span><span class="wf <?=in_array((string)$c['status'],['accepted','in_service'],true)?'on':''?>">รับงาน</span><span class="wf <?=($c['status']??'')==='in_service'?'on':''?>">บริการ</span></div><?php endif;?>
<div class="row-actions"><form method="post" onsubmit="return confirm('ยืนยันปิดงานนี้?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="force_complete"><input type="hidden" name="checkin_id" value="<?=$c['id']?>"><button class="success">จบงาน</button></form><form method="post" onsubmit="return confirm('ยืนยันยกเลิกรายการและคืนโต๊ะ?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="checkin_id" value="<?=$c['id']?>"><button class="danger">ยกเลิก</button></form></div></div></div><?php endforeach;?></div></article>
<article class="panel" id="prs"><div class="panel-head"><div><span class="num">2</span><h2>PR Live Board</h2><p>เห็นสถานะพร้อมรับงานแบบรวดเร็ว</p></div></div><div class="pr-grid"><?php foreach($d['prs'] as $p):?><div class="pr-card <?=h((string)$p['status'])?>"><div class="avatar">♛</div><div><b><?=h((string)$p['name'])?></b><small><?=h((string)$p['code'])?></small></div><span><?=h(strtoupper((string)$p['status']))?></span></div><?php endforeach;?></div></article>
<article class="panel" id="tables"><div class="panel-head"><div><span class="num">3</span><h2>แผนผังโต๊ะ</h2><p>เขียว = ว่าง · ชมพู = มีลูกค้า · เทา = ปิด</p></div></div><div class="table-map"><?php foreach($d['tables'] as $t):?><div class="table-box <?=h((string)$t['status'])?>"><b><?=h((string)$t['code'])?></b><span><?=h((string)($t['zone']??'Main'))?></span><small><?=h(strtoupper((string)$t['status']))?></small></div><?php endforeach;?></div></article>
<article class="panel" id="alerts"><div class="panel-head"><div><span class="num">4</span><h2>การแจ้งเตือน</h2><p>เหตุการณ์ที่ต้องรู้ล่าสุด</p></div><?php if($notifications):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="read_notifications"><button class="ghost">อ่านทั้งหมด</button></form><?php endif;?></div><div class="alerts"><?php if(!$notifications):?><div class="empty small"><b>ไม่มีแจ้งเตือนใหม่</b></div><?php endif;?><?php foreach(array_slice(array_reverse($notifications),0,10) as $n):?><div><span>◉</span><p><b><?=h((string)$n['message'])?></b><small><?=h(date('H:i',strtotime((string)$n['created_at'])))?></small></p></div><?php endforeach;?></div></article></section>
<footer>✦ MR BAR Staff Operations · v1.18.0 · <span>ระบบเชื่อม Staff ↔ PR ↔ Customer</span></footer></main><script src="assets/staff.js?v=140"></script></body></html>
