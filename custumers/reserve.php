<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/reservation-sales.php';
require_once __DIR__.'/../app/floor-plan.php';
require_once __DIR__.'/../app/customer-integrations.php';
require_once __DIR__.'/../app/reservation-payments.php';
require_once __DIR__.'/../app/reservation-request.php';

$d=db_load();
$branch=branch_current($d);
$publicBranchSlug=(string)($branch['slug']??'');
$branchHome=branch_slug_path($branch);
$shop=(string)($d['settings']['shop_name']??'MR BAR');
$msg='';$err='';
$preferredTableId=max(0,(int)($_GET['table_id']??$_POST['preferred_table_id']??0));
$preferredTable=null;
if($preferredTableId>0){
    foreach($d['tables']??[] as $table){
        if((int)($table['id']??0)===$preferredTableId&&!empty($table['active'])){$preferredTable=$table;break;}
    }
    if(!$preferredTable)$preferredTableId=0;
}
$preferredZone=trim((string)($_GET['zone']??($preferredTable['zone']??'')));
$preferredParty=max(1,min(30,(int)($_GET['party']??$_POST['party_size']??2)));
$embed=(string)($_GET['embed']??'')==='1';
$lineIdentity=mrbar_customer_line_identity();
$paymentConfig=mrbar_reservation_payment_config($d);
$lineLoginConfig=mrbar_line_login_config();
$lineLiffReady=$lineLoginConfig['ready'];
$customerOa=mrbar_line_customer_oa_config();
$lineBookingAccess=mrbar_customer_line_booking_access($lineIdentity,$customerOa['configured']);
$returnParams=[
    'embed'=>$embed?'1':'0',
    'public_branch'=>preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/i',$publicBranchSlug)?$publicBranchSlug:'',
    'table_id'=>$preferredTableId,
    'zone'=>substr($preferredZone,0,80),
    'party'=>$preferredParty,
];
$reservationReturn=branch_public_base().'/custumers/reserve.php?'.http_build_query($returnParams);
$requestKey=$_SERVER['REQUEST_METHOD']==='POST'?(string)($_POST['booking_request_key']??''):bin2hex(random_bytes(16));
$requestLimits=reservation_request_limits($d);
$preferredParty=min($preferredParty,$requestLimits['party']);
$receipt=reservation_request_receipt($d,(string)($_GET['receipt']??''),(string)($lineIdentity['line_user_id']??''));
if($receipt)$msg=reservation_receipt_message($receipt);
$lineAuthUrl=$lineLiffReady?'https://liff.line.me/'.rawurlencode($lineLoginConfig['liff_id']).'/?'.http_build_query(['flow'=>'customer_booking','return_to'=>$reservationReturn]):'';

if(($d['settings']['reservation_enabled']??'1')!=='1'){
    http_response_code(503);
    $err='ระบบจองโต๊ะยังไม่เปิดให้บริการ';
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$err===''){
    csrf_check();
    $uploadedSlipPath='';
    $reservationCommitted=false;
    if(!$lineBookingAccess['allowed']){
        http_response_code($lineBookingAccess['reason']==='login_required'?401:403);
        $err=match($lineBookingAccess['reason']){
            'oa_not_configured'=>'ร้านยังไม่ได้ตั้งค่าลิงก์ LINE OA จึงยังรับคำจองผ่าน LINE ไม่ได้ กรุณาติดต่อร้าน',
            'friend_required'=>'กรุณาเพิ่มเพื่อน LINE OA และตรวจสอบสถานะให้เรียบร้อยก่อนส่งคำขอจอง',
            'friendship_stale'=>'สถานะเพื่อน LINE OA หมดอายุ กรุณาตรวจสอบ LINE อีกครั้งก่อนส่งคำขอจอง',
            default=>'กรุณาเข้าสู่ระบบด้วย LINE ก่อนส่งคำขอจองโต๊ะ',
        };
    }else{
        try{
            if(!preg_match('/^[a-f0-9]{32}$/D',$requestKey))throw new RuntimeException('แบบฟอร์มหมดอายุ กรุณาเปิดหน้าจองใหม่');
            if(reservation_request_receipt($d,$requestKey,(string)$lineIdentity['line_user_id'])){
                header('Location: '.$reservationReturn.'&receipt='.$requestKey,true,303);exit;
            }
            $fields=reservation_validate_request($_POST,$d);
            $name=$fields['guest_name'];$phone=$fields['phone'];$date=$fields['date'];$time=$fields['time'];
            $party=$fields['party_size'];$zone=$fields['zone'];$note=$fields['note'];
            $salesRaw=trim((string)($_POST['sales_employee_id']??''));
            $requestedTableId=max(0,(int)($_POST['preferred_table_id']??0));
            $requestedTable=null;
            if($requestedTableId>0){
                foreach($d['tables']??[] as $table){
                    if((int)($table['id']??0)===$requestedTableId&&!empty($table['active'])){$requestedTable=$table;break;}
                }
                if(!$requestedTable)throw new RuntimeException('โต๊ะที่เลือกไม่พร้อมใช้งานแล้ว กรุณาเลือกใหม่');
                if($zone==='')$zone=(string)($requestedTable['zone']??'');
            }
            if($requestedTableId>0){
                $zoneRule=fp_zone_for_table($d,$requestedTableId);
                if($zoneRule&&!fp_zone_booking_allowed($zoneRule,$date,$time))throw new RuntimeException('โซนนี้ไม่เปิดรับจองในวันหรือเวลาที่เลือก กรุณาเปลี่ยนเวลา/โต๊ะ หรือเลือกโซนอื่น');
            }
            $depositAmount=0;$depositStatus='not_required';$verificationResult=['status'=>'not_required'];
            $paymentMode=$paymentConfig['mode'];
            if($paymentConfig['required']){
                if(!$paymentConfig['ready'])throw new RuntimeException('ร้านยังตั้งค่ารายละเอียดมัดจำไม่ครบ กรุณาติดต่อร้าน หรือปิดการบังคับมัดจำชั่วคราว');
                if($paymentConfig['terms']!==''&&($_POST['accept_reservation_terms']??'')!=='1')throw new RuntimeException('กรุณายอมรับเงื่อนไขการจองก่อนส่งคำขอ');
                if($paymentMode==='slip_api'&&$paymentConfig['api_ready']&&($_POST['accept_slip_verification']??'')!=='1')throw new RuntimeException('กรุณายืนยันการส่งข้อมูลสลิปให้บริการตรวจสอบก่อนส่งคำขอ');
                $depositAmount=mrbar_reservation_deposit_amount($paymentConfig,$party);
                $uploadedSlipPath=mrbar_reservation_slip_upload($_FILES['deposit_slip']??[],(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0));
                if($paymentMode==='slip_api'){
                    $slipAbsolute=mrbar_reservation_slip_absolute_path($uploadedSlipPath,(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0));
                    try{
                        $verificationResult=$slipAbsolute?mrbar_verify_reservation_slip($slipAbsolute,[
                            'branch_id'=>(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0),
                            'expected_amount'=>$depositAmount,'currency'=>(string)($d['settings']['currency']??'THB'),
                            'expected_receiver'=>$paymentConfig['receiver'],'reservation'=>['guest_name'=>$name,'phone'=>$phone,'date'=>$date,'time'=>$time,'party_size'=>$party],
                        ]):['status'=>'manual_review','reason'=>'ไม่สามารถอ่านไฟล์สลิปที่จัดเก็บได้'];
                    }catch(Throwable $verifyError){
                        error_log('Reservation slip verifier failed');
                        $verificationResult=['status'=>'manual_review','reason'=>'ตรวจสอบกับ Provider ไม่สำเร็จ'];
                    }
                    if(($verificationResult['status']??'')==='rejected')throw new RuntimeException('ระบบตรวจสลิปไม่ผ่าน กรุณาตรวจยอดและบัญชีผู้รับ แล้วลองส่งใหม่');
                    if(($verificationResult['status']??'')==='verified'&&(($verificationResult['duplicate']??false)===true||($verificationResult['amount_match']??true)===false||($verificationResult['receiver_match']??true)===false))throw new RuntimeException('สลิปนี้มียอดหรือบัญชีผู้รับไม่ตรง หรือเป็นรายการซ้ำ กรุณาตรวจสอบแล้วส่งใหม่');
                    $depositStatus=mrbar_reservation_slip_is_verified($verificationResult,$depositAmount)?'verified':'pending_review';
                }else $depositStatus='pending_review';
            }
            $autoConfirm=$paymentConfig['required']&&$paymentMode==='slip_api'&&$paymentConfig['confirmation_mode']==='api_auto'&&$depositStatus==='verified';
            $slipConsentAccepted=$paymentMode==='slip_api'&&$paymentConfig['api_ready'];
            $createdReservationId=0;
            $duplicateRequest=false;
            $committedData=db_mutate(function($data)use($name,$phone,$date,$time,$party,$zone,$note,$salesRaw,$requestedTableId,$requestedTable,$lineIdentity,$paymentMode,$paymentConfig,$depositAmount,$depositStatus,$verificationResult,$uploadedSlipPath,$autoConfirm,$slipConsentAccepted,$requestKey,$fields,&$createdReservationId,&$duplicateRequest){
                $existing=reservation_request_receipt($data,$requestKey,(string)$lineIdentity['line_user_id']);
                if($existing){$createdReservationId=(int)$existing['id'];$duplicateRequest=true;return $data;}
                reservation_validate_request($fields,$data);
                $currentPayment=mrbar_reservation_payment_config($data);
                if(!$currentPayment['shop_reservations_enabled']||$currentPayment!==$paymentConfig)throw new RuntimeException('เงื่อนไขการจองมีการเปลี่ยนแปลง กรุณาเปิดหน้าจองใหม่');
                if($requestedTableId>0){
                    $latestTable=null;
                    foreach($data['tables']??[] as $table)if((int)($table['id']??0)===$requestedTableId&&!empty($table['active'])){$latestTable=$table;break;}
                    if(!$latestTable)throw new RuntimeException('โต๊ะที่เลือกไม่พร้อมใช้งานแล้ว กรุณาเลือกใหม่');
                    $rule=fp_zone_for_table($data,$requestedTableId);
                    if($rule&&!fp_zone_booking_allowed($rule,$date,$time))throw new RuntimeException('โซนนี้ไม่เปิดรับจองในวันหรือเวลาที่เลือก');
                    $requestedTable=$latestTable;
                }
                $sales=reservation_sales_selection($data,$salesRaw);
                $requestedCode=$requestedTable?(string)($requestedTable['code']??''):'';
                $requestNote=trim(($requestedCode!==''?'โต๊ะที่ขอ: '.$requestedCode.' · ':'').($zone?'โซนที่ต้องการ: '.$zone.' · ':'').$note);
                $reservation=[
                    'id'=>next_id($data['reservations']),
                    'guest_name'=>$name,'phone'=>$phone,'party_size'=>$party,'date'=>$date,'time'=>$time,
                    'table_id'=>null,'requested_table_id'=>$requestedTableId?:null,'requested_table_code'=>$requestedCode,
                    'requested_zone'=>$zone,'note'=>$requestNote,'status'=>$autoConfirm?'confirmed':'waitlist','source'=>'customer',
                    'line_user_id'=>$lineIdentity['line_user_id'],'created_at'=>date('c'),'updated_at'=>date('c'),'seated_checkin_id'=>null,
                    'request_key'=>$requestKey,
                    'payment_mode'=>$paymentMode,'deposit_amount'=>$depositAmount,'deposit_status'=>$depositStatus,
                    'deposit_slip_path'=>$uploadedSlipPath,'deposit_provider'=>(string)($verificationResult['provider']??''),
                    'deposit_verification_reason'=>substr((string)($verificationResult['reason']??''),0,240),
                    'deposit_verified_at'=>$depositStatus==='verified'?date('c'):null,
                    'slip_verification_consent_at'=>$slipConsentAccepted?date('c'):null,
                    'reservation_terms_snapshot'=>$paymentConfig['terms'],'reservation_terms_accepted_at'=>$paymentConfig['terms']!==''?date('c'):null,
                ];
                foreach($sales as $key=>$value)$reservation[$key]=$value;
                customer_crm_upsert_from_reservation($data,$reservation,true);
                $data['reservations'][]=$reservation;
                mrbar_notify_booking_created($data,$reservation);
                $createdReservationId=(int)$reservation['id'];
                $data['audit'][]=['at'=>date('c'),'action'=>'customer_reservation_created','sales_selection'=>$reservation['sales_selection'],'sales_employee_id'=>$reservation['sales_employee_id'],'requested_table_id'=>$requestedTableId?:null,'requested_table_code'=>$requestedCode,'line_user_id'=>$lineIdentity['line_user_id'],'payment_mode'=>$paymentMode,'deposit_status'=>$depositStatus];
                return $data;
            });
            if($duplicateRequest){
                if($uploadedSlipPath!==''){
                    $unusedSlip=mrbar_reservation_slip_absolute_path($uploadedSlipPath,(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0));
                    if($unusedSlip)@unlink($unusedSlip);
                }
                header('Location: '.$reservationReturn.'&receipt='.$requestKey,true,303);exit;
            }
            $reservationCommitted=true;
            $customerLineDelivery=['status'=>'not_configured'];
            foreach($committedData['reservations']??[] as $createdReservation){
                if((int)($createdReservation['id']??0)!==$createdReservationId)continue;
                try{
                    $customerLineDelivery=($createdReservation['status']??'')==='confirmed'
                        ?mrbar_send_customer_reservation_confirmation($committedData,$createdReservation)
                        :mrbar_send_customer_booking_confirmation($committedData,$createdReservation);
                    $delivery=mrbar_send_booking_line_notifications($committedData,$createdReservation);
                    db_mutate(function(array $data)use($createdReservation,$delivery,$customerLineDelivery):array{mrbar_apply_booking_line_delivery($data,$createdReservation,$delivery,$customerLineDelivery);if(($createdReservation['status']??'')==='confirmed')mrbar_apply_customer_reservation_confirmation_delivery($data,$createdReservation,$customerLineDelivery);return $data;});
                }catch(Throwable $lineError){error_log('LINE booking notification dispatch failed for reservation '.$createdReservationId);}
                break;
            }
            header('Location: '.$reservationReturn.'&receipt='.$requestKey,true,303);exit;
        }catch(Throwable $error){
            if(!$reservationCommitted&&$uploadedSlipPath!==''){
                $slipPath=mrbar_reservation_slip_absolute_path($uploadedSlipPath,(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0));
                if($slipPath)@unlink($slipPath);
            }
            $err=$error->getMessage();
        }
    }
}

$d=db_load();
$salesOptions=reservation_sales_options($d);
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($shop)?> — Reserve</title>
<link rel="stylesheet" href="assets/customer.css?v=1220">
<link rel="stylesheet" href="<?=h(branch_public_base())?>/assets/reservation-sales-v1278.css?v=1278">
<link rel="stylesheet" href="<?=h(branch_public_base())?>/assets/line-customer-oa-v14910.css?v=14915">
<style>
.reservebox{max-width:720px;margin:8vh auto;padding:34px}.reservegrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.reservegrid .wide{grid-column:1/-1}.flash{padding:14px;border:1px solid #ffffff22;border-radius:14px;margin:12px 0}.ok{border-color:#48ff9b66}.bad{border-color:#ff4d8066}.deposit-card{grid-column:1/-1;border:1px solid #245c59;border-radius:12px;padding:16px;background:#0c1e28}.deposit-card h2{font-size:18px;margin:0 0 8px}.deposit-card p{white-space:pre-line;line-height:1.65;color:#b7c9d7}.deposit-card img{display:block;width:min(100%,260px);max-height:300px;object-fit:contain;margin:14px auto;border-radius:8px;background:white;padding:8px}.deposit-card .deposit-amount{font-size:23px;font-weight:900;color:#5ce0b3}.deposit-card label{display:grid;gap:7px}.deposit-card input[type=file]{width:100%;box-sizing:border-box;border:1px solid #344b66;border-radius:7px;padding:12px;background:#0b1422;color:#eaf2ff}.deposit-terms{grid-column:1/-1;display:flex;gap:10px;align-items:flex-start;color:#bbc9da;font-size:12px;line-height:1.55}.deposit-terms input{margin-top:3px;accent-color:#11ae84}
.preferred-table{grid-column:1/-1;display:grid;grid-template-columns:48px 1fr auto;gap:12px;align-items:center;padding:13px 14px;border:1px solid #5ba9ff55;border-radius:16px;background:linear-gradient(135deg,#29408045,#7436b22f);box-shadow:0 12px 30px #0002}.preferred-table .icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#4d6eff22;border:1px solid #7d97ff4f;font-weight:950}.preferred-table small,.preferred-table b{display:block}.preferred-table small{font-size:9px;color:#90a0bd;letter-spacing:.08em}.preferred-table b{font-size:17px;margin-top:2px}.preferred-table em{font-style:normal;color:#6ce3b3;font-size:9px;font-weight:900}.preferred-table-note{grid-column:1/-1;color:#7e8da8;font-size:9px;margin-top:-5px}.line-booking-gate{max-width:460px;margin:24px auto 0;padding:22px;border:1px solid #344663;border-radius:14px;background:#111a2b;text-align:center}.line-booking-gate p{color:#aebbd1;line-height:1.6}.line-booking-gate a{display:block;padding:14px 16px;border-radius:10px;background:linear-gradient(100deg,#04a85a,#078b55);color:#fff;text-decoration:none;font-weight:900}.line-booking-gate small{display:block;margin-top:11px;color:#8d9ab2}
@media(max-width:650px){.reservebox{margin:20px auto;padding:22px 16px}.reservegrid{grid-template-columns:1fr}.reservegrid .wide{grid-column:auto}.preferred-table{grid-column:auto;grid-template-columns:42px 1fr}.preferred-table .icon{width:42px;height:42px}.preferred-table em{grid-column:1/-1}.line-booking-gate{padding:18px}}
</style>
</head>
<body class="<?=$embed?'embed-reserve':''?>">
<?php if(!$embed):?><div class="noise"></div><div class="ambient ambient-a"></div><?php endif;?>
<main class="reservebox glass">
<p class="eyebrow">MR BAR / RESERVATION</p><h1>จองโต๊ะล่วงหน้า</h1>
<p>ส่งคำขอจอง แล้วทีมงานจะจัดโต๊ะที่เหมาะสมให้คุณ</p>
<?php if($msg):?><div class="flash ok">✓ <?=h($msg)?></div><?php endif;?>
<?php if($err):?><div class="flash bad">⚠ <?=h($err)?></div><?php endif;?>
<?php if(!$lineBookingAccess['allowed']&&($d['settings']['reservation_enabled']??'1')==='1'):?>
<section class="line-booking-gate"><h2><?=h(match($lineBookingAccess['reason']){'login_required'=>'เข้าสู่ระบบก่อนจอง','oa_not_configured'=>'ยังจองผ่าน LINE ไม่ได้','friend_required'=>'เพิ่มเพื่อน OA ก่อนจอง','friendship_stale'=>'ตรวจสอบ LINE อีกครั้ง','default'=>'ยืนยัน LINE ก่อนจอง'})?></h2><p><?=h(match($lineBookingAccess['reason']){'login_required'=>'เข้าสู่ระบบ LINE เพื่อผูกคำจองกับบัญชีของคุณ และรับข้อความตอบกลับจากร้าน','oa_not_configured'=>'ร้านยังไม่ได้ตั้งค่าลิงก์ OA สำหรับรับข้อความคำจอง กรุณาติดต่อร้านก่อน','friend_required'=>'เพิ่มเพื่อน LINE OA และยืนยันสถานะก่อนส่งคำขอ เพื่อให้ร้านส่งข้อความยืนยันกลับหาคุณได้','friendship_stale'=>'การยืนยันสถานะเพื่อน OA ใช้ได้ 30 นาที กรุณาตรวจสอบใหม่ก่อนส่งคำขอ','default'=>'ยืนยันบัญชี LINE และสถานะเพื่อน OA ก่อนส่งคำขอจอง'})?></p><?php if($lineLiffReady&&$customerOa['configured']&&$lineBookingAccess['reason']!=='oa_not_configured'):?><a href="<?=h($lineAuthUrl)?>" data-line-booking-login><?=h($lineBookingAccess['reason']==='login_required'?'เข้าสู่ระบบด้วย LINE เพื่อจอง':'ตรวจสอบ / เพิ่มเพื่อน OA')?> →</a><?php elseif(!$lineLiffReady):?><small>ระบบ LINE Login ยังไม่พร้อมใช้งาน กรุณาติดต่อร้าน</small><?php endif;?><?php if($customerOa['configured']&&$lineBookingAccess['reason']!=='login_required'):?><a class="line-booking-oa-link" href="<?=h($customerOa['url'])?>" target="_blank" rel="noopener noreferrer">เปิด LINE OA เพื่อเพิ่มเพื่อน ↗</a><?php endif;?><small>ต้องยืนยันว่าเป็นเพื่อนกับ OA ก่อนส่งจอง · <a href="<?=h(branch_public_base())?>/custumers/privacy.php">อ่าน Privacy Notice</a></small></section>
<?php elseif(!$msg&&$lineBookingAccess['allowed']&&($d['settings']['reservation_enabled']??'1')==='1'):?>
<aside class="line-oa-reminder is-friend"><span class="line-oa-status-dot" aria-hidden="true"></span><div><strong>เชื่อมต่อ LINE OA แล้ว</strong><small>ตรวจสอบสถานะเพื่อนล่าสุดแล้ว · สามารถรับข้อความเกี่ยวกับคำจองทาง LINE</small></div></aside>
<?php if($paymentConfig['required']&&!$paymentConfig['ready']):?><div class="flash bad">ร้านยังตั้งค่ายอดมัดจำ QR หรือรายละเอียดการชำระไม่ครบ กรุณาติดต่อร้าน หรือปิดการบังคับมัดจำชั่วคราว</div><?php else:?>
<form method="post" enctype="multipart/form-data" action="<?=h($reservationReturn)?>" class="reservegrid">
<input type="hidden" name="booking_request_key" value="<?=h($requestKey)?>"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="public_branch" value="<?=h($publicBranchSlug)?>"><input type="hidden" name="preferred_table_id" value="<?=$preferredTableId?>">
<?php if($paymentConfig['required']):$shownDeposit=mrbar_reservation_deposit_amount($paymentConfig,$preferredParty);?><section class="deposit-card wide"><h2>มัดจำเพื่อส่งคำขอจอง</h2><div class="deposit-amount"><span data-reservation-deposit-total data-base="<?=(int)$paymentConfig['amount']?>" data-calculation="<?=h($paymentConfig['calculation'])?>"><?=number_format($shownDeposit)?></span> บาท</div><?php if($paymentConfig['calculation']==='per_person'):?><small>คิด <?=number_format((int)$paymentConfig['amount'])?> บาท × <span data-reservation-party-count><?=$preferredParty?></span> คน</small><?php endif;?><?php if($paymentConfig['qr_url']!==''):?><img src="<?=h($paymentConfig['qr_url'])?>" alt="QR Code สำหรับชำระมัดจำ" loading="lazy"><?php endif;?><p><?=h($paymentConfig['instructions'])?></p><small>แนบสลิปหลังโอน · JPG, PNG หรือ WEBP ไม่เกิน 5 MB<?php if($paymentConfig['mode']==='slip_api'):?> · ระบบจะตรวจอัตโนมัติเมื่อเชื่อม Provider แล้ว หากตรวจไม่ได้จะเข้าคิวให้พนักงานตรวจ<?php else:?> · ทีมงานจะตรวจสอบก่อนยืนยันโต๊ะ<?php endif;?></small></section><label class="wide">รูปสลิปโอนเงิน<input type="file" name="deposit_slip" accept="image/jpeg,image/png,image/webp" required></label><?php if($paymentConfig['terms']!==''):?><label class="deposit-terms"><input type="checkbox" name="accept_reservation_terms" value="1" required><span><?=nl2br(h($paymentConfig['terms']))?></span></label><?php endif;?><?php if($paymentConfig['mode']==='slip_api'&&$paymentConfig['api_ready']):?><label class="deposit-terms"><input type="checkbox" name="accept_slip_verification" value="1" required><span>ยินยอมให้ร้านส่งข้อมูลรายการจองและภาพสลิปให้ผู้ให้บริการภายนอกเพื่อตรวจสอบการชำระ · <a href="privacy.php" target="_blank" rel="noopener">อ่าน Privacy Notice</a></span></label><?php endif;?><?php endif;?>
<?php if($preferredTable):?><div class="preferred-table"><span class="icon">▦</span><div><small>โต๊ะที่เลือกจากแผนผัง</small><b><?=h((string)$preferredTable['code'])?> · <?=h((string)($preferredTable['zone']??'Main'))?></b><small><?=(int)($preferredTable['capacity']??4)?> ที่นั่ง · ระบบจะบันทึกเป็น “โต๊ะที่ขอ” จนกว่าทีมงานจะยืนยัน</small></div><em>SELECTED</em></div><?php endif;?>
<label>ชื่อ / Nickname<input name="guest_name" maxlength="120" value="<?=h($lineIdentity['display_name'])?>" required></label><label>เบอร์โทร<input name="phone" maxlength="30" <?=($d['settings']['reservation_require_phone']??'1')==='1'?'required':''?> inputmode="tel"></label>
<fieldset class="wide sales-card-field"><legend>เลือก Sales / เซลที่แนะนำร้าน</legend><p>จำชื่อไม่ได้ไม่เป็นไร ดูรูปแล้วเลือกคนที่คุ้นหน้าได้ · ลูกค้าใหม่ก็เลือก Sales ที่อยากให้ดูแลได้ ระบบจะผูก Booking นี้กับ Sales คนนั้น</p><div class="sales-card-grid">
<label class="sales-choice sales-choice-none"><input type="radio" name="sales_employee_id" value="none" required <?=$salesOptions?'':'checked'?>><span class="sales-choice-ui"><span class="sales-avatar sales-avatar-none">＋</span><span class="sales-copy"><b>ไม่มีเซล / มาครั้งแรก</b><small>เลือกข้อนี้ถ้ายังไม่รู้จัก Sales ของร้าน</small></span><span class="sales-check">✓</span></span></label>
<?php foreach($salesOptions as $sales):$salesName=(string)$sales['name'];?><label class="sales-choice"><input type="radio" name="sales_employee_id" value="<?=(int)$sales['employee_id']?>" required><span class="sales-choice-ui"><span class="sales-avatar"><?php if(!empty($sales['has_photo'])):?><img src="<?=h((string)$sales['photo_url'])?>" alt="<?=h($salesName)?>" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="sales-avatar-fallback" hidden><?=h(reservation_sales_initials($salesName))?></span><?php else:?><span class="sales-avatar-fallback"><?=h(reservation_sales_initials($salesName))?></span><?php endif;?></span><span class="sales-copy"><b><?=h($salesName)?></b><small><?=h((string)($sales['code']??''))?> · SALES</small></span><span class="sales-check">✓</span></span></label><?php endforeach;?>
</div><?php if(!$salesOptions):?><div class="sales-empty-note">ตอนนี้ยังไม่มีพนักงานตำแหน่ง Sales ที่ Active · ลูกค้ายังเลือก “ไม่มีเซล / มาครั้งแรก” ได้ตามปกติ</div><?php endif;?></fieldset>
<label>วันที่<input type="date" name="date" min="<?=date('Y-m-d')?>" max="<?=(new DateTimeImmutable('today'))->modify('+'.$requestLimits['days'].' days')->format('Y-m-d')?>" value="<?=date('Y-m-d')?>" required></label><label>เวลา<input type="time" name="time" value="19:00" required></label><label>จำนวนลูกค้า<input type="number" min="1" max="<?=$requestLimits['party']?>" name="party_size" value="<?=$preferredParty?>" required></label><label>โซนที่ต้องการ<input name="zone" value="<?=h($preferredZone)?>" placeholder="เช่น Main / VIP"></label><label class="wide">หมายเหตุ<input name="note" maxlength="200" placeholder="วันเกิด / โอกาสพิเศษ / ความต้องการเพิ่มเติม"></label>
<button class="neon-btn wide"><span>ส่งคำขอจองโต๊ะ</span><b>→</b></button><small class="privacy wide">ข้อมูลชื่อ เบอร์โทร วันเวลา Sales ที่เลือก และรายละเอียดที่กรอกจะใช้เพื่อรับคำขอจองและติดต่อกลับ · <a href="privacy.php" target="_blank" rel="noopener">อ่าน Privacy Notice / PDPA</a></small>
</form>
<?php endif;?>
<?php endif;?>
<?php if(!$embed):?><a class="back" href="<?=h($branchHome)?>">← กลับหน้าร้าน</a><?php endif;?>
</main>
<?php if($paymentConfig['required']):?><script>const depositTotal=document.querySelector('[data-reservation-deposit-total]');const partyInput=document.querySelector('input[name="party_size"]');if(depositTotal&&partyInput){const partyCount=document.querySelector('[data-reservation-party-count]');const refreshDeposit=()=>{const party=Math.max(1,Math.min(30,Number.parseInt(partyInput.value,10)||1));const base=Number.parseInt(depositTotal.dataset.base||'0',10);depositTotal.textContent=new Intl.NumberFormat('th-TH').format(depositTotal.dataset.calculation==='per_person'?base*party:base);if(partyCount)partyCount.textContent=String(party)};partyInput.addEventListener('input',refreshDeposit);refreshDeposit()}</script><?php endif;?>
<?php if($embed&&!$lineBookingAccess['allowed']&&$lineLiffReady&&$customerOa['configured']&&($d['settings']['reservation_enabled']??'1')==='1'):?><script>document.querySelector('[data-line-booking-login]')?.addEventListener('click',event=>{event.preventDefault();window.top.location.assign(event.currentTarget.href)});</script><?php endif;?>
</body></html>
