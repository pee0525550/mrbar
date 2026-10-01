<?php
declare(strict_types=1);

function mrbar_integration_env(string $name): string {
    $value=getenv($name);
    return is_string($value)?trim($value):'';
}

function mrbar_customer_line_message_templates(?array $data=null): array {
    if($data===null)$data=db_load_global();
    $settings=is_array($data['portal_settings']??null)?$data['portal_settings']:[];
    $receipt=trim((string)($settings['line_customer_booking_template']??''));
    $confirmed=trim((string)($settings['line_customer_confirmation_template']??''));
    return [
        'booking_template'=>$receipt!==''?$receipt:"MR BAR · ได้รับคำขอจองโต๊ะแล้ว\nเลขที่ #{reservation_id}\nคุณ {customer}\nวันที่ {date} เวลา {time}\nจำนวน {party_size} ท่าน · โต๊ะ/โซน {table} / {zone}\nทีมงานจะตรวจสอบและติดต่อกลับ รายการนี้ยังไม่ใช่การยืนยันโต๊ะ",
        'confirmation_template'=>$confirmed!==''?$confirmed:"MR BAR · ยืนยันการจองแล้ว ✅\nเลขที่ #{reservation_id}\nคุณ {customer}\nวันที่ {date} เวลา {time}\nจำนวน {party_size} ท่าน\nโต๊ะ/โซน {table} / {zone}\nขอบคุณที่ใช้บริการ",
        'confirmation_enabled'=>!array_key_exists('line_customer_confirmation_enabled',$settings)||(string)$settings['line_customer_confirmation_enabled']==='1',
    ];
}

function mrbar_render_customer_line_template(string $template,array $data,array $reservation): string {
    $tableCode='';$tableZone='';
    $tableId=(int)($reservation['table_id']??0);
    if($tableId>0){
        foreach($data['tables']??[] as $table)if((int)($table['id']??0)===$tableId){$tableCode=trim((string)($table['code']??''));$tableZone=trim((string)($table['zone']??''));break;}
    }
    if($tableCode==='')$tableCode=trim((string)($reservation['requested_table_code']??$reservation['table_code_snapshot']??''));
    if($tableCode==='')$tableCode='ทีมงานจะจัดโต๊ะให้';
    $zone=trim((string)($reservation['requested_zone']??''))?:$tableZone;
    if($zone==='')$zone='ไม่ระบุ';
    $date=trim((string)($reservation['date']??''));
    $timestamp=$date!==''?strtotime($date):false;
    if($timestamp!==false)$date=date('d/m/Y',$timestamp);
    $values=[
        '{customer}'=>trim((string)($reservation['guest_name']??''))?:'ลูกค้า',
        '{reservation_id}'=>(string)(int)($reservation['id']??0),
        '{date}'=>$date,
        '{time}'=>trim((string)($reservation['time']??'')),
        '{party_size}'=>(string)max(1,(int)($reservation['party_size']??1)),
        '{table}'=>$tableCode,
        '{zone}'=>$zone,
        '{shop}'=>trim((string)($data['settings']['shop_name']??''))?:'MR BAR',
    ];
    $message=trim(strtr($template,$values));
    if(strlen($message)>4500){
        if(function_exists('mb_strcut'))$message=mb_strcut($message,0,4500,'UTF-8');
        else{
            $message=substr($message,0,4500);
            while($message!==''&&!preg_match('//u',$message))$message=substr($message,0,-1);
        }
    }
    return trim($message);
}

function mrbar_should_send_customer_reservation_confirmation(array $reservation,string $previousStatus,string $nextStatus): bool {
    if(($reservation['source']??'')!=='customer'||!in_array($nextStatus,['booked','confirmed'],true))return false;
    if(($reservation['line_customer_confirmation_status']??'')==='sent')return false;
    if(in_array($previousStatus,['booked','confirmed'],true))return in_array((string)($reservation['line_customer_confirmation_status']??''),['failed','not_configured','not_linked','disabled'],true);
    return true;
}

function mrbar_line_retry_key(string $seed): string {
    $namespace=hex2bin('6ba7b8109dad11d180b400c04fd430c8');
    $hex=substr(sha1($namespace.$seed),0,32);
    $hex[12]='5';
    $hex[16]=dechex((hexdec($hex[16])&0x3)|0x8);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
}

function mrbar_line_login_config(?array $data=null): array {
    $settings=$data['portal_settings']??null;
    if(!is_array($settings))$settings=db_load_global()['portal_settings']??[];
    $liffId=trim((string)($settings['line_liff_id']??''));
    $channelId=trim((string)($settings['line_login_channel_id']??''));
    if($liffId==='')$liffId=mrbar_integration_env('MRBAR_LINE_LIFF_ID');
    if($channelId==='')$channelId=mrbar_integration_env('MRBAR_LINE_LOGIN_CHANNEL_ID');
    return [
        'liff_id'=>$liffId,'channel_id'=>$channelId,
        'liff_ready'=>$liffId!=='','channel_ready'=>$channelId!=='',
        'ready'=>$liffId!==''&&$channelId!=='',
        'source'=>trim((string)($settings['line_liff_id']??''))!==''?'settings':'server',
    ];
}

function mrbar_line_oa_destination(string $value): ?array {
    $value=trim($value);
    if($value==='')return null;
    if(preg_match('/^@[A-Za-z0-9._-]{3,40}$/',$value))return ['url'=>'https://line.me/R/ti/p/'.$value,'basic_id'=>$value];
    $parts=parse_url($value);
    if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||isset($parts['port'])&&$parts['port']!==443)return null;
    $host=strtolower((string)($parts['host']??''));
    $path=(string)($parts['path']??'');
    if(in_array($host,['lin.ee'],true)&&preg_match('#^/[A-Za-z0-9_-]{3,80}/?$#',$path))return ['url'=>$value,'basic_id'=>''];
    if(in_array($host,['line.me','www.line.me'],true)&&preg_match('#^/R/ti/p/(@[A-Za-z0-9._-]{3,40})/?$#i',$path,$match))return ['url'=>$value,'basic_id'=>$match[1]];
    return null;
}

function mrbar_line_customer_oa_config(?array $data=null): array {
    if($data===null)$data=db_load_global();
    $settings=is_array($data['portal_settings']??null)?$data['portal_settings']:[];
    $value=trim((string)($settings['line_customer_oa_url']??''));
    $destination=mrbar_line_oa_destination($value);
    return [
        'configured'=>$destination!==null,
        'value'=>$value,
        'url'=>$destination['url']??'',
        'basic_id'=>$destination['basic_id']??'',
    ];
}

function mrbar_customer_integrations_status(): array {
    $slipProvider=mrbar_integration_env('MRBAR_SLIP_PROVIDER');
    $slipReady=mrbar_slip_verifier_adapter()!==null;
    return [
        'slip_verifier'=>[
            'provider'=>$slipProvider!==''?$slipProvider:'disabled',
            'ready'=>$slipReady,
        ],
        'line_messaging'=>['ready'=>mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!== ''],
        'line_webhook'=>['ready'=>mrbar_integration_env('MRBAR_LINE_CHANNEL_SECRET')!== ''],
        'line_liff'=>['ready'=>mrbar_line_login_config()['ready']],
        'mobile_push'=>['provider'=>mrbar_integration_env('MRBAR_PUSH_PROVIDER')?:'disabled','ready'=>mrbar_integration_env('MRBAR_PUSH_PROVIDER')!==''&&mrbar_integration_env('MRBAR_PUSH_SERVER_KEY')!==''&&mrbar_integration_env('MRBAR_PUSH_APP_ID')!== ''],
    ];
}

function mrbar_booking_notification_recipient_ids(array $data,array $reservation): array {
    $branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
    $related=[];$salesId=(int)($reservation['sales_employee_id']??0);
    if($salesId>0){
        foreach($data['employees']??[] as $employee){
            if((int)($employee['id']??0)===$salesId&&(int)($employee['user_id']??0)>0)$related[]=(int)$employee['user_id'];
        }
    }
    $recipients=[];
    foreach($data['users']??[] as $user){
        $userId=(int)($user['id']??0);
        if($userId<=0||empty($user['active'])||!empty($user['deleted_at']))continue;
        if($branchId>0&&!db_user_can_branch($user,$branchId))continue;
        $canManage=($user['role']??'')==='admin'||user_can($user,'reservations.manage',$data);
        $canView=($user['role']??'')==='admin'||user_can($user,'reservations.view',$data);
        if($canManage||$canView||in_array($userId,$related,true))$recipients[]=$userId;
    }
    return array_values(array_unique($recipients));
}

function mrbar_booking_notification_event_key(array $data,array $reservation): string {
    $branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
    return 'reservation:'.$branchId.':'.(int)($reservation['id']??0).':created';
}

function mrbar_line_push_accepted(array $result): bool {
    return !empty($result['ok'])||(int)($result['status']??0)===409;
}

function mrbar_notify_booking_created(array &$data,array $reservation): int {
    if(!function_exists('op_notify'))throw new RuntimeException('Notification service is unavailable');
    $recipients=mrbar_booking_notification_recipient_ids($data,$reservation);
    $id=(int)($reservation['id']??0);$branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
    $eventKey=mrbar_booking_notification_event_key($data,$reservation);
    $message='มีคำขอจองโต๊ะใหม่ · '.(string)($reservation['guest_name']??'ลูกค้า').' · '.(string)($reservation['date']??'').' '.(string)($reservation['time']??'');
    $sales=trim((string)($reservation['sales_name_snapshot']??''));
    if($sales!=='')$message.=' · เซล: '.$sales;
    $lineStatus=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!==''?'queued':'not_configured';
    foreach($recipients as $userId){
        op_notify($data,$userId,'staff','reservation_new',$message,['event'=>'reservation.created','event_key'=>$eventKey,'reservation_id'=>$id,'branch_id'=>$branchId,'channels'=>['in_app'=>'sent','mobile_push'=>'not_configured','line'=>$lineStatus]]);
    }
    return count($recipients);
}

function mrbar_send_booking_line_notifications(array $data,array $reservation,?callable $push=null): array {
    $recipients=mrbar_booking_notification_recipient_ids($data,$reservation);
    $eventKey=mrbar_booking_notification_event_key($data,$reservation);
    $accounts=[];
    foreach($data['users']??[] as $account)$accounts[(int)($account['id']??0)]=$account;
    $table=trim((string)($reservation['requested_table_code']??$reservation['table_code_snapshot']??''));
    if($table==='')$table=empty($reservation['table_id'])?'รอจัดโต๊ะ':'โต๊ะที่จอง';
    $message='MR BAR · มีรายการจองใหม่'."\n".
        'ลูกค้า: '.trim((string)($reservation['guest_name']??'ลูกค้า'))."\n".
        'วันเวลา: '.trim((string)($reservation['date']??'')).' '.trim((string)($reservation['time']??''))."\n".
        'จำนวน: '.max(1,(int)($reservation['party_size']??1)).' คน · '.$table;
    $tokenReady=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!=='';
    $push=$push??static fn(string $lineUserId,string $text,string $retryKey):array=>mrbar_line_push_text($lineUserId,$text,$retryKey);
    $delivery=[];
    foreach($recipients as $userId){
        $alreadySent=false;
        foreach($data['notifications']??[] as $notice){
            if((int)($notice['user_id']??0)===$userId&&(string)($notice['meta']['event_key']??'')===$eventKey&&($notice['meta']['channels']['line']??'')==='sent'){$alreadySent=true;break;}
        }
        if($alreadySent){$delivery[$userId]=['status'=>'sent','duplicate_skipped'=>true];continue;}
        $lineUserId=trim((string)($accounts[$userId]['line_user_id']??''));
        if(!$tokenReady){$delivery[$userId]=['status'=>'not_configured'];continue;}
        if($lineUserId===''){$delivery[$userId]=['status'=>'not_linked'];continue;}
        $retryKey=mrbar_line_retry_key('mrbar-line:'.$eventKey.':'.$userId);
        try{$result=$push($lineUserId,$message,$retryKey);}
        catch(Throwable $exception){$result=['ok'=>false,'status'=>0];}
        $delivery[$userId]=['status'=>mrbar_line_push_accepted($result)?'sent':'failed','http_status'=>(int)($result['status']??0)];
    }
    return $delivery;
}

function mrbar_send_customer_booking_confirmation(array $data,array $reservation,?callable $push=null): array {
    if(($reservation['source']??'')!=='customer')return ['status'=>'not_applicable'];
    if(($reservation['line_customer_notification_status']??'')==='sent')return ['status'=>'sent','duplicate_skipped'=>true];
    if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')return ['status'=>'not_configured'];
    $lineUserId=trim((string)($reservation['line_user_id']??''));
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$lineUserId))return ['status'=>'not_linked'];
    $eventKey=mrbar_booking_notification_event_key($data,$reservation);
    $templates=mrbar_customer_line_message_templates($data);
    $message=mrbar_render_customer_line_template($templates['booking_template'],$data,$reservation);
    $push=$push??static fn(string $id,string $text,string $retryKey):array=>mrbar_line_push_text($id,$text,$retryKey);
    $retryKey=mrbar_line_retry_key('mrbar-line-customer:'.$eventKey.':'.$lineUserId);
    try{$result=$push($lineUserId,$message,$retryKey);}
    catch(Throwable $exception){$result=['ok'=>false,'status'=>0];}
    return ['status'=>mrbar_line_push_accepted($result)?'sent':'failed','http_status'=>(int)($result['status']??0)];
}

function mrbar_send_customer_reservation_confirmation(array $data,array $reservation,?callable $push=null): array {
    if(($reservation['source']??'')!=='customer'||!in_array((string)($reservation['status']??''),['booked','confirmed'],true))return ['status'=>'not_applicable'];
    $templates=mrbar_customer_line_message_templates($data);
    if(!$templates['confirmation_enabled'])return ['status'=>'disabled'];
    if(($reservation['line_customer_confirmation_status']??'')==='sent')return ['status'=>'sent','duplicate_skipped'=>true];
    if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')==='')return ['status'=>'not_configured'];
    $lineUserId=trim((string)($reservation['line_user_id']??''));
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$lineUserId))return ['status'=>'not_linked'];
    $message=mrbar_render_customer_line_template($templates['confirmation_template'],$data,$reservation);
    $branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
    $retryKey=mrbar_line_retry_key('mrbar-line-customer-confirmed:'.$branchId.':'.(int)($reservation['id']??0).':'.hash('sha256',$message));
    $push=$push??static fn(string $id,string $text,string $key):array=>mrbar_line_push_text($id,$text,$key);
    try{$result=$push($lineUserId,$message,$retryKey);}
    catch(Throwable $exception){$result=['ok'=>false,'status'=>0];}
    return ['status'=>mrbar_line_push_accepted($result)?'sent':'failed','http_status'=>(int)($result['status']??0)];
}

function mrbar_apply_customer_reservation_confirmation_delivery(array &$data,array $reservation,array $delivery): void {
    if(!isset($data['reservations'])||!is_array($data['reservations']))return;
    foreach($data['reservations'] as &$row){
        if((int)($row['id']??0)!==(int)($reservation['id']??0))continue;
        $row['line_customer_confirmation_status']=(string)($delivery['status']??'failed');
        if(!empty($delivery['http_status']))$row['line_customer_confirmation_http_status']=(int)$delivery['http_status'];
        if(($delivery['status']??'')==='sent')$row['line_customer_confirmation_sent_at']=date('c');
        break;
    }
    unset($row);
}

function mrbar_apply_booking_line_delivery(array &$data,array $reservation,array $delivery,?array $customerDelivery=null): void {
    $eventKey=mrbar_booking_notification_event_key($data,$reservation);
    if(!isset($data['notifications'])||!is_array($data['notifications']))$data['notifications']=[];
    foreach($data['notifications'] as &$notice){
        $userId=(int)($notice['user_id']??0);
        if(!array_key_exists($userId,$delivery)||(string)($notice['meta']['event_key']??'')!==$eventKey)continue;
        if(!is_array($notice['meta']??null))$notice['meta']=[];
        if(!is_array($notice['meta']['channels']??null))$notice['meta']['channels']=[];
        $notice['meta']['channels']['line']=(string)$delivery[$userId]['status'];
        if(!empty($delivery[$userId]['http_status']))$notice['meta']['line_http_status']=(int)$delivery[$userId]['http_status'];
        if(($delivery[$userId]['status']??'')==='sent')$notice['meta']['line_sent_at']=date('c');
    }
    unset($notice);
    $summary=['eligible'=>count($delivery),'sent'=>0,'not_linked'=>0,'failed'=>0,'not_configured'=>0];
    foreach($delivery as $result){$status=(string)($result['status']??'');if(array_key_exists($status,$summary))$summary[$status]++;}
    if(!isset($data['reservations'])||!is_array($data['reservations']))$data['reservations']=[];
    foreach($data['reservations'] as &$row){
        if((int)($row['id']??0)!==(int)($reservation['id']??0))continue;
        $row['line_staff_notification_summary']=$summary;
        if($customerDelivery!==null){
            $row['line_customer_notification_status']=(string)($customerDelivery['status']??'failed');
            if(!empty($customerDelivery['http_status']))$row['line_customer_notification_http_status']=(int)$customerDelivery['http_status'];
            if(($customerDelivery['status']??'')==='sent')$row['line_customer_notification_sent_at']=date('c');
        }
        break;
    }
    unset($row);
}

function mrbar_slip_verifier_adapter(): ?callable {
    $provider=mrbar_integration_env('MRBAR_SLIP_PROVIDER');
    if($provider===''||!preg_match('/^[a-z0-9_-]{1,40}$/i',$provider))return null;
    $path=__DIR__.'/integrations/slip-'.$provider.'.php';
    if(!is_file($path)||mrbar_integration_env('MRBAR_SLIP_API_KEY')==='')return null;
    try{require_once $path;}catch(Throwable $error){error_log('Slip verifier adapter could not be loaded');return null;}
    $function='mrbar_slip_verify_'.$provider;
    return function_exists($function)?$function:null;
}

function mrbar_verify_reservation_slip(string $filePath,array $context): array {
    $adapter=mrbar_slip_verifier_adapter();
    if(!$adapter)return ['status'=>'manual_review','provider'=>'not_configured','reason'=>'ยังไม่ได้ตั้งค่า Provider/API Key'];
    $result=$adapter($filePath,$context);
    if(!is_array($result)||!in_array((string)($result['status']??''),['verified','rejected','manual_review'],true)){
        return ['status'=>'manual_review','provider'=>mrbar_integration_env('MRBAR_SLIP_PROVIDER'),'reason'=>'ผลตอบกลับจาก Provider ไม่ตรงรูปแบบ'];
    }
    return $result;
}

function mrbar_line_post(string $path,array $payload,string $retryKey=''): array {
    if($retryKey!==''&&!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$retryKey))return ['ok'=>false,'status'=>0,'error'=>'Invalid LINE retry key'];
    $token=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN');
    if($token==='')return ['ok'=>false,'status'=>0,'error'=>'LINE Messaging API is not configured'];
    $headers="Content-Type: application/json\r\nAuthorization: Bearer ".$token."\r\n";
    if($retryKey!=='')$headers.="X-Line-Retry-Key: ".$retryKey."\r\n";
    $context=stream_context_create(['http'=>[
        'method'=>'POST','header'=>$headers,'content'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'timeout'=>8,'ignore_errors'=>true,
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $body=@file_get_contents('https://api.line.me'.$path,false,$context);
    $status=0;
    foreach($http_response_header??[] as $header)if(preg_match('#^HTTP/\S+\s+(\d{3})#',$header,$m))$status=(int)$m[1];
    return ['ok'=>$status>=200&&$status<300,'status'=>$status,'error'=>$status>=200&&$status<300?'':('LINE API request failed (HTTP '.$status.')'),'response'=>$body===false?'':substr($body,0,1000)];
}

function mrbar_line_liff_get(string $endpoint,string $accessToken): array {
    $paths=[
        'verify'=>'/oauth2/v2.1/verify?access_token='.rawurlencode($accessToken),
        'userinfo'=>'/oauth2/v2.1/userinfo',
        'friendship'=>'/friendship/v1/status',
    ];
    if(!isset($paths[$endpoint]))return ['status'=>0,'body'=>''];
    $headers=$endpoint==='verify'?"Accept: application/json\r\n":"Authorization: Bearer ".$accessToken."\r\nAccept: application/json\r\n";
    $context=stream_context_create(['http'=>[
        'method'=>'GET','header'=>$headers,'timeout'=>8,'ignore_errors'=>true,
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $http_response_header=[];
    $body=@file_get_contents('https://api.line.me'.$paths[$endpoint],false,$context);
    $status=0;
    foreach($http_response_header as $header)if(preg_match('#^HTTP/\\S+\\s+(\\d{3})#',$header,$match))$status=(int)$match[1];
    return ['status'=>$status,'body'=>$body===false?'':$body];
}

function mrbar_line_customer_friendship_result(string $accessToken,string $expectedUserId,?callable $request=null): array {
    if(trim($accessToken)==='')return ['friend'=>null,'error'=>'line_access_token_missing'];
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$expectedUserId))return ['friend'=>null,'error'=>'line_token_subject_mismatch'];
    $channelId=mrbar_line_login_config()['channel_id'];
    if($channelId==='')return ['friend'=>null,'error'=>'line_login_not_configured'];
    $request=$request??static fn(string $endpoint,string $token):array=>mrbar_line_liff_get($endpoint,$token);
    try{$verified=$request('verify',$accessToken);}catch(Throwable $error){return ['friend'=>null,'error'=>'line_friendship_unavailable'];}
    $verifiedData=json_decode((string)($verified['body']??''),true);
    if((int)($verified['status']??0)!==200||!is_array($verifiedData)){
        $status=(int)($verified['status']??0);
        return ['friend'=>null,'error'=>in_array($status,[400,401],true)?'line_access_token_invalid':'line_friendship_unavailable'];
    }
    if((string)($verifiedData['client_id']??'')!==$channelId)return ['friend'=>null,'error'=>'line_access_token_channel_mismatch'];
    $scopes=preg_split('/\\s+/',trim((string)($verifiedData['scope']??'')))?:[];
    if(!in_array('openid',$scopes,true)||!in_array('profile',$scopes,true))return ['friend'=>null,'error'=>'line_profile_scope_required'];
    try{$profile=$request('userinfo',$accessToken);}catch(Throwable $error){return ['friend'=>null,'error'=>'line_friendship_unavailable'];}
    $profileData=json_decode((string)($profile['body']??''),true);
    if((int)($profile['status']??0)!==200||!is_array($profileData))return ['friend'=>null,'error'=>'line_access_token_invalid'];
    if(!hash_equals($expectedUserId,(string)($profileData['sub']??'')))return ['friend'=>null,'error'=>'line_token_subject_mismatch'];
    try{$friendship=$request('friendship',$accessToken);}catch(Throwable $error){return ['friend'=>null,'error'=>'line_friendship_unavailable'];}
    $friendshipData=json_decode((string)($friendship['body']??''),true);
    if((int)($friendship['status']??0)!==200||!is_array($friendshipData)||!is_bool($friendshipData['friendFlag']??null))return ['friend'=>null,'error'=>'line_friendship_unavailable'];
    return ['friend'=>$friendshipData['friendFlag'],'error'=>$friendshipData['friendFlag']?'':'line_oa_friend_required'];
}

function mrbar_line_push_text(string $lineUserId,string $text,string $retryKey=''): array {
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$lineUserId))return ['ok'=>false,'status'=>0,'error'=>'Invalid LINE user ID'];
    $text=trim($text);
    if($text===''||strlen($text)>4500)return ['ok'=>false,'status'=>0,'error'=>'Invalid LINE message length'];
    return mrbar_line_post('/v2/bot/message/push',['to'=>$lineUserId,'messages'=>[['type'=>'text','text'=>$text]]],$retryKey);
}

function mrbar_line_broadcast_payload(string $text): ?array {
    $text=trim($text);
    if($text===''||!preg_match('//u',$text))return null;
    $length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):preg_match_all('/./us',$text);
    if($length===false||$length>5000)return null;
    return ['messages'=>[['type'=>'text','text'=>$text]]];
}

function mrbar_line_verify_liff_id_token(string $idToken): ?string {
    $identity=mrbar_line_verify_liff_identity($idToken);
    return $identity!==null?$identity['sub']:null;
}

function mrbar_line_verify_liff_identity(string $idToken): ?array {
    $result=mrbar_line_verify_liff_identity_result($idToken);
    return $result['identity'];
}

function mrbar_line_id_token_error_code(string $description): string {
    $description=strtolower(trim($description));
    if(str_contains($description,'audience'))return 'line_channel_mismatch';
    if(str_contains($description,'expired'))return 'line_token_expired';
    if(str_contains($description,'idtoken'))return 'line_token_invalid';
    return 'line_verify_failed';
}

function mrbar_line_verify_liff_identity_result(string $idToken): array {
    $channelId=mrbar_line_login_config()['channel_id'];
    if($channelId==='')return ['identity'=>null,'error'=>'line_login_not_configured'];
    if($idToken==='')return ['identity'=>null,'error'=>'line_token_missing'];
    $context=stream_context_create(['http'=>[
        'method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",
        'content'=>http_build_query(['id_token'=>$idToken,'client_id'=>$channelId]),'timeout'=>8,'ignore_errors'=>true,
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $http_response_header=[];
    $body=@file_get_contents('https://api.line.me/oauth2/v2.1/verify',false,$context);
    $status=0;
    foreach($http_response_header as $header)if(preg_match('#^HTTP/\S+\s+(\d{3})#',$header,$match))$status=(int)$match[1];
    if($body===false)return ['identity'=>null,'error'=>'line_verify_unavailable','http_status'=>$status];
    $result=json_decode($body,true);
    $subject=is_array($result)?(string)($result['sub']??''):'';
    if($status>=200&&$status<300&&preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$subject)){
        if((string)($result['aud']??'')!==$channelId)return ['identity'=>null,'error'=>'line_channel_mismatch','http_status'=>$status];
        return ['identity'=>['sub'=>$subject,'name'=>trim((string)($result['name']??''))],'error'=>'','http_status'=>$status];
    }
    $description=is_array($result)?(string)($result['error_description']??''):'';
    return ['identity'=>null,'error'=>mrbar_line_id_token_error_code($description),'http_status'=>$status];
}

function mrbar_line_staff_login_match(array $data,string $lineUserId): array {
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$lineUserId))return ['status'=>'unlinked','user'=>null];
    $matches=[];
    foreach($data['users']??[] as $user){
        if((string)($user['line_user_id']??'')!==$lineUserId)continue;
        if(empty($user['active'])||!empty($user['deleted_at']))continue;
        $matches[]=$user;
    }
    if(count($matches)>1)return ['status'=>'ambiguous','user'=>null];
    if(!$matches)return ['status'=>'unlinked','user'=>null];
    return ['status'=>'matched','user'=>$matches[0]];
}

function mrbar_customer_line_identity(?array $session=null,int $maxAge=43200): ?array {
    $session=$session??$_SESSION;
    $identity=$session['customer_line_auth']??null;
    if(!is_array($identity))return null;
    $lineUserId=(string)($identity['line_user_id']??'');
    $authenticatedAt=(int)($identity['authenticated_at']??0);
    if(!preg_match('/^U[A-Za-z0-9_-]{20,64}$/',$lineUserId)||$authenticatedAt<=0||$authenticatedAt>time()||time()-$authenticatedAt>$maxAge)return null;
    $friendStatus=(string)($identity['friend_status']??'unknown');
    if(!in_array($friendStatus,['friend','not_friend','unknown'],true))$friendStatus='unknown';
    return ['line_user_id'=>$lineUserId,'display_name'=>trim((string)($identity['display_name']??'')),'friend_status'=>$friendStatus,'friend_checked_at'=>(int)($identity['friend_checked_at']??0)];
}

function mrbar_customer_line_booking_access(?array $identity,bool $oaConfigured,?int $now=null,int $maxFriendAge=1800): array {
    if($identity===null)return ['allowed'=>false,'reason'=>'login_required'];
    if(!$oaConfigured)return ['allowed'=>false,'reason'=>'oa_not_configured'];
    if(($identity['friend_status']??'unknown')!=='friend')return ['allowed'=>false,'reason'=>'friend_required'];
    $checkedAt=(int)($identity['friend_checked_at']??0);
    $now=$now??time();
    if($checkedAt<=0||$checkedAt>$now||$now-$checkedAt>$maxFriendAge)return ['allowed'=>false,'reason'=>'friendship_stale'];
    return ['allowed'=>true,'reason'=>''];
}

function mrbar_customer_reservation_return_url(string $candidate): string {
    $branchBase=function_exists('branch_public_base')?branch_public_base():'';
    $base=$branchBase.'/custumers/reserve.php';
    $parts=parse_url($candidate);
    if(!is_array($parts)||isset($parts['scheme'])||isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment']))return $base;
    $candidatePath=(string)($parts['path']??'');
    if($candidatePath!==$base&&$candidatePath!=='custumers/reserve.php'&&$candidatePath!=='/custumers/reserve.php')return $base;
    $query=[];parse_str((string)($parts['query']??''),$query);
    $allowed=[
        'embed'=>!empty($query['embed'])?'1':'0',
        'public_branch'=>preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/i',(string)($query['public_branch']??''))?(string)$query['public_branch']:'',
        'table_id'=>max(0,(int)($query['table_id']??0)),
        'zone'=>substr(trim((string)($query['zone']??'')),0,80),
        'party'=>max(1,min(30,(int)($query['party']??2))),
    ];
    return $base.'?'.http_build_query($allowed);
}
