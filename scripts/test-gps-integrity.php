<?php
declare(strict_types=1);

require __DIR__.'/../app/workforce.php';

function gps_integrity_check(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

$employee=['id'=>21,'pr_id'=>7,'branch_id'=>1,'attendance_policy'=>['gps_required'=>null,'geofence_mode'=>'warn']];
$now=new DateTimeImmutable('now');
$previousAt=$now->modify('-5 minutes')->format(DateTimeInterface::ATOM);
$base=['settings'=>['attendance_gps_required'=>'1','attendance_gps_integrity_enabled'=>'1'],'employees'=>[$employee],'branches'=>[['id'=>1,'name'=>'Main','lat'=>13.7563,'lng'=>100.5018,'radius_m'=>200,'active'=>1]],'attendance'=>[['employee_id'=>21,'pr_id'=>7,'check_in'=>$previousAt,'checkin_lat'=>13.7563,'checkin_lng'=>100.5018]]];

$risk=workforce_gps_integrity_check($base,21,7,15.87,100.9925,$now);
gps_integrity_check($risk!==null&&$risk['speed_kmh']>250,'impossible recent movement is detected');
gps_integrity_check(workforce_gps_integrity_check($base,21,7,13.7564,100.5019,$now)===null,'nearby GPS jitter is accepted');

$old=$base;$old['attendance'][0]['check_in']=$now->modify('-60 minutes')->format(DateTimeInterface::ATOM);
gps_integrity_check(workforce_gps_integrity_check($old,21,7,15.87,100.9925,$now)===null,'old location fixes are outside the detection window');

$blocked=false;
try{workforce_resolve_branch($base,$employee,15.87,100.9925);}catch(RuntimeException $e){$blocked=strpos($e->getMessage(),'GPS เคลื่อนที่เร็วผิดปกติ')!==false;}
gps_integrity_check($blocked,'enabled GPS integrity guard rejects impossible movement');

$testMode=$base;$testMode['settings']['attendance_gps_integrity_enabled']='0';
$resolved=workforce_resolve_branch($testMode,$employee,15.87,100.9925);
gps_integrity_check(isset($resolved['_distance']),'turning the guard off allows fake-GPS test coordinates through this guard');

echo "GPS integrity tests passed\n";
