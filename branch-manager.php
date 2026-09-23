<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/admin-nav.php';
$u=current_user();if(!$u){header('Location:login.php');exit;}if(empty($u['super_admin'])){http_response_code(403);exit(permission_denied_page('Super Admin · Branch Manager'));}
$msg='';$err='';
function bm_unique_slug(array $d,string $wanted,int $ignore=0): string {
    $base=db_slugify_branch($wanted);$slug=$base;$n=2;
    while(true){$used=false;foreach($d['branches']??[] as $b){if((int)$b['id']===$ignore)continue;if(db_slug_key((string)($b['slug']??''))===db_slug_key($slug)){$used=true;break;}foreach($b['slug_history']??[] as $oldSlug)if(db_slug_key((string)$oldSlug)===db_slug_key($slug)){$used=true;break;}if($used)break;}if(!$used)return $slug;$slug=$base.'-'.$n++;}
}
function bm_media_dir(): string {return __DIR__.'/storage/branch-media';}
function bm_media_ensure_dir(): void {
    $dir=bm_media_dir();
    if(!is_dir($dir)){@mkdir($dir,0775,true);clearstatcache(true,$dir);}
    if(is_dir($dir)&&!is_writable($dir)){@chmod($dir,0775);clearstatcache(true,$dir);}
    if(!is_dir($dir)||!is_writable($dir))throw new RuntimeException('Server ไม่อนุญาตให้เขียนโฟลเดอร์รูป Branch กรุณาตรวจ Permission ของ storage/branch-media');
}
function bm_media_upload(array $file,string $kind): string {
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK){
        $messages=[UPLOAD_ERR_INI_SIZE=>'ไฟล์ใหญ่เกิน upload_max_filesize ของ Hosting',UPLOAD_ERR_FORM_SIZE=>'ไฟล์ใหญ่เกินขนาดที่ฟอร์มอนุญาต',UPLOAD_ERR_PARTIAL=>'ไฟล์อัปโหลดมาไม่ครบ กรุณาลองใหม่',UPLOAD_ERR_NO_FILE=>'ไม่ได้เลือกไฟล์',UPLOAD_ERR_NO_TMP_DIR=>'Hosting ไม่มี Temporary Folder',UPLOAD_ERR_CANT_WRITE=>'Hosting ไม่สามารถเขียนไฟล์ Upload ได้',UPLOAD_ERR_EXTENSION=>'PHP Extension หยุดการ Upload'];
        throw new RuntimeException($messages[$error]??('อัปโหลดรูปไม่สำเร็จ (code '.$error.')'));
    }
    $isCover=$kind==='cover';$limit=$isCover?15*1024*1024:5*1024*1024;
    $size=(int)($file['size']??0);if($size<=0||$size>$limit)throw new RuntimeException($isCover?'รูป Cover ต้องมีขนาดไม่เกิน 15MB':'Logo ต้องมีขนาดไม่เกิน 5MB');
    $tmp=(string)($file['tmp_name']??'');if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    $info=@getimagesize($tmp);if(!$info||empty($info[0])||empty($info[1]))throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพที่รองรับ');
    $width=(int)$info[0];$height=(int)$info[1];
    if($width>16000||$height>16000)throw new RuntimeException('มิติรูปต้องไม่เกิน 16,000 × 16,000 px');
    if($isCover&&($width<320||$height<180))throw new RuntimeException('รูป Cover ต้องมีขนาดอย่างน้อย 320 × 180 px');
    if(!$isCover&&($width<32||$height<32))throw new RuntimeException('Logo ต้องมีขนาดอย่างน้อย 32 × 32 px');
    $mime=(string)($info['mime']??'');$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$ext=$allowed[$mime]??'';
    if($ext==='')throw new RuntimeException('รองรับเฉพาะรูป JPG / PNG / WEBP');
    bm_media_ensure_dir();$name='branch-'.$kind.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$ext;$destination=bm_media_dir().'/'.$name;
    if(!@move_uploaded_file($tmp,$destination))throw new RuntimeException('บันทึกรูป Branch ลง Server ไม่สำเร็จ');@chmod($destination,0644);
    return 'branch-media.php?file='.rawurlencode($name);
}
function bm_media_url(string $value): string {
    $value=trim($value);if($value===''||stripos($value,'javascript:')===0)return '';
    if(preg_match('#^https?://#i',$value)||strpos($value,'//')===0||strpos($value,'/')===0)return $value;
    return branch_public_base().'/'.ltrim($value,'/');
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0){
 $err='ไฟล์รวมมีขนาดใหญ่เกินค่า post_max_size ของ Hosting กรุณาลดขนาดไฟล์หรือติดต่อผู้ดูแล Server';
}elseif(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 csrf_check();$action=(string)($_POST['action']??'save_branch');
 try{
  if($action==='save_branch'){
   $id=max(0,(int)($_POST['id']??0));$name=trim((string)($_POST['name']??''));if($name==='')throw new RuntimeException('กรุณากรอกชื่อร้าน');
   $logoValue=trim((string)($_POST['logo']??''));$coverValue=trim((string)($_POST['cover_image']??''));
   if((int)($_FILES['logo_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$logoValue=bm_media_upload($_FILES['logo_file'],'logo');
   if((int)($_FILES['cover_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$coverValue=bm_media_upload($_FILES['cover_file'],'cover');
   $savedId=0;
   db_mutate_global(function($d)use($id,$name,$logoValue,$coverValue,&$savedId,$u){
    $idx=null;foreach($d['branches']??[] as $i=>$b)if((int)$b['id']===$id){$idx=$i;break;}
    $old=$idx!==null?$d['branches'][$idx]:[];$newId=$idx!==null?$id:next_id($d['branches']??[]);
    $requested=(string)($_POST['slug']??$name);$slug=bm_unique_slug($d,$requested,$newId);
    $history=is_array($old['slug_history']??null)?$old['slug_history']:[];
    $oldSlug=(string)($old['slug']??'');if($oldSlug!==''&&$oldSlug!==$slug)$history[]=$oldSlug;
    $history=array_values(array_unique(array_filter($history,fn($x)=>db_slug_key((string)$x)!==db_slug_key($slug))));
    $row=db_normalize_branch(array_merge($old,[
      'id'=>$newId,'code'=>strtoupper(trim((string)($_POST['code']??('BR'.$newId)))),
      'name'=>$name,'short_name'=>trim((string)($_POST['short_name']??$name)),
      'slug'=>$slug,'slug_history'=>$history,'description'=>trim((string)($_POST['description']??'')),
      'address'=>trim((string)($_POST['address']??'')),'phone'=>trim((string)($_POST['phone']??'')),
      'line_url'=>trim((string)($_POST['line_url']??'')),'facebook_url'=>trim((string)($_POST['facebook_url']??'')),
      'logo'=>$logoValue,'cover_image'=>$coverValue,
      'lat'=>is_numeric($_POST['lat']??null)?(float)$_POST['lat']:null,'lng'=>is_numeric($_POST['lng']??null)?(float)$_POST['lng']:null,
      'radius_m'=>max(20,(int)($_POST['radius_m']??200)),'active'=>!empty($_POST['active'])?1:0,
      'published'=>!empty($_POST['published'])?1:0,'portal_featured'=>!empty($_POST['portal_featured'])?1:0,
      'sort_order'=>(int)($_POST['sort_order']??100),'updated_at'=>date('c')
    ]),$newId);
    if($idx===null){$row['created_at']=date('c');$d['branches'][]=$row;}else$d['branches'][$idx]=$row;
    $savedId=$newId;$d['audit'][]=['at'=>date('c'),'action'=>$idx===null?'branch_created':'branch_updated','branch_id'=>$newId,'by'=>(int)$u['id'],'slug'=>$slug];return $d;
   });
   $_SESSION['mrbar_active_branch_id']=$savedId;$msg=$id?'บันทึกข้อมูลร้านแล้ว':'สร้างร้านใหม่และเลือกเป็นร้านปัจจุบันแล้ว';
  }elseif($action==='save_access'){
   $userId=max(1,(int)($_POST['user_id']??0));$ids=array_values(array_unique(array_filter(array_map('intval',$_POST['branch_ids']??[]),fn($x)=>$x>0)));if(!$ids)throw new RuntimeException('ต้องเลือกอย่างน้อย 1 ร้าน');
   db_mutate_global(function($d)use($userId,$ids,$u){$activeIds=array_map(fn($b)=>(int)$b['id'],array_filter($d['branches']??[],fn($b)=>!empty($b['active'])&&empty($b['deleted_at'])));if(array_diff($ids,$activeIds))throw new RuntimeException('เลือกได้เฉพาะสาขาที่เปิดใช้งานอยู่');$found=false;foreach($d['users'] as &$row){if((int)$row['id']!==$userId)continue;if(!empty($row['super_admin']))throw new RuntimeException('Owner / Super Admin เข้าถึงทุกร้านอยู่แล้ว');$row['branch_ids']=$ids;$found=true;break;}unset($row);if(!$found)throw new RuntimeException('ไม่พบบัญชีผู้ใช้');$d['audit'][]=['at'=>date('c'),'action'=>'user_branch_access_updated','branch_id'=>(int)($ids[0]??1),'by'=>(int)$u['id'],'user_id'=>$userId,'branch_ids'=>$ids];return $d;});$msg='บันทึกสิทธิ์ร้านของทีมงานแล้ว';
  }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$d=db_load_global();$recovery=$d['meta']['schema26_recovery']??null;if(is_array($recovery)&&!empty($recovery['copied'])&&$msg==='')$msg='กู้ข้อมูลร้านเดิมกลับเข้า Priest แล้ว โดยเก็บข้อมูลต้นทางไว้ครบ';$editId=max(0,(int)($_GET['edit']??0));$edit=$editId?branch_find($d,$editId):null;
if(!$edit)$edit=db_normalize_branch(['id'=>0,'name'=>'','slug'=>'','active'=>1,'published'=>1],1);
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Branch Manager · MR BAR</title><link rel="stylesheet" href="assets/admin.css?v=1172"><link rel="stylesheet" href="assets/admin-v14.css?v=1172"><link rel="stylesheet" href="assets/multi-branch-admin-v1300.css?v=1300"><link rel="stylesheet" href="assets/branch-media-upload-v1303.css?v=1303"><link rel="stylesheet" href="assets/multi-branch-admin-polish-v1306.css?v=1306"><link rel="stylesheet" href="assets/branch-logo-preview-v1309.css?v=1309"><link rel="stylesheet" href="assets/branch-logo-preview-fix-v1310.css?v=1310"><script src="assets/branch-media-upload-v1303.js?v=1303" defer></script></head><body class="admin-v14-page mb-page"><div class="ambient"><i></i><i></i><i></i></div><?=admin_sidebar('branchmanager',$u)?>
<main class="mb-shell"><header class="mb-top"><div><p>PLATFORM / MULTI BRANCH</p><h1>Branch Manager</h1><span>จัดการร้าน URL ทีมงาน และการเผยแพร่บน Portal กลาง</span></div><nav><a href="portal-config.php">Config Web Portal</a><a href="<?=h(branch_public_base().'/Portal')?>" target="_blank">เปิด Portal ↗</a></nav></header>
<?php if($msg):?><div class="mb-notice ok">✓ <?=h($msg)?></div><?php endif;?><?php if($err):?><div class="mb-notice err">⚠ <?=h($err)?></div><?php endif;?>
<section class="mb-kpis"><article><span>ร้านทั้งหมด</span><b><?=count($d['branches']??[])?></b></article><article><span>เปิดใช้งาน</span><b><?=count(array_filter($d['branches']??[],fn($b)=>!empty($b['active'])))?></b></article><article><span>ขึ้น Portal</span><b><?=count(array_filter($d['branches']??[],fn($b)=>!empty($b['published'])&&!empty($b['active'])))?></b></article></section>
<div class="mb-grid"><section class="mb-panel">
<header><div><small>SHOP DIRECTORY</small><h2>ร้านและสาขา</h2></div><a class="mb-primary" href="branch-manager.php">+ เพิ่มร้าน</a></header>
<div class="mb-branch-list">
<?php foreach(($d['branches']??[]) as $branchRow){$branchStats=$d['branch_data'][(string)(int)$branchRow['id']]??[];$initial=mb_strtoupper(mb_substr((string)$branchRow['name'],0,2,'UTF-8'),'UTF-8');$thumb=bm_media_url((string)($branchRow['logo']?:$branchRow['cover_image']?:''));?>
<article class="<?php echo !empty($branchRow['active'])?'':'muted';?>">
 <div class="mb-thumb"><?php if($thumb!==''):?><img src="<?=h($thumb)?>" alt="" loading="lazy"><?php endif;?><b><?php echo h($initial);?></b></div>
 <div><h3><?php echo h((string)$branchRow['name']);?></h3><code>/shop/<?php echo h((string)$branchRow['slug']);?>/</code><p><?php echo h((string)($branchRow['address']??''));?></p><span><?php echo !empty($branchRow['published'])?'● PUBLIC':'○ HIDDEN';?> · <?php echo !empty($branchRow['active'])?'ACTIVE':'INACTIVE';?> · <?=count($branchStats['tables']??[])?> โต๊ะ · <?=count($branchStats['prs']??[])?> PR · <?=count($branchStats['customer_media']??[])?> รูป</span></div>
 <nav><a href="<?php echo h(branch_slug_path($branchRow).'?'.http_build_query(['admin_preview'=>'1','preview_token'=>csrf_token()]));?>" target="_blank" rel="noopener">Preview</a><a href="?edit=<?php echo (int)$branchRow['id'];?>">แก้ไข</a></nav>
</article>
<?php }?>
</div></section>
<section class="mb-panel"><header><div><small><?=!empty($editId)?'EDIT SHOP':'NEW SHOP'?></small><h2><?=!empty($editId)?h((string)$edit['name']):'เพิ่มร้านใหม่'?></h2></div></header><form method="post" enctype="multipart/form-data" class="mb-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_branch"><input type="hidden" name="id" value="<?=$editId?>">
<div class="mb-form-grid"><label><span>ชื่อร้าน</span><input name="name" required value="<?=h((string)$edit['name'])?>"></label><label><span>ชื่อย่อ</span><input name="short_name" value="<?=h((string)$edit['short_name'])?>"></label><label><span>รหัสร้าน</span><input name="code" value="<?=h((string)$edit['code'])?>"></label><label><span>URL Slug</span><div class="mb-slug"><i>/shop/</i><input name="slug" value="<?=h((string)$edit['slug'])?>" placeholder="ชื่อร้าน"><i>/</i></div><small>แก้ภายหลังได้ ลิงก์เก่าจะ Redirect อัตโนมัติ</small></label><label class="wide"><span>คำอธิบายบน Portal</span><textarea name="description" rows="3"><?=h((string)$edit['description'])?></textarea></label><label class="wide"><span>ที่อยู่</span><textarea name="address" rows="2"><?=h((string)$edit['address'])?></textarea></label><label><span>โทรศัพท์</span><input name="phone" value="<?=h((string)$edit['phone'])?>"></label><label><span>Line URL</span><input name="line_url" value="<?=h((string)$edit['line_url'])?>"></label><label><span>Facebook URL</span><input name="facebook_url" value="<?=h((string)$edit['facebook_url'])?>"></label>
<div class="mb-media-item" data-branch-media>
 <div class="mb-media-title"><span>Logo ร้าน</span><small>JPG / PNG / WEBP · ไม่เกิน 5MB</small></div>
 <div class="mb-media-row">
  <div class="mb-media-preview logo<?=bm_media_url((string)$edit['logo'])!==''?' has-image':''?>" data-media-preview><?php if($logoPreview=bm_media_url((string)$edit['logo'])):?><img src="<?=h($logoPreview)?>" alt="ตัวอย่าง Logo"><?php else:?><b>LOGO</b><?php endif;?></div>
  <div class="mb-media-controls"><label class="mb-upload-button"><input type="file" name="logo_file" accept="image/jpeg,image/png,image/webp" data-media-file><span>เลือกรูป Logo จากเครื่อง</span></label><em data-media-name>เมื่อเลือกแล้ว กด “บันทึกร้าน” เพื่ออัปโหลดเข้า Server</em><input name="logo" value="<?=h((string)$edit['logo'])?>" placeholder="หรือวาง Logo URL จากภายนอก"><small>ถ้าเลือกไฟล์ ระบบจะใช้ไฟล์ใหม่แทน URL ในช่องนี้</small></div>
 </div>
</div>
<div class="mb-media-item wide cover" data-branch-media>
 <div class="mb-media-title"><span>รูป Cover บน Portal</span><small>แนะนำแนวนอน 1600 × 900 px · ไม่เกิน 15MB</small></div>
 <div class="mb-media-row">
  <div class="mb-media-preview cover<?=bm_media_url((string)$edit['cover_image'])!==''?' has-image':''?>" data-media-preview><?php if($coverPreview=bm_media_url((string)$edit['cover_image'])):?><img src="<?=h($coverPreview)?>" alt="ตัวอย่าง Cover"><?php else:?><b>COVER IMAGE</b><?php endif;?></div>
  <div class="mb-media-controls"><label class="mb-upload-button"><input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp" data-media-file><span>เลือกรูป Cover จากเครื่อง</span></label><em data-media-name>รูปนี้จะแสดงบนการ์ดร้านในหน้า Portal กลาง</em><input name="cover_image" value="<?=h((string)$edit['cover_image'])?>" placeholder="หรือวาง Cover Image URL จากภายนอก"><small>ถ้าเลือกไฟล์ ระบบจะใช้ไฟล์ใหม่แทน URL ในช่องนี้</small></div>
 </div>
</div><label><span>Latitude</span><input name="lat" value="<?=h((string)($edit['lat']??''))?>"></label><label><span>Longitude</span><input name="lng" value="<?=h((string)($edit['lng']??''))?>"></label><label><span>รัศมีลงเวลา (เมตร)</span><input type="number" min="20" name="radius_m" value="<?=(int)$edit['radius_m']?>"></label><label><span>ลำดับบน Portal</span><input type="number" name="sort_order" value="<?=(int)$edit['sort_order']?>"></label></div>
<div class="mb-switches"><label><input type="checkbox" name="active" <?=!empty($edit['active'])?'checked':''?>><span>เปิดใช้งานร้าน</span></label><label><input type="checkbox" name="published" <?=!empty($edit['published'])?'checked':''?>><span>แสดงบน Portal</span></label><label><input type="checkbox" name="portal_featured" <?=!empty($edit['portal_featured'])?'checked':''?>><span>ร้านแนะนำ</span></label></div><button class="mb-save">บันทึกร้าน</button></form></section></div>
<section class="mb-panel mb-team"><header><div><small>TEAM ACCESS</small><h2>กำหนดร้านให้ทีมหลังบ้าน</h2><p>Owner / Super Admin เข้าถึงทุกร้านอัตโนมัติ</p></div></header><div class="mb-team-grid"><?php foreach($d['users']??[] as $account):if(!empty($account['super_admin']))continue;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="save_access"><input type="hidden" name="user_id" value="<?=(int)$account['id']?>"><div><b><?=h((string)($account['display_name']??$account['username']??'User'))?></b><small><?=h((string)($account['role']??''))?> · <?=h((string)($account['username']??''))?></small></div><fieldset><?php $allowed=db_user_branch_ids($account);foreach($d['branches']??[] as $b):?><label><input type="checkbox" name="branch_ids[]" value="<?=(int)$b['id']?>" <?=in_array((int)$b['id'],$allowed,true)?'checked':''?>><span><?=h((string)$b['name'])?></span></label><?php endforeach;?></fieldset><button>บันทึกสิทธิ์</button></form><?php endforeach;?></div></section>
</main></body></html>
