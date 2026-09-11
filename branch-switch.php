<?php
require __DIR__.'/app/bootstrap.php';
$u=current_user();if(!$u){header('Location:login.php');exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Location:admin.php');exit;}
csrf_check();
$branchId=max(0,(int)($_POST['branch_id']??0));$raw=db_load_global();$branch=branch_find($raw,$branchId);
if(!$branch||empty($branch['active'])||!db_user_can_branch($u,$branchId)){http_response_code(403);exit('ไม่มีสิทธิ์เข้าถึงร้านนี้');}
$_SESSION['mrbar_active_branch_id']=$branchId;
try{db_mutate_global(function($d)use($u,$branchId){$d['audit'][]=['at'=>date('c'),'action'=>'branch_switched','branch_id'=>$branchId,'by'=>(int)($u['id']??0)];return $d;});}catch(Throwable $e){}
$return=trim((string)($_POST['return_to']??'admin.php'));
if($return===''||preg_match('/[\r\n]/',$return)||preg_match('#^[a-z][a-z0-9+.-]*:#i',$return)||strpos($return,'//')===0)$return='admin.php';
header('Location: '.$return);exit;
