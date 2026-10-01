<?php
declare(strict_types=1);
require_once __DIR__.'/customer-integrations.php';

function mrbar_reservation_payment_config(?array $data=null): array {
    if($data===null)$data=db_load();
    $settings=is_array($data['settings']??null)?$data['settings']:[];
    $mode=(string)($settings['reservation_payment_mode']??'none');
    if(!in_array($mode,['none','slip_manual','slip_api'],true))$mode='none';
    $depositEnabled=(string)($settings['reservation_deposit_enabled']??'0')==='1';
    if(!$depositEnabled)$mode='none';
    $amount=max(0,(int)($settings['reservation_deposit_amount']??0));
    $instructions=trim((string)($settings['reservation_payment_instructions']??''));
    $qrUrl=trim((string)($settings['reservation_payment_qr_url']??''));
    $calculation=(string)($settings['reservation_deposit_calculation']??'per_booking');
    if(!in_array($calculation,['per_booking','per_person'],true))$calculation='per_booking';
    $confirmation=(string)($settings['reservation_confirmation_mode']??'staff');
    if(!in_array($confirmation,['staff','api_auto'],true))$confirmation='staff';
    $apiReady=mrbar_slip_verifier_adapter()!==null;
    $qrValid=mrbar_reservation_payment_qr_url_valid($qrUrl);
    $usesSlip=in_array($mode,['slip_manual','slip_api'],true);
    return [
        'deposit_enabled'=>$depositEnabled,'mode'=>$mode,'confirmation_mode'=>$confirmation,
        'amount'=>$amount,'calculation'=>$calculation,'instructions'=>$instructions,'qr_url'=>$qrValid?$qrUrl:'',
        'receiver'=>trim((string)($settings['reservation_payment_receiver']??'')),'terms'=>trim((string)($settings['reservation_terms']??'')),
        'ready'=>!$usesSlip||($amount>0&&$instructions!==''&&$qrValid&&$qrUrl!==''&&($mode!=='slip_api'||trim((string)($settings['reservation_payment_receiver']??''))!=='')),
        'api_ready'=>$apiReady,'auto_confirmation_ready'=>$mode==='slip_api'&&$apiReady,
        'required'=>$usesSlip,'shop_reservations_enabled'=>(string)($settings['reservation_enabled']??'1')==='1',
    ];
}

function mrbar_reservation_payment_qr_url_valid(string $qrUrl): bool {
    if($qrUrl==='')return true;
    if(str_starts_with($qrUrl,'/'))return !str_starts_with($qrUrl,'//')&&!preg_match('#^/storage(?:/|$)#i',$qrUrl)&&!str_contains($qrUrl,'..')&&!preg_match('/[\r\n<>"\\\\]/',$qrUrl);
    return filter_var($qrUrl,FILTER_VALIDATE_URL)!==false&&strtolower((string)parse_url($qrUrl,PHP_URL_SCHEME))==='https';
}

function mrbar_reservation_payment_settings_from_input(array $input): array {
    $enabled=isset($input['reservation_deposit_enabled'])?'1':'0';
    $mode=(string)($input['reservation_payment_mode']??'none');
    $confirmation=(string)($input['reservation_confirmation_mode']??'staff');
    $calculation=(string)($input['reservation_deposit_calculation']??'per_booking');
    if(!in_array($mode,['none','slip_manual','slip_api'],true))throw new RuntimeException('เลือกรูปแบบชำระเงินไม่ถูกต้อง');
    if(!in_array($confirmation,['staff','api_auto'],true))throw new RuntimeException('เลือกวิธียืนยันการจองไม่ถูกต้อง');
    if(!in_array($calculation,['per_booking','per_person'],true))throw new RuntimeException('เลือกรูปแบบคำนวณมัดจำไม่ถูกต้อง');
    $amountRaw=trim((string)($input['reservation_deposit_amount']??'0'));
    if($amountRaw!==''&&!preg_match('/^\d{1,7}$/',$amountRaw))throw new RuntimeException('จำนวนมัดจำต้องเป็นจำนวนเงินบาทเต็ม');
    $amount=max(0,min(1000000,(int)$amountRaw));
    $instructions=trim((string)($input['reservation_payment_instructions']??''));
    $receiver=trim((string)($input['reservation_payment_receiver']??''));
    $qrUrl=trim((string)($input['reservation_payment_qr_url']??''));
    $terms=trim((string)($input['reservation_terms']??''));
    if(strlen($instructions)>6000||strlen($receiver)>360||strlen($terms)>9000||strlen($qrUrl)>2000)throw new RuntimeException('ข้อความเงื่อนไขยาวเกินกำหนด');
    if(!mrbar_reservation_payment_qr_url_valid($qrUrl))throw new RuntimeException('ลิงก์ QR ต้องเป็น HTTPS หรือลิงก์ภายในเว็บไซต์');
    return [
        'reservation_enabled'=>isset($input['reservation_enabled'])?'1':'0',
        'reservation_deposit_enabled'=>$enabled,'reservation_payment_mode'=>$mode,
        'reservation_confirmation_mode'=>$confirmation,'reservation_deposit_amount'=>(string)$amount,
        'reservation_deposit_calculation'=>$calculation,'reservation_payment_receiver'=>$receiver,'reservation_payment_instructions'=>$instructions,
        'reservation_payment_qr_url'=>$qrUrl,'reservation_terms'=>$terms,
    ];
}

function mrbar_reservation_deposit_amount(array $config,int $partySize): int {
    $amount=max(0,(int)($config['amount']??0));
    return ($config['calculation']??'per_booking')==='per_person'?$amount*max(1,min(30,$partySize)):$amount;
}

function mrbar_reservation_payment_mode_label(string $mode): string {
    return ['none'=>'ไม่ต้องชำระ','slip_manual'=>'สแกนจ่าย · แนบสลิป','slip_api'=>'สแกนจ่าย · แนบสลิป · ตรวจ API'][$mode]??'ไม่ทราบรูปแบบ';
}

function mrbar_reservation_deposit_cleared(array $reservation): bool {
    return !in_array((string)($reservation['payment_mode']??'none'),['slip_manual','slip_api'],true)
        ||($reservation['deposit_status']??'')==='verified';
}

function mrbar_reservation_slip_upload(array $file,int $branchId): string {
    if($branchId<1)throw new RuntimeException('ไม่พบสาขาสำหรับจัดเก็บสลิป');
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE?'กรุณาแนบรูปสลิปโอนเงิน':'อัปโหลดสลิปไม่สำเร็จ');
    $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
    if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>5*1024*1024)throw new RuntimeException('สลิปต้องเป็นไฟล์รูปขนาดไม่เกิน 5 MB');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($extensions[$mime]))throw new RuntimeException('รองรับไฟล์สลิป JPG, PNG หรือ WEBP เท่านั้น');
    $image=@getimagesize($tmp);
    if(!is_array($image)||($image['mime']??'')!==$mime)throw new RuntimeException('ไฟล์ที่แนบไม่ใช่รูปภาพที่ถูกต้อง');
    if((int)($image[0]??0)>10000||(int)($image[1]??0)>10000||(int)($image[0]??0)*(int)($image[1]??0)>40000000)throw new RuntimeException('รูปสลิปมีความละเอียดสูงเกินกำหนด');
    $root=dirname(__DIR__).'/storage/reservation-slips';$dir=$root.'/'.$branchId;
    if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('ระบบจัดเก็บสลิปไม่พร้อม กรุณาติดต่อร้าน');
    if(!is_writable($dir))throw new RuntimeException('ระบบจัดเก็บสลิปเขียนไฟล์ไม่ได้ กรุณาติดต่อร้าน');
    $filename=bin2hex(random_bytes(16)).'.'.$extensions[$mime];$destination=$dir.'/'.$filename;
    if(!move_uploaded_file($tmp,$destination))throw new RuntimeException('บันทึกสลิปไม่สำเร็จ กรุณาลองอีกครั้ง');
    @chmod($destination,0660);
    return 'storage/reservation-slips/'.$branchId.'/'.$filename;
}

function mrbar_reservation_slip_absolute_path(string $relative,int $branchId): ?string {
    if(!preg_match('#^storage/reservation-slips/([1-9][0-9]*)/([a-f0-9]{32})\.(jpg|png|webp)$#D',$relative,$match)||(int)$match[1]!==$branchId)return null;
    $base=realpath(dirname(__DIR__).'/storage/reservation-slips/'.$branchId);
    $file=realpath(dirname(__DIR__).'/'.$relative);
    return $base&&$file&&is_file($file)&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)?$file:null;
}

function mrbar_reservation_slip_is_verified(array $result,int $expectedAmount): bool {
    return ($result['status']??'')==='verified'
        &&($result['amount_match']??false)===true
        &&($result['receiver_match']??false)===true
        &&($result['duplicate']??true)===false
        &&is_numeric($result['amount']??null)
        &&(int)round((float)$result['amount']*100)===$expectedAmount*100;
}
