<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/app.php';
date_default_timezone_set($config['timezone']);
require_once __DIR__.'/secure-context.php';
$staffTimeScripts=['time.php','employee-time.php','employee-calendar.php','employee-income.php','pr.php','pr-calendar.php','pr-jobs.php','staff-preview.php','login.php','setup-pin.php','employee-activate.php'];
if(in_array(basename((string)($_SERVER['SCRIPT_NAME']??'')),$staffTimeScripts,true))mrbar_require_https('/'.basename((string)$_SERVER['SCRIPT_NAME']));
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/permissions.php';
require_once __DIR__.'/branches.php';
require_once __DIR__.'/workforce.php';
require_once __DIR__.'/customer-crm.php';
require_once __DIR__.'/auth-state.php';
try { db_auto_migrate(); } catch(Throwable $e) { http_response_code(500); exit('MR BAR storage error. Open preflight.php for diagnostics.'); }
$attendanceHousekeepingPages=['admin.php','dashboard.php','employee-calendar.php','employee-income.php','employee-time.php','employees.php','hr-approval-center.php','payroll-attendance.php','pr-calendar.php','pr-jobs.php','pr.php','workforce-exceptions.php','workforce-schedule.php'];
$requestPage=basename((string)($_SERVER['SCRIPT_NAME']??''));
if(is_array($_SESSION['user']??null)&&in_array($requestPage,$attendanceHousekeepingPages,true)&&(int)($_SESSION['attendance_housekeeping_at']??0)<time()-60){
    $_SESSION['attendance_housekeeping_at']=time();
    try { if(function_exists('workforce_mark_missing_checkouts')) workforce_mark_missing_checkouts(); } catch(Throwable $e) { /* housekeeping must never block the app */ }
}
function app_config():array{global $config;return $config;}
function current_user():?array{
    $session=$_SESSION['user']??null;if(!is_array($session))return null;
    try{$fresh=auth_session_account(db_load_global(),$session);}catch(Throwable $e){$fresh=null;}
    if($fresh===null){
        unset($_SESSION['user'],$_SESSION['needs_pin_setup']);
        return null;
    }
    $_SESSION['user']=$fresh;
    return $fresh;
}
function require_roles(string ...$roles):array{$u=current_user();if(!$u||!in_array($u['role'],$roles,true)){header('Location:login.php');exit;}return $u;}
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function next_id(array $rows):int{return $rows?max(array_map(fn($r)=>(int)$r['id'],$rows))+1:1;}
function ticket():string{return 'CHK-'.date('ymd-His').'-'.random_int(100,999);}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));return $_SESSION['csrf'];}
function csrf_check():void{if(!auth_csrf_valid((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);exit('Security token expired. Please reload and try again.');}}
function user_pr(array $d,int $userId):?array{foreach($d['prs'] as $p){if((int)($p['user_id']??0)===$userId)return $p;}return null;}
function attendance_open(array $d,int $prId):?array{foreach(array_reverse($d['attendance']) as $a){if((int)$a['pr_id']===$prId && empty($a['check_out']))return $a;}return null;}
function role_home(string $role):string{try{$d=db_load();$home=(string)($d['roles'][$role]['home']??'');if(in_array($home,['admin.php','dashboard.php','pr.php','employee-time.php','employee-calendar.php','night-ops.php','admin-tools.php'],true))return $home;}catch(Throwable $e){}return $role==='pr'?'pr.php':($role==='admin'?'admin.php':'dashboard.php');}

/* PWA/login return helpers. Only named, whitelisted destinations are accepted. */
function login_return_key():string{
    $key=trim((string)($_POST['return_to']??$_GET['return_to']??''));
    return in_array($key,['time','income','sales_table'],true)?$key:'';
}
function login_success_target(array $u,string $returnKey=''):string{
    if($returnKey==='sales_table'){ $q=$_SESSION['sales_table_return']??[]; return 'sales-table.php?'.http_build_query(['public_branch'=>(string)($q['public_branch']??''),'table_id'=>(int)($q['table_id']??0)]); }
    if($returnKey==='time')return 'time.php';
    if($returnKey==='income')return 'employee-income.php';
    return role_home((string)($u['role']??''));
}
function login_return_query(string $returnKey='',array $extra=[]):string{
    $q=$extra;
    if($returnKey!=='')$q['return_to']=$returnKey;
    return $q?'?'.http_build_query($q):'';
}

/* Trusted-device + 6-digit PIN helpers. Passwords are never stored in browser cookies. */
function trusted_cookie_name():string{return 'mrbar_trusted_device';}
function trusted_cookie_path():string{$base=function_exists('branch_public_base')?branch_public_base():'';return $base!==''?$base.'/':'/';}
function cookie_secure():bool{return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off';}
function set_trusted_cookie(string $token,int $days=30):void{
    setcookie(trusted_cookie_name(),$token,[
        'expires'=>time()+($days*86400),'path'=>trusted_cookie_path(),'secure'=>cookie_secure(),
        'httponly'=>true,'samesite'=>'Lax'
    ]);
    $_COOKIE[trusted_cookie_name()]=$token;
}
function clear_trusted_cookie():void{
    setcookie(trusted_cookie_name(),'',[
        'expires'=>time()-3600,'path'=>trusted_cookie_path(),'secure'=>cookie_secure(),
        'httponly'=>true,'samesite'=>'Lax'
    ]);
    unset($_COOKIE[trusted_cookie_name()]);
}
function ua_hash():string{return hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'unknown'));}
function remembered_device(array $d):?array{
    return auth_remembered_device($d,(string)($_COOKIE[trusted_cookie_name()]??''));
}
function register_trusted_device(int $userId,string $pin):void{
    if(!preg_match('/^\d{6}$/',$pin))throw new RuntimeException('PIN ต้องเป็นตัวเลข 6 หลัก');
    $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);$pinHash=password_hash($pin,PASSWORD_DEFAULT);
    $days=max(1,min(365,(int)(db_load()['settings']['trusted_device_days']??30)));
    db_mutate_global(function($d)use($userId,$tokenHash,$pinHash,$days){
        $found=false;
        foreach($d['users'] as &$u){if((int)$u['id']!==$userId)continue;
            if(empty($u['active'])||!empty($u['deleted_at']))throw new RuntimeException('บัญชีนี้ไม่พร้อมใช้งาน');
            $devices=array_values($u['trusted_devices']??[]);
            /* Keep max 5 remembered devices per account. */
            if(count($devices)>=5)$devices=array_slice($devices,-4);
            $devices[]=['token_hash'=>$tokenHash,'pin_hash'=>$pinHash,'created_at'=>date('c'),'expires_at'=>date('c',time()+$days*86400),'last_used_at'=>date('c'),'failed_attempts'=>0,'locked_until'=>null,'ua_hash'=>ua_hash()];
            $u['trusted_devices']=$devices;$found=true;break;
        }unset($u);
        if(!$found)throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
        $d['audit'][]=['at'=>date('c'),'action'=>'trusted_device_registered','user_id'=>$userId,'branch_id'=>db_active_branch_id($d)];
        return $d;
    });
    set_trusted_cookie($token,$days);
}
function forget_current_device():void{
    $token=(string)($_COOKIE[trusted_cookie_name()]??'');
    if($token!==''){
        $hash=hash('sha256',$token);
        try{db_mutate_global(function($d)use($hash){foreach($d['users'] as &$u){$u['trusted_devices']=array_values(array_filter($u['trusted_devices']??[],fn($dev)=>!hash_equals((string)($dev['token_hash']??''),$hash)));}unset($u);return $d;});}catch(Throwable $e){}
    }
    clear_trusted_cookie();
}
function establish_session(array $u):void{
    session_regenerate_id(true);
    $_SESSION['user']=['id'=>(int)$u['id'],'username'=>(string)$u['username'],'role'=>(string)$u['role'],'display_name'=>(string)$u['display_name'],'super_admin'=>!empty($u['super_admin']),'branch_ids'=>array_values(array_map('intval',$u['branch_ids']??[1])),'active'=>!empty($u['active']),'credential_version'=>auth_credential_version($u)];
}
function pin_login(string $pin):array{
    if(!preg_match('/^\d{6}$/',$pin))return ['ok'=>false,'message'=>'กรุณากรอก PIN 6 หลัก'];
    $token=(string)($_COOKIE[trusted_cookie_name()]??'');$result=[];
    db_mutate_global(function(array $data)use($token,$pin,&$result):array{
        $branchId=db_active_branch_id($data);
        $days=(int)($data['branch_data'][(string)$branchId]['settings']['trusted_device_days']??30);
        $result=auth_pin_attempt($data,$token,$pin,null,$days);
        if(!empty($result['ok']))$data['audit'][]=['at'=>date('c'),'action'=>'pin_login','user_id'=>(int)$result['user']['id'],'branch_id'=>db_active_branch_id($data)];
        return $data;
    });
    if(!empty($result['ok']))establish_session($result['user']);
    return $result;
}

/* PR account-link helpers (v1.8.1). */
function pr_link_normalize(string $v):string{
    $v=mb_strtolower(trim($v),'UTF-8');
    return preg_replace('/[^a-z0-9ก-๙]+/u','',$v)??'';
}
function try_auto_link_pr_account(int $userId):?array{
    $d=db_load();$user=null;
    foreach($d['users'] as $x)if((int)$x['id']===$userId){$user=$x;break;}
    if(!$user||($user['role']??'')!=='pr')return null;
    if($linked=user_pr($d,$userId))return $linked;
    $un=pr_link_normalize((string)($user['username']??''));
    $dn=pr_link_normalize((string)($user['display_name']??''));
    $variants=array_values(array_unique(array_filter([$un,$dn,preg_replace('/^pr/','',$un),preg_replace('/^pr/','',$dn)])));
    $candidates=[];
    foreach($d['prs'] as $pr){
        if(empty($pr['active'])||!empty($pr['user_id']))continue;
        $pc=pr_link_normalize((string)($pr['code']??''));
        $pn=pr_link_normalize((string)($pr['name']??''));
        if(in_array($pc,$variants,true)||in_array($pn,$variants,true))$candidates[]=$pr;
    }
    if(count($candidates)!==1)return null;
    $prId=(int)$candidates[0]['id'];
    db_mutate(function($d)use($userId,$prId){
        foreach($d['prs'] as &$p){
            if((int)$p['id']===$prId && empty($p['user_id'])){$p['user_id']=$userId;break;}
        }unset($p);
        $d['audit'][]=['at'=>date('c'),'action'=>'pr_account_auto_linked','pr_id'=>$prId,'user_id'=>$userId];
        return $d;
    });
    $fresh=db_load();return user_pr($fresh,$userId);
}
function pr_link_status(array $d,int $userId):array{
    $pr=user_pr($d,$userId);
    return ['linked'=>(bool)$pr,'pr'=>$pr];
}

/* Operations workflow helpers (schema v5). */
function op_status_label(string $status): string {
    return [
        'pending'=>'รอพนักงาน','assigned'=>'รอ PR ตอบรับ','accepted'=>'PR รับงานแล้ว',
        'in_service'=>'กำลังให้บริการ','completed'=>'เสร็จสิ้น','cancelled'=>'ยกเลิก','rejected'=>'PR ปฏิเสธ'
    ][$status]??$status;
}
function op_find_pr(array $d,int $id): ?array { foreach($d['prs'] as $p) if((int)$p['id']===$id) return $p; return null; }
function op_find_table(array $d,int $id): ?array { foreach($d['tables'] as $t) if((int)$t['id']===$id) return $t; return null; }
function op_find_checkin(array $d,int $id): ?array { foreach($d['checkins'] as $c) if((int)$c['id']===$id) return $c; return null; }
function op_notify(array &$d,?int $userId,string $role,string $type,string $message,array $meta=[]): void {
    $d['notifications'][]=[
        'id'=>next_id($d['notifications']??[]),'user_id'=>$userId,'role'=>$role,'type'=>$type,
        'message'=>$message,'meta'=>$meta,'created_at'=>date('c'),'read_at'=>null
    ];
}
function op_acknowledge_notification(array &$d,array $u,int $notificationId): bool {
    if($notificationId<=0)return false;
    if(!isset($d['notifications'])||!is_array($d['notifications']))$d['notifications']=[];
    foreach($d['notifications'] as &$row){
        if((int)($row['id']??0)!==$notificationId)continue;
        $uid=(int)($row['user_id']??0);$role=(string)($row['role']??'');
        $recipient=($uid>0&&$uid===(int)($u['id']??0))||($uid===0&&$role===(string)($u['role']??''));
        if(!$recipient||!empty($row['read_at']))break;
        $readBy=array_map('intval',is_array($row['read_by']??null)?$row['read_by']:[]);
        if(!in_array((int)$u['id'],$readBy,true))$readBy[]=(int)$u['id'];
        $row['read_by']=$readBy;
        unset($row);
        return true;
    }
    unset($row);
    return false;
}
function op_unread_notifications(array $d,array $u): array {
    return array_values(array_filter($d['notifications']??[],function($n)use($u){
        if(!empty($n['read_at'])) return false;
        $readBy=array_map('intval',is_array($n['read_by']??null)?$n['read_by']:[]);
        if(in_array((int)($u['id']??0),$readBy,true))return false;
        $uid=(int)($n['user_id']??0);$role=(string)($n['role']??'');
        return ($uid>0 && $uid===(int)$u['id']) || ($uid===0 && $role===(string)$u['role']);
    }));
}

/* Night Operations helpers (schema v7). */
function reservation_status_label(string $status): string {
    return ['booked'=>'จองแล้ว','waitlist'=>'Waitlist','confirmed'=>'ยืนยันแล้ว','seated'=>'นั่งโต๊ะแล้ว','cancelled'=>'ยกเลิก','no_show'=>'ไม่มา'][ $status ] ?? $status;
}
function find_reservation(array $d,int $id): ?array {foreach($d['reservations']??[] as $r)if((int)$r['id']===$id)return $r;return null;}
function active_checkin_for_table(array $d,int $tableId): ?array {foreach(array_reverse($d['checkins']??[]) as $c)if((int)($c['table_id']??0)===$tableId&&!in_array((string)($c['status']??''),['completed','cancelled'],true))return $c;return null;}

/* Global Light/Dark Theme Engine (v1.12.1).
 * Injects the shared theme assets only into HTML pages. API/media/download routes
 * are deliberately excluded so binary and JSON responses are never modified.
 */
function mr_theme_asset_base(): string {
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
    if(preg_match('#^(.*?/it)(?:/|$)#i',$script,$m))return rtrim((string)$m[1],'/');
    $dir=rtrim(str_replace('\\','/',dirname($script)),'/');
    if(basename($dir)==='custumers')$dir=rtrim(str_replace('\\','/',dirname($dir)),'/');
    return ($dir===''||$dir==='.'||$dir==='/')?'':$dir;
}
function mr_branding_media_prefix(): string {return 'storage/customer-web-media/system-branding/';}
function mr_branding_key(string $type): string {
    $map=['sidebar'=>'brand_sidebar_logo','favicon'=>'brand_favicon','favicon_customer'=>'brand_favicon_customer','favicon_admin'=>'brand_favicon_admin','favicon_checkin'=>'brand_favicon_checkin'];
    return (string)($map[$type]??'');
}
function mr_branding_asset_path(string $type,?array $data=null): string {
    $key=mr_branding_key($type);if($key==='')return '';
    if($data===null){try{$data=db_load();}catch(Throwable $e){$data=[];}}
    $settings=is_array($data['settings']??null)?$data['settings']:[];$hasScoped=array_key_exists($key,$settings);$rel=trim((string)($settings[$key]??''));
    /* v1.27.5 had one global favicon. Preserve it for Customer/Admin only until that scope is explicitly saved/reset. */
    if(!$hasScoped&&in_array($type,['favicon_customer','favicon_admin'],true))$rel=trim((string)($settings['brand_favicon']??''));
    if($rel===''||strpos(str_replace('\\\\','/',$rel),mr_branding_media_prefix())!==0)return '';
    $root=realpath(__DIR__.'/../storage/customer-web-media/system-branding');$file=realpath(__DIR__.'/../'.$rel);
    if(!$root||!$file||!is_file($file)||strpos($file,$root.DIRECTORY_SEPARATOR)!==0)return '';
    return $rel;
}
function mr_branding_asset_url(string $type,?array $data=null): string {
    $rel=mr_branding_asset_path($type,$data);if($rel==='')return '';
    return mr_theme_asset_base().'/brand-media.php?type='.rawurlencode($type).'&v='.substr(sha1($rel),0,12);
}
function mr_branding_favicon_scope(): string {
    $path=strtolower(str_replace('\\\\','/',(string)($_SERVER['SCRIPT_NAME']??'')));$script=basename($path);
    if(strpos($path,'/custumers/')!==false)return 'customer';
    if(in_array($script,['time.php','employee-time.php'],true))return 'checkin';
    if($script==='pr.php'&&(string)($_GET['source']??'')==='pwa')return 'checkin';
    if($script==='login.php'&&((string)($_GET['pwa']??'')==='1'||(string)($_GET['return_to']??'')==='time'))return 'checkin';
    if($script==='setup-pin.php'&&(string)($_GET['return_to']??'')==='time')return 'checkin';
    return 'admin';
}
function mr_theme_install(): void {
    static $installed=false;if($installed)return;$installed=true;
    $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
    if(in_array($script,['ops-api.php','attendance-evidence.php','pr-attendance-evidence.php','pr-media.php','brand-media.php','sales-photo.php','logout.php'],true))return;
    if($script==='payroll-attendance.php' && isset($_GET['export']))return;
    $base=mr_theme_asset_base();$ver=(string)(app_config()['version']??'1.12.1');
    try{$brandingData=db_load();}catch(Throwable $e){$brandingData=[];}
    $faviconScope=mr_branding_favicon_scope();$favicon=mr_branding_asset_url('favicon_'.$faviconScope,$brandingData);
    if($favicon==='')$favicon=$base.'/assets/icons/mrbar-time-192.png?v='.$ver;
    $staffTimeScripts=['time.php','employee-time.php','employee-calendar.php','employee-income.php','pr.php','pr-calendar.php','pr-jobs.php'];
    $isStaffTime=in_array($script,$staffTimeScripts,true);
    $isEmbedded=((string)($_GET['embed']??'')==='1');
    $branchUi=(!$isStaffTime&&!$isEmbedded&&!db_is_customer_request()&&current_user())?branch_switcher_html($brandingData):'';
    $approvalUi=false;$approvalUser=current_user();
    $timeStaffNotificationsEnabled=(string)($brandingData['settings']['time_staff_notifications']??'1')==='1';
    if($timeStaffNotificationsEnabled&&$approvalUser&&!$isEmbedded&&!db_is_customer_request()){
        $approvalUi=($approvalUser['role']??'')==='admin'||count(workforce_user_direct_report_ids($brandingData,$approvalUser))>0;
        foreach(['leave.manage','workforce.exceptions.manage','attendance.manage','substitute.manage','payroll.manage'] as $permission){if(user_can($approvalUser,$permission,$brandingData)){$approvalUi=true;break;}}
    }
    $approvalPanel=$approvalUi?'<aside class="mr-approval-toast" id="mrApprovalToast" data-user="'.(int)$approvalUser['id'].'" data-endpoint="'.h($base.'/api/approval-notifications.php').'" hidden><div class="mr-approval-toast-head"><span class="mr-approval-bell" aria-hidden="true">✓</span><div><b>รายการรออนุมัติ</b><small data-approval-count>กำลังตรวจสอบ…</small></div><button type="button" data-approval-dismiss aria-label="รับทราบรายการแจ้งเตือน">รับทราบ</button></div><div class="mr-approval-toast-items" data-approval-items></div><a class="mr-approval-toast-link" href="'.h($base.'/hr-approval-center.php').'">เปิดศูนย์อนุมัติ <span aria-hidden="true">→</span></a></aside>':'';
    $notificationUser=current_user();$notificationEnabled=$notificationUser&&!$isEmbedded&&!db_is_customer_request();
    $notificationPanel=$notificationEnabled?'<div class="mr-live-notifications" id="mrLiveNotifications" data-enabled="'.((($brandingData['settings']['reservation_staff_notifications']??'1')==='1')?'1':'0').'" data-endpoint="'.h($base.'/api/notifications-feed.php').'" data-ack-endpoint="'.h($base.'/api/notification-ack.php').'" data-csrf="'.h(csrf_token()).'" data-after-id="0" data-desktop="'.((($brandingData['settings']['notification_desktop']??'0')==='1')?'1':'0').'" data-sound="'.((($brandingData['settings']['notification_sound']??'0')==='1')?'1':'0').'" aria-live="polite" aria-atomic="false"></div>':'';
    $approvalCss=($approvalUi?'<link rel="stylesheet" href="'.h($base).'/assets/approval-notice.css?v=14880">':'').'<link rel="stylesheet" href="'.h($base).'/assets/process-confirm-v14881.css?v=14886">';
    $branchUi.='<script src="'.h($base).'/assets/process-confirm-v14881.js?v=14881" defer></script>'.($script==='employees.php'?'<script src="'.h($base).'/assets/employee-supervision-v14881.js?v=14886" defer></script>':'');
    ob_start(function($html)use($base,$ver,$favicon,$branchUi,$approvalUi,$approvalPanel,$approvalCss,$isEmbedded,$notificationEnabled,$notificationPanel){
        if(!is_string($html)||stripos($html,'<html')===false||stripos($html,'</head>')===false)return $html;
        if(stripos($html,'data-mr-theme-engine')!==false)return $html;
        $embeddedCss=$isEmbedded?'<link rel="stylesheet" href="'.h($base).'/assets/hr-embedded-v14880.css?v=14880">':'';
        $notificationCss=$notificationEnabled?'<link rel="stylesheet" href="'.h($base).'/assets/live-notifications-v14895.css?v=14917">':'';
        $pre='<meta name="color-scheme" content="dark light"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="MR BAR TIME"><link rel="icon" href="'.h($favicon).'" data-mr-brand-favicon><link rel="shortcut icon" href="'.h($favicon).'" data-mr-brand-favicon-shortcut><link rel="manifest" href="'.h($base).'/pwa-manifest.php?v='.h($ver).'" data-mr-pwa-manifest><link rel="apple-touch-icon" sizes="180x180" href="'.h($base).'/assets/icons/mrbar-time-180.png?v='.h($ver).'"><script data-mr-theme-engine="pre">(function(){try{var t=localStorage.getItem("mrbar_theme");if(t!=="light"&&t!=="dark")t="dark";document.documentElement.setAttribute("data-mr-theme",t);}catch(e){document.documentElement.setAttribute("data-mr-theme","dark");}})();</script><link rel="stylesheet" href="'.h($base).'/assets/typography.css?v='.h($ver).'" data-mr-typography><link rel="stylesheet" href="'.h($base).'/assets/theme.css?v='.h($ver).'"><link rel="stylesheet" href="'.h($base).'/assets/pwa-time.css?v='.h($ver).'" data-mr-pwa-style><link rel="stylesheet" href="'.h($base).'/assets/branch-switcher-v1300.css?v=1301" data-mr-branch-style><link rel="stylesheet" href="'.h($base).'/assets/mr-loading-v14871.css?v=14871" data-mr-loading-style>'.$approvalCss.$notificationCss.$embeddedCss;
        $out=preg_replace('/<\/head>/i',$pre.'</head>',$html,1);
        if(!is_string($out))$out=$html;
        $loader='<script data-mr-loading-early>(function(){var r=document.documentElement;r.classList.add("mr-loading-init");var done=false;function finish(){if(done)return;done=true;setTimeout(function(){r.classList.remove("mr-loading-init");},180);}if(document.readyState==="complete")finish();else window.addEventListener("load",finish,{once:true});setTimeout(finish,15000);})();</script><div class="mr-page-loader" id="mrPageLoader" role="status" aria-live="polite" aria-hidden="false"><div class="mr-page-loader-card"><span class="mr-loader-ring" aria-hidden="true"></span><b>กำลังโหลดระบบ</b><small>กรุณารอสักครู่</small></div></div><div class="mr-async-loader" id="mrAsyncLoader" role="status" aria-live="polite" aria-hidden="true"><span></span><b>กำลังโหลดข้อมูล...</b></div>';
        $tmp=preg_replace('/(<body\\b[^>]*>)/i','$1'.$loader.$approvalPanel.$notificationPanel,$out,1);
        if(is_string($tmp))$out=$tmp;
        if(stripos($out,'</body>')!==false){
        $notificationJs=$notificationEnabled?'<script src="'.h($base).'/assets/live-notifications-v14895.js?v=14917" defer></script>':'';
        $js=$branchUi.'<script data-mr-theme-engine="main" src="'.h($base).'/assets/theme.js?v='.h($ver).'"></script><script data-mr-loading="1" src="'.h($base).'/assets/mr-loading-v14871.js?v=14871" defer></script><script data-mr-pwa="1" src="'.h($base).'/assets/pwa-time.js?v='.h($ver).'" defer></script><script src="'.h($base).'/assets/branch-switcher-v1300.js?v=1300" defer></script>'.($approvalUi?'<script src="'.h($base).'/assets/approval-notice.js?v=14880" defer></script>':'').$notificationJs;
            $tmp=preg_replace('/<\/body>/i',$js.'</body>',$out,1);
            if(is_string($tmp))$out=$tmp;
        }
        return $out;
    });
}
mr_theme_install();

