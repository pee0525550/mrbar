<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
if(!function_exists('db_load_global')){function db_load_global():array{return ['portal_settings'=>[]];}}
require __DIR__.'/../app/reservation-payments.php';

function payment_check(bool $condition,string $message):void{
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

putenv('MRBAR_SLIP_PROVIDER');putenv('MRBAR_SLIP_API_KEY');
$defaults=db_migrate_legacy_array(['settings'=>[]]);
payment_check(($defaults['settings']['reservation_deposit_enabled']??'')==='0'&&($defaults['settings']['reservation_payment_mode']??'')==='none','legacy installs default to existing no-payment booking flow');
$base=['settings'=>[
    'reservation_enabled'=>'1','reservation_deposit_enabled'=>'0','reservation_payment_mode'=>'slip_manual',
    'reservation_deposit_amount'=>'500','reservation_deposit_calculation'=>'per_booking',
    'reservation_payment_instructions'=>'Test PromptPay','reservation_payment_receiver'=>'0812345678',
    'reservation_confirmation_mode'=>'api_auto','reservation_payment_qr_url'=>'https://example.test/qr.png','reservation_terms'=>'',
]];
$config=mrbar_reservation_payment_config($base);
payment_check($config['mode']==='none'&&!$config['required']&&$config['shop_reservations_enabled'],'deposit emergency toggle bypasses payments without turning off reservations');
$base['settings']['reservation_deposit_enabled']='1';
$config=mrbar_reservation_payment_config($base);
payment_check($config['required']&&$config['mode']==='slip_manual'&&$config['ready'],'manual slip mode becomes ready with amount and payment instructions');
$base['settings']['reservation_payment_qr_url']='';
payment_check(!mrbar_reservation_payment_config($base)['ready'],'slip mode stays unavailable until a payment QR is configured');
$base['settings']['reservation_payment_qr_url']='https://example.test/qr.png';
payment_check(mrbar_reservation_deposit_amount($config,4)===500,'per-booking deposit calculation stays fixed');
$config['calculation']='per_person';
payment_check(mrbar_reservation_deposit_amount($config,4)===2000,'per-person deposit multiplies by the submitted party size');
$base['settings']['reservation_payment_mode']='slip_api';
$config=mrbar_reservation_payment_config($base);
payment_check(!$config['api_ready']&&!$config['auto_confirmation_ready'],'missing third-party verifier never enables automatic confirmation');
$base['settings']['reservation_payment_receiver']='';
payment_check(!mrbar_reservation_payment_config($base)['ready'],'API payment mode requires a configured recipient account');
$base['settings']['reservation_payment_receiver']='0812345678';
payment_check(mrbar_reservation_deposit_cleared(['payment_mode'=>'none'])&& !mrbar_reservation_deposit_cleared(['payment_mode'=>'slip_api','deposit_status'=>'pending_review'])&&mrbar_reservation_deposit_cleared(['payment_mode'=>'slip_manual','deposit_status'=>'verified']),'reservation confirmation requires verified payment when a slip is required');
$verified=['status'=>'verified','amount_match'=>true,'receiver_match'=>true,'duplicate'=>false,'amount'=>500];
payment_check(mrbar_reservation_slip_is_verified($verified,500),'API success requires matching amount, recipient and a unique transaction');
$verified['duplicate']=true;
payment_check(!mrbar_reservation_slip_is_verified($verified,500),'duplicate slip is not considered verified');
$verified['duplicate']=false;$verified['amount']=499;
payment_check(!mrbar_reservation_slip_is_verified($verified,500),'amount mismatch is not considered verified');
$verified['amount']=500;$verified['receiver_match']=false;
payment_check(!mrbar_reservation_slip_is_verified($verified,500),'recipient mismatch is not considered verified');
$valid=mrbar_reservation_payment_settings_from_input([
    'reservation_enabled'=>'1','reservation_deposit_enabled'=>'1','reservation_payment_mode'=>'slip_api',
    'reservation_confirmation_mode'=>'api_auto','reservation_deposit_amount'=>'350','reservation_deposit_calculation'=>'per_person',
    'reservation_payment_receiver'=>'0812345678','reservation_payment_instructions'=>'PromptPay details',
    'reservation_payment_qr_url'=>'https://example.test/qr.png','reservation_terms'=>'Terms',
]);
payment_check($valid['reservation_payment_mode']==='slip_api'&&$valid['reservation_deposit_amount']==='350'&&$valid['reservation_confirmation_mode']==='api_auto','validate and store payment and confirmation choices');
$badQr=false;
try{mrbar_reservation_payment_settings_from_input(['reservation_payment_mode'=>'slip_manual','reservation_payment_qr_url'=>'http://example.test/qr.png']);}catch(RuntimeException){$badQr=true;}
payment_check($badQr,'reject non-HTTPS external payment QR URLs');
$privateQr=false;
try{mrbar_reservation_payment_settings_from_input(['reservation_payment_mode'=>'slip_manual','reservation_payment_qr_url'=>'/storage/payment-qr.png']);}catch(RuntimeException){$privateQr=true;}
payment_check($privateQr,'reject private storage paths as public payment QR URLs');
payment_check(mrbar_reservation_slip_absolute_path('storage/reservation-slips/1/../../data.php',1)===null,'reject traversal in private slip paths');
echo "Reservation payment checks passed.\n";
