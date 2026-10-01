<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$user=current_user();
if(!$user){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo json_encode(['error'=>'method_not_allowed']);exit;}
$data=db_load();
session_write_close();
$admin=($user['role']??'')==='admin';
$teamReportIds=workforce_user_direct_report_ids($data,$user);
$canLeave=$admin||user_can($user,'leave.manage',$data)||count($teamReportIds)>0;
$canCorrection=$admin||user_can($user,'workforce.exceptions.manage',$data)||user_can($user,'attendance.manage',$data)||count($teamReportIds)>0;
$canSubstitute=$admin||user_can($user,'substitute.manage',$data);
$canException=$admin||user_can($user,'workforce.exceptions.manage',$data)||user_can($user,'attendance.manage',$data)||count($teamReportIds)>0;
$canPenalty=$admin||user_can($user,'payroll.manage',$data);
if(!$canLeave&&!$canCorrection&&!$canSubstitute&&!$canException&&!$canPenalty){echo json_encode(['items'=>[],'count'=>0]);exit;}

$items=[];
$add=function(string $type,string $label,string $name,string $detail,string $href,string $at)use(&$items):void{
    $items[]=['type'=>$type,'label'=>$label,'name'=>$name,'detail'=>$detail,'href'=>$href,'at'=>$at];
};
$employeeName=function(int $id)use($data):string{
    $employee=workforce_employee_by_id($data,$id);
    if(!$employee)return 'พนักงาน';
    return workforce_employee_display_name($employee)?:((string)($employee['code']??'พนักงาน'));
};

if($canLeave)foreach($data['leave_requests']??[] as $row){
    if(($row['status']??'')!=='pending')continue;
    if(!$admin&&!user_can($user,'leave.manage',$data)&&!workforce_can_review_leave($data,$user,$row))continue;
    $eid=(int)($row['employee_id']??0);
    if(!$eid&&!empty($row['pr_id']))$eid=(int)(workforce_employee_by_pr($data,(int)$row['pr_id'])['id']??0);
    $types=['sick'=>'ลาป่วย','personal'=>'ลากิจ','vacation'=>'ลาพักร้อน','unpaid'=>'ลาไม่รับค่าจ้าง','other'=>'ลาอื่น ๆ'];
    $range=(string)($row['start_date']??'');if(($row['end_date']??$range)!==$range)$range.=' ถึง '.(string)$row['end_date'];
    $add('leave','คำขอลา',$employeeName($eid),($types[(string)($row['type']??'')]??'ลา').' · '.$range,'hr-approval-center.php?view=leave',(string)($row['requested_at']??''));
}
if($canCorrection)foreach($data['time_correction_requests']??[] as $row){
    if(($row['status']??'')!=='pending')continue;
    if(!$admin&&!user_can($user,'workforce.exceptions.manage',$data)&&!user_can($user,'attendance.manage',$data)&&!workforce_can_review_assigned_employee($data,$user,(int)($row['employee_id']??0)))continue;
    $add('correction','ขอแก้เวลา',$employeeName((int)($row['employee_id']??0)),(string)($row['reason']??'รอตรวจสอบ'),'hr-approval-center.php?view=correction',(string)($row['requested_at']??''));
}
if($canSubstitute)foreach($data['substitute_requests']??[] as $row){
    if(($row['status']??'')!=='pending')continue;
    $add('substitute','ขอ PR มาแทน',$employeeName((int)($row['original_employee_id']??0)),(string)($row['date']??''),'hr-approval-center.php?view=substitute',(string)($row['requested_at']??''));
}
if($canException)foreach($data['attendance']??[] as $row){
    if(!in_array(workforce_attendance_state($row),['missing_checkout','provisional'],true))continue;
    $employeeId=workforce_attendance_employee_id($data,$row);
    if(!$admin&&!user_can($user,'workforce.exceptions.manage',$data)&&!user_can($user,'attendance.manage',$data)&&!workforce_can_review_assigned_employee($data,$user,$employeeId))continue;
    $add('exception','ตรวจสอบเวลา',$employeeName($employeeId),(string)($row['check_in']??''),'hr-approval-center.php?view=exception',(string)($row['check_in']??''));
}
if($canPenalty)foreach($data['attendance_penalties']??[] as $row){
    if(($row['status']??'pending')!=='pending')continue;
    $add('penalty','No-show / Penalty',$employeeName((int)($row['employee_id']??0)),(string)($row['date']??''),'hr-approval-center.php?view=penalty',(string)($row['created_at']??''));
}
usort($items,static fn($a,$b)=>strcmp((string)$b['at'],(string)$a['at']));
echo json_encode(['items'=>array_slice($items,0,8),'count'=>count($items)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
