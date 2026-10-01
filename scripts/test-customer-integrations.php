<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
if(!function_exists('branch_public_base')){function branch_public_base():string{return '';}}
if(!function_exists('next_id')){function next_id(array $rows):int{return $rows?max(array_map(static fn($row)=>(int)($row['id']??0),$rows))+1:1;}}
require __DIR__.'/../app/customer-integrations.php';
require __DIR__.'/../app/customer-crm.php';

function integration_check(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}
function op_notify(array &$data,?int $userId,string $role,string $type,string $message,array $meta=[]): void {
    $data['notifications'][]=['id'=>count($data['notifications']??[])+1,'user_id'=>$userId,'role'=>$role,'type'=>$type,'message'=>$message,'meta'=>$meta,'created_at'=>date('c'),'read_at'=>null];
}

$roles=permission_default_roles();
$roles['sales']=['name'=>'Sales','permissions'=>['reservations.view'=>0]];
$data=[
    'roles'=>$roles,'_branch_context'=>['id'=>1],'meta'=>['active_branch_id'=>1],
    'users'=>[
        ['id'=>1,'role'=>'admin','active'=>1,'branch_ids'=>[1]],
        ['id'=>2,'role'=>'staff','active'=>1,'branch_ids'=>[1]],
        ['id'=>3,'role'=>'sales','active'=>1,'branch_ids'=>[1]],
        ['id'=>4,'role'=>'staff','active'=>1,'branch_ids'=>[2]],
        ['id'=>5,'role'=>'staff','active'=>0,'branch_ids'=>[1]],
    ],
    'employees'=>[['id'=>44,'user_id'=>3,'position'=>'sales']],
];
$booking=['id'=>81,'sales_employee_id'=>44];
integration_check(mrbar_booking_notification_recipient_ids($data,$booking)===[1,2,3],'notify branch admins, reservation staff and assigned Sales only');
$booking['sales_employee_id']=null;
integration_check(mrbar_booking_notification_recipient_ids($data,$booking)===[1,2],'do not notify unrelated Sales when no Sales is assigned');
$lineData=$data;
$lineData['users'][0]['line_user_id']='U12345678901234567890123456789012';
$lineData['users'][2]['line_user_id']='U32345678901234567890123456789012';
$lineBooking=['id'=>81,'sales_employee_id'=>44,'guest_name'=>'Test Guest','date'=>'2026-09-29','time'=>'19:00','party_size'=>3,'source'=>'customer','line_user_id'=>'U92345678901234567890123456789012'];
putenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN=unit-test-token');
$noticeCount=mrbar_notify_booking_created($lineData,$lineBooking);
integration_check($noticeCount===3&&count($lineData['notifications'])===3&&$lineData['notifications'][0]['meta']['channels']['line']==='queued','create in-app booking notices and mark configured LINE delivery as queued');
$pushCalls=[];
$delivery=mrbar_send_booking_line_notifications($lineData,$lineBooking,static function(string $lineUserId,string $message,string $retryKey)use(&$pushCalls):array{
    $pushCalls[]=[$lineUserId,$message,$retryKey];
    return ['ok'=>$lineUserId==='U12345678901234567890123456789012','status'=>$lineUserId==='U12345678901234567890123456789012'?200:400];
});
integration_check(count($pushCalls)===2&&$delivery[1]['status']==='sent'&&$delivery[3]['status']==='failed'&&$delivery[2]['status']==='not_linked','send booking LINE notifications only to linked eligible users and keep per-user results');
$lineRetryUuid='/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
integration_check((bool)preg_match($lineRetryUuid,$pushCalls[0][2])&&$pushCalls[0][2]===mrbar_line_retry_key('mrbar-line:'.mrbar_booking_notification_event_key($lineData,$lineBooking).':1')&&str_contains($pushCalls[0][1],'Test Guest'),'booking pushes contain useful details and a stable LINE-compatible UUID retry key');
$invalidRetry=mrbar_line_post('/v2/bot/message/push',['to'=>$lineBooking['line_user_id'],'messages'=>[]],str_repeat('a',64));
integration_check(!$invalidRetry['ok']&&$invalidRetry['status']===0&&$invalidRetry['error']==='Invalid LINE retry key','reject malformed LINE retry keys before any network request');
$customerPushCalls=[];
$customerDelivery=mrbar_send_customer_booking_confirmation($lineData,$lineBooking,static function(string $lineUserId,string $message,string $retryKey)use(&$customerPushCalls):array{$customerPushCalls[]=[$lineUserId,$message,$retryKey];return ['ok'=>true,'status'=>200];});
integration_check(count($customerPushCalls)===1&&$customerDelivery['status']==='sent'&&$customerPushCalls[0][0]===$lineBooking['line_user_id'],'send a booking receipt to the customer LINE identity');
integration_check((bool)preg_match($lineRetryUuid,$customerPushCalls[0][2]),'customer booking receipt uses a LINE-compatible UUID retry key');
integration_check(str_contains($customerPushCalls[0][1],'ได้รับคำขอจองโต๊ะแล้ว')&&str_contains($customerPushCalls[0][1],'ยังไม่ใช่การยืนยันโต๊ะ'),'customer receipt clearly distinguishes a request from a confirmed booking');
$templateData=$lineData;$templateData['settings']=['shop_name'=>'MR BAR Test'];$templateData['portal_settings']=['line_customer_booking_template'=>'Hi {customer} #{reservation_id} {date} {time} {party_size} {table} {zone} {shop}'];
$templateBooking=array_merge($lineBooking,['requested_table_code'=>'T01','requested_zone'=>'VIP']);
$customTemplate=mrbar_customer_line_message_templates($templateData);
$renderedTemplate=mrbar_render_customer_line_template($customTemplate['booking_template'],$templateData,$templateBooking);
integration_check(str_contains($renderedTemplate,'Test Guest #81 29/09/2026 19:00 3 T01 VIP MR BAR Test'),'customer LINE templates support editable reservation placeholders');
$confirmedBooking=array_merge($templateBooking,['status'=>'confirmed']);$confirmationCalls=[];
$confirmationDelivery=mrbar_send_customer_reservation_confirmation($templateData,$confirmedBooking,static function(string $lineUserId,string $message,string $retryKey)use(&$confirmationCalls):array{$confirmationCalls[]=[$lineUserId,$message,$retryKey];return ['ok'=>true,'status'=>200];});
integration_check(count($confirmationCalls)===1&&$confirmationDelivery['status']==='sent'&&str_contains($confirmationCalls[0][1],'ยืนยันการจองแล้ว')&&(bool)preg_match($lineRetryUuid,$confirmationCalls[0][2]),'send a LINE confirmation when the customer booking is confirmed');
integration_check(mrbar_line_push_accepted(['ok'=>false,'status'=>409]),'treat LINE duplicate-accepted response as a successful idempotent delivery');
integration_check(mrbar_should_send_customer_reservation_confirmation($confirmedBooking,'waitlist','confirmed')&&!mrbar_should_send_customer_reservation_confirmation(array_merge($confirmedBooking,['line_customer_confirmation_status'=>'sent']),'confirmed','booked')&&mrbar_should_send_customer_reservation_confirmation(array_merge($confirmedBooking,['line_customer_confirmation_status'=>'failed']),'confirmed','confirmed'),'send confirmation on transition, avoid duplicates, and allow failed delivery retry');
$disabledTemplates=$templateData;$disabledTemplates['portal_settings']['line_customer_confirmation_enabled']='0';$disabledCalls=0;
$disabledDelivery=mrbar_send_customer_reservation_confirmation($disabledTemplates,$confirmedBooking,static function()use(&$disabledCalls):array{$disabledCalls++;return ['ok'=>true,'status'=>200];});
integration_check($disabledDelivery['status']==='disabled'&&$disabledCalls===0,'respect the customer confirmation notification toggle');
$confirmationData=$templateData;$confirmationData['reservations']=[$confirmedBooking];
mrbar_apply_customer_reservation_confirmation_delivery($confirmationData,$confirmedBooking,$confirmationDelivery);
integration_check(($confirmationData['reservations'][0]['line_customer_confirmation_status']??'')==='sent','persist customer confirmation delivery status');
$lineData['notifications']=[];
$lineData['reservations']=[$lineBooking];
foreach([1,2,3] as $noticeUserId)$lineData['notifications'][]=['user_id'=>$noticeUserId,'meta'=>['event_key'=>mrbar_booking_notification_event_key($lineData,$lineBooking),'channels'=>['line'=>'queued']]];
mrbar_apply_booking_line_delivery($lineData,$lineBooking,$delivery,$customerDelivery);
$savedStatuses=[];foreach($lineData['notifications'] as $notice)$savedStatuses[(int)$notice['user_id']]=$notice['meta']['channels']['line'];
integration_check($savedStatuses===[1=>'sent',2=>'not_linked',3=>'failed'],'persist LINE delivery outcome on matching in-app notifications');
integration_check(($lineData['reservations'][0]['line_customer_notification_status']??'')==='sent'&&($lineData['reservations'][0]['line_staff_notification_summary']['sent']??0)===1,'persist customer and staff LINE delivery summary on the reservation');
$retryCalls=0;
$retryDelivery=mrbar_send_booking_line_notifications($lineData,$lineBooking,static function()use(&$retryCalls):array{$retryCalls++;return ['ok'=>true,'status'=>200];});
integration_check($retryCalls===1&&$retryDelivery[1]['duplicate_skipped']===true&&$retryDelivery[3]['status']==='sent','avoid resending delivered alerts while allowing failed recipients to retry');
putenv('MRBAR_LINE_CHANNEL_ACCESS_TOKEN');
$result=mrbar_verify_reservation_slip('private/slip.jpg',['reservation_id'=>81]);
integration_check(($result['status']??'')==='manual_review','missing verifier credentials never auto-approve a slip');
$status=mrbar_customer_integrations_status();
integration_check(isset($status['slip_verifier']['ready'],$status['line_messaging']['ready'],$status['line_liff']['ready'],$status['mobile_push']['ready']),'integration status exposes readiness flags only');
$lineConfig=mrbar_line_login_config(['portal_settings'=>['line_liff_id'=>'2011745152-yvbsYagI','line_login_channel_id'=>'2011745152']]);
integration_check($lineConfig['ready']&&$lineConfig['liff_id']==='2011745152-yvbsYagI','admin-managed LINE Login IDs enable both staff and customer flow');
integration_check(mrbar_line_id_token_error_code('Invalid IdToken Audience.')==='line_channel_mismatch'&&mrbar_line_id_token_error_code('IdToken expired.')==='line_token_expired'&&mrbar_line_id_token_error_code('Invalid IdToken.')==='line_token_invalid','classify LINE ID Token verification errors without exposing token data');
$previousLoginChannel=getenv('MRBAR_LINE_LOGIN_CHANNEL_ID');putenv('MRBAR_LINE_LOGIN_CHANNEL_ID=2011745152');
$friendshipUserId='U12345678901234567890123456789012';
$friendshipResponses=[
    'verify'=>['status'=>200,'body'=>json_encode(['client_id'=>'2011745152','scope'=>'openid profile'])],
    'userinfo'=>['status'=>200,'body'=>json_encode(['sub'=>$friendshipUserId])],
    'friendship'=>['status'=>200,'body'=>json_encode(['friendFlag'=>true])],
];
$friendshipCalls=[];$friendshipCheck=mrbar_line_customer_friendship_result('user-access-token',$friendshipUserId,static function(string $endpoint,string $accessToken)use(&$friendshipCalls,$friendshipResponses):array{$friendshipCalls[]=[$endpoint,$accessToken];return $friendshipResponses[$endpoint];});
integration_check(($friendshipCheck['friend']??false)===true&&array_column($friendshipCalls,0)===['verify','userinfo','friendship']&&count(array_filter($friendshipCalls,static fn(array $call):bool=>$call[1]==='user-access-token'))===3,'verify customer LINE access token, identity and OA friendship server-side');
$wrongChannelResponses=$friendshipResponses;$wrongChannelResponses['verify']=['status'=>200,'body'=>json_encode(['client_id'=>'999999','scope'=>'openid profile'])];
$wrongChannel=mrbar_line_customer_friendship_result('user-access-token',$friendshipUserId,static fn(string $endpoint):array=>$wrongChannelResponses[$endpoint]);
integration_check(($wrongChannel['error']??'')==='line_access_token_channel_mismatch','reject customer LINE access token from another channel');
$wrongIdentityResponses=$friendshipResponses;$wrongIdentityResponses['userinfo']=['status'=>200,'body'=>json_encode(['sub'=>'U99999999999999999999999999999999'])];
$wrongIdentity=mrbar_line_customer_friendship_result('user-access-token',$friendshipUserId,static fn(string $endpoint):array=>$wrongIdentityResponses[$endpoint]);
integration_check(($wrongIdentity['error']??'')==='line_token_subject_mismatch','reject LINE access token belonging to another user');
$unfriendedResponses=$friendshipResponses;$unfriendedResponses['friendship']=['status'=>200,'body'=>json_encode(['friendFlag'=>false])];
$unfriended=mrbar_line_customer_friendship_result('user-access-token',$friendshipUserId,static fn(string $endpoint):array=>$unfriendedResponses[$endpoint]);
integration_check(($unfriended['friend']??true)===false&&($unfriended['error']??'')==='line_oa_friend_required','block server-side booking auth when customer has not added OA');
$missingScopes=$friendshipResponses;$missingScopes['verify']=['status'=>200,'body'=>json_encode(['client_id'=>'2011745152','scope'=>'openid'])];
integration_check((mrbar_line_customer_friendship_result('user-access-token',$friendshipUserId,static fn(string $endpoint):array=>$missingScopes[$endpoint])['error']??'')==='line_profile_scope_required','require profile and openid scopes for server-side friendship check');
if($previousLoginChannel===false)putenv('MRBAR_LINE_LOGIN_CHANNEL_ID');else putenv('MRBAR_LINE_LOGIN_CHANNEL_ID='.$previousLoginChannel);
$oaById=mrbar_line_oa_destination('@117rttja');
$oaByUrl=mrbar_line_oa_destination('https://line.me/R/ti/p/@117rttja');
$oaByShortLink=mrbar_line_oa_destination('https://lin.ee/Abc_123');
integration_check(($oaById['url']??'')==='https://line.me/R/ti/p/@117rttja'&&($oaByUrl['basic_id']??'')==='@117rttja'&&($oaByShortLink['url']??'')==='https://lin.ee/Abc_123','accept OA Basic ID and official LINE add-friend URLs');
integration_check(mrbar_line_oa_destination('http://line.me/R/ti/p/@117rttja')===null&&mrbar_line_oa_destination('https://evil.example/@117rttja')===null,'reject insecure and non-LINE OA destinations');
$oaConfig=mrbar_line_customer_oa_config(['portal_settings'=>['line_customer_oa_url'=>'https://lin.ee/Abc_123']]);
integration_check($oaConfig['configured']&&$oaConfig['url']==='https://lin.ee/Abc_123','load the configured customer OA destination from portal settings');
$lineId='U12345678901234567890123456789012';
$loginUsers=[
    ['id'=>10,'role'=>'admin','active'=>1,'line_user_id'=>$lineId],
    ['id'=>11,'role'=>'staff','active'=>1,'line_user_id'=>'U22345678901234567890123456789012'],
    ['id'=>12,'role'=>'pr','active'=>1,'line_user_id'=>'U32345678901234567890123456789012'],
    ['id'=>13,'role'=>'staff','active'=>0,'line_user_id'=>'U42345678901234567890123456789012'],
    ['id'=>14,'role'=>'staff','active'=>1,'deleted_at'=>'2026-01-01','line_user_id'=>'U52345678901234567890123456789012'],
];
integration_check(mrbar_line_staff_login_match(['users'=>$loginUsers],$lineId)['status']==='matched','LINE login matches linked account without restricting staff role');
integration_check(mrbar_line_staff_login_match(['users'=>$loginUsers],$loginUsers[1]['line_user_id'])['status']==='matched','LINE login matches a non-admin staff role');
integration_check(mrbar_line_staff_login_match(['users'=>$loginUsers],$loginUsers[2]['line_user_id'])['status']==='matched','LINE login matches a PR role');
integration_check(mrbar_line_staff_login_match(['users'=>$loginUsers],$loginUsers[3]['line_user_id'])['status']==='unlinked','disabled account cannot use LINE login');
$duplicate=$loginUsers;$duplicate[]=['id'=>15,'role'=>'staff','active'=>1,'line_user_id'=>$lineId];
integration_check(mrbar_line_staff_login_match(['users'=>$duplicate],$lineId)['status']==='ambiguous','duplicate LINE bindings fail closed');
$now=time();
$customerSession=['customer_line_auth'=>['line_user_id'=>$lineId,'display_name'=>'Test','authenticated_at'=>$now,'friend_status'=>'friend','friend_checked_at'=>$now]];
integration_check(mrbar_customer_line_identity($customerSession)!==null,'fresh LINE customer session is accepted');
$customerIdentity=mrbar_customer_line_identity($customerSession);
integration_check(($customerIdentity['friend_status']??'')==='friend'&&($customerIdentity['friend_checked_at']??0)===$now,'retain verified LINE OA friendship state and timestamp for customer booking flow');
integration_check(mrbar_customer_line_booking_access($customerIdentity,true,$now)['allowed'],'allow booking with a recent verified OA friendship');
integration_check(mrbar_customer_line_booking_access($customerIdentity,false,$now)['reason']==='oa_not_configured','block booking when the customer OA destination is not configured');
$notFriend=array_merge($customerIdentity,['friend_status'=>'not_friend']);
integration_check(mrbar_customer_line_booking_access($notFriend,true,$now)['reason']==='friend_required','block booking until LINE OA friendship is confirmed');
integration_check(mrbar_customer_line_booking_access(array_merge($customerIdentity,['friend_checked_at'=>$now-1801]),true,$now)['reason']==='friendship_stale','require a fresh OA friendship check before booking');
integration_check(mrbar_customer_line_booking_access(array_merge($customerIdentity,['friend_checked_at'=>$now+1]),true,$now)['reason']==='friendship_stale','reject future-dated OA friendship checks');
$customerSession['customer_line_auth']['friend_status']='forged';
integration_check((mrbar_customer_line_identity($customerSession)['friend_status']??'')==='unknown','ignore unrecognized LINE friendship states');
$customerSession['customer_line_auth']['authenticated_at']=time()-43201;
integration_check(mrbar_customer_line_identity($customerSession)===null,'expired LINE customer session cannot book');
$crmData=['customers'=>[
    ['id'=>1,'full_name'=>'Old phone owner','phone'=>'0812345678','line_user_id'=>''],
    ['id'=>2,'full_name'=>'LINE customer','phone'=>'0899999999','line_user_id'=>$lineId],
]];
$reservation=['guest_name'=>'LINE customer','phone'=>'0812345678','line_user_id'=>$lineId,'created_at'=>date('c')];
integration_check(customer_crm_upsert_from_reservation($crmData,$reservation,true)===2,'LINE identity takes priority over a reused phone number');
$newReservation=['guest_name'=>'New LINE user','phone'=>'0812345678','line_user_id'=>'U62345678901234567890123456789012','created_at'=>date('c')];
integration_check(customer_crm_upsert_from_reservation($crmData,$newReservation,true)===3,'different LINE identity does not merge into an existing phone-only customer');
$safeReturn=mrbar_customer_reservation_return_url('/custumers/reserve.php?embed=1&table_id=7&party=3&unknown=drop');
integration_check($safeReturn==='/custumers/reserve.php?embed=1&public_branch=&table_id=7&zone=&party=3','LINE return URL keeps only reservation context');
integration_check(mrbar_customer_reservation_return_url('https://evil.example/')==='/custumers/reserve.php','external LINE return URL is rejected');
echo "Customer integration checks passed.\n";
