<?php
declare(strict_types=1);

if(!function_exists('customer_crm_phone_key')){
function customer_crm_phone_key(string $value): string {
    $digits=preg_replace('/\D+/','',$value)??'';
    if(strlen($digits)===11&&substr($digits,0,2)==='66')$digits='0'.substr($digits,2);
    return substr($digits,0,20);
}
function customer_crm_defaults(array $row=[]): array {
    $defaults=[
        'id'=>0,'full_name'=>'','nickname'=>'','phone'=>'','phone_key'=>'','email'=>'','line_user_id'=>'',
        'birthday'=>'','tier'=>'standard','vip'=>0,'tags'=>[],'notes'=>'','preferred_sales_employee_id'=>null,
        'preferred_pr_id'=>null,'active'=>1,'marketing_consent'=>0,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    $row=array_replace($defaults,$row);
    $row['id']=max(0,(int)$row['id']);$row['phone_key']=customer_crm_phone_key((string)($row['phone_key']?:$row['phone']));
    $row['tags']=is_array($row['tags'])?array_values(array_unique(array_filter(array_map('strval',$row['tags'])))):[];
    if(!in_array((string)$row['tier'],['standard','silver','gold','platinum'],true))$row['tier']='standard';
    $row['vip']=!empty($row['vip'])?1:0;$row['active']=!empty($row['active'])?1:0;
    $row['marketing_consent']=!empty($row['marketing_consent'])?1:0;
    return $row;
}
function customer_crm_find(array $d,int $id): ?array {
    foreach($d['customers']??[] as $row)if((int)($row['id']??0)===$id)return customer_crm_defaults($row);
    return null;
}
function customer_crm_find_index(array $d,int $id): ?int {
    foreach($d['customers']??[] as $index=>$row)if((int)($row['id']??0)===$id)return $index;
    return null;
}
function customer_crm_upsert_from_reservation(array &$d,array &$reservation): ?int {
    $phone=trim((string)($reservation['phone']??''));$phoneKey=customer_crm_phone_key($phone);
    $customerId=max(0,(int)($reservation['customer_id']??0));$index=$customerId?customer_crm_find_index($d,$customerId):null;
    if($index===null&&$phoneKey!==''){
        foreach($d['customers']??[] as $i=>$row)if(customer_crm_phone_key((string)($row['phone_key']??$row['phone']??''))===$phoneKey){$index=$i;break;}
    }
    if($index===null&&$phoneKey==='')return null;
    $now=date('c');$name=trim((string)($reservation['guest_name']??''));
    if($index===null){
        $customer=customer_crm_defaults([
            'id'=>next_id($d['customers']??[]),'full_name'=>$name,'nickname'=>$name,'phone'=>$phone,'phone_key'=>$phoneKey,
            'preferred_sales_employee_id'=>$reservation['sales_employee_id']??null,
            'created_at'=>(string)($reservation['created_at']??$now),'updated_at'=>$now
        ]);
        $d['customers'][]=$customer;$index=count($d['customers'])-1;
    }else{
        $customer=customer_crm_defaults($d['customers'][$index]);
        if($customer['full_name']===''&&$name!=='')$customer['full_name']=$name;
        if($customer['nickname']===''&&$name!=='')$customer['nickname']=$name;
        if($customer['phone']===''&&$phone!=='')$customer['phone']=$phone;
        if(empty($customer['preferred_sales_employee_id'])&&!empty($reservation['sales_employee_id']))$customer['preferred_sales_employee_id']=(int)$reservation['sales_employee_id'];
        $customer['updated_at']=$now;$d['customers'][$index]=$customer;
    }
    $customerId=(int)$d['customers'][$index]['id'];$reservation['customer_id']=$customerId;
    return $customerId;
}
function customer_crm_sync_reservations(array &$d): int {
    if(!isset($d['customers'])||!is_array($d['customers']))$d['customers']=[];
    $linked=0;
    foreach($d['reservations']??[] as &$reservation){
        $before=(int)($reservation['customer_id']??0);
        $id=customer_crm_upsert_from_reservation($d,$reservation);
        if($id!==null&&$before!==$id)$linked++;
    }unset($reservation);
    return $linked;
}
function customer_crm_metric_map(array $d): array {
    $empty=['reservations'=>0,'seated'=>0,'cancelled'=>0,'last_visit'=>'','last_sales'=>'','last_pr'=>''];$map=[];$reservationCustomers=[];
    foreach($d['customers']??[] as $customer)$map[(int)($customer['id']??0)]=$empty;
    foreach($d['reservations']??[] as $reservation){
        $customerId=(int)($reservation['customer_id']??0);if($customerId<=0)continue;
        if(!isset($map[$customerId]))$map[$customerId]=$empty;$reservationId=(int)($reservation['id']??0);if($reservationId>0)$reservationCustomers[$reservationId]=$customerId;
        $map[$customerId]['reservations']++;$status=(string)($reservation['status']??'');
        if($status==='seated')$map[$customerId]['seated']++;if(in_array($status,['cancelled','no_show'],true))$map[$customerId]['cancelled']++;
        $visit=trim((string)($reservation['date']??'').' '.(string)($reservation['time']??''));
        if($visit>$map[$customerId]['last_visit']){$map[$customerId]['last_visit']=$visit;$map[$customerId]['last_sales']=trim((string)($reservation['sales_name_snapshot']??''));}
    }
    $prNames=[];foreach($d['prs']??[] as $pr)$prNames[(int)($pr['id']??0)]=trim((string)($pr['name']??''));
    foreach($d['checkins']??[] as $checkin){
        $customerId=$reservationCustomers[(int)($checkin['reservation_id']??0)]??0;$prId=(int)($checkin['pr_id']??0);
        if($customerId>0&&$prId>0&&!empty($prNames[$prId]))$map[$customerId]['last_pr']=$prNames[$prId];
    }
    return $map;
}
function customer_crm_metrics(array $d,int $customerId): array {
    $empty=['reservations'=>0,'seated'=>0,'cancelled'=>0,'last_visit'=>'','last_sales'=>'','last_pr'=>''];
    return customer_crm_metric_map($d)[$customerId]??$empty;
}
function customer_crm_employee_label(array $d,?int $employeeId): string {
    if(!$employeeId)return '—';
    foreach($d['employees']??[] as $employee)if((int)($employee['id']??0)===$employeeId)return trim((string)($employee['code']??'').' · '.(string)($employee['name']??''),' ·');
    return '#'.$employeeId;
}
function customer_crm_pr_label(array $d,?int $prId): string {
    if(!$prId)return '—';
    foreach($d['prs']??[] as $pr)if((int)($pr['id']??0)===$prId)return trim((string)($pr['code']??'').' · '.(string)($pr['name']??''),' ·');
    return '#'.$prId;
}
}
