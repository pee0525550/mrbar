<?php
declare(strict_types=1);
require __DIR__.'/../app/reservation-request.php';
function request_check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$data=['settings'=>['reservation_max_party'=>'6','reservation_advance_days'=>'7']];
$input=['guest_name'=>'Customer','phone'=>'0812345678','date'=>'2026-10-01','time'=>'19:00','party_size'=>'2'];
request_check(reservation_validate_request($input,$data,true,'2026-10-01')['party_size']===2,'valid request');
foreach([['date','2026-02-30'],['date','2026-10-09'],['date','2026-09-30'],['time','24:00'],['time','19:99'],['party_size','0'],['party_size','7'],['party_size','2.5'],['phone',''],['guest_name',str_repeat('a',361)]] as [$key,$value]){
    $rejected=false;
    try{reservation_validate_request(array_replace($input,[$key=>$value]),$data,true,'2026-10-01');}catch(RuntimeException){$rejected=true;}
    request_check($rejected,'reject invalid '.$key.'='.$value);
}
request_check(reservation_validate_request(array_replace($input,['date'=>'2026-10-08','party_size'=>'6']),$data,true,'2026-10-01')['party_size']===6,'accept exact boundaries');
request_check(reservation_validate_request(array_replace($input,['phone'=>'']),['settings'=>['reservation_require_phone'=>'0']],true,'2026-10-01')['phone']==='','honor optional phone');
request_check(reservation_validate_request(array_replace($input,['party_size'=>'30','phone'=>'']),$data,false,'2026-10-01')['party_size']===30,'staff may record larger parties');
$key=str_repeat('a',32);$uid='U'.str_repeat('1',32);
$data['reservations']=[['id'=>1,'request_key'=>$key,'source'=>'customer','line_user_id'=>$uid]];
request_check(reservation_request_receipt($data,$key,$uid)['id']===1,'duplicate request finds original');
request_check(reservation_request_receipt($data,$key,'U'.str_repeat('2',32))===null,'receipt is private to its LINE identity');
request_check(reservation_request_receipt($data,'1',$uid)===null,'sequential IDs are not receipts');
request_check(str_contains(reservation_receipt_message(['payment_mode'=>'slip_manual','deposit_status'=>'pending_review']),'สลิป'),'pending deposit receipt');
request_check(str_contains(reservation_receipt_message(['status'=>'confirmed','line_customer_notification_status'=>'sent']),'LINE'),'persisted LINE delivery receipt');
request_check(str_contains(reservation_receipt_message(['status'=>'cancelled']),'ยกเลิก'),'receipt reflects later cancellation');
echo "Reservation request regression passed.\n";
