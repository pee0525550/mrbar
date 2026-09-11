<?php
declare(strict_types=1);
$config=require __DIR__.'/../config/app.php';
date_default_timezone_set($config['timezone']);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/db.php';
require_once __DIR__.'/permissions.php';
require_once __DIR__.'/branches.php';
require_once __DIR__.'/workforce.php';
try { db_auto_migrate(); } catch(Throwable $e) { http_response_code(500); exit('MR BAR storage error. Open preflight.php for diagnostics.'); }
try { if(function_exists('workforce_mark_missing_checkouts')) workforce_mark_missing_checkouts(); } catch(Throwable $e) { /* housekeeping must never block the app */ }
function app_config():array{global $config;return $config;}
function current_user():?array{
    $session=$_SESSION['user']??null;if(!is_array($session))return null;
    static $fresh=null;$uid=(int)($session['id']??0);
    if($fresh===null||((int)($fresh['id']??0)!==$uid)){
        $fresh=$session;
        try{
            $global=db_load_global();
            foreach($global['users']??[] as $row){
                if((int)($row['id']??0)!==$uid)continue;
                foreach(['username','role','display_name','super_admin','branch_ids','active'] as $key){
                    if(array_key_exists($key,$row))$fresh[$key]=$row[$key];
                }
                $_SESSION['user']=$fresh;break;
            }
        }catch(Throwable $e){}
    }
    return $fresh;
}
function require_roles(string ...$roles):array{$u=current_user();if(!$u||!in_array($u['role'],$roles,true)){header('Location:login.php');exit;}return $u;}
function h(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function next_id(array $rows):int{return $rows?max(array_map(fn($r)=>(int)$r['id'],$rows))+1:1;}
function ticket():string{return 'CHK-'.date('ymd-His').'-'.random_int(100,999);}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));return $_SESSION['csrf'];}
function csrf_check():void{if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);exit('Security token expired. Please reload and try again.');}}
function user_pr(array $d,int $userId):?array{foreach($d['prs'] as $p){if((int)($p['user_id']??0)===$userId)return $p;}return null;}
function attendance_open(array $d,int $prId):?array{foreach(array_reverse($d['attendance']) as $a){if((int)$a['pr_id']===$prId && empty($a['check_out']))return $a;}return null;}
function role_home(string $role):string{try{$d=db_load();$home=(string)($d['roles'][$role]['home']??'');if(in_array($home,['admin.php','dashboard.php','pr.php','employee-time.php','employee-calendar.php','night-ops.php','admin-tools.php'],true))return $home;}catch(Throwable $e){}return $role==='pr'?'pr.php':($role==='admin'?'admin.php':'dashboard.php');}

/* PWA/login return helpers. Only named, whitelisted destinations are accepted. */
function login_return_key():string{
    $key=trim((string)($_POST['return_to']??$_GET['return_to']??''));
    return in_array($key,['time','income'],true)?$key:'';
}
function login_success_target(array $u,string $returnKey=''):string{
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
    $token=(string)($_COOKIE[trusted_cookie_name()]??'');
    if($token===''||strlen($token)<32)return null;
    $hash=hash('sha256',$token);
    foreach($d['users'] as $u){
        if(empty($u['active']))continue;
        foreach(($u['trusted_devices']??[]) as $idx=>$dev){
            if(hash_equals((string)($dev['token_hash']??''),$hash)){
                return ['user'=>$u,'device'=>$dev,'device_index'=>$idx,'token_hash'=>$hash];
            }
        }
    }
    return null;
}
function register_trusted_device(int $userId,string $pin):void{
    if(!preg_match('/^\d{6}$/',$pin))throw new RuntimeException('PIN ต้องเป็นตัวเลข 6 หลัก');
    $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);$pinHash=password_hash($pin,PASSWORD_DEFAULT);
    db_mutate(function($d)use($userId,$tokenHash,$pinHash){
        foreach($d['users'] as &$u){if((int)$u['id']!==$userId)continue;
            $devices=array_values($u['trusted_devices']??[]);
            /* Keep max 5 remembered devices per account. */
            if(count($devices)>=5)$devices=array_slice($devices,-4);
            $devices[]=['token_hash'=>$tokenHash,'pin_hash'=>$pinHash,'created_at'=>date('c'),'last_used_at'=>date('c'),'failed_attempts'=>0,'locked_until'=>null,'ua_hash'=>ua_hash()];
            $u['trusted_devices']=$devices;break;
        }unset($u);
        $d['audit'][]=['at'=>date('c'),'action'=>'trusted_device_registered','user_id'=>$userId];
        return $d;
    });
    set_trusted_cookie($token,30);
}
function forget_current_device():void{
    $token=(string)($_COOKIE[trusted_cookie_name()]??'');
    if($token!==''){
        $hash=hash('sha256',$token);
        try{db_mutate(function($d)use($hash){foreach($d['users'] as &$u){$u['trusted_devices']=array_values(array_filter($u['trusted_devices']??[],fn($dev)=>!hash_equals((string)($dev['token_hash']??''),$hash)));}unset($u);return $d;});}catch(Throwable $e){}
    }
    clear_trusted_cookie();
}
function establish_session(array $u):void{
    session_regenerate_id(true);
    $_SESSION['user']=['id'=>(int)$u['id'],'username'=>(string)$u['username'],'role'=>(string)$u['role'],'display_name'=>(string)$u['display_name'],'super_admin'=>!empty($u['super_admin']),'branch_ids'=>array_values(array_map('intval',$u['branch_ids']??[1])),'active'=>!empty($u['active'])];
}
function pin_login(string $pin):array{
    if(!preg_match('/^\d{6}$/',$pin))return ['ok'=>false,'message'=>'กรุณากรอก PIN 6 หลัก'];
    $d=db_load();$remembered=remembered_device($d);
    if(!$remembered)return ['ok'=>false,'message'=>'ไม่พบอุปกรณ์ที่จดจำ กรุณาเข้าสู่ระบบด้วยรหัสผ่าน'];
    $u=$remembered['user'];$dev=$remembered['device'];$hash=$remembered['token_hash'];
    $lockedUntil=(string)($dev['locked_until']??'');
    if($lockedUntil!=='' && strtotime($lockedUntil)>time()){
        $mins=max(1,(int)ceil((strtotime($lockedUntil)-time())/60));
        return ['ok'=>false,'message'=>'PIN ถูกล็อกชั่วคราว กรุณารอประมาณ '.$mins.' นาที'];
    }
    if(!password_verify($pin,(string)($dev['pin_hash']??''))){
        db_mutate(function($d)use($u,$hash){foreach($d['users'] as &$row){if((int)$row['id']!==(int)$u['id'])continue;foreach(($row['trusted_devices']??[]) as &$device){if(!hash_equals((string)($device['token_hash']??''),$hash))continue;$n=(int)($device['failed_attempts']??0)+1;$device['failed_attempts']=$n;if($n>=5){$device['locked_until']=date('c',time()+900);$device['failed_attempts']=0;}break;}unset($device);break;}unset($row);return $d;});
        return ['ok'=>false,'message'=>'PIN ไม่ถูกต้อง'];
    }
    db_mutate(function($d)use($u,$hash){foreach($d['users'] as &$row){if((int)$row['id']!==(int)$u['id'])continue;foreach(($row['trusted_devices']??[]) as &$device){if(hash_equals((string)($device['token_hash']??''),$hash)){$device['failed_attempts']=0;$device['locked_until']=null;$device['last_used_at']=date('c');break;}}unset($device);break;}unset($row);$d['audit'][]=['at'=>date('c'),'action'=>'pin_login','user_id'=>(int)$u['id']];return $d;});
    establish_session($u);
    return ['ok'=>true,'user'=>$u];
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
function op_unread_notifications(array $d,array $u): array {
    return array_values(array_filter($d['notifications']??[],function($n)use($u){
        if(!empty($n['read_at'])) return false;
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
    $branchUi=(!db_is_customer_request()&&current_user())?branch_switcher_html($brandingData):'';
    ob_start(function($html)use($base,$ver,$favicon,$branchUi){
        if(!is_string($html)||stripos($html,'<html')===false||stripos($html,'</head>')===false)return $html;
        if(stripos($html,'data-mr-theme-engine')!==false)return $html;
        $pre='<meta name="color-scheme" content="dark light"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="MR BAR TIME"><link rel="icon" href="'.h($favicon).'" data-mr-brand-favicon><link rel="shortcut icon" href="'.h($favicon).'" data-mr-brand-favicon-shortcut><link rel="manifest" href="'.h($base).'/pwa-manifest.php?v='.h($ver).'" data-mr-pwa-manifest><link rel="apple-touch-icon" sizes="180x180" href="'.h($base).'/assets/icons/mrbar-time-180.png?v='.h($ver).'"><script data-mr-theme-engine="pre">(function(){try{var t=localStorage.getItem("mrbar_theme");if(t!=="light"&&t!=="dark")t="dark";document.documentElement.setAttribute("data-mr-theme",t);}catch(e){document.documentElement.setAttribute("data-mr-theme","dark");}})();</script><link rel="stylesheet" href="'.h($base).'/assets/typography.css?v='.h($ver).'" data-mr-typography><link rel="stylesheet" href="'.h($base).'/assets/theme.css?v='.h($ver).'"><link rel="stylesheet" href="'.h($base).'/assets/pwa-time.css?v='.h($ver).'" data-mr-pwa-style><link rel="stylesheet" href="'.h($base).'/assets/branch-switcher-v1300.css?v=1300" data-mr-branch-style>';
        $out=preg_replace('/<\/head>/i',$pre.'</head>',$html,1);
        if(!is_string($out))$out=$html;
        if(stripos($out,'</body>')!==false){
            $js=$branchUi.'<script data-mr-theme-engine="main" src="'.h($base).'/assets/theme.js?v='.h($ver).'"></script><script data-mr-pwa="1" src="'.h($base).'/assets/pwa-time.js?v='.h($ver).'" defer></script><script src="'.h($base).'/assets/branch-switcher-v1300.js?v=1300" defer></script>';
            $tmp=preg_replace('/<\/body>/i',$js.'</body>',$out,1);
            if(is_string($tmp))$out=$tmp;
        }
        return $out;
    });
}
mr_theme_install();

