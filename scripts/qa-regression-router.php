<?php
declare(strict_types=1);
$root=(string)getenv('MRBAR_QA_ROOT');
if($root===''||realpath($root)!==realpath(getcwd())){http_response_code(404);exit;}
$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(!str_starts_with((string)$route,'/__qa/'))return false;
require_once $root.'/app/db.php';
date_default_timezone_set('Asia/Bangkok');
session_start();
if($route==='/__qa/init'){
    $data=db_default();
    $data['users']=[['id'=>1,'username'=>'qa_admin','display_name'=>'QA Admin','role'=>'admin','active'=>1,'super_admin'=>1,'branch_ids'=>[1],'password_hash'=>password_hash('QA-local-only',PASSWORD_DEFAULT),'trusted_devices'=>[]]];
    $data['tables']=[['id'=>1,'code'=>'QA-VIP01','zone'=>'VIP','active'=>1,'status'=>'available','capacity'=>6]];
    $data['reservations']=[['id'=>1,'guest_name'=>'ทดสอบชื่อยาวสำหรับหน้ามือถือและการตรวจสอบสลิป','phone'=>'0812345678','party_size'=>2,'date'=>date('Y-m-d'),'time'=>'19:00','status'=>'waitlist','payment_mode'=>'slip_manual','deposit_status'=>'pending_review','deposit_amount'=>500,'line_customer_notification_status'=>'failed','line_staff_notification_summary'=>['eligible'=>5,'sent'=>0,'not_linked'=>5,'failed'=>0]]];
    $data['notifications']=array_map(static fn($id)=>['id'=>$id,'user_id'=>1,'role'=>'admin','read_at'=>null,'type'=>'reservation_new','message'=>'แจ้งเตือนทดสอบรายการ '.$id,'created_at'=>date('c')],range(1,7));
    $data=db_migrate_array($data);
    $data['portal_settings']['line_customer_oa_url']='@qa-test';
    file_put_contents($root.'/storage/data.php',db_payload($data));
    echo 'initialized';exit;
}
if($route==='/__qa/customer'){
    unset($_SESSION['user']);
    $_SESSION['customer_line_auth']=['line_user_id'=>'U'.str_repeat('1',32),'display_name'=>'QA Customer','authenticated_at'=>time(),'friend_status'=>'friend','friend_checked_at'=>time()];
    echo 'customer';exit;
}
if($route==='/__qa/admin'){
    $data=db_load_global();$user=$data['users'][0];
    $_SESSION['user']=['id'=>1,'username'=>$user['username'],'display_name'=>$user['display_name'],'role'=>'admin','super_admin'=>true,'branch_ids'=>[1],'active'=>1,'credential_version'=>hash('sha256',$user['password_hash'])];
    echo 'admin';exit;
}
if($route==='/__qa/device'){
    unset($_SESSION['user']);$token=str_repeat('a',64);
    db_mutate_global(static function(array $data)use($token):array{
        $data['users'][0]['trusted_devices']=[['token_hash'=>hash('sha256',$token),'pin_hash'=>password_hash('123456',PASSWORD_DEFAULT),'created_at'=>date('c'),'expires_at'=>date('c',time()+86400),'failed_attempts'=>0,'locked_until'=>null]];
        return $data;
    });
    setcookie('mrbar_trusted_device',$token,['path'=>'/','httponly'=>true,'samesite'=>'Lax']);echo 'device';exit;
}
if($route==='/__qa/unlock'){
    db_mutate_global(static function(array $data):array{$data['users'][0]['trusted_devices'][0]['locked_until']=null;return $data;});
    echo 'unlocked';exit;
}
if($route==='/__qa/expire-device'){
    unset($_SESSION['user']);
    db_mutate_global(static function(array $data):array{
        $data['branch_data']['1']['settings']['trusted_device_days']='1';
        $data['users'][0]['trusted_devices'][0]['created_at']=date('c',time()-2*86400);
        return $data;
    });
    echo 'expired';exit;
}
if($route==='/__qa/outbox-worker'){
    require_once $root.'/app/line-outbox.php';
    $previous=getenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN');putenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN=synthetic-worker-only');
    $calls=[];$status=(int)($_GET['status']??503);
    try{
        $processed=mrbar_line_outbox_run(1,1,static function(string $destination,string $message,string $key)use(&$calls,$status):array{
            $calls[]=['key'=>$key,'message'=>$message];
            return ['ok'=>$status===200,'status'=>$status,'accepted_request_id'=>$status===409?'synthetic-accepted':''];
        });
    }finally{putenv($previous===false?'MRBAR_LINE_CHANNEL_ACCESS_TOKEN':'MRBAR_LINE_CHANNEL_ACCESS_TOKEN='.$previous);}
    header('Content-Type: application/json');echo json_encode(['processed'=>$processed,'calls'=>$calls]);exit;
}
if($route==='/__qa/outbox-due'){
    db_mutate_global(static function(array $data):array{
        foreach($data['branch_data']['1']['line_outbox'] as &$job)$job['next_attempt_at']=time()-1;
        unset($job);$data['portal_settings']['line_customer_confirmation_template']='Changed after enqueue';return $data;
    });
    echo 'due';exit;
}
if($route==='/__qa/state'){
    $data=db_load();header('Content-Type: application/json');
    $device=$data['users'][0]['trusted_devices'][0]??[];
    echo json_encode(['reservations'=>$data['reservations'],'line_outbox'=>$data['line_outbox']??[],'checkins'=>$data['checkins'],'tables'=>$data['tables'],'notifications'=>$data['notifications'],'device_attempts'=>$device['failed_attempts']??0,'device_lock'=>$device['locked_until']??null]);exit;
}
http_response_code(404);
