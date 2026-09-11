<?php
declare(strict_types=1);

if (!function_exists('reservation_sales_options')) {
function reservation_sales_options(array $d): array {
    $rows=[];
    foreach($d['employees']??[] as $e){
        if(empty($e['active'])||(string)($e['position']??'')!=='sales')continue;
        $employeeId=(int)($e['id']??0);if($employeeId<=0)continue;
        $name=function_exists('workforce_employee_display_name')?workforce_employee_display_name($e):trim((string)($e['name']??''));
        $code=trim((string)($e['code']??''));
        if($name==='')$name=$code!==''?$code:('Sales '.$employeeId);
        $photo=(string)($e['profile']['profile_photo']??'');
        $rows[]=[
            'employee_id'=>$employeeId,
            'code'=>$code,
            'name'=>$name,
            'label'=>$name.($code!==''?' · '.$code:''),
            'has_photo'=>$photo!=='' && strpos(str_replace('\\','/',$photo),'storage/employee-media/'.$employeeId.'/')===0,
            'photo_url'=>'../sales-photo.php?employee_id='.$employeeId,
        ];
    }
    usort($rows,function($a,$b){$n=strcasecmp((string)$a['name'],(string)$b['name']);return $n!==0?$n:strcasecmp((string)$a['code'],(string)$b['code']);});
    return $rows;
}

function reservation_sales_selection(array $d,string $raw): array {
    $raw=trim($raw);
    if($raw==='none')return [
        'sales_selection'=>'none','sales_pr_id'=>null,'sales_employee_id'=>null,
        'sales_code_snapshot'=>'','sales_name_snapshot'=>'ไม่มีเซล / มาครั้งแรก','sales_role_snapshot'=>'none'
    ];
    if($raw===''||!ctype_digit($raw))throw new RuntimeException('กรุณาเลือก Sales / เซล หรือเลือก ไม่มีเซล / มาครั้งแรก');
    $wanted=(int)$raw;
    foreach(reservation_sales_options($d) as $row){
        if((int)$row['employee_id']!==$wanted)continue;
        return [
            'sales_selection'=>'sales','sales_pr_id'=>null,'sales_employee_id'=>(int)$row['employee_id'],
            'sales_code_snapshot'=>(string)$row['code'],'sales_name_snapshot'=>(string)$row['name'],'sales_role_snapshot'=>'sales'
        ];
    }
    throw new RuntimeException('Sales / เซล ที่เลือกไม่พร้อมใช้งาน กรุณาเลือกใหม่');
}

function reservation_sales_label(array $reservation): string {
    $mode=(string)($reservation['sales_selection']??'');
    if($mode==='none')return 'ไม่มีเซล / มาครั้งแรก';
    $name=trim((string)($reservation['sales_name_snapshot']??''));$code=trim((string)($reservation['sales_code_snapshot']??''));
    /* Backward compatibility for v1.27.7 reservations that attributed a PR as Sales. */
    if($mode==='pr'||!empty($reservation['sales_pr_id'])){
        $legacy=trim($code.' · '.$name,' ·');return 'PR เดิม'.($legacy!==''?' · '.$legacy:' #'.(int)($reservation['sales_pr_id']??0));
    }
    if($name!==''&&$code!=='')return $code.' · '.$name;
    if($name!=='')return $name;
    if($code!=='')return $code;
    return 'ไม่ระบุ (รายการเดิม)';
}

function reservation_sales_initials(string $name): string {
    $name=trim($name);if($name==='')return 'SA';
    if(function_exists('mb_substr')&&function_exists('mb_strtoupper'))return mb_strtoupper(mb_substr($name,0,2,'UTF-8'),'UTF-8');
    return strtoupper(substr($name,0,2));
}
}
