<?php
declare(strict_types=1);

require __DIR__.'/../app/workforce.php';

function attendance_check(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

$normal=['employee_id'=>5,'pr_id'=>5,'check_in'=>'2026-09-23T18:00:00+07:00','check_out'=>'2026-09-24T02:00:00+07:00','payroll_status'=>'final'];
$overnight=['employee_id'=>5,'pr_id'=>5,'check_in'=>'2026-09-07T10:03:08+07:00','check_out'=>'2026-09-23T10:44:54+07:00','payroll_status'=>'final'];
$invalid=['check_in'=>'2026-09-23T18:00:00+07:00','check_out'=>'2026-09-23T17:59:00+07:00','payroll_status'=>'final'];

attendance_check(workforce_attendance_duration_issue($normal)===null,'normal overnight shift remains valid');
attendance_check(workforce_payroll_final($normal),'normal overnight shift remains payable');
attendance_check(workforce_attendance_state($overnight)==='duration_over_limit','over-24-hour attendance is flagged');
attendance_check(!workforce_payroll_final($overnight),'over-24-hour attendance cannot be finalized');
attendance_check(workforce_attendance_state($invalid)==='invalid_duration','negative duration is flagged');
attendance_check(!workforce_payroll_final($invalid),'negative duration cannot be finalized');
attendance_check(workforce_attendance_checkout_matches(['employee_id'=>5,'pr_id'=>5],5,5),'same employee and PR can close the shift');
attendance_check(!workforce_attendance_checkout_matches(['employee_id'=>5,'pr_id'=>5],5,6),'different PR cannot close another PR shift');
attendance_check(!workforce_attendance_checkout_matches(['employee_id'=>5,'pr_id'=>0],5,5),'PR cannot close a non-PR employee shift');
attendance_check(workforce_attendance_checkout_is_stale(['check_in'=>'2026-09-21T10:00:00+07:00'],new DateTimeImmutable('2026-09-23T10:00:01+07:00')),'check-in older than 24 hours is stale');
attendance_check(!workforce_attendance_checkout_is_stale(['check_in'=>'2026-09-22T10:00:00+07:00'],new DateTimeImmutable('2026-09-23T10:00:00+07:00')),'check-in at 24 hours remains eligible');

$invalidLocationRejected=false;
try{workforce_attendance_submission_validate(91.0,100.0,10.0,'',false);}catch(RuntimeException $e){$invalidLocationRejected=true;}
attendance_check($invalidLocationRejected,'out-of-range coordinates are rejected');
$negativeAccuracyRejected=false;
try{workforce_attendance_submission_validate(13.0,100.0,-1.0,'',false);}catch(RuntimeException $e){$negativeAccuracyRejected=true;}
attendance_check($negativeAccuracyRejected,'negative GPS accuracy is rejected');
$missingCameraRejected=false;
try{workforce_attendance_submission_validate(null,null,null,'',true);}catch(RuntimeException $e){$missingCameraRejected=true;}
attendance_check($missingCameraRejected,'camera-required attendance rejects missing image evidence');
workforce_attendance_submission_validate(null,null,null,'',false);

$income=workforce_employee_income_estimate(['attendance'=>[$overnight]],['id'=>5,'pr_id'=>5,'position'=>'pr','payroll'=>['type'=>'hourly','hourly_rate'=>100]],'2026-09-01','2026-09-30');
attendance_check($income['pending_count']===1&&$income['work_minutes']===0&&(float)$income['total']===0.0,'invalid historical duration is held out of payroll');

echo "attendance integrity tests passed\n";
