<?php
declare(strict_types=1);

function assert_staff_shell(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

$bootstrap=(string)file_get_contents(__DIR__.'/../app/bootstrap.php');
$calendar=(string)file_get_contents(__DIR__.'/../pr-calendar.php');
$employeeCalendar=(string)file_get_contents(__DIR__.'/../employee-calendar.php');
$employeeIncome=(string)file_get_contents(__DIR__.'/../employee-income.php');
$approvalApi=(string)file_get_contents(__DIR__.'/../api/approval-notifications.php');
$previewJs=(string)file_get_contents(__DIR__.'/../assets/staff-preview-v12718.js');

foreach(['time.php','employee-time.php','employee-calendar.php','employee-income.php','pr.php','pr-calendar.php','pr-jobs.php'] as $route){
    assert_staff_shell(strpos($bootstrap,"'{$route}'")!==false,"staff route missing from branch-switcher exclusion: {$route}");
}
assert_staff_shell(strpos($bootstrap,'$isStaffTime=in_array($script,$staffTimeScripts,true)')!==false,'staff routes must use the shared exclusion list');
assert_staff_shell(strpos($calendar,'?:$employee')===false,'PR calendar must not reference employee before assignment');
assert_staff_shell(strpos($employeeCalendar,"!\$isAdminPreview&&empty(\$employee['active'])")!==false,'inactive employees cannot access calendar outside admin preview');
assert_staff_shell(strpos($employeeIncome,"!\$isAdminPreview&&empty(\$employee['active'])")!==false,'inactive employees cannot access income outside admin preview');
assert_staff_shell(strpos($bootstrap,'assets/approval-notice.js')!==false&&strpos($bootstrap,"'leave.manage'")!==false,'approval notice is shared and permission-gated');
assert_staff_shell(strpos($approvalApi,"user_can(\$user,'leave.manage',\$data)")!==false&&strpos($approvalApi,'array_slice($items,0,8)')!==false,'approval notification endpoint filters access and bounds the payload');
foreach(['pr.php','pr-calendar.php','pr-jobs.php','employee-time.php','employee-calendar.php','employee-income.php'] as $route){
    $escaped=str_replace('.','\\.',$route);
    assert_staff_shell(strpos($previewJs,$escaped)!==false,"preview navigation allow-list missing: {$route}");
}

echo "staff time shell tests passed\n";
