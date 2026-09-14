<?php
declare(strict_types=1);

function workforce_employee_by_id(array $d,int $id): ?array {
    foreach($d['employees']??[] as $e) if((int)($e['id']??0)===$id) return $e;
    return null;
}
function workforce_employee_by_user(array $d,int $userId): ?array {
    foreach($d['employees']??[] as $e) if((int)($e['user_id']??0)===$userId) return $e;
    return null;
}
function workforce_employee_by_pr(array $d,int $prId): ?array {
    foreach($d['employees']??[] as $e) if((int)($e['pr_id']??0)===$prId) return $e;
    return null;
}
function workforce_user_employee(array $d,array $u): ?array { return workforce_employee_by_user($d,(int)($u['id']??0)); }
function workforce_branch(array $d,int $id): ?array { foreach($d['branches']??[] as $b) if((int)($b['id']??0)===$id)return $b; return null; }
function workforce_bool($v,bool $fallback): bool {
    if($v===null||$v==='inherit'||$v==='') return $fallback;
    if($v===true||$v===1||$v==='1'||$v==='true'||$v==='yes') return true;
    if($v===false||$v===0||$v==='0'||$v==='false'||$v==='no') return false;
    return $fallback;
}
function workforce_policy(array $d,array $employee): array {
    $s=$d['settings']??[];$p=$employee['attendance_policy']??[];
    $gpsDefault=(string)($s['attendance_gps_required']??'1')==='1';
    $cameraDefault=(string)($s['attendance_camera_required']??$s['attendance_face_required']??'1')==='1';
    $evidenceDefault=(string)($s['attendance_evidence_required']??'1')==='1';
    $geo=(string)($p['geofence_mode']??'inherit');if(!in_array($geo,['strict','warn','off'],true))$geo=(string)($s['attendance_geofence_mode']??'strict');
    if($geo==='inherit')$geo=(string)($s['attendance_geofence_mode']??'strict');
    $radius=is_numeric($p['radius_m']??null)?(int)$p['radius_m']:(int)($s['attendance_default_radius_m']??200);
    $accuracy=is_numeric($p['max_accuracy_m']??null)?(int)$p['max_accuracy_m']:(int)($s['attendance_max_accuracy_m']??120);
    return [
        'gps_required'=>workforce_bool($p['gps_required']??null,$gpsDefault),
        'camera_required'=>workforce_bool($p['camera_required']??null,$cameraDefault),
        'evidence_required'=>workforce_bool($p['evidence_required']??null,$evidenceDefault),
        'geofence_mode'=>$geo,
        'radius_m'=>max(10,$radius),
        'max_accuracy_m'=>max(5,$accuracy),
    ];
}
function workforce_distance_m(float $lat1,float $lng1,float $lat2,float $lng2): float {
    $r=6371000.0;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=deg2rad($lat2-$lat1);$dl=deg2rad($lng2-$lng1);
    $a=sin($dp/2)*sin($dp/2)+cos($p1)*cos($p2)*sin($dl/2)*sin($dl/2);return 2*$r*atan2(sqrt($a),sqrt(max(0,1-$a)));
}
function workforce_resolve_branch(array $d,array $employee,float $lat,float $lng): array {
    $assigned=(int)($employee['branch_id']??0);$candidates=[];
    foreach($d['branches']??[] as $b){if(empty($b['active'])||!is_numeric($b['lat']??null)||!is_numeric($b['lng']??null))continue;$candidates[]=$b;}
    if($assigned>0){foreach($candidates as $b)if((int)$b['id']===$assigned){$b['_distance']=workforce_distance_m($lat,$lng,(float)$b['lat'],(float)$b['lng']);return $b;}}
    $best=null;foreach($candidates as $b){$dist=workforce_distance_m($lat,$lng,(float)$b['lat'],(float)$b['lng']);if($best===null||$dist<$best['_distance']){$b['_distance']=$dist;$best=$b;}}
    if($best===null)throw new RuntimeException('ยังไม่มีสาขาที่ตั้งพิกัด GPS ไว้');return $best;
}
function workforce_attendance_open(array $d,int $employeeId): ?array {
    for($i=count($d['attendance']??[])-1;$i>=0;$i--){$a=$d['attendance'][$i];if((int)($a['employee_id']??0)===$employeeId&&empty($a['check_out']))return $a;}return null;
}
function workforce_attendance_for_date(array $d,int $employeeId,string $date): array {
    $rows=[];foreach($d['attendance']??[] as $a){if((int)($a['employee_id']??0)!==$employeeId||empty($a['check_in']))continue;if(substr((string)$a['check_in'],0,10)===$date)$rows[]=$a;}return $rows;
}
function workforce_evidence_save(int $employeeId,string $mode,string $dataUrl): string {
    if(!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#',$dataUrl,$m))throw new RuntimeException('รูปยืนยันไม่ถูกต้อง');
    $raw=base64_decode($m[2],true);if($raw===false||strlen($raw)<1000)throw new RuntimeException('รูปยืนยันไม่สมบูรณ์');if(strlen($raw)>8*1024*1024)throw new RuntimeException('รูปยืนยันมีขนาดใหญ่เกินไป');
    $ext=$m[1]==='png'?'png':($m[1]==='webp'?'webp':'jpg');$dir=__DIR__.'/../storage/attendance-evidence/'.date('Y/m').'/employee-'.$employeeId;
    if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Server ไม่สามารถสร้างโฟลเดอร์หลักฐาน Attendance ได้');
    $name=$mode.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$ext;$file=$dir.'/'.$name;if(@file_put_contents($file,$raw,LOCK_EX)===false)throw new RuntimeException('บันทึกรูปยืนยันไม่ได้');@chmod($file,0664);
    return 'storage/attendance-evidence/'.date('Y/m').'/employee-'.$employeeId.'/'.$name;
}
function workforce_employee_display_name(array $e): string {
    $pf=is_array($e['profile']??null)?$e['profile']:[];
    $display=trim((string)($pf['display_name']??''));if($display!=='')return $display;
    $legacy=trim((string)($e['name']??''));if($legacy!=='')return $legacy;
    $formal=trim((string)($pf['title']??'').' '.(string)($pf['first_name']??'').' '.(string)($pf['last_name']??''));if($formal!=='')return preg_replace('/\\s+/u',' ',$formal)?:$formal;
    return trim((string)($e['code']??''));
}
function workforce_employee_formal_name(array $e): string {
    $pf=is_array($e['profile']??null)?$e['profile']:[];
    $formal=trim((string)($pf['title']??'').' '.(string)($pf['first_name']??'').' '.(string)($pf['last_name']??''));
    return $formal===''?'':(preg_replace('/\\s+/u',' ',$formal)?:$formal);
}
function workforce_employee_label(array $e): string {return trim((string)($e['code']??'').' · '.workforce_employee_display_name($e),' ·');}
function workforce_position_label(string $p): string {
    $map=['pr'=>'PR','sales'=>'Sales / เซล','staff'=>'พนักงานทั่วไป','manager'=>'ผู้จัดการ','cashier'=>'แคชเชียร์','service'=>'บริการ','reception'=>'ต้อนรับ','office'=>'สำนักงาน','admin'=>'Admin / Owner','other'=>'อื่นๆ'];return $map[$p]??ucfirst($p);
}
function workforce_recommended_account_role(array $employee): string {
    $position=(string)($employee['position']??'staff');
    if($position==='pr'||!empty($employee['pr_id']))return 'pr';
    if($position==='sales')return 'sales';
    return 'staff';
}
function workforce_account_role_compatible(array $employee,?array $account): bool {
    if(!$account)return true;$position=(string)($employee['position']??'staff');$role=(string)($account['role']??'');
    if($position==='pr'||!empty($employee['pr_id']))return $role==='pr';
    if($position==='sales')return $role==='sales';
    return !in_array($role,['pr','sales'],true);
}
function workforce_people_health(array $d): array {
    $linked=0;$withoutAccount=0;$roleMismatch=0;$inactiveLogin=0;$orphanAccounts=0;$employeeByUser=[];
    $branchId=(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0);
    $accounts=array_values(array_filter($d['users']??[],fn($account)=>$branchId<=0||!function_exists('db_user_can_branch')||db_user_can_branch($account,$branchId)));
    foreach($d['employees']??[] as $employee){
        $uid=(int)($employee['user_id']??0);$account=null;
        if($uid>0){foreach($accounts as $row)if((int)($row['id']??0)===$uid){$account=$row;break;}}
        if($account){$linked++;$employeeByUser[$uid]=1;if(!workforce_account_role_compatible($employee,$account))$roleMismatch++;if(empty($employee['active'])&&!empty($account['active']))$inactiveLogin++;}
        elseif(!empty($employee['active']))$withoutAccount++;
    }
    foreach($accounts as $account)if(empty($employeeByUser[(int)($account['id']??0)]))$orphanAccounts++;
    return ['employees'=>count($d['employees']??[]),'accounts'=>count($accounts),'linked'=>$linked,'without_account'=>$withoutAccount,'role_mismatch'=>$roleMismatch,'inactive_login'=>$inactiveLogin,'orphan_accounts'=>$orphanAccounts,'issues'=>$roleMismatch+$inactiveLogin+$orphanAccounts];
}
function workforce_sync_employee_to_pr(array &$d,array $employee): void {
    $prId=(int)($employee['pr_id']??0);if($prId<=0)return;
    foreach($d['prs'] as &$p){
        if((int)($p['id']??0)!==$prId)continue;
        $p['employee_id']=(int)$employee['id'];
        $p['branch_id']=$employee['branch_id']??null;
        $p['user_id']=!empty($employee['user_id'])?(int)$employee['user_id']:null;
        $p['active']=!empty($employee['active'])?1:0;
        $display=workforce_employee_display_name($employee);if($display!=='')$p['name']=$display;
        $pay=$employee['payroll']??[];$comp=$employee['pr_compensation']??[];$epf=is_array($employee['profile']??null)?$employee['profile']:[];$pf=&$p['profile'];if(!is_array($pf))$pf=[];
        foreach(['title','first_name','last_name','nickname','display_name','phone','email','line_id','birthday','gender','address','emergency_name','emergency_relation','emergency_phone','profile_photo'] as $k)$pf[$k]=(string)($epf[$k]??'');
        $pf['payroll_type']=$pay['type']??($pf['payroll_type']??'daily');
        $pf['daily_rate']=$pay['daily_rate']??($pf['daily_rate']??'0');
        $pf['hourly_rate']=$pay['hourly_rate']??($pf['hourly_rate']??'0');
        $pf['monthly_salary']=$pay['monthly_salary']??($pf['monthly_salary']??'0');
        $pf['overtime_rate']=$pay['overtime_rate']??($pf['overtime_rate']??'0');
        $pf['payroll_start_time']=$pay['start_time']??($pf['payroll_start_time']??'18:00');
        $pf['payroll_standard_hours']=$pay['standard_hours']??($pf['payroll_standard_hours']??'8');
        $pf['pr_commission_rate']=$comp['commission_rate']??($pf['pr_commission_rate']??'0');
        $pf['pr_drink_rate']=$comp['drink_rate']??($pf['pr_drink_rate']??'0');
        $pf['pr_other_rate']=$comp['other_rate']??($pf['pr_other_rate']??'0');
        $pf['pr_compensation_note']=$comp['note']??($pf['pr_compensation_note']??'');
        $pf['updated_at']=date('c');
        break;
    }unset($p);
}

/* v1.19.0 schedule presentation helpers. */
function workforce_shift_band(string $start): string {
    $hour=(int)substr($start,0,2);
    if($hour>=5 && $hour<12)return 'morning';
    if($hour>=12 && $hour<17)return 'day';
    if($hour>=17 && $hour<22)return 'evening';
    return 'night';
}
function workforce_shift_band_label(string $band): string {
    return ['morning'=>'กะเช้า','day'=>'กะบ่าย','evening'=>'กะเย็น','night'=>'กะดึก'][$band]??'กะ';
}
function workforce_employee_avatar_url(array $d,array $employee): string {
    $employeeId=(int)($employee['id']??0);$prId=(int)($employee['pr_id']??0);$rel=(string)($employee['profile']['profile_photo']??'');
    if($rel!=='' && strpos($rel,'storage/employee-media/')===0 && $employeeId>0)return 'employee-photo.php?employee_id='.$employeeId;
    if($rel!=='' && strpos($rel,'storage/pr-media/')===0 && $prId>0)return 'pr-media.php?pr_id='.$prId.'&field=profile_photo';
    if($rel!=='')return $rel;
    if($prId<=0)return '';
    foreach($d['prs']??[] as $p){if((int)($p['id']??0)!==$prId)continue;$prRel=(string)($p['profile']['profile_photo']??'');if($prRel==='')return '';if(strpos($prRel,'storage/pr-media/')===0)return 'pr-media.php?pr_id='.$prId.'&field=profile_photo';if(strpos($prRel,'storage/employee-media/')===0 && $employeeId>0)return 'employee-photo.php?employee_id='.$employeeId;return $prRel;}
    return '';
}
function workforce_shift_employee(array $d,array $shift): ?array {
    $employeeId=(int)($shift['employee_id']??0);
    if($employeeId>0){$e=workforce_employee_by_id($d,$employeeId);if($e)return $e;}
    $prId=(int)($shift['pr_id']??0);
    if($prId>0)return workforce_employee_by_pr($d,$prId);
    $external=trim((string)($shift['external_substitute_name']??''));
    if($external!=='')return ['id'=>-max(1,(int)($shift['id']??1)),'code'=>'EXT','name'=>$external,'position'=>'pr','department'=>'PR Substitute','active'=>1,'pr_id'=>null,'user_id'=>null,'branch_id'=>$shift['branch_id']??null,'profile'=>[]];
    return null;
}
function workforce_initial(string $name): string {
    $name=trim($name);if($name==='')return '?';
    if(function_exists('mb_substr'))return mb_substr($name,0,1,'UTF-8');
    if(preg_match('/^./us',$name,$m))return (string)$m[0];
    return substr($name,0,1);
}


/* v1.20.0 Attendance Exceptions + PR Substitute helpers */
function workforce_attendance_state(array $a): string {
    $state=(string)($a['attendance_state']??'');
    if($state!=='')return $state;
    return empty($a['check_out'])?'open':'complete';
}
function workforce_payroll_final(array $a): bool {
    return !empty($a['check_out']) && (string)($a['payroll_status']??'final')==='final' && !in_array(workforce_attendance_state($a),['missing_checkout','provisional'],true);
}
function workforce_income_minutes(?string $in,?string $out): int {
    if(!$in||!$out)return 0;try{$a=new DateTime($in);$b=new DateTime($out);}catch(Throwable $e){return 0;}$seconds=$b->getTimestamp()-$a->getTimestamp();return $seconds>0?(int)floor($seconds/60):0;
}
function workforce_employee_income_estimate(array $d,array $employee,string $from,string $to): array {
    $eid=(int)($employee['id']??0);$prId=(int)($employee['pr_id']??0);$pay=is_array($employee['payroll']??null)?$employee['payroll']:[];$type=(string)($pay['type']??'daily');if(!in_array($type,['daily','hourly','monthly'],true))$type='daily';
    $daily=max(0,(float)($pay['daily_rate']??0));$hourly=max(0,(float)($pay['hourly_rate']??0));$monthly=max(0,(float)($pay['monthly_salary']??0));$otRate=max(0,(float)($pay['overtime_rate']??0));$stdHours=max(1,min(24,(float)($pay['standard_hours']??8)));$stdMinutes=(int)round($stdHours*60);
    $rows=[];$finalDays=0;$workMinutes=0;$otMinutes=0;$pending=0;$basePay=0.0;$otPay=0.0;
    foreach($d['attendance']??[] as $a){
        $match=(int)($a['employee_id']??0)===$eid||($prId>0&&(int)($a['pr_id']??0)===$prId);if(!$match||empty($a['check_in']))continue;$date=substr((string)$a['check_in'],0,10);if($date<$from||$date>$to)continue;
        $final=workforce_payroll_final($a);$minutes=$final?workforce_income_minutes((string)$a['check_in'],(string)($a['check_out']??'')):0;$ot=$final?max(0,$minutes-$stdMinutes):0;$regular=max(0,$minutes-$ot);$rowPay=0.0;
        if($final){$finalDays++;$workMinutes+=$minutes;$otMinutes+=$ot;if($type==='hourly'){$rowPay=($regular/60)*$hourly+($ot/60)*$otRate;$basePay+=($regular/60)*$hourly;$otPay+=($ot/60)*$otRate;}elseif($type==='daily'){$rowPay=$daily+($ot/60)*$otRate;$basePay+=$daily;$otPay+=($ot/60)*$otRate;}else{$rowPay=($ot/60)*$otRate;$otPay+=($ot/60)*$otRate;}}else{$pending++;}
        $rows[]=['id'=>(int)($a['id']??0),'date'=>$date,'check_in'=>(string)($a['check_in']??''),'check_out'=>(string)($a['check_out']??''),'minutes'=>$minutes,'ot_minutes'=>$ot,'final'=>$final,'state'=>workforce_attendance_state($a),'pay'=>$rowPay];
    }
    usort($rows,function($a,$b){return strcmp((string)$b['check_in'],(string)$a['check_in']);});
    if($type==='monthly'&&$monthly>0){try{$sd=new DateTime($from);$ed=new DateTime($to);$same=$sd->format('Y-m')===$ed->format('Y-m');$days=(int)$sd->diff($ed)->days+1;$dim=(int)$sd->format('t');$ratio=$same?min(1,max(0,$days/$dim)):1;$basePay=$monthly*$ratio;}catch(Throwable $e){$basePay=$monthly;}}
    $completedJobs=0;$commissionPay=0.0;$otherPay=0.0;$drinkRate=0.0;$commissionRate=0.0;$otherRate=0.0;$isPr=$prId>0||((string)($employee['position']??'')==='pr');
    if($isPr){$pc=is_array($employee['pr_compensation']??null)?$employee['pr_compensation']:[];$commissionRate=max(0,(float)($pc['commission_rate']??0));$drinkRate=max(0,(float)($pc['drink_rate']??0));$otherRate=max(0,(float)($pc['other_rate']??0));foreach($d['checkins']??[] as $c){if((int)($c['pr_id']??0)!==$prId||($c['status']??'')!=='completed')continue;$at=(string)($c['completed_at']??$c['updated_at']??'');if($at==='')continue;$date=substr($at,0,10);if($date>=$from&&$date<=$to)$completedJobs++;}$commissionPay=$completedJobs*$commissionRate;$otherPay=$finalDays*$otherRate;}
    $total=$basePay+$otPay+$commissionPay+$otherPay;
    return ['employee_id'=>$eid,'pr_id'=>$prId?:null,'from'=>$from,'to'=>$to,'type'=>$type,'final_days'=>$finalDays,'pending_count'=>$pending,'work_minutes'=>$workMinutes,'ot_minutes'=>$otMinutes,'base_pay'=>$basePay,'ot_pay'=>$otPay,'completed_jobs'=>$completedJobs,'commission_rate'=>$commissionRate,'commission_pay'=>$commissionPay,'drink_rate'=>$drinkRate,'drink_units'=>null,'drink_pay'=>0.0,'other_rate'=>$otherRate,'other_pay'=>$otherPay,'total'=>$total,'rows'=>$rows,'is_pr'=>$isPr];
}
function workforce_planned_checkout(array $d,array $a): ?DateTime {
    if(empty($a['check_in']))return null;
    $checkIn=new DateTime((string)$a['check_in']);
    $date=$checkIn->format('Y-m-d');$eid=(int)($a['employee_id']??0);$pid=(int)($a['pr_id']??0);
    $shift=null;
    foreach($d['shifts']??[] as $s){
        if((string)($s['date']??'')!==$date||in_array((string)($s['status']??''),['cancelled','off','substituted'],true))continue;
        $same=($eid>0&&(int)($s['employee_id']??0)===$eid)||($pid>0&&(int)($s['pr_id']??0)===$pid);
        if($same){$shift=$s;break;}
    }
    if($shift){
        $start=(string)($shift['start']??'18:00');$end=(string)($shift['end']??'02:00');
        $dt=new DateTime($date.' '.$end);
        if(strcmp($end,$start)<=0)$dt->modify('+1 day');
        return $dt;
    }
    $open=(string)($d['settings']['shop_open_time']??'18:00');$close=(string)($d['settings']['shop_close_time']??'02:00');
    $dt=new DateTime($date.' '.$close);if(strcmp($close,$open)<=0)$dt->modify('+1 day');return $dt;
}
function workforce_missing_due(array $d,array $a,?DateTime $now=null): bool {
    if(!empty($a['check_out']))return false;
    if(in_array(workforce_attendance_state($a),['missing_checkout','provisional'],true))return false;
    $pid=(int)($a['pr_id']??0);if($pid>0){foreach($d['checkins']??[] as $job){if((int)($job['pr_id']??0)===$pid&&in_array((string)($job['status']??''),['assigned','accepted','in_service'],true))return false;}}
    $planned=workforce_planned_checkout($d,$a);if(!$planned)return false;
    $grace=max(0,(int)($d['settings']['attendance_missing_checkout_grace_minutes']??120));
    $planned->modify('+'.$grace.' minutes');$now=$now?:new DateTime('now');return $now>$planned;
}
function workforce_mark_missing_checkouts(): int {
    static $ran=false;if($ran)return 0;$ran=true;
    $snapshot=db_load();$ids=[];$now=new DateTime('now');
    foreach($snapshot['attendance']??[] as $a)if(workforce_missing_due($snapshot,$a,$now))$ids[]=(int)($a['id']??0);
    $ids=array_values(array_filter(array_unique($ids)));if(!$ids)return 0;
    db_mutate(function($d)use($ids){
        $changed=0;
        foreach($d['attendance'] as &$a){
            if(!in_array((int)($a['id']??0),$ids,true)||!empty($a['check_out']))continue;
            if((string)($a['attendance_state']??'')==='missing_checkout')continue;
            $planned=workforce_planned_checkout($d,$a);
            $a['attendance_state']='missing_checkout';$a['payroll_status']='pending_review';$a['missing_checkout_marked_at']=date('c');$a['planned_checkout_at']=$planned?$planned->format('c'):null;$changed++;
            $pid=(int)($a['pr_id']??0);if($pid&&($d['settings']['attendance_auto_offline_missing']??'1')==='1'){foreach($d['prs'] as &$p)if((int)($p['id']??0)===$pid){$p['status']='offline';$p['current_checkin_id']=null;break;}unset($p);}
            $d['audit'][]=['at'=>date('c'),'action'=>'attendance_missing_checkout_marked','attendance_id'=>(int)$a['id'],'employee_id'=>(int)($a['employee_id']??0),'pr_id'=>$pid];
        }unset($a);return $d;
    });
    return count($ids);
}
function workforce_correction_pending(array $d,int $attendanceId): ?array {
    foreach(array_reverse($d['time_correction_requests']??[]) as $r)if((int)($r['attendance_id']??0)===$attendanceId&&($r['status']??'')==='pending')return $r;return null;
}
function workforce_shift_for_id(array $d,int $id): ?array {foreach($d['shifts']??[] as $s)if((int)($s['id']??0)===$id)return $s;return null;}
function workforce_pr_for_employee(array $d,int $employeeId): ?array {$e=workforce_employee_by_id($d,$employeeId);$pid=(int)($e['pr_id']??0);if(!$pid)return null;foreach($d['prs']??[] as $p)if((int)($p['id']??0)===$pid)return $p;return null;}
function workforce_substitute_label(array $d,array $r): string {
    if(($r['substitute_type']??'internal')==='external')return trim((string)($r['external_name']??'คนนอก'));
    $e=workforce_employee_by_id($d,(int)($r['substitute_employee_id']??0));return $e?workforce_employee_label($e):'PR ในระบบ';
}

function workforce_shift_intervals_overlap(string $aStart,string $aEnd,string $bStart,string $bEnd): bool {
    $base='2000-01-01 ';$a1=strtotime($base.$aStart);$a2=strtotime($base.$aEnd);$b1=strtotime($base.$bStart);$b2=strtotime($base.$bEnd);
    if($a1===false||$a2===false||$b1===false||$b2===false)return false;if($a2<=$a1)$a2+=86400;if($b2<=$b1)$b2+=86400;return $a1<$b2&&$b1<$a2;
}
function workforce_apply_substitute_approval(array &$d,array &$r,int $reviewerId): void {
    $shift=workforce_shift_for_id($d,(int)($r['shift_id']??0));if(!$shift)throw new RuntimeException('ไม่พบกะต้นฉบับ');
    if(in_array((string)($shift['status']??''),['cancelled','off'],true))throw new RuntimeException('กะต้นฉบับถูกยกเลิกแล้ว');
    foreach($d['shifts'] as &$s)if((int)($s['id']??0)===(int)$shift['id']){$s['status']='substituted';$s['updated_at']=date('c');$s['substitution_id']=(int)$r['id'];break;}unset($s);
    $new=[
        'id'=>next_id($d['shifts']??[]),'employee_id'=>null,'pr_id'=>0,'date'=>(string)$shift['date'],'start'=>(string)$shift['start'],'end'=>(string)$shift['end'],'status'=>'scheduled','type'=>(string)($shift['type']??'regular'),
        'branch_id'=>$shift['branch_id']??null,'note'=>'มาแทน '.(string)($r['original_pr_id']?'PR':'พนักงาน').' · '.(string)($r['reason']??''),'created_by'=>$reviewerId,'created_at'=>date('c'),'updated_at'=>date('c'),
        'substitution_id'=>(int)$r['id'],'replaces_shift_id'=>(int)$shift['id'],'external_substitute_name'=>'','external_substitute_phone'=>''
    ];
    if(($r['substitute_type']??'internal')==='internal'){
        $eid=(int)($r['substitute_employee_id']??0);$emp=workforce_employee_by_id($d,$eid);if(!$emp)throw new RuntimeException('ไม่พบ PR ที่มาแทน');if($eid===(int)($r['original_employee_id']??0))throw new RuntimeException('PR คนเดิมไม่สามารถเป็นคนมาแทนตัวเอง');
        foreach($d['shifts']??[] as $existing){if((int)($existing['employee_id']??0)!==$eid||(string)($existing['date']??'')!==(string)$shift['date']||in_array((string)($existing['status']??''),['cancelled','off','substituted'],true))continue;if(workforce_shift_intervals_overlap((string)$shift['start'],(string)$shift['end'],(string)($existing['start']??''),(string)($existing['end']??'')))throw new RuntimeException('PR ที่มาแทนมีกะซ้อนในช่วงเวลานี้');}
        $new['employee_id']=$eid;$new['pr_id']=(int)($emp['pr_id']??0);
    }else{
        $name=trim((string)($r['external_name']??''));if($name==='')throw new RuntimeException('กรุณาระบุชื่อ PR คนนอก');
        $new['external_substitute_name']=$name;$new['external_substitute_phone']=trim((string)($r['external_phone']??''));
    }
    $d['shifts'][]=$new;$r['created_shift_id']=(int)$new['id'];
}


/* v1.21.0 Shift Template & Auto Roster helpers */
function workforce_roster_team(string $team): string {
    return in_array($team,['day','night','flex'],true)?$team:'flex';
}
function workforce_roster_team_label(string $team): string {
    $team=workforce_roster_team($team);
    return ['day'=>'ทีมกลางวัน','night'=>'ทีมกลางคืน','flex'=>'Flex / ไม่ล็อกทีม'][$team];
}
function workforce_shift_template_by_id(array $d,int $id): ?array {
    foreach($d['shift_templates']??[] as $t)if((int)($t['id']??0)===$id)return $t;
    return null;
}
function workforce_roster_dates(string $start,string $end,array $days,int $maxDays=62): array {
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))throw new RuntimeException('ช่วงวันที่ไม่ถูกต้อง');
    $a=strtotime($start);$b=strtotime($end);if($a===false||$b===false||$b<$a)throw new RuntimeException('วันที่สิ้นสุดต้องไม่น้อยกว่าวันเริ่ม');
    $span=(int)floor(($b-$a)/86400)+1;if($span>$maxDays)throw new RuntimeException('Auto Roster จำกัดช่วงละไม่เกิน '.$maxDays.' วัน');
    $allowed=[];foreach($days as $v){$n=(int)$v;if($n>=0&&$n<=6)$allowed[$n]=true;}if(!$allowed)throw new RuntimeException('Template ยังไม่ได้เลือกวันทำงาน');
    $out=[];for($ts=$a;$ts<=$b;$ts+=86400){$dow=(int)date('w',$ts);if(isset($allowed[$dow]))$out[]=date('Y-m-d',$ts);}
    return $out;
}
function workforce_employee_has_approved_leave(array $d,int $employeeId,string $date): ?array {
    foreach($d['leave_requests']??[] as $l){
        if((int)($l['employee_id']??0)!==$employeeId||(string)($l['status']??'')!=='approved')continue;
        $st=(string)($l['start_date']??'');$en=(string)($l['end_date']??'');if($st!==''&&$en!==''&&$date>=$st&&$date<=$en)return $l;
    }
    return null;
}
function workforce_active_shifts_for_employee_date(array $d,int $employeeId,string $date): array {
    $rows=[];foreach($d['shifts']??[] as $s){
        if((int)($s['employee_id']??0)!==$employeeId||(string)($s['date']??'')!==$date)continue;
        if(in_array((string)($s['status']??'scheduled'),['cancelled','off','substituted'],true))continue;
        $rows[]=$s;
    }return $rows;
}
function workforce_roster_conflict(array $d,int $employeeId,string $date,string $start,string $end): ?array {
    foreach(workforce_active_shifts_for_employee_date($d,$employeeId,$date) as $s){
        if(workforce_shift_intervals_overlap($start,$end,(string)($s['start']??''),(string)($s['end']??'')))return $s;
    }return null;
}
function workforce_roster_duplicate(array $d,int $employeeId,string $date,array $template): ?array {
    $start=(string)($template['start']??'');$end=(string)($template['end']??'');$type=(string)($template['type']??'regular');$branch=(int)($template['branch_id']??0);
    foreach(workforce_active_shifts_for_employee_date($d,$employeeId,$date) as $s){
        if((string)($s['start']??'')!==$start||(string)($s['end']??'')!==$end||(string)($s['type']??'regular')!==$type)continue;
        $existingBranch=(int)($s['branch_id']??0);if($branch>0&&$existingBranch!==$branch)continue;
        return $s;
    }return null;
}
function workforce_roster_preview(array $d,array $template,array $employeeIds,string $dateStart,string $dateEnd): array {
    $days=is_array($template['days']??null)?$template['days']:[0,1,2,3,4,5,6];$dates=workforce_roster_dates($dateStart,$dateEnd,$days);
    $ids=[];foreach($employeeIds as $id){$id=(int)$id;if($id>0)$ids[$id]=true;}if(!$ids)throw new RuntimeException('กรุณาเลือกพนักงานอย่างน้อย 1 คน');
    if(count($ids)*max(1,count($dates))>2000)throw new RuntimeException('จำนวนกะใน Preview มากเกินไป กรุณาแบ่งช่วงวันที่หรือแบ่งทีม');
    $rows=[];$stats=['ready'=>0,'leave'=>0,'duplicate'=>0,'conflict'=>0,'total'=>0];
    foreach(array_keys($ids) as $eid){
        $emp=workforce_employee_by_id($d,$eid);if(!$emp||empty($emp['active']))continue;
        foreach($dates as $date){
            $status='ready';$detail='พร้อมสร้างกะ';$ref=0;
            $leave=workforce_employee_has_approved_leave($d,$eid,$date);
            $dup=workforce_roster_duplicate($d,$eid,$date,$template);
            $conflict=$dup?null:workforce_roster_conflict($d,$eid,$date,(string)$template['start'],(string)$template['end']);
            if($leave){$status='leave';$detail='มีวันลาอนุมัติ';$ref=(int)($leave['id']??0);}
            elseif($dup){$status='duplicate';$detail='มีกะเดียวกันอยู่แล้ว';$ref=(int)($dup['id']??0);}
            elseif($conflict){$status='conflict';$detail='กะซ้อน '.(string)($conflict['start']??'').'–'.(string)($conflict['end']??'');$ref=(int)($conflict['id']??0);}
            $branchId=(int)($template['branch_id']??0);if($branchId<=0)$branchId=(int)($emp['branch_id']??0);
            $rows[]=['employee_id'=>$eid,'code'=>(string)($emp['code']??''),'name'=>(string)($emp['name']??''),'team'=>workforce_roster_team((string)($emp['roster_team']??'flex')),'position'=>(string)($emp['position']??'staff'),'date'=>$date,'start'=>(string)$template['start'],'end'=>(string)$template['end'],'branch_id'=>$branchId,'status'=>$status,'detail'=>$detail,'ref_id'=>$ref];
            $stats[$status]++;$stats['total']++;
        }
    }
    return ['rows'=>$rows,'stats'=>$stats,'dates'=>$dates];
}
