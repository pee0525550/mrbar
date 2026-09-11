<?php
declare(strict_types=1);

if(!function_exists('pr_showcase_status_label')){
function pr_showcase_status_label(string $status,bool $present=false): string {
    $labels=['online'=>'พร้อม','busy'=>'กำลังดูแล','break'=>'พัก','offline'=>'ออฟไลน์'];
    if(isset($labels[$status]))return $labels[$status];
    return $present?'มาแล้ว':'ออฟไลน์';
}

function pr_showcase_initials(string $name): string {
    $name=trim($name);if($name==='')return 'PR';
    if(function_exists('mb_substr')&&function_exists('mb_strtoupper'))return mb_strtoupper(mb_substr($name,0,2,'UTF-8'),'UTF-8');
    return strtoupper(substr($name,0,2));
}

function pr_showcase_rows(array $d): array {
    $open=[];
    foreach($d['attendance']??[] as $a){
        $prId=(int)($a['pr_id']??0);
        if($prId>0&&empty($a['check_out']))$open[$prId]=true;
    }
    $rows=[];
    foreach($d['prs']??[] as $p){
        if(empty($p['active']))continue;
        $prId=(int)($p['id']??0);if($prId<=0)continue;
        $status=(string)($p['status']??'offline');
        $present=!empty($open[$prId])||in_array($status,['online','busy','break'],true);
        $profile=is_array($p['profile']??null)?$p['profile']:[];
        $showcase=trim((string)($profile['showcase_photo']??''));
        $profilePhoto=trim((string)($profile['profile_photo']??''));
        $p['_showcase_present']=$present?1:0;
        $p['_showcase_status']=$status;
        $p['_showcase_has_photo']=($showcase!==''||$profilePhoto!=='')?1:0;
        $p['_showcase_using_profile']=$showcase===''&&$profilePhoto!==''?1:0;
        $rows[]=$p;
    }
    $rank=['online'=>0,'busy'=>1,'break'=>2,'offline'=>3];
    usort($rows,function($a,$b)use($rank){
        $ap=!empty($a['_showcase_present'])?0:1;$bp=!empty($b['_showcase_present'])?0:1;if($ap!==$bp)return $ap-$bp;
        $as=(string)($a['_showcase_status']??'offline');$bs=(string)($b['_showcase_status']??'offline');$ar=$rank[$as]??9;$br=$rank[$bs]??9;if($ar!==$br)return $ar-$br;
        $an=trim((string)($a['name']??''));$bn=trim((string)($b['name']??''));$cmp=strcasecmp($an,$bn);if($cmp!==0)return $cmp;
        return strcasecmp((string)($a['code']??''),(string)($b['code']??''));
    });
    return $rows;
}

function pr_showcase_ready_rows(array $d): array {
    return array_values(array_filter(pr_showcase_rows($d),fn($p)=>!empty($p['_showcase_present'])));
}

function pr_showcase_photo_url(array $p,string $prefix=''): string {
    if(empty($p['_showcase_has_photo']))return '';
    return $prefix.'public-pr-photo.php?id='.(int)($p['id']??0).'&mode=showcase&v='.rawurlencode((string)($p['profile']['updated_at']??''));
}
}
