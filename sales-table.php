<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/sales-table.php';
header('Cache-Control: no-store');
$slug=trim((string)($_GET['public_branch']??''));
$tableId=(int)($_GET['table_id']??0);
$raw=db_load_global();$found=$slug!==''?db_branch_by_slug_raw($raw,$slug):null;
if(!$found||empty($found['branch']['active'])){http_response_code(404);exit('ไม่พบสาขาจาก QR กรุณาสแกน QR ใหม่');}
$branch=$found['branch'];$d=db_branch_view($raw,(int)$branch['id']);$qrToken=trim((string)($_GET['qr_token']??$_POST['qr_token']??''));$publicQr=st_public_token_valid($d,$slug,$tableId,$qrToken);$u=current_user();
if(!$publicQr){
 if(!$u){$_SESSION['sales_table_return']=['public_branch'=>$slug,'table_id'=>$tableId];header('Location:login.php?return_to=sales_table');exit;}
 if(!db_user_can_branch($u,(int)$branch['id'])){http_response_code(403);exit('บัญชีนี้ไม่มีสิทธิ์ในสาขาของ QR');}
 try{st_actor($d,(int)$u['id']);}catch(Throwable $e){http_response_code(403);exit(h($e->getMessage()));}
}
if(isset($_GET['photo'])){
 try{$e=st_sales($d,(int)$_GET['photo']);}catch(Throwable $e){http_response_code(404);exit;}
 $rel=(string)($e['profile']['profile_photo']??'');
 $base=realpath(__DIR__.'/storage/employee-media');$file=$rel!==''?realpath(__DIR__.'/'.$rel):false;
 if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit;}
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);
 if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
 header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');readfile($file);exit;
}
$err='';$url='sales-table.php?'.http_build_query(array_filter(['public_branch'=>$slug,'table_id'=>$tableId,'qr_token'=>$publicQr?$qrToken:null],fn($v)=>$v!==null&&$v!==''));
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 try{
  if((string)($_POST['public_branch']??'')!==$slug||(int)($_POST['table_id']??0)!==$tableId)throw new RuntimeException('ข้อมูล QR ไม่ตรงกัน');
  $input=$_POST;$bid=(int)$branch['id'];
  if($publicQr){if(!hash_equals($qrToken,(string)($_POST['qr_token']??'')))throw new RuntimeException('QR Token ไม่ตรงกัน');db_mutate_global(function($raw)use($input,$bid){$view=db_branch_view($raw,$bid);$view=st_apply_public($view,$input,'QR Public');return db_merge_branch_view($raw,$view,$bid);});}
  else{$uid=(int)$u['id'];db_mutate_global(function($raw)use($input,$uid,$bid){$view=db_branch_view($raw,$bid);$view=st_apply($view,$input,$uid);return db_merge_branch_view($raw,$view,$bid);});}
  header('Location:'.$url.'&saved=1');exit;
 }catch(Throwable $e){$err=$e->getMessage();}
 $d=db_branch_view(db_load_global(),(int)$branch['id']);
}
$table=null;foreach($d['tables']??[] as $t)if((int)$t['id']===$tableId){$table=$t;break;}
$sales=array_values(array_filter($d['employees']??[],fn($e)=>!empty($e['active'])&&($e['position']??'')==='sales'));
$open=st_open_session($d,$tableId);$canManage=!$publicQr&&$u&&user_can($u,'sales_sessions.manage',$d);
$history=array_values(array_filter($d['sales_table_sessions']??[],fn($s)=>st_row_in_branch($d,$s)&&($tableId===0||(int)$s['table_id']===$tableId)));$history=array_reverse($history);
function st_fields($slug,$tableId,$s=null,$qrToken=''){?><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="public_branch" value="<?=h($slug)?>"><input type="hidden" name="table_id" value="<?=$tableId?>"><?php if($qrToken!==''):?><input type="hidden" name="qr_token" value="<?=h($qrToken)?>"><?php endif;?><?php if($s):?><input type="hidden" name="session_id" value="<?=(int)$s['id']?>"><input type="hidden" name="revision" value="<?=(int)$s['revision']?>"><?php endif;}
function st_cards($sales,$d,$qrToken=''){?><div class="sales-cards"><?php foreach($sales as $e):?><label class="sales-card"><input type="radio" name="sales_id" value="<?=(int)$e['id']?>" required><?php $photo=!empty($e['profile']['profile_photo'])?'sales-table.php?'.http_build_query(array_filter(['public_branch'=>(string)($_GET['public_branch']??''),'table_id'=>(int)($_GET['table_id']??0),'qr_token'=>$qrToken!==''?$qrToken:null,'photo'=>(int)$e['id']],fn($v)=>$v!==null&&$v!=='')):'';if($photo):?><img src="<?=h($photo)?>" alt=""><?php endif;?><b><?=h(st_label($e))?></b></label><?php endforeach;?></div><?php }
$publicQr=$publicQr??false;$qrToken=$qrToken??'';
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sales เปิด–ปิดโต๊ะ</title><link rel="stylesheet" href="assets/typography.css"><link rel="stylesheet" href="assets/sales-table.css"></head><body><main>
<header><small>SALES TABLE · <?=h((string)$branch['name'])?></small><h1><?=$table?'โต๊ะ '.h((string)$table['code']):'ประวัติเปิด–ปิดโต๊ะ Sales'?></h1><p>ผู้ทำรายการ: <?=$publicQr?'QR สาธารณะ · ไม่ต้อง Login':h((string)($u['display_name']??$u['username']))?> · บันทึกแทน Sales ได้</p><?php if(!$publicQr):?><a href="admin-tools.php">กลับเครื่องมือร้าน</a><?php else:?><span class="badge">PUBLIC QR ACCESS</span><?php endif;?></header>
<?php if($err):?><p class="error" role="alert"><?=h($err)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="success">บันทึกสำเร็จ</p><?php endif;?>
<p class="note">บันทึกเจ้าของยอดเชียร์เท่านั้น ไม่ใช่ยอดดื่มส่วนตัว และยังไม่คำนวณค่าคอม · เปิดรอบแล้วหน้าลูกค้าจะแสดงมีลูกค้า · ปิดพร้อมใบเสร็จจะคืนโต๊ะว่างเมื่อไม่มีงานอื่นค้าง</p>
<?php if($table):?><?php if(!$open):?>
<section><h2>เปิดรอบใหม่ · เลือก Sales เจ้าของยอด</h2><p>ตรวจรูป ชื่อ และรหัสก่อนยืนยัน ผู้ทำรายการจะถูกบันทึกแยกไว้</p>
<?php if(!$sales):?><p class="error">ยังไม่มี Sales ที่ใช้งานในร้านนี้ กรุณาเพิ่มใน Employee Center</p><?php else:?><form method="post" action="<?=h($url)?>"><?php st_fields($slug,$tableId,null,$publicQr?$qrToken:'');?><input type="hidden" name="action" value="open"><?php st_cards($sales,$d,$publicQr?$qrToken:'');?><button>ยืนยันเปิดโต๊ะให้ Sales ที่เลือก</button></form><?php endif;?></section>
<?php else:?>
<section><span class="badge">กำลังดูแลโต๊ะ</span><h2><?=h($open['sales_label'])?></h2><p>เปิดเมื่อ <?=h($open['opened_at'])?></p><p>บันทึกโดย <?=h($open['opened_by_label'])?></p>
<form method="post" action="<?=h($url)?>"><?php st_fields($slug,$tableId,$open,$publicQr?$qrToken:'');?><input type="hidden" name="action" value="close"><label>หมายเลขใบเสร็จ (หนึ่งใบต่อบรรทัด)<textarea name="receipts" rows="4" required maxlength="2500" placeholder="RC-001&#10;RC-002"></textarea></label><p>ปิดแล้วจะรอจับคู่กับรายงานใบเสร็จ POS ยังไม่ถือเป็นยอดขายที่ยืนยัน</p><button>ยืนยันปิดรอบและผูกใบเสร็จ</button></form></section>
<?php endif;?><?php elseif($tableId>0):?><p class="error">ไม่พบโต๊ะนี้ในร้าน</p><?php endif;?>
<?php if(!$publicQr):?><section><h2>ประวัติรอบโต๊ะ</h2><div class="scroll"><table><thead><tr><th>โต๊ะ / Sales เจ้าของยอด</th><th>เปิด / ผู้บันทึก</th><th>ปิด / ผู้บันทึก</th><th>ใบเสร็จ</th><th>สถานะยอด POS</th></tr></thead><tbody>
<?php if(!$history):?><tr><td colspan="5">ยังไม่มีรายการ</td></tr><?php endif;?>
<?php foreach(array_slice($history,0,100) as $s):?><tr><td><a href="sales-table.php?<?=h(http_build_query(['public_branch'=>$slug,'table_id'=>(int)$s['table_id']]))?>"><b><?=h($s['table_code'])?></b></a><br><?=h($s['sales_label'])?></td><td><?=h($s['opened_at'])?><br><?=h($s['opened_by_label'])?></td><td><?=h((string)($s['closed_at']??'ยังเปิดอยู่'))?><br><?=h((string)($s['closed_by_label']??''))?></td><td><?=h(implode(', ',$s['receipts']))?></td><td><?=$s['status']==='open'?'รอปิดรอบ':'รอจับคู่ยอด POS'?></td></tr>
<?php if($canManage&&$tableId>0):?><tr><td colspan="5"><details><summary>แก้ไขโดยผู้ดูแล · ต้องระบุเหตุผล</summary><form method="post" action="<?=h($url)?>"><?php st_fields($slug,$tableId,$s,$publicQr?$qrToken:'');?><input type="hidden" name="action" value="transfer"><label>เปลี่ยน Sales เจ้าของยอด<select name="sales_id" required><?php foreach($sales as $e):?><option value="<?=(int)$e['id']?>" <?=(int)$s['sales_id']===(int)$e['id']?'selected':''?>><?=h(st_label($e))?></option><?php endforeach;?></select></label><label>เหตุผล<input name="reason" required maxlength="500"></label><button>บันทึกการเปลี่ยนเจ้าของยอด</button></form>
<?php if($s['status']==='closed'):?><form method="post" action="<?=h($url)?>"><?php st_fields($slug,$tableId,$s,$publicQr?$qrToken:'');?><input type="hidden" name="action" value="edit_receipts"><label>แก้เลขใบเสร็จ<textarea name="receipts" required><?=h(implode("\n",$s['receipts']))?></textarea></label><label>เหตุผล<input name="reason" required maxlength="500"></label><button>บันทึกใบเสร็จที่แก้ไข</button></form><?php endif;?></details></td></tr><?php endif;?>
<?php endforeach;?></tbody></table></div><p>แสดง 100 รอบล่าสุด · ประวัติการแก้ไขเก็บใน Audit Log</p></section><?php endif;?>
</main></body></html>
