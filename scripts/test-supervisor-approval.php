<?php
declare(strict_types=1);

require __DIR__.'/../app/permissions.php';
require __DIR__.'/../app/workforce.php';

function expect_supervisor(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
    fwrite(STDOUT,"PASS: {$message}\n");
}

$data=[
    'meta'=>['active_branch_id'=>1],
    '_branch_context'=>['id'=>1],
    'settings'=>['workforce_peer_approval_enabled'=>'0'],
    'roles'=>permission_default_roles(),
    'users'=>[
        ['id'=>10,'role'=>'staff','active'=>1],
        ['id'=>11,'role'=>'staff','active'=>1],
        ['id'=>12,'role'=>'staff','active'=>1],
        ['id'=>13,'role'=>'staff','active'=>1],
        ['id'=>1,'role'=>'admin','active'=>1],
        ['id'=>15,'role'=>'staff','active'=>0],
        ['id'=>16,'role'=>'pr','active'=>1],
    ],
    'employees'=>[
        ['id'=>100,'user_id'=>10,'code'=>'M01','position'=>'manager','branch_id'=>null,'active'=>1],
        ['id'=>101,'user_id'=>11,'code'=>'S01','position'=>'staff','branch_id'=>null,'supervisor_employee_id'=>100,'active'=>1],
        ['id'=>102,'user_id'=>12,'code'=>'S02','position'=>'staff','branch_id'=>2,'supervisor_employee_id'=>100,'active'=>1],
        ['id'=>103,'user_id'=>null,'code'=>'S03','position'=>'staff','branch_id'=>null,'supervisor_employee_id'=>100,'active'=>0],
        ['id'=>104,'user_id'=>13,'code'=>'P01','position'=>'staff','branch_id'=>1,'active'=>1],
        ['id'=>105,'user_id'=>12,'code'=>'S04','position'=>'staff','branch_id'=>1,'supervisor_employee_id'=>104,'active'=>1],
        ['id'=>106,'user_id'=>12,'code'=>'S05','position'=>'staff','branch_id'=>2,'supervisor_employee_id'=>104,'active'=>1],
        ['id'=>107,'user_id'=>15,'code'=>'S06','position'=>'staff','branch_id'=>1,'active'=>1],
        ['id'=>108,'user_id'=>null,'pr_id'=>201,'code'=>'PR07','position'=>'pr','branch_id'=>1,'active'=>1],
    ],
    'prs'=>[
        ['id'=>201,'employee_id'=>108,'user_id'=>16,'branch_id'=>1,'active'=>1,'code'=>'PR07','name'=>'PR Seven'],
    ],
];
$manager=$data['users'][0];
$peer=$data['users'][3];
$approverCandidates=workforce_approver_candidates($data,1);
expect_supervisor(count($approverCandidates)===6,'approver selector includes every active employee in the current branch');
expect_supervisor(count(array_filter($approverCandidates,fn($row)=>!empty($row['selectable'])))===1,'only eligible managers are selectable while peer approval is off');
$sameBranchLeave=['employee_id'=>101];
$otherBranchLeave=['employee_id'=>102];
expect_supervisor(workforce_direct_report_ids($data,100)===[101],'direct team includes active AUTO employee in current branch only');
expect_supervisor(workforce_can_review_leave($data,$manager,$sameBranchLeave),'manager can review a direct report leave without global leave permission');
expect_supervisor(!workforce_can_review_leave($data,$manager,$otherBranchLeave),'manager cannot review leave from another branch');
expect_supervisor(workforce_can_review_leave($data,$data['users'][4],$otherBranchLeave),'admin retains global leave review access');
expect_supervisor(!workforce_approver_candidate_eligible($data,$data['employees'][5],$data['employees'][4]),'peer approver selection is disabled by default');
$data['employees'][4]['workforce_approval_enabled']=1;
expect_supervisor(workforce_approver_candidate_eligible($data,$data['employees'][5],$data['employees'][4]),'individual approval permission enables a peer while branch-wide Peer Approval is off');
unset($data['employees'][4]['workforce_approval_enabled']);
$data['employees'][0]['workforce_approval_enabled']=0;
expect_supervisor(!workforce_can_review_leave($data,$manager,$sameBranchLeave),'individual approval opt-out blocks a manager from team-routed leave');
unset($data['employees'][0]['workforce_approval_enabled']);
$data['settings']['workforce_peer_approval_enabled']='1';
expect_supervisor(workforce_approver_candidate_eligible($data,$data['employees'][5],$data['employees'][4]),'branch policy permits an active same-branch peer approver');
expect_supervisor(count(array_filter(workforce_approver_candidates($data,1),fn($row)=>!empty($row['selectable'])))===5,'enabling peer approval makes active same-branch account holders selectable');
expect_supervisor(!workforce_approver_candidate_eligible($data,$data['employees'][5],$data['employees'][6]),'inactive login cannot be selected as an approver');
expect_supervisor(!workforce_approver_candidate_eligible($data,$data['employees'][4],$data['employees'][4]),'employee cannot be assigned as their own approver');
expect_supervisor((int)(workforce_employee_login_account($data,$data['employees'][8])['id']??0)===16,'PR profile login is recognized when Employee user_id is empty');
expect_supervisor((int)(workforce_user_employee($data,$data['users'][6])['id']??0)===108,'PR login resolves to its Employee Profile for approval routing');
expect_supervisor((int)(workforce_employee_by_user($data,16)['id']??0)===108,'PR login is recognized by shared Employee lookup');
expect_supervisor(workforce_people_health($data)['linked']===8,'employee health counts Employee-linked and PR Profile-linked logins together');
$prSyncFixture=$data;$prSyncFixture['employees'][8]['user_id']=null;
workforce_sync_employee_to_pr($prSyncFixture,$prSyncFixture['employees'][8]);
expect_supervisor((int)($prSyncFixture['prs'][0]['user_id']??0)===16,'saving a PR Employee without Employee user_id preserves the PR login link');
expect_supervisor(workforce_can_review_assigned_employee($data,$peer,105),'assigned peer can review the employee when policy is enabled');
expect_supervisor(workforce_can_review_leave($data,$peer,['employee_id'=>105]),'assigned peer can review a direct report leave request');
expect_supervisor(!workforce_can_review_assigned_employee($data,$peer,101),'approver cannot review employees outside their assigned route');
expect_supervisor(!workforce_can_review_assigned_employee($data,$peer,106),'peer approver cannot review a different branch');
expect_supervisor(!workforce_can_review_assigned_employee($data,$peer,104),'approver cannot review their own attendance or requests');
$data['settings']['workforce_peer_approval_enabled']='0';
expect_supervisor(!workforce_can_review_assigned_employee($data,$peer,105),'turning off peer approval revokes peer approval access');

echo "Supervisor approval tests passed\n";
