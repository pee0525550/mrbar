<?php
declare(strict_types=1);
require_once __DIR__.'/customer-integrations.php';

function mrbar_line_outbox_add(array &$data,array $reservation,string $kind,string $destination,string $message,int $userId=0,bool $receipt=false): string {
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/D',$destination))return 'not_linked';
    $branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
    $seed='mrbar-outbox:'.$branchId.':'.(int)$reservation['id'].':'.$kind.':'.($userId?:$destination);
    $id=mrbar_line_retry_key($seed);
    foreach($data['line_outbox']??[] as $job)if(($job['id']??'')===$id)return (string)$job['status'];
    $status=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!==''?'queued':'not_configured';
    // Persist the original recipient, text and retry key together; retries never re-render templates.
    $data['line_outbox'][]=['id'=>$id,'branch_id'=>$branchId,'reservation_id'=>(int)$reservation['id'],
        'kind'=>$kind,'user_id'=>$userId,'destination'=>$destination,'message'=>$message,'receipt'=>$receipt,
        'status'=>$status,'attempts'=>0,'created_at'=>time(),'next_attempt_at'=>time(),
        'first_attempt_at'=>0,'lease_until'=>0,'lease_token'=>'','http_status'=>0,'error_code'=>''];
    return $status;
}

function mrbar_line_enqueue_booking(array &$data,array $reservation): void {
    $customer=null;
    if(($reservation['source']??'')==='customer'){
        if(($reservation['status']??'')==='confirmed'){
            mrbar_line_enqueue_confirmation($data,$reservation,true);
        }elseif(($reservation['line_customer_notification_status']??'')!=='sent'){
            $template=mrbar_customer_line_message_templates($data)['booking_template'];
            $status=mrbar_line_outbox_add($data,$reservation,'receipt',(string)($reservation['line_user_id']??''),mrbar_render_customer_line_template($template,$data,$reservation));
            $customer=['status'=>$status];
        }
    }
    $accounts=[];foreach($data['users']??[] as $account)$accounts[(int)$account['id']]=$account;
    $deliveries=[];$event=mrbar_booking_notification_event_key($data,$reservation);
    foreach(mrbar_booking_notification_recipient_ids($data,$reservation) as $userId){
        $sent=false;
        foreach($data['notifications']??[] as $notice)if((int)($notice['user_id']??0)===$userId&&($notice['meta']['event_key']??'')===$event&&($notice['meta']['channels']['line']??'')==='sent'){$sent=true;break;}
        $deliveries[$userId]=['status'=>$sent?'sent':mrbar_line_outbox_add($data,$reservation,'staff',(string)($accounts[$userId]['line_user_id']??''),mrbar_booking_line_staff_message($reservation),$userId)];
    }
    mrbar_apply_booking_line_delivery($data,$reservation,$deliveries,$customer);
    mrbar_line_outbox_refresh($data,(int)$reservation['id']);
}

function mrbar_line_enqueue_confirmation(array &$data,array $reservation,bool $receipt=false): void {
    if(($reservation['source']??'')!=='customer'||!in_array($reservation['status']??'',['booked','confirmed'],true)||($reservation['line_customer_confirmation_status']??'')==='sent')return;
    $templates=mrbar_customer_line_message_templates($data);
    $status=$templates['confirmation_enabled']
        ?mrbar_line_outbox_add($data,$reservation,'confirmation',(string)($reservation['line_user_id']??''),mrbar_render_customer_line_template($templates['confirmation_template'],$data,$reservation),0,$receipt)
        :'disabled';
    mrbar_apply_customer_reservation_confirmation_delivery($data,$reservation,['status'=>$status]);
    if($receipt){
        foreach($data['reservations'] as &$row)if((int)$row['id']===(int)$reservation['id'])$row['line_customer_notification_status']=$status;
        unset($row);
    }
}

function mrbar_line_outbox_refresh(array &$data,int $reservationId): void {
    $reservation=null;
    foreach($data['reservations']??[] as $row)if((int)$row['id']===$reservationId){$reservation=$row;break;}
    if(!$reservation)return;
    $event=mrbar_booking_notification_event_key($data,$reservation);$delivery=[];
    foreach($data['notifications']??[] as $notice)if(($notice['meta']['event_key']??'')===$event)$delivery[(int)$notice['user_id']]=['status'=>(string)($notice['meta']['channels']['line']??'not_configured')];
    $receipt=null;
    foreach($data['line_outbox']??[] as $job){
        if((int)$job['reservation_id']!==$reservationId)continue;
        $result=['status'=>$job['status']==='processing'?'queued':$job['status'],'http_status'=>(int)$job['http_status']];
        if($job['kind']==='staff')$delivery[(int)$job['user_id']]=$result;
        elseif($job['kind']==='receipt')$receipt=$result;
        elseif($job['kind']==='confirmation'){
            mrbar_apply_customer_reservation_confirmation_delivery($data,$reservation,$result);
            if(!empty($job['receipt']))$receipt=$result;
        }
    }
    mrbar_apply_booking_line_delivery($data,$reservation,$delivery,$receipt);
    foreach($data['reservations'] as &$row)if((int)$row['id']===$reservationId){
        foreach(['queued','retrying','cancelled','expired'] as $status)$row['line_staff_notification_summary'][$status]=count(array_filter($delivery,static fn(array $item):bool=>$item['status']===$status));
    }
    unset($row);
}

function mrbar_line_outbox_invalid_reason(array $data,array $job): string {
    $reservation=null;foreach($data['reservations']??[] as $row)if((int)$row['id']===(int)$job['reservation_id']){$reservation=$row;break;}
    if(!$reservation||in_array($reservation['status']??'',['cancelled','no_show'],true))return 'reservation_closed';
    if($job['kind']==='receipt'&&in_array($reservation['status']??'',['booked','confirmed','seated'],true))return 'receipt_superseded';
    if($job['kind']==='confirmation'){
        if(!in_array($reservation['status']??'',['booked','confirmed'],true))return 'confirmation_withdrawn';
        if(!mrbar_customer_line_message_templates($data)['confirmation_enabled'])return 'confirmation_disabled';
    }
    if($job['kind']==='staff'){
        if(!in_array((int)$job['user_id'],mrbar_booking_notification_recipient_ids($data,$reservation),true))return 'recipient_permission_changed';
        foreach($data['users']??[] as $user)if((int)$user['id']===(int)$job['user_id'])return ($user['line_user_id']??'')===$job['destination']?'':'recipient_changed';
        return 'recipient_removed';
    }
    return ($reservation['line_user_id']??'')===$job['destination']?'':'recipient_changed';
}

function mrbar_line_outbox_claim(array &$data,int $now): ?array {
    foreach($data['line_outbox']??[] as $index=>$job){
        if(!in_array($job['status'],['queued','retrying','processing','not_configured'],true))continue;
        if($job['status']==='processing'&&(int)$job['lease_until']>$now)continue;
        if((int)$job['next_attempt_at']>$now)continue;
        $reason=mrbar_line_outbox_invalid_reason($data,$job);
        if($reason!==''||$now-(int)$job['created_at']>=23*3600||((int)$job['first_attempt_at']>0&&$now-(int)$job['first_attempt_at']>=23*3600)||(int)$job['attempts']>=5){
            $data['line_outbox'][$index]['status']=$reason!==''?'cancelled':((int)$job['attempts']>=5?'failed':'expired');
            $data['line_outbox'][$index]['error_code']=$reason!==''?$reason:((int)$job['attempts']>=5?'attempts_exhausted':'retry_window_expired');
            mrbar_line_outbox_refresh($data,(int)$job['reservation_id']);continue;
        }
        $job['status']='processing';$job['attempts']++;$job['first_attempt_at']=$job['first_attempt_at']?:$now;
        $job['lease_until']=$now+120;$job['lease_token']=bin2hex(random_bytes(16));$job['last_attempt_at']=$now;
        $data['line_outbox'][$index]=$job;mrbar_line_outbox_refresh($data,(int)$job['reservation_id']);
        return $job;
    }
    return null;
}

function mrbar_line_outbox_finish(array &$data,array $claim,array $result,int $now): bool {
    foreach($data['line_outbox']??[] as $index=>$job){
        if($job['id']!==$claim['id']||$job['status']!=='processing'||!hash_equals((string)$job['lease_token'],(string)$claim['lease_token']))continue;
        $accepted=mrbar_line_push_accepted($result);$http=(int)($result['status']??0);
        $retryable=$http===0||($http>=500&&$http<=599);
        $job['status']=$accepted?'sent':($retryable&&(int)$job['attempts']<5?'retrying':'failed');
        $job['http_status']=$http;$job['error_code']=$accepted?'':($retryable?'network_or_server':'line_http_'.$http);
        $job['request_id']=(string)(!empty($result['accepted_request_id'])?$result['accepted_request_id']:($result['request_id']??''));
        $job['next_attempt_at']=$now+60*(2**max(0,(int)$job['attempts']-1));$job['lease_until']=0;$job['lease_token']='';
        if($accepted)$job['sent_at']=$now;
        $data['line_outbox'][$index]=$job;mrbar_line_outbox_refresh($data,(int)$job['reservation_id']);return true;
    }
    return false;
}

function mrbar_line_outbox_retry(array &$data,string $id,int $now): void {
    foreach($data['line_outbox']??[] as $index=>$job){
        if($job['id']!==$id)continue;
        if(!in_array($job['status'],['failed','not_configured','retrying'],true))throw new RuntimeException('รายการนี้ส่งสำเร็จแล้วหรือกำลังส่งอยู่');
        if(mrbar_line_outbox_invalid_reason($data,$job)!=='')throw new RuntimeException('รายการจองหรือผู้รับเปลี่ยนไปแล้ว ไม่สามารถส่งข้อความเดิมได้');
        if($now-(int)$job['created_at']>=23*3600||((int)$job['first_attempt_at']>0&&$now-(int)$job['first_attempt_at']>=23*3600))throw new RuntimeException('เกินเวลาส่งซ้ำอย่างปลอดภัย กรุณาติดต่อลูกค้าโดยตรง');
        if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')throw new RuntimeException('กรุณาตั้งค่า LINE Messaging API ก่อน');
        if((int)$job['next_attempt_at']>$now&&(int)$job['attempts']>0)throw new RuntimeException('กรุณารอรอบส่งถัดไปก่อนลองใหม่');
        if((int)$job['attempts']>=5)throw new RuntimeException('ครบจำนวนครั้งที่ส่งแล้ว กรุณาติดต่อลูกค้าโดยตรง');
        $data['line_outbox'][$index]['status']='queued';$data['line_outbox'][$index]['next_attempt_at']=$now;
        mrbar_line_outbox_refresh($data,(int)$job['reservation_id']);return;
    }
    throw new RuntimeException('ไม่พบรายการส่ง LINE ในสาขานี้');
}

function mrbar_line_outbox_run(?int $branchId=null,int $limit=3,?callable $push=null): int {
    if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')return 0;
    $processed=0;$deadline=microtime(true)+18;
    $push=$push??static fn(string $id,string $text,string $key):array=>mrbar_line_push_text($id,$text,$key);
    while($processed<max(1,min(20,$limit))&&microtime(true)<$deadline){
        $claim=null;$claimedBranch=0;
        db_mutate_global(function(array $raw)use($branchId,&$claim,&$claimedBranch):array{
            foreach($raw['branches']??[] as $branch){
                $id=(int)$branch['id'];if(($branchId!==null&&$branchId!==$id)||empty($branch['active']))continue;
                $view=db_branch_view_migrated($raw,$id);
                $before=$view['line_outbox']??[];
                $view['line_outbox']=array_values(array_filter($before,static fn(array $job):bool=>!in_array($job['status'],['sent','failed','expired','cancelled'],true)||(int)($job['sent_at']??$job['last_attempt_at']??$job['created_at'])>time()-30*86400));
                $claim=mrbar_line_outbox_claim($view,time());
                if($before!==($view['line_outbox']??[]))$raw=db_merge_branch_view($raw,$view,$id);
                if($claim){$claimedBranch=$id;break;}
            }
            return $raw;
        });
        if(!$claim)break;
        try{$result=$push($claim['destination'],$claim['message'],$claim['id']);}
        catch(Throwable $error){$result=['ok'=>false,'status'=>0];}
        db_mutate_global(function(array $raw)use($claim,$claimedBranch,$result):array{
            $view=db_branch_view_migrated($raw,$claimedBranch);
            if(mrbar_line_outbox_finish($view,$claim,$result,time()))$raw=db_merge_branch_view($raw,$view,$claimedBranch);
            return $raw;
        });
        $processed++;
    }
    return $processed;
}

function mrbar_line_outbox_after_response(int $branchId): void {
    if($branchId<=0||isset($GLOBALS['mrbar_line_outbox_scheduled'])||mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')return;
    $data=db_load_global();$jobs=$data['branch_data'][(string)$branchId]['line_outbox']??[];
    $due=false;$now=time();
    foreach($jobs as $job)if(in_array($job['status'],['queued','retrying','processing','not_configured'],true)&&(int)$job['next_attempt_at']<=$now&&($job['status']!=='processing'||(int)$job['lease_until']<=$now)){$due=true;break;}
    if(!$due)return;
    $GLOBALS['mrbar_line_outbox_scheduled']=true;
    register_shutdown_function(static function()use($branchId):void{
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        ignore_user_abort(true);
        if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();
        try{mrbar_line_outbox_run($branchId);}
        catch(Throwable $error){error_log('LINE outbox worker failed; pending messages remain in storage');}
    });
}
