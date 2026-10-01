<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/line-outbox.php';
function outbox_check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function op_notify(array &$data,?int $userId,string $role,string $type,string $message,array $meta=[]): void {$data['notifications'][]=['id'=>count($data['notifications']??[])+1,'user_id'=>$userId,'meta'=>$meta];}
$previous=getenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN');putenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN=synthetic-test-only');
try{
    $now=time();$uid='U'.str_repeat('1',32);
    $data=['_branch_context'=>['id'=>1],'meta'=>['active_branch_id'=>1],'roles'=>permission_default_roles(),'portal_settings'=>[],
        'users'=>[['id'=>1,'role'=>'admin','active'=>1,'branch_ids'=>[1],'line_user_id'=>'U'.str_repeat('2',32)],['id'=>2,'role'=>'staff','active'=>1,'branch_ids'=>[1]]],
        'reservations'=>[['id'=>1,'source'=>'customer','status'=>'waitlist','line_user_id'=>$uid,'guest_name'=>'Customer','date'=>date('Y-m-d'),'time'=>'19:00','party_size'=>2]],'notifications'=>[]];
    $reservation=$data['reservations'][0];mrbar_notify_booking_created($data,$reservation);mrbar_line_enqueue_booking($data,$reservation);
    outbox_check(count($data['line_outbox'])===2&&$data['line_outbox'][0]['kind']==='receipt','persist customer-first and linked eligible staff messages');
    outbox_check($data['reservations'][0]['line_customer_notification_status']==='queued'&&$data['reservations'][0]['line_staff_notification_summary']['not_linked']===1,'persist pending delivery and missing staff binding separately');
    $initial=$data['line_outbox'];$data['portal_settings']['line_customer_booking_template']='Changed template';mrbar_line_enqueue_booking($data,$reservation);
    outbox_check($data['line_outbox']===$initial,'re-enqueue never changes text, destination or retry key');
    $claim=mrbar_line_outbox_claim($data,$now);$otherClaim=mrbar_line_outbox_claim($data,$now);
    outbox_check($claim['id']!==$otherClaim['id']&&mrbar_line_outbox_claim($data,$now)===null,'concurrent claims cannot send the same live lease');
    outbox_check(mrbar_line_outbox_finish($data,$claim,['ok'=>false,'status'=>0],$now),'persist timeout after claim');
    outbox_check($data['line_outbox'][0]['status']==='retrying'&&$data['line_outbox'][0]['next_attempt_at']===$now+60,'network failure backs off without losing message');
    outbox_check(mrbar_line_outbox_claim($data,$now+59)===null,'do not retry before backoff');
    $retry=mrbar_line_outbox_claim($data,$now+60);
    outbox_check($retry['id']===$claim['id']&&$retry['message']===$claim['message']&&$retry['destination']===$claim['destination'],'retry uses exact original payload and key');
    outbox_check(!mrbar_line_outbox_finish($data,$claim,['ok'=>true,'status'=>200],$now+61),'ignore stale worker result after lease changes');
    $accepted=mrbar_line_http_result(['HTTP/1.1 409 Conflict','X-Line-Accepted-Request-ID: accepted-original','x-line-request-id: retry-request'],'{}');
    mrbar_line_outbox_finish($data,$retry,$accepted,$now+61);
    outbox_check($data['line_outbox'][0]['status']==='sent'&&$data['reservations'][0]['line_customer_notification_status']==='sent','409 accepted header resolves ambiguous timeout without resending');
    mrbar_line_outbox_finish($data,$otherClaim,['ok'=>false,'status'=>401],$now+61);
    outbox_check($data['line_outbox'][1]['status']==='failed'&&$data['reservations'][0]['line_staff_notification_summary']['failed']===1,'authentication errors stop automatic retries and appear in summary');
    $failedRetry=false;try{mrbar_line_outbox_retry($data,$otherClaim['id'],$now+62);}catch(RuntimeException){$failedRetry=true;}
    outbox_check($failedRetry,'manual retries also respect cooldown');
    mrbar_line_outbox_retry($data,$otherClaim['id'],$now+121);
    $reclaimed=mrbar_line_outbox_claim($data,$now+121);
    outbox_check($reclaimed['id']===$otherClaim['id']&&$reclaimed['attempts']===2,'manual retry keeps delivery identity and attempt count');
    $afterCrash=mrbar_line_outbox_claim($data,$now+242);
    outbox_check($afterCrash['id']===$reclaimed['id']&&$afterCrash['lease_token']!==$reclaimed['lease_token'],'expired worker lease can recover after process death');
    outbox_check(!mrbar_line_outbox_finish($data,$reclaimed,['ok'=>true,'status'=>200],$now+243),'crashed worker cannot overwrite replacement worker');
    $removed=$data;$removed['users'][0]['active']=0;$removed['line_outbox'][1]['lease_until']=0;
    outbox_check(mrbar_line_outbox_claim($removed,$now+500)===null&&$removed['line_outbox'][1]['error_code']==='recipient_permission_changed','stop queued sends to disabled staff');
    $changed=$data;$changed['users'][0]['line_user_id']='U'.str_repeat('9',32);$changed['line_outbox'][1]['lease_until']=0;
    outbox_check(mrbar_line_outbox_claim($changed,$now+500)===null&&$changed['line_outbox'][1]['error_code']==='recipient_changed','do not send personal details after LINE binding changes');
    $expired=$data;$expired['line_outbox'][1]['lease_until']=0;
    outbox_check(mrbar_line_outbox_claim($expired,$now+23*3600)===null&&$expired['line_outbox'][1]['status']==='expired','expire retries before LINE deduplication window ends');
    $exhausted=$data;$exhausted['line_outbox'][1]['lease_until']=0;$exhausted['line_outbox'][1]['attempts']=5;
    outbox_check(mrbar_line_outbox_claim($exhausted,$now+500)===null&&$exhausted['line_outbox'][1]['status']==='failed','bound attempts after repeated crashes or failures');
    $confirmed=$data;$confirmed['reservations'][0]['status']='confirmed';$confirmed['reservations'][0]['table_id']=5;$confirmed['reservations'][0]['requested_zone']='Main';$confirmed['tables']=[['id'=>5,'code'=>'VIP05','zone'=>'VIP']];
    mrbar_line_enqueue_confirmation($confirmed,$confirmed['reservations'][0]);
    $confirmed['line_outbox'][1]['status']='sent';
    outbox_check(count($confirmed['line_outbox'])===3&&str_contains($confirmed['line_outbox'][2]['message'],'VIP05 / VIP'),'enqueue customer confirmation with assigned table and zone');
    $cancelled=$confirmed;$cancelled['reservations'][0]['status']='cancelled';mrbar_line_outbox_claim($cancelled,$now+500);
    outbox_check($cancelled['line_outbox'][2]['status']==='cancelled','cancelled bookings cannot dispatch pending confirmations');
    $withdrawn=$confirmed;$withdrawn['reservations'][0]['status']='waitlist';mrbar_line_outbox_claim($withdrawn,$now+500);
    outbox_check($withdrawn['line_outbox'][2]['status']==='cancelled','withdrawn confirmations stop before sending');
    $disabled=$confirmed;$disabled['portal_settings']['line_customer_confirmation_enabled']='0';mrbar_line_outbox_claim($disabled,$now+500);
    outbox_check($disabled['line_outbox'][2]['error_code']==='confirmation_disabled','notification toggle is checked again at dispatch');
    $fresh=$data;$fresh['line_outbox']=[];$fresh['reservations'][0]['status']='confirmed';unset($fresh['reservations'][0]['line_customer_confirmation_status'],$fresh['reservations'][0]['line_customer_notification_status']);
    mrbar_line_enqueue_booking($fresh,$fresh['reservations'][0]);
    outbox_check($fresh['line_outbox'][0]['kind']==='confirmation'&&$fresh['line_outbox'][0]['receipt'],'auto-confirmed booking sends confirmation instead of contradictory pending receipt');
    $missingToken=$data;$missingToken['line_outbox']=[];putenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN');mrbar_line_enqueue_booking($missingToken,$reservation);
    outbox_check($missingToken['line_outbox'][0]['status']==='not_configured','missing token retains unsent payload for later configuration');
    $legacy=db_default();$legacy['line_outbox']=$initial;$migrated=db_migrate_array($legacy);
    outbox_check(db_branch_view_migrated($migrated,1)['line_outbox']===$initial,'branch migration preserves outbox without schema upgrade');
    $otherBranch=db_empty_branch_data();$otherBranch['line_outbox']=[array_replace($initial[0],['branch_id'=>2])];$migrated['branch_data']['2']=$otherBranch;
    $migrated['branches'][]=['id'=>2,'active'=>1,'name'=>'Second branch'];
    outbox_check(count(db_branch_view_migrated($migrated,1)['line_outbox'])===2,'branch view never exposes other branch queue');
    outbox_check(count(db_branch_view_migrated($migrated,2)['line_outbox'])===1,'second branch keeps its own queue');
    $branchView=db_branch_view_migrated($migrated,1);$branchView['line_outbox'][0]['status']='sent';$merged=db_merge_branch_view($migrated,$branchView,1);
    outbox_check($merged['branch_data']['2']['line_outbox'][0]['status']==='queued','saving one branch does not overwrite another branch delivery');
    $multiple=mrbar_line_http_result(['HTTP/1.1 409 Conflict','x-line-accepted-request-id: stale','HTTP/1.1 401 Unauthorized'],'');
    outbox_check($multiple['accepted_request_id']===''&&!mrbar_line_push_accepted($multiple),'response headers are scoped to final HTTP response');
    outbox_check(!mrbar_line_push_accepted(mrbar_line_http_result(['HTTP/1.1 409 Conflict'],'{}')),'unverified conflict is not marked sent');
    echo "LINE outbox regression passed.\n";
}finally{putenv($previous===false?'MRBAR_LINE_CHANNEL_ACCESS_TOKEN':'MRBAR_LINE_CHANNEL_ACCESS_TOKEN='.$previous);}
