<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$user=current_user();
if(!$user){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'method_not_allowed']);exit;}
if(!auth_csrf_valid((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);echo json_encode(['error'=>'csrf_expired']);exit;}
try{
    db_mutate_global(function(array $data)use($user):array{
        $found=false;
        foreach($data['users'] as &$account){
            if((int)($account['id']??0)!==(int)$user['id'])continue;
            if(empty($account['active'])||!empty($account['deleted_at']))throw new RuntimeException('ACCOUNT_DISABLED');
            $account['line_user_id']='';$account['line_linked_at']=null;$found=true;break;
        }unset($account);
        if(!$found)throw new RuntimeException('ACCOUNT_NOT_FOUND');
        $data['audit'][]=['at'=>date('c'),'action'=>'line_account_unlinked','by'=>(int)$user['id']];
        return $data;
    });
    echo json_encode(['ok'=>true]);
}catch(Throwable $error){http_response_code(409);echo json_encode(['error'=>'account_update_failed']);}
