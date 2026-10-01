<?php
declare(strict_types=1);

function reservation_request_limits(array $data): array {
    $settings=$data['settings']??[];
    return ['party'=>max(1,min(30,(int)($settings['reservation_max_party']??20))),
        'days'=>max(0,min(365,(int)($settings['reservation_advance_days']??30)))];
}

function reservation_validate_request(array $input,array $data,bool $customer=true,?string $today=null): array {
    $name=trim((string)($input['guest_name']??''));
    $phone=trim((string)($input['phone']??''));
    $date=(string)($input['date']??'');$time=(string)($input['time']??'');
    $party=(string)($input['party_size']??'');
    $zone=trim((string)($input['zone']??''));$note=trim((string)($input['note']??''));
    if($name===''||($customer&&($data['settings']['reservation_require_phone']??'1')==='1'&&$phone===''))throw new RuntimeException('กรอกชื่อและเบอร์โทรให้ครบ');
    if(strlen($name)>360||strlen($phone)>30||strlen($zone)>240||strlen($note)>1500)throw new RuntimeException('ข้อมูลการจองยาวเกินกำหนด');
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$parsed||$parsed->format('Y-m-d')!==$date||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time))throw new RuntimeException('วันหรือเวลาจองไม่ถูกต้อง');
    $today=$today??date('Y-m-d');$limits=reservation_request_limits($data);
    if($date<$today)throw new RuntimeException('ไม่สามารถจองย้อนหลังได้');
    if($customer&&$date>(new DateTimeImmutable($today))->modify('+'.$limits['days'].' days')->format('Y-m-d'))throw new RuntimeException('จองล่วงหน้าได้ไม่เกิน '.$limits['days'].' วัน');
    $maxParty=$customer?$limits['party']:30;
    if(!preg_match('/^[1-9]\d?$/D',$party)||(int)$party>$maxParty)throw new RuntimeException('จำนวนลูกค้าต้องอยู่ระหว่าง 1 ถึง '.$maxParty.' คน');
    return ['guest_name'=>$name,'phone'=>$phone,'date'=>$date,'time'=>$time,'party_size'=>(int)$party,'zone'=>$zone,'note'=>$note];
}

function reservation_request_receipt(array $data,string $key,string $lineUserId): ?array {
    if(!preg_match('/^[a-f0-9]{32}$/D',$key)||$lineUserId==='')return null;
    foreach($data['reservations']??[] as $row){
        if(($row['request_key']??'')===$key&&($row['source']??'')==='customer'&&($row['line_user_id']??'')===$lineUserId)return $row;
    }
    return null;
}

function reservation_receipt_message(array $row): string {
    $status=(string)($row['status']??'');
    if($status==='cancelled')return 'รายการจองนี้ถูกยกเลิกแล้ว กรุณาติดต่อร้านหากต้องการจองใหม่';
    if($status==='no_show')return 'รายการจองนี้ถูกระบุว่าไม่มาตามนัด กรุณาติดต่อร้าน';
    if($status==='seated')return 'รับลูกค้าเข้าร้านแล้ว';
    if(in_array($status,['confirmed','booked'],true))$message='ยืนยันการจองแล้ว';
    elseif(($row['deposit_status']??'')==='verified')$message='ตรวจสอบมัดจำผ่านแล้ว · รอทีมงานยืนยันโต๊ะ';
    elseif(in_array($row['payment_mode']??'none',['slip_manual','slip_api'],true))$message='รับคำขอและสลิปแล้ว · ทีมงานจะตรวจสอบและติดต่อกลับ';
    else $message='รับคำขอจองแล้ว ทีมงานจะตรวจสอบและติดต่อกลับ';
    $status=$row['line_customer_notification_status']??'';
    if($status==='sent')$message.=' · ส่งข้อความไปยัง LINE แล้ว';
    elseif($status==='failed')$message.=' · ส่ง LINE ไม่สำเร็จ กรุณาติดต่อร้าน';
    elseif($status==='not_configured')$message.=' · ร้านยังไม่ได้ตั้งค่า LINE แจ้งเตือน';
    elseif($status==='not_linked')$message.=' · ไม่พบ LINE สำหรับส่งข้อความ';
    return $message;
}
