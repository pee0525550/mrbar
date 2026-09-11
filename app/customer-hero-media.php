<?php
declare(strict_types=1);

function chm_defaults(array $item): array {
    $defaults=[
        'id'=>0,'type'=>'image','source'=>'','poster'=>'','original_name'=>'','mime'=>'','size'=>0,
        'poster_original_name'=>'','poster_mime'=>'','poster_size'=>0,
        'title'=>'','caption'=>'','alt'=>'','link_url'=>'','sort_order'=>100,'active'=>1,'duration'=>7,'autoplay'=>1,'loop'=>1,
        'created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>null,
    ];
    $item=array_merge($defaults,$item);
    $types=['image','video_upload','youtube','video_external'];
    if(!in_array((string)$item['type'],$types,true))$item['type']='image';
    $item['id']=(int)$item['id'];
    $item['sort_order']=max(0,min(9999,(int)$item['sort_order']));
    $item['active']=!empty($item['active'])?1:0;
    $item['duration']=max(3,min(60,(int)$item['duration']));
    $item['autoplay']=!empty($item['autoplay'])?1:0;
    $item['loop']=!empty($item['loop'])?1:0;
    return $item;
}
function chm_all(array $d,bool $activeOnly=false): array {
    $items=[];foreach($d['customer_hero_media']??[] as $item){if(!is_array($item))continue;$item=chm_defaults($item);if($activeOnly&&!$item['active'])continue;$items[]=$item;}
    usort($items,function($a,$b){$s=((int)$a['sort_order'])<=>((int)$b['sort_order']);return $s!==0?$s:((int)$a['id']<=> (int)$b['id']);});
    return $items;
}
function chm_active(array $d): array {return chm_all($d,true);}
function chm_find(array $d,int $id): ?array {foreach(chm_all($d) as $item)if((int)$item['id']===$id)return $item;return null;}
function chm_next_id(array $d): int {$max=0;foreach($d['customer_hero_media']??[] as $item)$max=max($max,(int)($item['id']??0));return $max+1;}
function chm_safe_relative_path(string $path): ?string {
    $path=str_replace('\\','/',$path);if($path===''||strpos($path,'..')!==false)return null;
    if(strpos($path,'uploads/customer-web/hero-media/')===0||strpos($path,'storage/customer-web-media/hero-media/')===0)return $path;
    return null;
}
function chm_absolute_path(string $relative): ?string {$safe=chm_safe_relative_path($relative);return $safe===null?null:dirname(__DIR__).'/'.$safe;}
function chm_pick_storage_dir(): array {
    $root=dirname(__DIR__);$candidates=[
        ['relative'=>'uploads/customer-web/hero-media','absolute'=>$root.'/uploads/customer-web/hero-media'],
        ['relative'=>'storage/customer-web-media/hero-media','absolute'=>$root.'/storage/customer-web-media/hero-media'],
    ];
    foreach($candidates as $c){
        if(!is_dir($c['absolute'])){@mkdir($c['absolute'],0775,true);@chmod($c['absolute'],0775);}clearstatcache(true,$c['absolute']);
        if(is_dir($c['absolute'])&&is_writable($c['absolute']))return $c;@chmod($c['absolute'],0777);clearstatcache(true,$c['absolute']);
        if(is_dir($c['absolute'])&&is_writable($c['absolute']))return $c;
    }
    throw new RuntimeException('Server ไม่อนุญาตให้เขียนโฟลเดอร์ Hero Media กรุณาตรวจ Permission ของ uploads/ หรือ storage/');
}
function chm_upload(array $file,string $kind): array {
    $err=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($err!==UPLOAD_ERR_OK){$messages=[UPLOAD_ERR_INI_SIZE=>'ไฟล์ใหญ่เกิน upload_max_filesize ของ Hosting',UPLOAD_ERR_FORM_SIZE=>'ไฟล์ใหญ่เกินขนาดที่ฟอร์มอนุญาต',UPLOAD_ERR_PARTIAL=>'ไฟล์อัปโหลดมาไม่ครบ',UPLOAD_ERR_NO_FILE=>'ไม่ได้เลือกไฟล์',UPLOAD_ERR_NO_TMP_DIR=>'Hosting ไม่มี Temporary Folder',UPLOAD_ERR_CANT_WRITE=>'Hosting เขียนไฟล์ Upload ไม่ได้',UPLOAD_ERR_EXTENSION=>'PHP Extension หยุดการ Upload'];throw new RuntimeException($messages[$err]??('Upload error '.$err));}
    $size=(int)($file['size']??0);$limit=$kind==='video'?100*1024*1024:12*1024*1024;if($size<=0||$size>$limit)throw new RuntimeException($kind==='video'?'วิดีโอต้องมีขนาดไม่เกิน 100MB':'รูปต้องมีขนาดไม่เกิน 12MB');
    $tmp=(string)($file['tmp_name']??'');if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    $fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($tmp);
    if($kind==='video'){$map=['video/mp4'=>'mp4','video/webm'=>'webm'];$origExt=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));if(!isset($map[$mime])&&$mime==='application/octet-stream'&&in_array($origExt,['mp4','webm'],true)){$mime=$origExt==='mp4'?'video/mp4':'video/webm';}if(!isset($map[$mime]))throw new RuntimeException('รองรับวิดีโอ MP4 / WEBM เท่านั้น');}
    else{$info=@getimagesize($tmp);if(!$info||empty($info[0])||empty($info[1]))throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพที่รองรับ');$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($map[$mime]))throw new RuntimeException('รองรับรูป JPG / PNG / WEBP เท่านั้น');}
    $dir=chm_pick_storage_dir();$name='hero_'.($kind==='video'?'video':'image').'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$map[$mime];$abs=$dir['absolute'].'/'.$name;
    if(!@move_uploaded_file($tmp,$abs))throw new RuntimeException('บันทึกไฟล์ Hero Media ลง Host ไม่สำเร็จ');@chmod($abs,0644);
    return ['path'=>$dir['relative'].'/'.$name,'original_name'=>basename((string)($file['name']??$name)),'mime'=>$mime,'size'=>$size];
}
function chm_delete_relative(string $path): void {$abs=chm_absolute_path($path);if($abs&&is_file($abs))@unlink($abs);}
function chm_delete_item_files(array $item): void {$item=chm_defaults($item);if(in_array($item['type'],['image','video_upload'],true))chm_delete_relative((string)$item['source']);if((string)$item['poster']!=='')chm_delete_relative((string)$item['poster']);}
function chm_youtube_id(string $url): string {
    $url=trim($url);if($url==='')return '';
    if(preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,20})~i',$url,$m))return $m[1];
    if(preg_match('/^[A-Za-z0-9_-]{6,20}$/',$url))return $url;
    return '';
}
function chm_validate_https_url(string $url,bool $allowRelative=false): string {
    $url=trim($url);if($url==='')return '';
    if($allowRelative&&preg_match('#^(?:/|\./|\.\./|#)#',$url))return $url;
    if(!filter_var($url,FILTER_VALIDATE_URL)||stripos($url,'https://')!==0)throw new RuntimeException('URL ต้องเป็น https:// ที่ถูกต้อง');
    return $url;
}
function chm_type_label(string $type): string {return ['image'=>'รูปภาพ','video_upload'=>'วิดีโออัปโหลด','youtube'=>'YouTube','video_external'=>'วิดีโอ URL ภายนอก'][$type]??$type;}
function chm_public_file_url(array $item,string $field='source',string $prefix=''): string {return $prefix.'customer-hero-media.php?id='.(int)$item['id'].'&field='.rawurlencode($field).'&v='.urlencode((string)($item['updated_at']??''));}
