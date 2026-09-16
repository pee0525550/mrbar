<?php
require __DIR__.'/../app/permissions.php';
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/sales-table.php';
function next_id(array $rows):int{return $rows?max(array_column($rows,'id'))+1:1;}
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);echo "PASS: $msg\n";}
function rejected(callable $fn,$msg){try{$fn();}catch(RuntimeException $e){echo "PASS: $msg\n";return;}throw new RuntimeException("Not rejected: $msg");}
$d=['_branch_context'=>['id'=>2],'roles'=>permission_default_roles(),'users'=>[
 ['id'=>10,'active'=>1,'role'=>'pr','branch_ids'=>[2],'username'=>'recorder'],
 ['id'=>11,'active'=>1,'role'=>'staff','branch_ids'=>[2],'username'=>'closer'],
 ['id'=>12,'active'=>1,'role'=>'admin','branch_ids'=>[2],'username'=>'manager'],
 ['id'=>13,'active'=>1,'role'=>'staff','branch_ids'=>[3]],
],'employees'=>[['id'=>20,'active'=>1,'position'=>'sales','name'=>'Sale A','code'=>'S01'],['id'=>21,'active'=>1,'position'=>'sales','name'=>'Sale B','code'=>'S02'],['id'=>22,'active'=>1,'position'=>'pr']],
'tables'=>[['id'=>1,'active'=>1,'code'=>'T01','status'=>'available'],['id'=>2,'active'=>1,'code'=>'T02','status'=>'available']]];
$open=['action'=>'open','table_id'=>1,'sales_id'=>20];
$d=st_apply($d,$open,10);
check($d['sales_table_sessions'][0]['sales_id']===20&&$d['sales_table_sessions'][0]['opened_by']===10,'proxy actor is distinct from Sales owner');
rejected(fn()=>st_apply($d,$open,11),'duplicate active session blocked');
rejected(fn()=>st_apply($d,array_merge($open,['table_id'=>2,'sales_id'=>22]),11),'PR cannot be selected as Sales');
rejected(fn()=>st_apply($d,array_merge($open,['table_id'=>2]),13),'wrong branch denied');
$close=['action'=>'close','table_id'=>1,'session_id'=>1,'revision'=>1,'receipts'=>"RC001\nRC002"];
rejected(fn()=>st_apply($d,array_merge($close,['receipts'=>'']),11),'receipt mandatory');
rejected(fn()=>st_apply($d,array_merge($close,['receipts'=>"RC001\nrc001"]),11),'duplicate receipts in submission blocked');
$d=st_apply($d,$close,11);
check($d['sales_table_sessions'][0]['closed_by']===11&&count($d['sales_table_sessions'][0]['receipts'])===2,'another actor closes with split receipts');
check($d['sales_table_sessions'][0]['matched_net_sales']===null,'unknown POS total stays null, not zero');
rejected(fn()=>st_apply($d,$close,11),'stale double submit rejected');
$d=st_apply($d,$open,10);
rejected(fn()=>st_apply($d,array_merge($close,['session_id'=>2]),11),'receipt reused across sessions blocked');
$transfer=['action'=>'transfer','table_id'=>1,'session_id'=>1,'revision'=>2,'sales_id'=>21,'reason'=>'Correct mistaken selection'];
rejected(fn()=>st_apply($d,$transfer,10),'regular recorder cannot reassign');
$d=st_apply($d,$transfer,12);
check($d['sales_table_sessions'][0]['sales_id']===21&&end($d['audit'])['before']['sales_id']===20,'manager transfer retains audit before and after');
rejected(fn()=>st_apply($d,array_merge($transfer,['revision'=>3,'reason'=>'']),12),'correction reason mandatory');
$d=st_apply($d,['action'=>'edit_receipts','table_id'=>1,'session_id'=>1,'revision'=>3,'receipts'=>'RC003','reason'=>'Typo'],12);
check($d['sales_table_sessions'][0]['receipts']===['RC003'],'manager corrects receipt');
check(in_array('sales_table_sessions',db_branch_bucket_names(),true),'sessions persisted in branch bucket');

$disabled=$d;$disabled['users'][0]['active']=0;
rejected(fn()=>st_apply($disabled,array_merge($open,['table_id'=>2]),10),'disabled user denied');
$denied=$d;$denied['users'][0]['permission_overrides']['sales_sessions.record']=0;
rejected(fn()=>st_apply($denied,array_merge($open,['table_id'=>2]),10),'explicit permission override respected');
check($d['tables'][0]['status']==='available','attribution does not silently alter Night Ops occupancy');
// Persist/restore through the real branch merge layer, without reading or writing live storage.
$raw=db_migrate_array(['users'=>$d['users'],'roles'=>$d['roles'],'branches'=>[['id'=>2,'name'=>'Priest','slug'=>'Priest','active'=>1],['id'=>3,'name'=>'Other','slug'=>'Other','active'=>1]],'branch_data'=>['2'=>db_empty_branch_data(),'3'=>db_empty_branch_data()],'meta'=>['schema'=>28],'audit'=>[]]);
$view=db_branch_view($raw,2);
$view['sales_table_sessions']=$d['sales_table_sessions'];
$raw=db_merge_branch_view($raw,$view,2);
check(count(db_branch_view($raw,2)['sales_table_sessions'])===2,'sessions survive branch merge and reload');
check(count(db_branch_view($raw,3)['sales_table_sessions'])===0,'sessions remain isolated from other branches');
echo "Sales table regression passed.\n";
