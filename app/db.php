<?php
declare(strict_types=1);
if(!function_exists('permission_default_roles')) require_once __DIR__.'/permissions.php';

const MRBAR_SCHEMA_VERSION = 28;
function db_primary_path(): string { return __DIR__.'/../storage/data.php'; }
function db_runtime_path(): string { return __DIR__.'/../storage/runtime-data.php'; }
function db_path(): string { return is_file(db_runtime_path()) ? db_runtime_path() : db_primary_path(); }
function db_default(): array {
    return [
        'users'=>[], 'tables'=>[], 'prs'=>[], 'checkins'=>[], 'attendance'=>[], 'notifications'=>[],
        'shifts'=>[], 'service_calls'=>[], 'reservations'=>[], 'customers'=>[], 'daily_closes'=>[], 'branches'=>[], 'leave_requests'=>[], 'customer_media'=>[], 'employees'=>[], 'time_correction_requests'=>[], 'substitute_requests'=>[], 'shift_templates'=>[], 'roster_batches'=>[], 'privacy_consents'=>[], 'account_invites'=>[],
        'roles'=>permission_default_roles(),
        'settings'=>[
            'shop_name'=>'MR BAR','shop_phone'=>'','maintenance'=>'0','attendance_face_required'=>'1',
            'staff_auto_refresh'=>'1','pr_auto_refresh'=>'1','reservation_enabled'=>'1','reservation_note'=>'',
            'shop_address'=>'','shop_open_time'=>'18:00','shop_close_time'=>'02:00','timezone'=>'Asia/Bangkok',
            'currency'=>'THB','table_auto_release'=>'1','allow_staff_force_complete'=>'1','customer_call_enabled'=>'1',
            'notification_sound'=>'1','notification_desktop'=>'0','notification_retention_days'=>'30',
            'dashboard_refresh_seconds'=>'15','dashboard_compact_mode'=>'0','dashboard_show_revenue'=>'0',
            'reservation_max_party'=>'20','reservation_advance_days'=>'30','reservation_require_phone'=>'1',
            'attendance_late_grace_minutes'=>'15','attendance_camera_required'=>'1','trusted_device_days'=>'30',
            'session_timeout_minutes'=>'480','audit_retention_days'=>'180',
            'attendance_gps_required'=>'1','attendance_geofence_mode'=>'strict','attendance_default_radius_m'=>'200',
            'attendance_max_accuracy_m'=>'120','attendance_evidence_required'=>'1','attendance_evidence_retention_days'=>'90',
            'leave_request_enabled'=>'1','leave_max_days_per_request'=>'31',
            'attendance_missing_checkout_grace_minutes'=>'120','attendance_auto_offline_missing'=>'1','attendance_provisional_close_enabled'=>'1','attendance_correction_request_enabled'=>'1',
            'substitute_request_enabled'=>'1','substitute_external_allowed'=>'1',
            'customer_web_enabled'=>'1','customer_web_show_staff_login'=>'0','customer_web_floating_booking'=>'1',
            'customer_web_hero_badge'=>'LIVE EXPERIENCE · MR BAR','customer_web_hero_title'=>'คืนนี้ให้เป็นเรื่องของเรา',
            'customer_web_hero_accent'=>'สนุกให้สุด ในแบบของคุณ','customer_web_hero_subtitle'=>'บรรยากาศ แสงสี PR และบริการที่เชื่อมต่อกับระบบร้านแบบเรียลไทม์',
            'customer_web_primary_cta'=>'จองโต๊ะ / Check-in','customer_web_secondary_cta'=>'ดู PR คืนนี้',
            'customer_web_hero_image'=>'','customer_web_show_live'=>'1','customer_web_show_pr'=>'1','customer_web_show_zones'=>'1',
            'customer_web_show_promo'=>'1','customer_web_show_gallery'=>'1','customer_web_show_location'=>'1','customer_web_show_policy'=>'1',
            'customer_web_promo_badge'=>'TONIGHT SPECIAL','customer_web_promo_title'=>'ค่ำคืนนี้มีอะไรพิเศษ',
            'customer_web_promo_body'=>'ปรับข้อความโปรโมชั่นจากหลังบ้านได้ทันที โดยไม่ต้องแก้หน้าเว็บ',
            'customer_web_promo_cta'=>'จองโต๊ะคืนนี้','customer_web_promo_url'=>'',
            'customer_web_gallery_title'=>'บรรยากาศของ MR BAR','customer_web_gallery_urls'=>'',
            'customer_web_contact_label'=>'ติดต่อร้าน','customer_web_line_url'=>'','customer_web_location_note'=>'',
            'customer_web_policy_text'=>'กรุณาติดต่อร้านสำหรับเงื่อนไขการจอง โต๊ะ VIP และโปรโมชั่นประจำวัน',
            'customer_privacy_enabled'=>'1','customer_privacy_policy_version'=>'2026-09-03',
            'customer_privacy_banner_title'=>'ความเป็นส่วนตัวและคุกกี้',
            'customer_privacy_banner_text'=>'เราใช้คุกกี้ที่จำเป็นเพื่อให้เว็บไซต์ทำงาน และจะขออนุญาตก่อนใช้คุกกี้หรือบริการเสริม เช่น Google Maps',
            'customer_privacy_notice'=>'MR BAR เก็บข้อมูลที่ท่านให้แก่เรา เช่น ชื่อ เบอร์โทร ข้อมูลการจอง และข้อมูล Check-in เพื่อดำเนินการตามคำขอ ให้บริการ ติดต่อกลับ ดูแลความปลอดภัย และบริหารงานที่เกี่ยวข้อง โดยจะใช้ข้อมูลตามวัตถุประสงค์ที่แจ้งไว้ เก็บเท่าที่จำเป็น และเปิดเผยเฉพาะเมื่อจำเป็นต่อการให้บริการหรือเป็นไปตามกฎหมาย ท่านสามารถติดต่อร้านเพื่อขอใช้สิทธิเกี่ยวกับข้อมูลส่วนบุคคลได้',
            'customer_cookie_policy'=>'คุกกี้ที่จำเป็นใช้สำหรับ Session ความปลอดภัย และการทำงานพื้นฐานของเว็บไซต์ ส่วนคุกกี้/บริการเชิงฟังก์ชัน เช่น Google Maps จะไม่โหลดก่อนที่ท่านจะอนุญาต หมวด Analytics และ Marketing จะถูกปิดไว้เป็นค่าเริ่มต้นจนกว่าจะมีการเชื่อมต่อบริการดังกล่าวและได้รับความยินยอม',
            'customer_privacy_contact'=>'','customer_cookie_functional_enabled'=>'1','customer_cookie_analytics_enabled'=>'0','customer_cookie_marketing_enabled'=>'0',
            'customer_privacy_consent_days'=>'183','customer_privacy_log_retention_days'=>'365'
        ],
        'audit'=>[], 'meta'=>['schema'=>MRBAR_SCHEMA_VERSION]
    ];
}
function db_migrate_legacy_array(array $d): array {
    $incomingSchema=(int)($d['meta']['schema']??0);
    $d=array_replace_recursive(db_default(),$d);
    foreach($d['users'] as &$u){
        if(!isset($u['trusted_devices']) || !is_array($u['trusted_devices'])) $u['trusted_devices']=[];
        if(!array_key_exists('active',$u)) $u['active']=1;
        if(!isset($u['permission_overrides']) || !is_array($u['permission_overrides'])) $u['permission_overrides']=[];
        if(!array_key_exists('super_admin',$u)) $u['super_admin']=0;
    } unset($u);
    if(!isset($d['roles']) || !is_array($d['roles'])) $d['roles']=permission_default_roles();
    foreach(permission_default_roles() as $rk=>$rv){
        if(!isset($d['roles'][$rk])) $d['roles'][$rk]=$rv;
        else { $d['roles'][$rk]['name']=$d['roles'][$rk]['name']??$rv['name']; $d['roles'][$rk]['description']=$d['roles'][$rk]['description']??$rv['description']; $d['roles'][$rk]['builtin']=1; $d['roles'][$rk]['home']=$d['roles'][$rk]['home']??$rv['home']; if(!isset($d['roles'][$rk]['permissions'])||!is_array($d['roles'][$rk]['permissions']))$d['roles'][$rk]['permissions']=$rv['permissions']; else foreach($rv['permissions'] as $pk=>$pv) if(!array_key_exists($pk,$d['roles'][$rk]['permissions'])) $d['roles'][$rk]['permissions'][$pk]=$pv; }
    }
    $hasSuper=false;foreach($d['users'] as $x)if(!empty($x['super_admin'])&&!empty($x['active'])){$hasSuper=true;break;}
    if(!$hasSuper){foreach($d['users'] as &$x){if(($x['role']??'')==='admin'&&!empty($x['active'])){$x['super_admin']=1;break;}}unset($x);}
    foreach($d['prs'] as &$p){
        if(!array_key_exists('user_id',$p)) $p['user_id']=null;
        if(!array_key_exists('status',$p)) $p['status']='offline';
        if(!array_key_exists('active',$p)) $p['active']=1;
        if(!array_key_exists('current_checkin_id',$p)) $p['current_checkin_id']=null;
        if(!isset($p['profile']) || !is_array($p['profile'])) $p['profile']=[];
        $profileDefaults=[
            'title'=>'','first_name'=>'','last_name'=>'','nickname'=>'','display_name'=>'','phone'=>'','email'=>'','line_id'=>'','birthday'=>'','gender'=>'',
            'address'=>'','emergency_name'=>'','emergency_relation'=>'','emergency_phone'=>'','bio'=>'','languages'=>'',
            'specialties'=>'','facebook'=>'','instagram'=>'','tiktok'=>'','profile_photo'=>'','cover_photo'=>'',
            'updated_at'=>null,'updated_by'=>null,
            'payroll_type'=>'daily','daily_rate'=>'0','hourly_rate'=>'0','monthly_salary'=>'0','overtime_rate'=>'0',
            'payroll_start_time'=>'18:00','payroll_standard_hours'=>'8',
            'pr_commission_rate'=>'0','pr_drink_rate'=>'0','pr_other_rate'=>'0','pr_compensation_note'=>''
        ];
        foreach($profileDefaults as $pk=>$pv) if(!array_key_exists($pk,$p['profile'])) $p['profile'][$pk]=$pv;
        if(trim((string)($p['profile']['display_name']??''))==='')$p['profile']['display_name']=(string)($p['name']??'');
    } unset($p);
    foreach($d['tables'] as &$t){
        if(!array_key_exists('active',$t)) $t['active']=1;
        if(!array_key_exists('zone',$t)) $t['zone']='Main';
        if(!array_key_exists('status',$t)) $t['status']='available';
        if(!array_key_exists('capacity',$t)) $t['capacity']=4;
    } unset($t);
    foreach($d['checkins'] as &$c){
        if(($c['status']??'')==='waiting') $c['status']='pending';
        foreach(['pr_id'=>null,'assigned_at'=>null,'accepted_at'=>null,'started_at'=>null,'completed_at'=>null,'rejected_at'=>null,'assigned_by'=>null,'reservation_id'=>null] as $k=>$v){if(!array_key_exists($k,$c))$c[$k]=$v;}
        if(!array_key_exists('updated_at',$c)) $c['updated_at']=$c['created_at']??date('c');
        if(!array_key_exists('service_note',$c)) $c['service_note']='';
        if(!array_key_exists('table_history',$c) || !is_array($c['table_history'])) $c['table_history']=[];
    } unset($c);
    foreach(['attendance','audit','notifications','shifts','service_calls','reservations','customers','daily_closes','branches','leave_requests','customer_media','employees','time_correction_requests','substitute_requests','shift_templates','roster_batches','privacy_consents','account_invites'] as $bucket){if(!isset($d[$bucket])||!is_array($d[$bucket]))$d[$bucket]=[];}

    if(!$d['branches']){
        $d['branches'][]=['id'=>1,'code'=>'MAIN','name'=>'MR BAR Main','address'=>(string)($d['settings']['shop_address']??''),'lat'=>null,'lng'=>null,'radius_m'=>(int)($d['settings']['attendance_default_radius_m']??200),'active'=>1,'created_at'=>date('c'),'updated_at'=>date('c')];
    }
    foreach($d['branches'] as &$b){
        foreach(['code'=>'BR','name'=>'Branch','address'=>'','lat'=>null,'lng'=>null,'radius_m'=>(int)($d['settings']['attendance_default_radius_m']??200),'active'=>1,'created_at'=>date('c'),'updated_at'=>date('c')] as $k=>$v) if(!array_key_exists($k,$b))$b[$k]=$v;
    } unset($b);
    foreach($d['prs'] as &$p){ if(!array_key_exists('branch_id',$p))$p['branch_id']=null; } unset($p);
    foreach($d['attendance'] as &$a){
        foreach(['branch_id'=>null,'checkin_lat'=>null,'checkin_lng'=>null,'checkin_accuracy_m'=>null,'checkin_distance_m'=>null,'checkin_evidence'=>'','checkout_lat'=>null,'checkout_lng'=>null,'checkout_accuracy_m'=>null,'checkout_distance_m'=>null,'checkout_evidence'=>''] as $k=>$v) if(!array_key_exists($k,$a))$a[$k]=$v;
        $stateDefault=empty($a['check_out'])?'open':'complete';
        foreach(['attendance_state'=>$stateDefault,'payroll_status'=>empty($a['check_out'])?'pending':'final','missing_checkout_marked_at'=>null,'planned_checkout_at'=>null,'checkout_source'=>empty($a['check_out'])?null:'employee','checkout_reason'=>'','checkout_note'=>'','checkout_corrected_by'=>null,'checkout_corrected_at'=>null,'provisional_checkout'=>0] as $k=>$v) if(!array_key_exists($k,$a))$a[$k]=$v;
        if(!empty($a['check_out']) && ($a['attendance_state']??'open')==='open')$a['attendance_state']='complete';
        if(!empty($a['check_out']) && ($a['payroll_status']??'pending')==='pending' && empty($a['provisional_checkout']))$a['payroll_status']='final';
    } unset($a);

    foreach($d['reservations'] as &$r){
        $defaults=['status'=>'booked','guest_name'=>'','phone'=>'','party_size'=>1,'date'=>date('Y-m-d'),'time'=>'18:00','table_id'=>null,'note'=>'','created_at'=>date('c'),'updated_at'=>date('c'),'seated_checkin_id'=>null,'source'=>'staff','customer_id'=>null];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$r))$r[$k]=$v;
    } unset($r);

    /* Branch-scoped Customer CRM foundation (schema v27). */
    $customerDefaults=[
        'id'=>0,'full_name'=>'','nickname'=>'','phone'=>'','phone_key'=>'','email'=>'','line_user_id'=>'',
        'birthday'=>'','tier'=>'standard','vip'=>0,'tags'=>[],'notes'=>'','preferred_sales_employee_id'=>null,
        'preferred_pr_id'=>null,'active'=>1,'marketing_consent'=>0,'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    $normalizeCustomerPhone=function($value): string {
        $digits=preg_replace('/\D+/','',(string)$value)??'';
        if(strlen($digits)===11&&substr($digits,0,2)==='66')$digits='0'.substr($digits,2);
        return substr($digits,0,20);
    };
    $customerByPhone=[];$maxCustomerId=0;
    foreach($d['customers'] as &$customer){
        foreach($customerDefaults as $k=>$v)if(!array_key_exists($k,$customer))$customer[$k]=$v;
        $customer['id']=max(1,(int)$customer['id']);$maxCustomerId=max($maxCustomerId,$customer['id']);
        $customer['phone_key']=$normalizeCustomerPhone($customer['phone_key']?:$customer['phone']);
        if(!is_array($customer['tags']))$customer['tags']=[];
        $customer['tags']=array_values(array_unique(array_filter(array_map('strval',$customer['tags']))));
        if(!in_array((string)$customer['tier'],['standard','silver','gold','platinum'],true))$customer['tier']='standard';
        $customer['vip']=!empty($customer['vip'])?1:0;$customer['active']=!empty($customer['active'])?1:0;
        $customer['marketing_consent']=!empty($customer['marketing_consent'])?1:0;
        if($customer['phone_key']!=='')$customerByPhone[$customer['phone_key']]=$customer['id'];
    }unset($customer);
    foreach($d['reservations'] as &$reservation){
        $phoneKey=$normalizeCustomerPhone($reservation['phone']??'');$customerId=max(0,(int)($reservation['customer_id']??0));
        if($customerId<=0&&$phoneKey!==''){
            if(isset($customerByPhone[$phoneKey]))$customerId=(int)$customerByPhone[$phoneKey];
            else{
                $customerId=++$maxCustomerId;$name=trim((string)($reservation['guest_name']??''));
                $customer=array_replace($customerDefaults,[
                    'id'=>$customerId,'full_name'=>$name,'nickname'=>$name,'phone'=>(string)($reservation['phone']??''),
                    'phone_key'=>$phoneKey,'preferred_sales_employee_id'=>$reservation['sales_employee_id']??null,
                    'created_at'=>(string)($reservation['created_at']??date('c')),'updated_at'=>(string)($reservation['updated_at']??date('c'))
                ]);
                $d['customers'][]=$customer;$customerByPhone[$phoneKey]=$customerId;
            }
            $reservation['customer_id']=$customerId;
        }
    }unset($reservation);

    foreach($d['daily_closes'] as &$c){if(!array_key_exists('closed_at',$c))$c['closed_at']=date('c');if(!array_key_exists('note',$c))$c['note']='';}unset($c);
    foreach($d['leave_requests'] as &$l){
        $defaults=['pr_id'=>0,'user_id'=>0,'type'=>'personal','start_date'=>date('Y-m-d'),'end_date'=>date('Y-m-d'),'portion'=>'full','reason'=>'','status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>''];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$l))$l[$k]=$v;
    }unset($l);

    foreach($d['time_correction_requests'] as &$r){
        $defaults=['id'=>0,'attendance_id'=>0,'employee_id'=>0,'pr_id'=>0,'user_id'=>0,'requested_checkout'=>'','reason'=>'','status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>''];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$r))$r[$k]=$v;
    }unset($r);
    foreach($d['substitute_requests'] as &$r){
        $defaults=['id'=>0,'requester_employee_id'=>0,'requester_pr_id'=>0,'user_id'=>0,'shift_id'=>0,'date'=>date('Y-m-d'),'original_employee_id'=>0,'original_pr_id'=>0,'substitute_type'=>'internal','substitute_employee_id'=>0,'substitute_pr_id'=>0,'external_name'=>'','external_phone'=>'','external_note'=>'','reason'=>'','status'=>'pending','requested_at'=>date('c'),'updated_at'=>date('c'),'reviewed_by'=>null,'reviewed_at'=>null,'admin_note'=>'','created_shift_id'=>0,'external_check_in'=>null,'external_check_out'=>null,'external_attendance_note'=>''];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$r))$r[$k]=$v;
    }unset($r);
    foreach($d['shifts'] as &$s){
        $defaults=['pr_id'=>0,'date'=>date('Y-m-d'),'start'=>'18:00','end'=>'02:00','status'=>'scheduled','type'=>'regular','branch_id'=>null,'note'=>'','created_by'=>null,'created_at'=>date('c'),'updated_at'=>date('c'),'substitution_id'=>0,'replaces_shift_id'=>0,'external_substitute_name'=>'','external_substitute_phone'=>'','shift_template_id'=>0,'roster_batch_id'=>0];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$s))$s[$k]=$v;
    }unset($s);

    /* Employee & Workforce Core (schema v18) */
    $employeeDefaults=[
        'id'=>0,'code'=>'','name'=>'','user_id'=>null,'pr_id'=>null,'position'=>'staff','department'=>'Operations','employment_type'=>'fulltime','employment_start_date'=>'','branch_id'=>null,'roster_team'=>'flex','active'=>1,'attendance_required'=>1,'identity_source'=>'employee_master',
        'attendance_policy'=>['gps_required'=>null,'camera_required'=>null,'evidence_required'=>null,'geofence_mode'=>'inherit','radius_m'=>null,'max_accuracy_m'=>null],
        'profile'=>['title'=>'','first_name'=>'','last_name'=>'','nickname'=>'','display_name'=>'','phone'=>'','email'=>'','line_id'=>'','birthday'=>'','gender'=>'','address'=>'','emergency_name'=>'','emergency_relation'=>'','emergency_phone'=>'','profile_photo'=>''],
        'payroll'=>['type'=>'daily','daily_rate'=>'0','hourly_rate'=>'0','monthly_salary'=>'0','overtime_rate'=>'0','start_time'=>'18:00','standard_hours'=>'8'],
        'pr_compensation'=>['commission_rate'=>'0','drink_rate'=>'0','other_rate'=>'0','note'=>''],
        'created_at'=>date('c'),'updated_at'=>date('c')
    ];
    foreach($d['employees'] as &$e){
        foreach($employeeDefaults as $k=>$v){if(!array_key_exists($k,$e))$e[$k]=$v;}
        if(!in_array((string)($e['roster_team']??'flex'),['day','night','flex'],true))$e['roster_team']='flex';
        if(!is_array($e['attendance_policy']))$e['attendance_policy']=$employeeDefaults['attendance_policy'];
        foreach($employeeDefaults['attendance_policy'] as $k=>$v)if(!array_key_exists($k,$e['attendance_policy']))$e['attendance_policy'][$k]=$v;
        if(!is_array($e['profile']))$e['profile']=$employeeDefaults['profile'];foreach($employeeDefaults['profile'] as $k=>$v)if(!array_key_exists($k,$e['profile']))$e['profile'][$k]=$v;
        if(trim((string)($e['profile']['display_name']??''))==='')$e['profile']['display_name']=(string)($e['name']??'');
        if(trim((string)($e['name']??''))==='')$e['name']=trim((string)($e['profile']['display_name']??''))?:trim((string)($e['profile']['first_name']??'').' '.(string)($e['profile']['last_name']??''));
        if(!is_array($e['payroll']))$e['payroll']=$employeeDefaults['payroll'];foreach($employeeDefaults['payroll'] as $k=>$v)if(!array_key_exists($k,$e['payroll']))$e['payroll'][$k]=$v;
        if(!is_array($e['pr_compensation']??null))$e['pr_compensation']=$employeeDefaults['pr_compensation'];foreach($employeeDefaults['pr_compensation'] as $k=>$v)if(!array_key_exists($k,$e['pr_compensation']))$e['pr_compensation'][$k]=$v;
    }unset($e);
    $nextEmployeeId=1;foreach($d['employees'] as $e)$nextEmployeeId=max($nextEmployeeId,(int)($e['id']??0)+1);
    $findEmployeeIndex=function($userId,$prId)use(&$d){foreach($d['employees'] as $i=>$e){if($prId&&((int)($e['pr_id']??0)===(int)$prId))return $i;if($userId&&((int)($e['user_id']??0)===(int)$userId))return $i;}return null;};
    foreach($d['prs'] as &$p){
        $uid=(int)($p['user_id']??0);$idx=$findEmployeeIndex($uid,(int)($p['id']??0));$pf=is_array($p['profile']??null)?$p['profile']:[];
        if($idx===null){$e=$employeeDefaults;$e['id']=$nextEmployeeId++;$e['code']=(string)($p['code']??('PR'.$e['id']));$e['name']=(string)($p['name']??'PR');$e['user_id']=$uid?:null;$e['pr_id']=(int)($p['id']??0);$e['position']='pr';$e['department']='PR / Floor';$e['employment_type']='daily';$e['branch_id']=$p['branch_id']??null;$e['active']=!empty($p['active'])?1:0;$e['attendance_required']=1;$e['profile']['phone']=(string)($pf['phone']??'');$e['profile']['email']=(string)($pf['email']??'');$e['profile']['birthday']=(string)($pf['birthday']??'');$e['profile']['address']=(string)($pf['address']??'');$e['profile']['emergency_name']=(string)($pf['emergency_name']??'');$e['profile']['emergency_phone']=(string)($pf['emergency_phone']??'');$e['profile']['profile_photo']=(string)($pf['profile_photo']??'');$e['payroll']=['type'=>(string)($pf['payroll_type']??'daily'),'daily_rate'=>(string)($pf['daily_rate']??'0'),'hourly_rate'=>(string)($pf['hourly_rate']??'0'),'monthly_salary'=>(string)($pf['monthly_salary']??'0'),'overtime_rate'=>(string)($pf['overtime_rate']??'0'),'start_time'=>(string)($pf['payroll_start_time']??'18:00'),'standard_hours'=>(string)($pf['payroll_standard_hours']??'8')];$e['pr_compensation']=['commission_rate'=>(string)($pf['pr_commission_rate']??'0'),'drink_rate'=>(string)($pf['pr_drink_rate']??'0'),'other_rate'=>(string)($pf['pr_other_rate']??'0'),'note'=>(string)($pf['pr_compensation_note']??'')];$d['employees'][]=$e;$idx=count($d['employees'])-1;}
        else{$d['employees'][$idx]['pr_id']=(int)($p['id']??0);if($uid)$d['employees'][$idx]['user_id']=$uid;if(($d['employees'][$idx]['code']??'')==='')$d['employees'][$idx]['code']=(string)($p['code']??'');if(($d['employees'][$idx]['name']??'')==='')$d['employees'][$idx]['name']=(string)($p['name']??'');$d['employees'][$idx]['position']='pr';if(empty($d['employees'][$idx]['branch_id'])&&!empty($p['branch_id']))$d['employees'][$idx]['branch_id']=$p['branch_id'];}
        $epf=&$d['employees'][$idx]['profile'];if(!is_array($epf))$epf=[];
        $legacyIdentity=['title'=>(string)($pf['title']??''),'first_name'=>(string)($pf['first_name']??''),'last_name'=>(string)($pf['last_name']??''),'nickname'=>(string)($pf['nickname']??''),'display_name'=>(string)($pf['display_name']??($p['name']??'')),'phone'=>(string)($pf['phone']??''),'email'=>(string)($pf['email']??''),'line_id'=>(string)($pf['line_id']??''),'birthday'=>(string)($pf['birthday']??''),'gender'=>(string)($pf['gender']??''),'address'=>(string)($pf['address']??''),'emergency_name'=>(string)($pf['emergency_name']??''),'emergency_relation'=>(string)($pf['emergency_relation']??''),'emergency_phone'=>(string)($pf['emergency_phone']??''),'profile_photo'=>(string)($pf['profile_photo']??'')];
        foreach($legacyIdentity as $k=>$v)if(trim((string)($epf[$k]??''))===''&&trim($v)!=='')$epf[$k]=$v;
        if(trim((string)($epf['display_name']??''))==='')$epf['display_name']=(string)($d['employees'][$idx]['name']??$p['name']??'');
        if(trim((string)($d['employees'][$idx]['name']??''))==='')$d['employees'][$idx]['name']=(string)($epf['display_name']??'');
        unset($epf);
        $p['employee_id']=(int)$d['employees'][$idx]['id'];
    }unset($p);
    foreach($d['users'] as $u){
        $uid=(int)($u['id']??0);if(!$uid)continue;$idx=$findEmployeeIndex($uid,0);if($idx!==null)continue;$role=(string)($u['role']??'staff');$e=$employeeDefaults;$e['id']=$nextEmployeeId++;$e['code']='EMP'.str_pad((string)$uid,3,'0',STR_PAD_LEFT);$e['name']=(string)($u['display_name']??$u['username']??('Employee '.$uid));$e['profile']['display_name']=$e['name'];$e['user_id']=$uid;$e['position']=$role==='admin'?'admin':($role==='pr'?'pr':($role==='sales'?'sales':'staff'));$e['department']=$role==='admin'?'Management':($role==='pr'?'PR / Floor':($role==='sales'?'Sales':'Operations'));$e['attendance_required']=$role==='admin'?0:1;$e['active']=!empty($u['active'])?1:0;$e['identity_source']='account_recovery';$d['employees'][]=$e;
    }
    if($incomingSchema<28){
        $usersById=[];foreach($d['users'] as $row)$usersById[(int)($row['id']??0)]=$row;
        $nextPrId=1;foreach($d['prs'] as $row)$nextPrId=max($nextPrId,(int)($row['id']??0)+1);
        foreach($d['employees'] as &$employee){
            $uid=(int)($employee['user_id']??0);$account=$usersById[$uid]??null;if(!$account)continue;
            $role=(string)($account['role']??'staff');$position=(string)($employee['position']??'staff');
            $autoProfile=(string)($employee['identity_source']??'')==='account_recovery'||preg_match('/^EMP\\d+$/',(string)($employee['code']??''));
            if($role==='sales'&&$autoProfile&&in_array($position,['staff','other'],true)){$employee['position']='sales';$employee['department']='Sales';$employee['identity_source']='account_recovery';}
            if($role==='pr'&&empty($employee['pr_id'])&&$autoProfile){
                $employee['position']='pr';$employee['department']='PR / Floor';$employee['identity_source']='account_recovery';
                $prCode=trim((string)($employee['code']??''))?:('PR'.$nextPrId);$base=$prCode;$suffix=2;
                $used=true;while($used){$used=false;foreach($d['prs'] as $existing)if(strcasecmp((string)($existing['code']??''),$prCode)===0){$used=true;$prCode=$base.'-'.$suffix++;break;}}
                $prId=$nextPrId++;$employee['pr_id']=$prId;
                $d['prs'][]=['id'=>$prId,'code'=>$prCode,'name'=>(string)($employee['name']??$account['display_name']??$prCode),'status'=>'offline','user_id'=>$uid,'active'=>!empty($employee['active'])?1:0,'current_checkin_id'=>null,'branch_id'=>$employee['branch_id']??null,'employee_id'=>(int)$employee['id'],'profile'=>is_array($employee['profile']??null)?$employee['profile']:[]];
            }
        }unset($employee);
    }
    $employeeIdFor=function($prId,$userId)use(&$d){foreach($d['employees'] as $e){if($prId&&((int)($e['pr_id']??0)===(int)$prId))return (int)$e['id'];if($userId&&((int)($e['user_id']??0)===(int)$userId))return (int)$e['id'];}return null;};
    foreach($d['attendance'] as &$a){if(!array_key_exists('employee_id',$a)||empty($a['employee_id']))$a['employee_id']=$employeeIdFor((int)($a['pr_id']??0),(int)($a['user_id']??0));if(!array_key_exists('policy_snapshot',$a))$a['policy_snapshot']=[];}unset($a);
    foreach($d['shifts'] as &$s){if(!array_key_exists('employee_id',$s)||empty($s['employee_id']))$s['employee_id']=$employeeIdFor((int)($s['pr_id']??0),(int)($s['user_id']??0));}unset($s);
    foreach($d['leave_requests'] as &$l){if(!array_key_exists('employee_id',$l)||empty($l['employee_id']))$l['employee_id']=$employeeIdFor((int)($l['pr_id']??0),(int)($l['user_id']??0));}unset($l);


    /* Shift Template & Auto Roster (schema v20) */
    foreach($d['shift_templates'] as &$t){
        $defaults=['id'=>0,'name'=>'Shift Template','team'=>'flex','start'=>'18:00','end'=>'02:00','type'=>'regular','branch_id'=>null,'days'=>[0,1,2,3,4,5,6],'note'=>'','active'=>1,'created_by'=>null,'created_at'=>date('c'),'updated_at'=>date('c')];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$t))$t[$k]=$v;
        if(!in_array((string)$t['team'],['day','night','flex'],true))$t['team']='flex';
        if(!in_array((string)$t['type'],['regular','half','event','standby'],true))$t['type']='regular';
        if(!is_array($t['days']))$t['days']=[0,1,2,3,4,5,6];
        $t['days']=array_values(array_unique(array_map('intval',array_filter($t['days'],function($v){return is_numeric($v)&&(int)$v>=0&&(int)$v<=6;}))));
        if(!$t['days'])$t['days']=[0,1,2,3,4,5,6];
    }unset($t);
    foreach($d['roster_batches'] as &$b){
        $defaults=['id'=>0,'template_id'=>0,'template_name'=>'','date_start'=>date('Y-m-d'),'date_end'=>date('Y-m-d'),'employee_ids'=>[],'created_shift_ids'=>[],'created_count'=>0,'skipped_conflict'=>0,'skipped_leave'=>0,'skipped_duplicate'=>0,'status'=>'active','created_by'=>null,'created_at'=>date('c'),'undone_by'=>null,'undone_at'=>null,'undo_count'=>0,'undo_skipped'=>0];
        foreach($defaults as $k=>$v)if(!array_key_exists($k,$b))$b[$k]=$v;
        if(!is_array($b['employee_ids']))$b['employee_ids']=[];
        if(!is_array($b['created_shift_ids']))$b['created_shift_ids']=[];
    }unset($b);

    foreach($d['customer_media'] as &$m){
        $mediaDefaults=['id'=>0,'path'=>'','original_name'=>'','mime'=>'image/jpeg','size'=>0,'width'=>0,'height'=>0,'slot'=>'library','alt'=>'','caption'=>'','sort_order'=>100,'position_x'=>50,'position_y'=>50,'zoom'=>100,'brightness'=>100,'contrast'=>100,'saturation'=>100,'blur'=>0,'overlay'=>20,'created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>null];
        foreach($mediaDefaults as $k=>$v)if(!array_key_exists($k,$m))$m[$k]=$v;
        if(!in_array((string)$m['slot'],['library','hero','promo','gallery','tonight','pr_section','zones','floor_preview','location','policy'],true))$m['slot']='library';
    }unset($m);

    /* Customer privacy / cookie consent log (schema v21) */
    foreach($d['privacy_consents'] as &$c){
        $consentDefaults=['id'=>'','policy_version'=>'','necessary'=>1,'functional'=>0,'analytics'=>0,'marketing'=>0,'source'=>'customer_web','created_at'=>date('c'),'ip_hash'=>'','ua_hash'=>''];
        foreach($consentDefaults as $k=>$v)if(!array_key_exists($k,$c))$c[$k]=$v;
        $c['necessary']=1;$c['functional']=!empty($c['functional'])?1:0;$c['analytics']=!empty($c['analytics'])?1:0;$c['marketing']=!empty($c['marketing'])?1:0;
    }unset($c);

    $d['meta']['schema']=MRBAR_SCHEMA_VERSION;
    $d['meta']['migrated_at']=$d['meta']['migrated_at']??date('c');
    return $d;
}
function db_read_file(string $p): array {if(!is_file($p))throw new RuntimeException('Database file missing: '.basename($p));if(!is_readable($p))throw new RuntimeException('Database file is not readable');clearstatcache(true,$p);if(function_exists('opcache_invalidate')){@opcache_invalidate($p,true);}$d=require $p;if(!is_array($d))throw new RuntimeException('Database file is invalid');return $d;}
function db_branch_bucket_names(): array {
    return [
        'settings','tables','prs','checkins','attendance','notifications','shifts','service_calls','reservations','customers','daily_closes',
        'leave_requests','customer_media','customer_hero_media','employees','time_correction_requests','substitute_requests',
        'shift_templates','roster_batches','privacy_consents','account_invites','floor_plans','floor_plan_items',
        'floor_plan_versions','floor_service_sessions','pos_import_batches','pos_incentive_closings','pos_incentive_rules','pos_sales_rows'
    ];
}
function db_portal_defaults(): array {
    return [
        'enabled'=>'1','brand_name'=>'MR BAR GROUP','eyebrow'=>'CHOOSE YOUR EXPERIENCE',
        'title'=>'เลือกร้านที่คุณต้องการ','subtitle'=>'ดูบรรยากาศ โปรโมชั่น PR และโต๊ะว่างของแต่ละสาขา',
        'logo'=>'','hero_image'=>'','contact_label'=>'ติดต่อเรา','contact_url'=>'','footer_text'=>'MR BAR GROUP',
        'show_closed_branches'=>'0'
    ];
}
function db_slugify_branch(string $value): string {
    $value=trim($value);
    $value=preg_replace('/[^a-zA-Z0-9ก-๙]+/u','-',$value)??'';
    $value=trim($value,'-');
    return $value!==''?substr($value,0,120):'shop';
}
function db_slug_key(string $value): string {
    return mb_strtolower(db_slugify_branch($value),'UTF-8');
}
function db_normalize_branch(array $b,int $fallbackId=1,array $settings=[]): array {
    $id=max(1,(int)($b['id']??$fallbackId));$name=trim((string)($b['name']??''))?:('Branch '.$id);
    $slug=db_slugify_branch((string)($b['slug']??$name));
    $history=array_values(array_unique(array_filter(array_map('db_slugify_branch',is_array($b['slug_history']??null)?$b['slug_history']:[]),fn($x)=>$x!==''&&db_slug_key($x)!==db_slug_key($slug))));
    return array_merge([
        'id'=>$id,'code'=>'BR'.str_pad((string)$id,2,'0',STR_PAD_LEFT),'name'=>$name,'short_name'=>$name,'slug'=>$slug,'slug_history'=>$history,
        'description'=>'','address'=>(string)($settings['shop_address']??''),'phone'=>(string)($settings['shop_phone']??''),
        'line_url'=>'','facebook_url'=>'','logo'=>'','cover_image'=>'','lat'=>null,'lng'=>null,
        'radius_m'=>(int)($settings['attendance_default_radius_m']??200),'active'=>1,'published'=>1,
        'portal_featured'=>0,'sort_order'=>100,'created_at'=>date('c'),'updated_at'=>date('c')
    ],$b,['id'=>$id,'name'=>$name,'slug'=>$slug,'slug_history'=>$history]);
}
function db_user_branch_ids(array $u): array {
    $ids=is_array($u['branch_ids']??null)?$u['branch_ids']:[];
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));
    return $ids?:[1];
}
function db_user_can_branch(array $u,int $branchId): bool {
    if(!empty($u['super_admin']))return true;
    return in_array($branchId,db_user_branch_ids($u),true);
}
function db_requested_branch_slug(): string {
    $slug=trim((string)($_REQUEST['public_branch']??$_GET['branch']??''));
    if($slug!=='')return db_slugify_branch(rawurldecode($slug));
    $uri=(string)($_SERVER['REQUEST_URI']??'');
    if(preg_match('#/shop/([^/?]+)#u',$uri,$m))return db_slugify_branch(rawurldecode((string)$m[1]));
    return '';
}
function db_is_customer_request(): bool {
    $path=strtolower(str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'')));
    $uri=strtolower((string)($_SERVER['REQUEST_URI']??''));
    return strpos($path,'/custumers/')!==false||strpos($uri,'/shop/')!==false||basename($path)==='portal.php';
}
function db_branch_by_slug_raw(array $raw,string $slug): ?array {
    $key=db_slug_key($slug);
    foreach($raw['branches']??[] as $b){
        if(db_slug_key((string)($b['slug']??''))===$key)return ['branch'=>$b,'alias'=>((string)($b['slug']??'')!==$slug)];
        foreach($b['slug_history']??[] as $old)if(db_slug_key((string)$old)===$key)return ['branch'=>$b,'alias'=>true];
    }
    return null;
}
function db_active_branch_id(array $raw): int {
    $branches=array_values(array_filter($raw['branches']??[],fn($b)=>!empty($b['active'])));
    $fallback=(int)($branches[0]['id']??1);
    if(db_is_customer_request()||isset($_REQUEST['public_branch'])){
        $slug=db_requested_branch_slug();
        if($slug!==''&&($found=db_branch_by_slug_raw($raw,$slug))){
            $id=(int)$found['branch']['id'];$_SESSION['mrbar_public_branch_id']=$id;return $id;
        }
        $remembered=(int)($_SESSION['mrbar_public_branch_id']??0);
        foreach($branches as $b)if((int)$b['id']===$remembered)return $remembered;
        return $fallback;
    }
    $u=is_array($_SESSION['user']??null)?$_SESSION['user']:[];
    $uid=(int)($u['id']??0);
    if($uid>0){
        foreach($raw['users']??[] as $storedUser){
            if((int)($storedUser['id']??0)!==$uid)continue;
            foreach(['username','role','display_name','super_admin','branch_ids','active'] as $key)if(array_key_exists($key,$storedUser))$u[$key]=$storedUser[$key];
            $_SESSION['user']=$u;break;
        }
    }
    $wanted=(int)($_SESSION['mrbar_active_branch_id']??0);
    if($wanted>0&&db_user_can_branch($u,$wanted))foreach($branches as $b)if((int)$b['id']===$wanted)return $wanted;
    foreach($branches as $b){$id=(int)$b['id'];if(db_user_can_branch($u,$id)){$_SESSION['mrbar_active_branch_id']=$id;return $id;}}
    return $fallback;
}
function db_empty_branch_data(array $settings=[]): array {
    $legacy=db_migrate_legacy_array(['settings'=>$settings]);
    $out=[];foreach(db_branch_bucket_names() as $bucket)$out[$bucket]=$legacy[$bucket]??($bucket==='settings'?[]:[]);
    return $out;
}
function db_branch_core_score(array $bd): int {
    $weights=['tables'=>2,'prs'=>2,'employees'=>2,'customer_media'=>2,'customer_hero_media'=>2,'floor_plans'=>4,'floor_plan_items'=>1,'reservations'=>1,'checkins'=>1,'attendance'=>1,'floor_service_sessions'=>1,'pos_sales_rows'=>1];
    $score=0;foreach($weights as $bucket=>$weight)$score+=min(50,count(is_array($bd[$bucket]??null)?$bd[$bucket]:[]))*$weight;
    return $score;
}
function db_repair_v26(array $d,int $incomingSchema): array {
    if($incomingSchema>=26)return $d;
    $priestIndex=null;
    foreach($d['branches']??[] as $i=>$branch){
        if(stripos((string)($branch['name']??''),'priest')!==false){$priestIndex=$i;break;}
    }
    if($priestIndex===null)return $d;
    $targetId=(int)$d['branches'][$priestIndex]['id'];$targetKey=(string)$targetId;
    $oldSlug=(string)($d['branches'][$priestIndex]['slug']??'');
    $priestTaken=false;
    foreach($d['branches'] as $i=>$branch)if($i!==$priestIndex&&db_slug_key((string)($branch['slug']??''))==='priest'){$priestTaken=true;break;}
    if(!$priestTaken&&db_slug_key($oldSlug)!=='priest'){
        $history=is_array($d['branches'][$priestIndex]['slug_history']??null)?$d['branches'][$priestIndex]['slug_history']:[];
        if($oldSlug!=='')$history[]=$oldSlug;
        $d['branches'][$priestIndex]['slug']='Priest';
        $d['branches'][$priestIndex]['slug_history']=array_values(array_unique($history));
        $d['branches'][$priestIndex]['updated_at']=date('c');
    }
    $target=is_array($d['branch_data'][$targetKey]??null)?$d['branch_data'][$targetKey]:db_empty_branch_data();
    $targetScore=db_branch_core_score($target);$sourceId=0;$sourceScore=0;$source=[];
    foreach($d['branch_data']??[] as $key=>$candidate){
        $id=(int)$key;if($id===$targetId||!is_array($candidate))continue;
        $score=db_branch_core_score($candidate);if($score>$sourceScore){$sourceScore=$score;$sourceId=$id;$source=$candidate;}
    }
    if($sourceId>0&&$sourceScore>=5&&$sourceScore>$targetScore){
        foreach(db_branch_bucket_names() as $bucket){
            if($bucket==='settings')continue;
            $existing=is_array($target[$bucket]??null)?$target[$bucket]:[];
            if($existing||empty($source[$bucket])||!is_array($source[$bucket]))continue;
            $target[$bucket]=$source[$bucket];
            foreach($target[$bucket] as &$row)if(is_array($row)&&array_key_exists('branch_id',$row))$row['branch_id']=$targetId;unset($row);
        }
        $target['settings']=is_array($source['settings']??null)?$source['settings']:($target['settings']??[]);
        $target['settings']['shop_name']=(string)($d['branches'][$priestIndex]['name']??'Priest Exclusive Club & KTV');
        $target['settings']['shop_address']=(string)($d['branches'][$priestIndex]['address']??($target['settings']['shop_address']??''));
        $target['settings']['shop_phone']=(string)($d['branches'][$priestIndex]['phone']??($target['settings']['shop_phone']??''));
        $target['settings']['customer_web_map_branch_id']=$targetId;
        $d['branch_data'][$targetKey]=$target;
        foreach($d['users']??[] as &$user){
            $ids=db_user_branch_ids($user);
            if(in_array($sourceId,$ids,true)&&!in_array($targetId,$ids,true)){$ids[]=$targetId;$user['branch_ids']=array_values(array_unique($ids));}
        }unset($user);
        $d['audit'][]=['at'=>date('c'),'action'=>'schema26_priest_data_recovered','branch_id'=>$targetId,'source_branch_id'=>$sourceId,'source_score'=>$sourceScore];
        $d['meta']['schema26_recovery']=['at'=>date('c'),'target_branch_id'=>$targetId,'source_branch_id'=>$sourceId,'copied'=>true];
    }else{
        $d['meta']['schema26_recovery']=['at'=>date('c'),'target_branch_id'=>$targetId,'source_branch_id'=>$sourceId,'copied'=>false,'target_score'=>$targetScore,'source_score'=>$sourceScore];
    }
    return $d;
}
function db_migrate_array(array $d): array {
    $incomingSchema=(int)($d['meta']['schema']??0);
    if(empty($d['branch_data'])||!is_array($d['branch_data'])){
        $legacy=db_migrate_legacy_array($d);
        $branches=[];$seenSlugs=[];
        foreach($legacy['branches']??[] as $i=>$b){
            $row=db_normalize_branch($b,(int)($b['id']??($i+1)),$legacy['settings']??[]);
            $base=$row['slug'];$n=2;while(isset($seenSlugs[db_slug_key($row['slug'])]))$row['slug']=$base.'-'.$n++;
            $seenSlugs[db_slug_key($row['slug'])]=1;$branches[]=$row;
        }
        if(!$branches)$branches[] = db_normalize_branch(['id'=>1,'name'=>(string)($legacy['settings']['shop_name']??'MR BAR'),'code'=>'MAIN'],1,$legacy['settings']??[]);
        $mainId=(int)$branches[0]['id'];
        $users=$legacy['users']??[];
        foreach($users as &$u){
            $assigned=[];
            foreach($legacy['employees']??[] as $e)if((int)($e['user_id']??0)===(int)($u['id']??0)&&!empty($e['branch_id']))$assigned[]=(int)$e['branch_id'];
            foreach($legacy['prs']??[] as $p)if((int)($p['user_id']??0)===(int)($u['id']??0)&&!empty($p['branch_id']))$assigned[]=(int)$p['branch_id'];
            $u['branch_ids']=array_values(array_unique($assigned?:[$mainId]));
        }unset($u);
        $raw=['users'=>$users,'roles'=>$legacy['roles']??permission_default_roles(),'branches'=>$branches,'portal_settings'=>db_portal_defaults(),'audit'=>[],'branch_data'=>[],'meta'=>$legacy['meta']??[]];
        foreach($branches as $b){$id=(int)$b['id'];$raw['branch_data'][(string)$id]=db_empty_branch_data(['shop_name'=>$b['name'],'shop_address'=>$b['address'],'shop_phone'=>$b['phone']]);}
        foreach(db_branch_bucket_names() as $bucket){
            if($bucket==='settings'){$raw['branch_data'][(string)$mainId]['settings']=$legacy['settings']??[];continue;}
            foreach($legacy[$bucket]??[] as $row){
                $target=(int)($row['branch_id']??$mainId);if(!isset($raw['branch_data'][(string)$target]))$target=$mainId;
                $raw['branch_data'][(string)$target][$bucket][]=$row;
            }
        }
        foreach($legacy['audit']??[] as $row){if(!isset($row['branch_id']))$row['branch_id']=$mainId;$raw['audit'][]=$row;}
        $d=$raw;
    }
    $d['portal_settings']=array_replace(db_portal_defaults(),is_array($d['portal_settings']??null)?$d['portal_settings']:[]);
    $d['roles']=is_array($d['roles']??null)?$d['roles']:permission_default_roles();
    $d['users']=is_array($d['users']??null)?$d['users']:[];
    foreach($d['users'] as &$u){$u['branch_ids']=db_user_branch_ids($u);}unset($u);
    $normalized=[];$seen=[];
    foreach($d['branches']??[] as $i=>$b){
        $row=db_normalize_branch(is_array($b)?$b:[],(int)($b['id']??($i+1)));
        $base=$row['slug'];$n=2;while(isset($seen[db_slug_key($row['slug'])]))$row['slug']=$base.'-'.$n++;
        $seen[db_slug_key($row['slug'])]=1;$normalized[]=$row;
    }
    if(!$normalized)$normalized[] = db_normalize_branch(['id'=>1,'name'=>'MR BAR','code'=>'MAIN'],1);
    $d['branches']=$normalized;
    if(!isset($d['branch_data'])||!is_array($d['branch_data']))$d['branch_data']=[];
    foreach($d['branches'] as $b){
        $id=(int)$b['id'];$key=(string)$id;$bd=is_array($d['branch_data'][$key]??null)?$d['branch_data'][$key]:db_empty_branch_data(['shop_name'=>$b['name'],'shop_address'=>$b['address'],'shop_phone'=>$b['phone']]);
        $view=['users'=>array_values(array_filter($d['users'],fn($u)=>db_user_can_branch($u,$id))),'roles'=>$d['roles'],'branches'=>$d['branches'],'settings'=>$bd['settings']??[],'audit'=>[],'meta'=>$d['meta']??[]];
        foreach(db_branch_bucket_names() as $bucket)if($bucket!=='settings')$view[$bucket]=$bd[$bucket]??[];
        $view=db_migrate_legacy_array($view);
        $clean=[];foreach(db_branch_bucket_names() as $bucket)$clean[$bucket]=$view[$bucket]??($bucket==='settings'?[]:[]);
        $d['branch_data'][$key]=$clean;
    }
    $d=db_repair_v26($d,$incomingSchema);
    foreach($d['users'] as &$account){
        $links=[];$uid=(int)($account['id']??0);
        foreach($d['branch_data'] as $branchKey=>$branchData){foreach($branchData['employees']??[] as $employee)if((int)($employee['user_id']??0)===$uid){$links[(string)(int)$branchKey]=(int)($employee['id']??0);break;}}
        $account['employee_ids_by_branch']=$links;
    }unset($account);
    $valid=array_map(fn($b)=>(string)(int)$b['id'],$d['branches']);
    foreach(array_keys($d['branch_data']) as $key)if(!in_array((string)$key,$valid,true))unset($d['branch_data'][$key]);
    $d['audit']=is_array($d['audit']??null)?$d['audit']:[];
    $d['meta']=is_array($d['meta']??null)?$d['meta']:[];
    $d['meta']['schema']=MRBAR_SCHEMA_VERSION;
    $d['meta']['migrated_at']=$d['meta']['migrated_at']??date('c');
    return $d;
}
function db_branch_view(array $raw,?int $branchId=null): array {
    $raw=db_migrate_array($raw);$branchId=$branchId?:db_active_branch_id($raw);
    if(!isset($raw['branch_data'][(string)$branchId]))$branchId=(int)($raw['branches'][0]['id']??1);
    $view=$raw['branch_data'][(string)$branchId]??db_empty_branch_data();
    $view['users']=$raw['users'];$view['roles']=$raw['roles'];$view['branches']=$raw['branches'];$view['portal_settings']=$raw['portal_settings'];
    $view['audit']=array_values(array_filter($raw['audit'],fn($a)=>(int)($a['branch_id']??$branchId)===$branchId));
    $view['meta']=$raw['meta'];$view['meta']['active_branch_id']=$branchId;
    $view['_branch_context']=['id'=>$branchId];
    return $view;
}
function db_merge_branch_view(array $raw,array $view,int $branchId): array {
    $raw=db_migrate_array($raw);$key=(string)$branchId;
    foreach(db_branch_bucket_names() as $bucket)$raw['branch_data'][$key][$bucket]=$view[$bucket]??($bucket==='settings'?[]:[]);
    if(isset($view['users'])&&is_array($view['users'])){
        foreach($view['users'] as &$u)if(!isset($u['branch_ids']))$u['branch_ids']=[$branchId];unset($u);
        $raw['users']=$view['users'];
    }
    if(isset($view['roles'])&&is_array($view['roles']))$raw['roles']=$view['roles'];
    if(isset($view['branches'])&&is_array($view['branches']))$raw['branches']=$view['branches'];
    if(isset($view['portal_settings'])&&is_array($view['portal_settings']))$raw['portal_settings']=$view['portal_settings'];
    $other=array_values(array_filter($raw['audit'],fn($a)=>(int)($a['branch_id']??$branchId)!==$branchId));
    $current=is_array($view['audit']??null)?$view['audit']:[];
    foreach($current as &$a)$a['branch_id']=$branchId;unset($a);
    $raw['audit']=array_merge($other,$current);
    return db_migrate_array($raw);
}
function db_load_raw(): array {return db_migrate_array(db_read_file(db_path()));}
function db_load_global(): array {return db_load_raw();}
function db_load(): array {return db_branch_view(db_load_raw());}
function db_payload(array $d): string {return "<?php\nreturn ".var_export(db_migrate_array($d),true).";\n";}
function db_write_locked($fp,array $d): void {$payload=db_payload($d);if(!ftruncate($fp,0))throw new RuntimeException('Database file cannot be truncated');rewind($fp);$n=fwrite($fp,$payload);if($n===false||$n<strlen($payload))throw new RuntimeException('Database file cannot be written completely');fflush($fp);$meta=stream_get_meta_data($fp);$path=(string)($meta['uri']??'');if($path!==''){clearstatcache(true,$path);if(function_exists('opcache_invalidate')){@opcache_invalidate($path,true);}}}
function db_try_make_primary_writable(): bool {$p=db_primary_path();if(is_writable($p))return true;@chmod($p,0664);clearstatcache(true,$p);if(is_writable($p))return true;@chmod($p,0666);clearstatcache(true,$p);return is_writable($p);}
function db_prepare_writable_path(): string {$runtime=db_runtime_path();if(is_file($runtime)&&is_writable($runtime))return $runtime;if(db_try_make_primary_writable())return db_primary_path();$dir=dirname(db_primary_path());if(!is_dir($dir))@mkdir($dir,0775,true);@chmod($dir,0775);clearstatcache(true,$dir);if(!is_writable($dir)){@chmod($dir,0777);clearstatcache(true,$dir);}if(is_writable($dir)){$source=db_load_raw();$ok=@file_put_contents($runtime,db_payload($source),LOCK_EX);if($ok!==false){@chmod($runtime,0664);clearstatcache(true,$runtime);return $runtime;}}throw new RuntimeException('Storage is read-only. Set storage folder writable in hosting/FileZilla permissions.');}
function db_save(array $d): void {
    $p=db_prepare_writable_path();$fp=@fopen($p,'c+b');if(!$fp)throw new RuntimeException('Database file is not writable');
    try{if(!flock($fp,LOCK_EX))throw new RuntimeException('Database lock failed');$raw=db_migrate_array(db_read_locked_handle($fp));if(isset($d['_branch_context']['id']))$raw=db_merge_branch_view($raw,$d,(int)$d['_branch_context']['id']);else $raw=db_migrate_array($d);db_write_locked($fp,$raw);flock($fp,LOCK_UN);}finally{fclose($fp);}
}
function db_read_locked_handle($fp): array {
    rewind($fp);$contents=stream_get_contents($fp);if($contents===false||trim($contents)==='')return db_default();
    $tmp=tempnam(sys_get_temp_dir(),'mrbar_');if($tmp===false)throw new RuntimeException('Temporary file unavailable');
    file_put_contents($tmp,$contents);$d=require $tmp;@unlink($tmp);if(!is_array($d))throw new RuntimeException('Database file is invalid');return $d;
}
function db_mutate(callable $fn): array {
    $p=db_prepare_writable_path();$fp=@fopen($p,'c+b');if(!$fp)throw new RuntimeException('Database file is not writable');
    try{
        if(!flock($fp,LOCK_EX))throw new RuntimeException('Database lock failed');
        $raw=db_migrate_array(db_read_locked_handle($fp));$branchId=db_active_branch_id($raw);$view=db_branch_view($raw,$branchId);
        $view=$fn($view);if(!is_array($view))throw new RuntimeException('Database mutation must return an array');
        $raw=db_merge_branch_view($raw,$view,$branchId);db_write_locked($fp,$raw);flock($fp,LOCK_UN);return db_branch_view($raw,$branchId);
    }finally{fclose($fp);}
}
function db_mutate_global(callable $fn): array {
    $p=db_prepare_writable_path();$fp=@fopen($p,'c+b');if(!$fp)throw new RuntimeException('Database file is not writable');
    try{if(!flock($fp,LOCK_EX))throw new RuntimeException('Database lock failed');$raw=db_migrate_array(db_read_locked_handle($fp));$raw=$fn($raw);if(!is_array($raw))throw new RuntimeException('Global database mutation must return an array');$raw=db_migrate_array($raw);db_write_locked($fp,$raw);flock($fp,LOCK_UN);return $raw;}finally{fclose($fp);}
}
function db_auto_migrate(): array {
    $raw=db_read_file(db_path());$old=(int)($raw['meta']['schema']??0);$m=db_migrate_array($raw);
    if($old<MRBAR_SCHEMA_VERSION||empty($raw['branch_data'])){try{db_save($m);}catch(Throwable $e){}}
    return db_branch_view($m);
}
