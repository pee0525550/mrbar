<?php
declare(strict_types=1);

function assert_staff_shell(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

$bootstrap=(string)file_get_contents(__DIR__.'/../app/bootstrap.php');
$calendar=(string)file_get_contents(__DIR__.'/../pr-calendar.php');
$previewJs=(string)file_get_contents(__DIR__.'/../assets/staff-preview-v12718.js');

foreach(['time.php','employee-time.php','employee-calendar.php','employee-income.php','pr.php','pr-calendar.php','pr-jobs.php'] as $route){
    assert_staff_shell(strpos($bootstrap,"'{$route}'")!==false,"staff route missing from branch-switcher exclusion: {$route}");
}
assert_staff_shell(strpos($bootstrap,'$isStaffTime=in_array($script,$staffTimeScripts,true)')!==false,'staff routes must use the shared exclusion list');
assert_staff_shell(strpos($calendar,'?:$employee')===false,'PR calendar must not reference employee before assignment');
foreach(['pr.php','pr-calendar.php','pr-jobs.php','employee-time.php','employee-calendar.php','employee-income.php'] as $route){
    $escaped=str_replace('.','\\.',$route);
    assert_staff_shell(strpos($previewJs,$escaped)!==false,"preview navigation allow-list missing: {$route}");
}

echo "staff time shell tests passed\n";
