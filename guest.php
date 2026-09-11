<?php
require __DIR__.'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location:custumers/');exit;}
csrf_check();
$name=trim((string)($_POST['guest_name']??''));$phone=trim((string)($_POST['phone']??''));$tid=(int)($_POST['table_id']??0);$d=db_load();$branch=branch_current($d);$publicBranchSlug=(string)($branch['slug']??'');$branchHome=branch_slug_path($branch);$table=null;
foreach($d['tables'] as $t){if((int)$t['id']===$tid&&$t['active']){$table=$t;break;}}
if($name===''||!$table||$table['status']!=='available')exit('ข้อมูล Check-in ไม่ถูกต้องหรือโต๊ะไม่พร้อมใช้งาน');
$ticket=ticket();$now=date('c');
db_mutate(function($d)use($name,$phone,$tid,$ticket,$now){$d['checkins'][]=['id'=>next_id($d['checkins']),'ticket'=>$ticket,'guest_name'=>$name,'phone'=>$phone,'table_id'=>$tid,'pr_id'=>null,'status'=>'waiting','created_at'=>$now,'updated_at'=>$now];foreach($d['tables'] as &$t)if((int)$t['id']===$tid)$t['status']='occupied';unset($t);return $d;});
?><!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="custumers/assets/customer.css"><body><div class="ambient ambient-a"></div><div class="maintenance"><div class="glass hero-card"><div class="orb"></div><p class="eyebrow">CHECK-IN CONFIRMED</p><h1>ยินดีต้อนรับ ✦</h1><p>หมายเลข Check-in ของคุณ</p><div style="font-size:34px;font-weight:950;letter-spacing:2px;margin:18px 0;background:linear-gradient(90deg,#ff55c8,#65baff);-webkit-background-clip:text;color:transparent"><?=h($ticket)?></div><p>โต๊ะ <?=h($table['code'])?> · <?=h($table['zone'])?></p><a class="neon-btn" style="text-decoration:none;display:flex" href="custumers/status.php?ticket=<?=urlencode($ticket)?>&amp;public_branch=<?=urlencode($publicBranchSlug)?>"><span>ดูสถานะของฉัน</span><b>→</b></a><a class="back" href="<?=h($branchHome)?>">กลับหน้าร้าน</a></div></div></body></html>
