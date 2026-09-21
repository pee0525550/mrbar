<?php
declare(strict_types=1);

require __DIR__.'/../app/db.php';

$data=db_load_raw();
$issues=[];
$buckets=['users','branches','employees','prs','tables','attendance','shifts','reservations','customers'];
$idMaps=[];

foreach($buckets as $bucket){
    $idMaps[$bucket]=[];
    foreach($data[$bucket]??[] as $row){
        $id=(int)($row['id']??0);
        if($id<=0)continue;
        if(isset($idMaps[$bucket][$id]))$issues[]=$bucket.' has duplicate ID '.$id;
        $idMaps[$bucket][$id]=true;
    }
}

foreach($data['employees']??[] as $employee){
    $id=(int)($employee['id']??0);
    $userId=(int)($employee['user_id']??0);
    $prId=(int)($employee['pr_id']??0);
    $branchId=(int)($employee['branch_id']??0);
    if($userId>0&&!isset($idMaps['users'][$userId]))$issues[]="employee $id references missing user $userId";
    if($prId>0&&!isset($idMaps['prs'][$prId]))$issues[]="employee $id references missing PR $prId";
    if($branchId>0&&!isset($idMaps['branches'][$branchId]))$issues[]="employee $id references missing branch $branchId";
}

foreach($data['attendance']??[] as $attendance){
    $id=(int)($attendance['id']??0);
    $employeeId=(int)($attendance['employee_id']??0);
    if($employeeId>0&&!isset($idMaps['employees'][$employeeId]))$issues[]="attendance $id references missing employee $employeeId";
}

$activeSuperAdmins=0;
foreach($data['users']??[] as $user)if(!empty($user['active'])&&!empty($user['super_admin']))$activeSuperAdmins++;
if($activeSuperAdmins<1)$issues[]='no active Super Admin account';

$summary=[];
foreach($buckets as $bucket)$summary[$bucket]=count($data[$bucket]??[]);
echo json_encode([
    'ok'=>!$issues,
    'schema'=>(int)($data['meta']['schema']??0),
    'active_database'=>basename(db_path()),
    'counts'=>$summary,
    'active_super_admins'=>$activeSuperAdmins,
    'issues'=>$issues,
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;

exit($issues?1:0);
