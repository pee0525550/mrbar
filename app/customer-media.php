<?php
declare(strict_types=1);

function cm_defaults(array $m): array {
    $defaults=[
        'id'=>0,'path'=>'','original_name'=>'','mime'=>'image/jpeg','size'=>0,'width'=>0,'height'=>0,
        'slot'=>'library','alt'=>'','caption'=>'','sort_order'=>100,
        'position_x'=>50,'position_y'=>50,'zoom'=>100,'brightness'=>100,'contrast'=>100,'saturation'=>100,'blur'=>0,'overlay'=>20,
        'created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>null,
    ];
    $m=array_merge($defaults,$m);
    $m['id']=(int)$m['id'];
    $m['slot']=in_array((string)$m['slot'],['library','hero','promo','gallery','tonight','pr_section','zones','floor_preview','location','policy'],true)?(string)$m['slot']:'library';
    foreach(['position_x'=>[0,100],'position_y'=>[0,100],'zoom'=>[100,180],'brightness'=>[50,150],'contrast'=>[50,150],'saturation'=>[0,180],'blur'=>[0,8],'overlay'=>[0,75],'sort_order'=>[0,9999]] as $k=>$r){
        $m[$k]=max($r[0],min($r[1],(int)$m[$k]));
    }
    return $m;
}
function cm_all(array $d): array {
    $items=[];foreach($d['customer_media']??[] as $m){if(is_array($m))$items[]=cm_defaults($m);}
    usort($items,function($a,$b){$s=((int)$a['sort_order'])<=>((int)$b['sort_order']);return $s!==0?$s:((int)$a['id']<=> (int)$b['id']);});
    return $items;
}
function cm_find(array $d,int $id): ?array {foreach(cm_all($d) as $m)if((int)$m['id']===$id)return $m;return null;}
function cm_is_singleton_slot(string $slot): bool {return in_array($slot,['hero','promo','tonight','pr_section','zones','floor_preview','location','policy'],true);}
function cm_by_slot(array $d,string $slot): array {
    $items=array_values(array_filter(cm_all($d),function($m)use($slot){return ($m['slot']??'')===$slot;}));
    /* Legacy data may contain more than one image in a single-image slot.
       Prefer the most recently updated image so an uploaded replacement becomes visible immediately. */
    if(cm_is_singleton_slot($slot)&&count($items)>1){
        usort($items,function($a,$b){
            $ta=strtotime((string)($a['updated_at']??''))?:0;$tb=strtotime((string)($b['updated_at']??''))?:0;
            if($ta!==$tb)return $tb<=>$ta;
            return ((int)($b['id']??0))<=>((int)($a['id']??0));
        });
    }
    return $items;
}
function cm_next_id(array $d): int {$max=0;foreach($d['customer_media']??[] as $m)$max=max($max,(int)($m['id']??0));return $max+1;}
function cm_safe_relative_path(string $path): ?string {
    $path=str_replace('\\','/',$path);
    if(strpos($path,'..')!==false)return null;
    if(strpos($path,'uploads/customer-web/')===0||strpos($path,'storage/customer-web-media/')===0)return $path;
    return null;
}
function cm_absolute_path(string $relative): ?string {$safe=cm_safe_relative_path($relative);return $safe===null?null:dirname(__DIR__).'/'.$safe;}
function cm_pick_storage_dir(): array {
    $root=dirname(__DIR__);
    $candidates=[['relative'=>'uploads/customer-web','absolute'=>$root.'/uploads/customer-web'],['relative'=>'storage/customer-web-media','absolute'=>$root.'/storage/customer-web-media']];
    foreach($candidates as $c){
        if(!is_dir($c['absolute'])){@mkdir($c['absolute'],0775,true);@chmod($c['absolute'],0775);}
        clearstatcache(true,$c['absolute']);
        if(is_dir($c['absolute'])&&is_writable($c['absolute']))return $c;
        @chmod($c['absolute'],0777);clearstatcache(true,$c['absolute']);
        if(is_dir($c['absolute'])&&is_writable($c['absolute']))return $c;
    }
    throw new RuntimeException('ไม่สามารถเขียนโฟลเดอร์รูป Customer Web ได้ กรุณาตรวจ permission ของ uploads/ หรือ storage/');
}
function cm_upload_image(array $file,int $userId,array $d): array {
    $uploadError=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($uploadError!==UPLOAD_ERR_OK){$messages=[UPLOAD_ERR_INI_SIZE=>'ไฟล์ใหญ่เกิน upload_max_filesize ของ Hosting',UPLOAD_ERR_FORM_SIZE=>'ไฟล์ใหญ่เกินขนาดที่ฟอร์มอนุญาต',UPLOAD_ERR_PARTIAL=>'ไฟล์อัปโหลดมาไม่ครบ กรุณาลองใหม่',UPLOAD_ERR_NO_FILE=>'ไม่ได้เลือกไฟล์',UPLOAD_ERR_NO_TMP_DIR=>'Hosting ไม่มี Temporary Folder สำหรับ Upload',UPLOAD_ERR_CANT_WRITE=>'Hosting ไม่สามารถเขียนไฟล์ Upload ได้',UPLOAD_ERR_EXTENSION=>'PHP Extension ของ Hosting หยุดการ Upload'];throw new RuntimeException($messages[$uploadError]??('อัปโหลดรูปไม่สำเร็จ (code '.$uploadError.')'));}
    $size=(int)($file['size']??0);if($size<=0||$size>8*1024*1024)throw new RuntimeException('รูปต้องมีขนาดไม่เกิน 8MB');
    $tmp=(string)($file['tmp_name']??'');if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    $info=@getimagesize($tmp);if(!$info||empty($info[0])||empty($info[1]))throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพที่รองรับ');
    $w=(int)$info[0];$h=(int)$info[1];if($w<120||$h<120||$w>16000||$h>16000)throw new RuntimeException('ขนาดมิติรูปไม่รองรับ');
    $mime=(string)($info['mime']??'');$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($map[$mime]))throw new RuntimeException('รองรับเฉพาะ JPG / PNG / WEBP');
    $dir=cm_pick_storage_dir();$name='cw_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$map[$mime];$absolute=$dir['absolute'].'/'.$name;
    if(!@move_uploaded_file($tmp,$absolute))throw new RuntimeException('บันทึกรูปลง Host ไม่สำเร็จ');@chmod($absolute,0644);
    $id=cm_next_id($d);return cm_defaults(['id'=>$id,'path'=>$dir['relative'].'/'.$name,'original_name'=>basename((string)($file['name']??$name)),'mime'=>$mime,'size'=>$size,'width'=>$w,'height'=>$h,'slot'=>'library','sort_order'=>$id*10,'created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>$userId]);
}
function cm_delete_file(array $m): void {$p=cm_absolute_path((string)($m['path']??''));if($p&&is_file($p))@unlink($p);}
function cm_public_url(int $id,string $prefix=''): string {return $prefix.'customer-media.php?id='.$id.'&v='.urlencode((string)$id);}
function cm_style(array $m): string {
    $m=cm_defaults($m);
    $x=(int)$m['position_x'];$y=(int)$m['position_y'];$z=max(1,((int)$m['zoom'])/100);
    $b=((int)$m['brightness'])/100;$c=((int)$m['contrast'])/100;$s=((int)$m['saturation'])/100;$blur=(int)$m['blur'];
    return 'object-position:'.$x.'% '.$y.'%;transform:scale('.number_format($z,2,'.','').');transform-origin:'.$x.'% '.$y.'%;filter:brightness('.number_format($b,2,'.','').') contrast('.number_format($c,2,'.','').') saturate('.number_format($s,2,'.','').') blur('.$blur.'px);';
}
function cm_overlay(array $m): string {$m=cm_defaults($m);return number_format(((int)$m['overlay'])/100,2,'.','');}
