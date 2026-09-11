<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';
require_once __DIR__.'/app/reservation-sales.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function nq_reply(array $payload,int $status=200): void {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function nq_pr(array $d,int $id): ?array {foreach($d['prs']??[] as $p)if((int)($p['id']??0)===$id)return $p;return null;}
function nq_employee(array $d,int $id): ?array {foreach($d['employees']??[] as $e)if((int)($e['id']??0)===$id)return $e;return null;}
function nq_active_service(array $d,int $tableId): ?array {
    foreach(array_reverse($d['floor_service_sessions']??[]) as $s){if((int)($s['table_id']??0)!==$tableId||!empty($s['ended_at']))continue;$cid=(int)($s['checkin_id']??0);foreach($d['checkins']??[] as $c)if((int)($c['id']??0)===$cid&&!in_array((string)($c['status']??''),['completed','cancelled'],true))return $s;}
    return null;
}
function nq_table_payload(array $d,array $table): array {
    $id=(int)($table['id']??0);$checkin=active_checkin_for_table($d,$id);$service=nq_active_service($d,$id);
    $prId=(int)($checkin['pr_id']??$service['pr_id']??0);$salesId=(int)($checkin['service_sales_employee_id']??$checkin['booking_sales_employee_id']??$service['sales_employee_id']??0);
    $pr=$prId?nq_pr($d,$prId):null;$sales=$salesId?nq_employee($d,$salesId):null;
    return [
        'id'=>$id,'code'=>(string)($table['code']??''),'zone'=>(string)($table['zone']??''),'capacity'=>(int)($table['capacity']??0),
        'status'=>(string)($table['status']??'available'),'active'=>!empty($table['active']),
        'checkin'=>$checkin?[
            'id'=>(int)($checkin['id']??0),'ticket'=>(string)($checkin['ticket']??''),'guest_name'=>(string)($checkin['guest_name']??''),
            'status'=>(string)($checkin['status']??''),'pr_id'=>$prId,'service_sales_employee_id'=>$salesId,
            'started_at'=>(string)($checkin['started_at']??$checkin['created_at']??'')
        ]:null,
        'service'=>$service?[
            'id'=>(int)($service['id']??0),'pr_id'=>$prId,'pr_code'=>(string)($service['pr_code_snapshot']??$pr['code']??''),
            'pr_name'=>(string)($service['pr_name_snapshot']??$pr['name']??''),'sales_employee_id'=>$salesId,
            'sales_code'=>(string)($service['sales_code_snapshot']??$sales['code']??''),
            'sales_name'=>(string)($service['sales_name_snapshot']??$sales['name']??''),'started_at'=>(string)($service['started_at']??'')
        ]:null
    ];
}
function nq_state(array $d): array {
    $tables=[];foreach($d['tables']??[] as $t){$id=(int)($t['id']??0);if($id>0)$tables[(string)$id]=nq_table_payload($d,$t);}
    $prs=[];foreach($d['prs']??[] as $p){if(empty($p['active']))continue;$prs[]=['id'=>(int)$p['id'],'code'=>(string)($p['code']??''),'name'=>(string)($p['name']??''),'status'=>(string)($p['status']??'offline'),'current_checkin_id'=>(int)($p['current_checkin_id']??0)];}
    $sales=[];foreach(reservation_sales_options($d) as $s)$sales[]=['id'=>(int)$s['employee_id'],'code'=>(string)$s['code'],'name'=>(string)$s['name'],'label'=>(string)$s['label']];
    return ['tables'=>$tables,'prs'=>$prs,'sales'=>$sales,'server_time'=>date('c')];
}

try{
    $u=require_any_permission(['operations.quick_floor','tables.manage','operations.assign_pr']);
    $d=db_load();
    if(!user_can($u,'operations.quick_floor',$d)&&!user_can($u,'tables.manage',$d)&&!user_can($u,'operations.assign_pr',$d))throw new RuntimeException('ไม่มี Permission สำหรับ Quick Floor Control');
    if($_SERVER['REQUEST_METHOD']!=='POST')nq_reply(['ok'=>true,'state'=>nq_state($d)]);

    csrf_check();
    if((string)($_POST['action']??'')!=='quick_floor_update')throw new RuntimeException('คำสั่งไม่ถูกต้อง');
    $tableId=(int)($_POST['table_id']??0);$target=(string)($_POST['quick_status']??'');
    $prId=max(0,(int)($_POST['pr_id']??0));$salesRaw=trim((string)($_POST['sales_employee_id']??'none'));$uid=(int)($u['id']??0);
    if($tableId<=0||!in_array($target,['available','occupied','blocked'],true))throw new RuntimeException('กรุณาเลือกโต๊ะและสถานะให้ถูกต้อง');
    if($target!=='occupied'&&($prId>0||($salesRaw!==''&&$salesRaw!=='none'&&$salesRaw!=='0')))throw new RuntimeException('เลือก PR หรือเซลได้เมื่อสถานะโต๊ะเป็น มีลูกค้า เท่านั้น');

    $updated=db_mutate(function($d)use($tableId,$target,$prId,$salesRaw,$uid){
        if(!isset($d['floor_service_sessions'])||!is_array($d['floor_service_sessions']))$d['floor_service_sessions']=[];
        foreach($d['floor_service_sessions'] as &$stale){if(!empty($stale['ended_at']))continue;$linked=null;foreach($d['checkins']??[] as $lc)if((int)($lc['id']??0)===(int)($stale['checkin_id']??0)){$linked=$lc;break;}if($linked&&in_array((string)($linked['status']??''),['completed','cancelled'],true)){$stale['ended_at']=(string)($linked['completed_at']??$linked['updated_at']??date('c'));$stale['ended_by']=$uid;$stale['ended_reason']='checkin_'.$linked['status'];$stale['status']=(string)$linked['status'];}}unset($stale);
        $ti=null;foreach($d['tables']??[] as $i=>$t)if((int)($t['id']??0)===$tableId){$ti=$i;break;}
        if($ti===null)throw new RuntimeException('ไม่พบโต๊ะ');if(empty($d['tables'][$ti]['active']))throw new RuntimeException('โต๊ะนี้ถูกปิดใช้งานใน Table Directory');
        $table=&$d['tables'][$ti];$oldTableStatus=(string)($table['status']??'available');$now=date('c');$checkin=active_checkin_for_table($d,$tableId);$ci=null;
        if($checkin){foreach($d['checkins'] as $i=>$c)if((int)($c['id']??0)===(int)$checkin['id']){$ci=$i;break;}}

        $newPr=null;if($prId>0){$newPr=nq_pr($d,$prId);if(!$newPr||empty($newPr['active']))throw new RuntimeException('PR ที่เลือกไม่พร้อมใช้งาน');$current=(int)($newPr['current_checkin_id']??0);if($current>0&&(!$checkin||$current!==(int)$checkin['id'])){foreach($d['checkins']??[] as $other)if((int)($other['id']??0)===$current&&!in_array((string)($other['status']??''),['completed','cancelled'],true))throw new RuntimeException('PR คนนี้กำลังดูแลโต๊ะอื่นอยู่');}}
        $sales=reservation_sales_selection($d,($salesRaw===''||$salesRaw==='0')?'none':$salesRaw);
        $salesId=(int)($sales['sales_employee_id']??0);

        if($target==='occupied'){
            if($ci===null){
                $cid=next_id($d['checkins']??[]);$code=(string)($table['code']??('T'.$tableId));
                $d['checkins'][]=['id'=>$cid,'ticket'=>ticket(),'guest_name'=>'ลูกค้าหน้าร้าน · '.$code,'phone'=>'','party_size'=>0,'table_id'=>$tableId,'status'=>$prId>0?'in_service':'pending','pr_id'=>$prId?:null,'created_at'=>$now,'updated_at'=>$now,'assigned_at'=>$prId>0?$now:null,'accepted_at'=>$prId>0?$now:null,'started_at'=>$prId>0?$now:null,'completed_at'=>null,'rejected_at'=>null,'service_note'=>'Quick Floor Control','assigned_by'=>$uid,'reservation_id'=>null,'source'=>'night_ops_quick','table_history'=>[]];$ci=count($d['checkins'])-1;
            }
            $oldPrId=(int)($d['checkins'][$ci]['pr_id']??0);$cid=(int)$d['checkins'][$ci]['id'];
            if($oldPrId>0&&$oldPrId!==$prId){foreach($d['prs'] as &$p)if((int)($p['id']??0)===$oldPrId){$p['status']=attendance_open($d,$oldPrId)?'online':'offline';$p['current_checkin_id']=null;break;}unset($p);}
            $d['checkins'][$ci]['pr_id']=$prId?:null;$d['checkins'][$ci]['status']=$prId>0?'in_service':'pending';$d['checkins'][$ci]['assigned_by']=$uid;$d['checkins'][$ci]['updated_at']=$now;
            if($prId>0){if(empty($d['checkins'][$ci]['assigned_at']))$d['checkins'][$ci]['assigned_at']=$now;if(empty($d['checkins'][$ci]['accepted_at']))$d['checkins'][$ci]['accepted_at']=$now;if(empty($d['checkins'][$ci]['started_at']))$d['checkins'][$ci]['started_at']=$now;foreach($d['prs'] as &$p)if((int)($p['id']??0)===$prId){$p['status']='busy';$p['current_checkin_id']=$cid;break;}unset($p);}
            $d['checkins'][$ci]['service_sales_employee_id']=$salesId?:null;$d['checkins'][$ci]['service_sales_code_snapshot']=(string)($sales['sales_code_snapshot']??'');$d['checkins'][$ci]['service_sales_name_snapshot']=(string)($sales['sales_name_snapshot']??'');$d['checkins'][$ci]['service_sales_assigned_at']=$now;$d['checkins'][$ci]['service_sales_assigned_by']=$uid;
            $activeSession=null;foreach($d['floor_service_sessions'] as $si=>$session)if((int)($session['table_id']??0)===$tableId&&empty($session['ended_at'])){$activeSession=$si;break;}
            $same=$activeSession!==null&&(int)($d['floor_service_sessions'][$activeSession]['checkin_id']??0)===$cid&&(int)($d['floor_service_sessions'][$activeSession]['pr_id']??0)===$prId&&(int)($d['floor_service_sessions'][$activeSession]['sales_employee_id']??0)===$salesId;
            if($activeSession!==null&&!$same){$d['floor_service_sessions'][$activeSession]['ended_at']=$now;$d['floor_service_sessions'][$activeSession]['ended_by']=$uid;$d['floor_service_sessions'][$activeSession]['ended_reason']='assignment_changed';$d['floor_service_sessions'][$activeSession]['status']='completed';}
            if(!$same){$pr=$prId?nq_pr($d,$prId):null;$d['floor_service_sessions'][]=['id'=>next_id($d['floor_service_sessions']),'checkin_id'=>$cid,'table_id'=>$tableId,'table_code_snapshot'=>(string)($table['code']??''),'zone_snapshot'=>(string)($table['zone']??''),'pr_id'=>$prId?:null,'pr_code_snapshot'=>(string)($pr['code']??''),'pr_name_snapshot'=>(string)($pr['name']??''),'sales_employee_id'=>$salesId?:null,'sales_code_snapshot'=>(string)($sales['sales_code_snapshot']??''),'sales_name_snapshot'=>(string)($sales['sales_name_snapshot']??''),'started_at'=>$now,'ended_at'=>null,'status'=>'active','source'=>'night_ops_quick','created_by'=>$uid,'ended_by'=>null,'ended_reason'=>''];}
            $table['status']='occupied';$table['updated_at']=$now;
        }else{
            $oldPrId=$ci!==null?(int)($d['checkins'][$ci]['pr_id']??0):0;
            if($ci!==null){$d['checkins'][$ci]['status']='completed';$d['checkins'][$ci]['completed_at']=$now;$d['checkins'][$ci]['updated_at']=$now;}
            if($oldPrId>0){foreach($d['prs'] as &$p)if((int)($p['id']??0)===$oldPrId){$p['status']=attendance_open($d,$oldPrId)?'online':'offline';$p['current_checkin_id']=null;break;}unset($p);}
            foreach($d['floor_service_sessions'] as &$session)if((int)($session['table_id']??0)===$tableId&&empty($session['ended_at'])){$session['ended_at']=$now;$session['ended_by']=$uid;$session['ended_reason']=$target==='available'?'table_released':'table_blocked';$session['status']='completed';}unset($session);
            $table['status']=$target;$table['updated_at']=$now;
        }
        $d['audit'][]=['at'=>$now,'action'=>'quick_floor_update','table_id'=>$tableId,'from'=>$oldTableStatus,'to'=>$target,'pr_id'=>$target==='occupied'?$prId:null,'sales_employee_id'=>$target==='occupied'?$salesId:null,'checkin_id'=>$ci!==null?(int)($d['checkins'][$ci]['id']??0):null,'by'=>$uid];
        unset($table);return $d;
    });
    $table=null;foreach($updated['tables']??[] as $t)if((int)($t['id']??0)===$tableId){$table=$t;break;}
    nq_reply(['ok'=>true,'message'=>'บันทึกสถานะโต๊ะแล้ว','table'=>$table?nq_table_payload($updated,$table):null,'state'=>nq_state($updated)]);
}catch(Throwable $e){nq_reply(['ok'=>false,'message'=>$e->getMessage()],400);}
