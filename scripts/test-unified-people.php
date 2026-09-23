<?php
declare(strict_types=1);

require __DIR__.'/../app/db.php';
require __DIR__.'/../app/workforce.php';
require __DIR__.'/../app/branches.php';
require __DIR__.'/../app/account-onboarding.php';
require __DIR__.'/../app/reservation-sales.php';

function expect_true(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
    fwrite(STDOUT,"PASS: {$message}\n");
}
function next_id(array $rows): int {return $rows?max(array_map(fn($row)=>(int)($row['id']??0),$rows))+1:1;}

$fixture=[
    'meta'=>['schema'=>27,'active_branch_id'=>1],
    'roles'=>permission_default_roles(),
    'branches'=>[
        ['id'=>1,'name'=>'Branch A','slug'=>'a','active'=>1],
        ['id'=>2,'name'=>'Branch B','slug'=>'b','active'=>1],
    ],
    'users'=>[
        ['id'=>1,'username'=>'owner','display_name'=>'Owner','role'=>'admin','super_admin'=>1,'active'=>1,'branch_ids'=>[]],
        ['id'=>2,'username'=>'sales-a','display_name'=>'Sales A','role'=>'sales','super_admin'=>0,'active'=>1,'branch_ids'=>[1]],
        ['id'=>3,'username'=>'sales-b','display_name'=>'Sales B','role'=>'sales','super_admin'=>0,'active'=>1,'branch_ids'=>[2]],
        ['id'=>4,'username'=>'pr-a','display_name'=>'PR A','role'=>'pr','super_admin'=>0,'active'=>1,'branch_ids'=>[1]],
    ],
    'branch_data'=>[
        '1'=>db_empty_branch_data(['shop_name'=>'Branch A']),
        '2'=>db_empty_branch_data(['shop_name'=>'Branch B']),
    ],
];
$migrated=db_migrate_array($fixture);
expect_true((int)$migrated['meta']['schema']===28,'migrates fixture to Schema 28');

$a=$migrated['branch_data']['1'];
$b=$migrated['branch_data']['2'];
$salesA=array_values(array_filter($a['employees'],fn($e)=>(int)($e['user_id']??0)===2))[0]??null;
$salesB=array_values(array_filter($b['employees'],fn($e)=>(int)($e['user_id']??0)===3))[0]??null;
$prA=array_values(array_filter($a['employees'],fn($e)=>(int)($e['user_id']??0)===4))[0]??null;

expect_true(($salesA['position']??'')==='sales','creates Sales employee in assigned branch');
expect_true(($salesB['position']??'')==='sales','creates Sales employee in second branch');
expect_true(!array_filter($a['employees'],fn($e)=>(int)($e['user_id']??0)===3),'does not copy employee across branches');
expect_true(($prA['position']??'')==='pr' && !empty($prA['pr_id']),'creates one PR extension for linked PR employee');
expect_true((int)($migrated['users'][1]['employee_ids_by_branch']['1']??0)===(int)$salesA['id'],'records per-branch employee link');

$branchBView=db_branch_view($migrated,2);
$branchBView['employees'][]=['id'=>next_id($branchBView['employees']),'code'=>'STB01','name'=>'Branch B Staff','position'=>'staff','active'=>1,'branch_id'=>2,'user_id'=>null];
$branchBEmployeeId=(int)$branchBView['employees'][array_key_last($branchBView['employees'])]['id'];
$newBranchBUser=account_create_and_link($branchBView,$branchBEmployeeId,'branch-b-staff','password-123');
expect_true(($newBranchBUser['branch_ids']??[])===[2],'new employee account inherits its employee branch membership');
$_SERVER['SCRIPT_NAME']='/it/employees.php';$_SERVER['HTTP_HOST']='example.test';
expect_true(str_contains(account_invite_url(str_repeat('x',40),'b'),'public_branch=b'),'employee invite link carries the employee branch slug');
expect_true(branch_publicly_available(['active'=>1,'published'=>1]),'active published branch is public');
expect_true(!branch_publicly_available(['active'=>1,'published'=>0]),'unpublished branch is not public');
expect_true(!branch_publicly_available(['active'=>0,'published'=>1]),'inactive branch is hidden by default');
expect_true(branch_publicly_available(['active'=>0,'published'=>1],['show_closed_branches'=>'1']),'inactive branch follows the show-closed portal setting');
expect_true(branch_admin_preview_authorized(['super_admin'=>1],'valid','valid'),'Super Admin may use a valid branch preview token');
expect_true(!branch_admin_preview_authorized(['super_admin'=>0],'valid','valid'),'regular users cannot preview unpublished branches');
expect_true(!branch_admin_preview_authorized(['super_admin'=>1],'invalid','valid'),'invalid branch preview token is rejected');
expect_true(count(branch_user_related_branch_ids(['branch_ids'=>[1],'employee_ids_by_branch'=>['1'=>4,'2'=>8]]))===2,'linked employee cards identify a shared account across branches');
$_REQUEST['public_branch']='missing-branch';
expect_true(branch_resolve_request($migrated)===null,'unknown public branch slug does not resolve to the default branch');

$view=db_branch_view($migrated,1);
$health=workforce_people_health($view);
expect_true($health['accounts']===3,'counts only branch accounts plus platform super admin');
expect_true($health['orphan_accounts']===0,'has no orphan account after migration');
expect_true(workforce_account_role_compatible($salesA,$migrated['users'][1]),'accepts Sales role for Sales position');
$bad=$migrated['users'][1];$bad['role']='pr';
expect_true(!workforce_account_role_compatible($salesA,$bad),'rejects PR role for Sales position');

$_SERVER['SCRIPT_NAME']='/it/custumers/reserve.php';
$options=reservation_sales_options($view);$salesOption=array_values(array_filter($options,fn($row)=>(int)$row['employee_id']===(int)$salesA['id']))[0]??null;
expect_true(str_starts_with((string)($salesOption['photo_url']??''),'/it/sales-photo.php?'),'uses installation-root Sales photo endpoint');
expect_true(str_contains((string)($salesOption['photo_url']??''),'public_branch=a'),'keeps public Branch context in Sales photo URL');

fwrite(STDOUT,"Unified People & Access regression passed.\n");
