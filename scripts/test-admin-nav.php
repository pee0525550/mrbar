<?php
declare(strict_types=1);

require __DIR__.'/../app/permissions.php';

function db_load(): array { return $GLOBALS['navFixture']; }
function h(string $value): string { return htmlspecialchars($value,ENT_QUOTES,'UTF-8'); }

$GLOBALS['navFixture']=['roles'=>[
    'limited'=>['permissions'=>['reports.view'=>1]],
    'reservation_user'=>['permissions'=>['reservations.view'=>1]],
    'admin'=>['permissions'=>permission_default_roles()['admin']['permissions']],
]];
require __DIR__.'/../app/admin-nav.php';

$limited=admin_sidebar('reports',['id'=>1,'role'=>'limited','display_name'=>'Report User']);
if(strpos($limited,'system-reports.php')===false)throw new RuntimeException('Limited report user should see Report Center');
foreach(['settings.php','role-permissions.php','employees.php','pos-incentive.php'] as $forbidden){
    if(strpos($limited,'href="'.$forbidden.'"')!==false)throw new RuntimeException('Limited report user should not see '.$forbidden);
}
if(strpos($limited,'night-ops.php')===false)throw new RuntimeException('Night Operations should remain available to report users');
if(strpos($limited,'href="reservations.php"')!==false)throw new RuntimeException('Report-only user should not see Reservation Management');
if(strpos($limited,'admin-tools.php')!==false)throw new RuntimeException('QR Tools should not appear for report-only users');
$GLOBALS['navFixture']['roles']['table_user']=['permissions'=>['tables.view'=>1]];
$tableUser=admin_sidebar('tools',['id'=>3,'role'=>'table_user','display_name'=>'Table User']);
if(strpos($tableUser,'admin-tools.php')===false)throw new RuntimeException('QR Tools should appear for table users');
$reservationUser=admin_sidebar('reservation',['id'=>4,'role'=>'reservation_user','display_name'=>'Reservation User']);
if(strpos($reservationUser,'href="reservations.php"')===false)throw new RuntimeException('Reservation-view user should see Reservation Management');
if(strpos($reservationUser,'href="night-ops.php"')!==false)throw new RuntimeException('Reservation-only user should not see Night Operations');

$admin=admin_sidebar('dashboard',['id'=>2,'role'=>'admin','display_name'=>'Admin']);
foreach(['admin.php','night-ops.php','reservations.php','admin-tools.php','employees.php','settings.php','system-reports.php'] as $visible){
    if(strpos($admin,'href="'.$visible.'"')===false)throw new RuntimeException('Admin should see '.$visible);
}

foreach(['CUSTOMER EXPERIENCE','REPORTS & PAYOUTS','STORE & SYSTEM'] as $groupLabel){
    if(strpos($admin,str_replace('&','&amp;',$groupLabel))===false)throw new RuntimeException('Expected sidebar group '.$groupLabel);
}
if(strpos($admin,'href="pos-incentive.php"')===false||strpos($admin,'href="system-reports.php"')===false){
    throw new RuntimeException('POS Incentive and Report Center should remain available in the reporting group');
}
if(strpos($admin,'href="staff-preview.php"')===false||strpos($admin,'href="zone-studio.php"')===false){
    throw new RuntimeException('Staff Preview and Zone Studio should remain available');
}
$ordered=['href="admin.php"','href="reservations.php"','href="night-ops.php"','href="admin-tools.php"','href="employees.php"','href="workforce-schedule.php"','href="payroll-attendance.php"','href="hr-approval-center.php"','href="staff-preview.php"','href="customers.php"','href="customer-web.php"','href="pos-incentive.php"','href="system-reports.php"','href="zone-studio.php"'];
$previous=-1;
foreach($ordered as $needle){$position=strpos($admin,$needle);if($position===false||$position<=$previous)throw new RuntimeException('Sidebar grouping/order incorrect at '.$needle);$previous=$position;}

foreach(['leaves'=>'hr-approval-center.php','exceptions'=>'hr-approval-center.php','roster'=>'workforce-schedule.php'] as $alias=>$expectedHref){
    $html=admin_sidebar($alias,['id'=>2,'role'=>'admin','display_name'=>'Admin']);
    if(strpos($html,'class="active " href="'.$expectedHref.'"')===false)throw new RuntimeException('Active sidebar alias '.$alias.' should highlight '.$expectedHref);
}

echo "Permission-aware admin navigation regression passed.\n";
