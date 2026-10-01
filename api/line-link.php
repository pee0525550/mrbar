<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$user=current_user();
if(!$user){http_response_code(401);echo json_encode(['error'=>'unauthorized']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'method_not_allowed']);exit;}
csrf_check();
$lineUserId=mrbar_line_verify_liff_id_token(trim((string)($_POST['id_token']??'')));
if($lineUserId===null){http_response_code(422);echo json_encode(['error'=>'line_token_invalid']);exit;}
$userId=(int)$user['id'];
try{
    db_mutate_global(function(array $data)use($lineUserId,$userId):array{
        $found=false;
        foreach($data['users']??[] as $account){
            if((int)($account['id']??0)!==$userId&&(string)($account['line_user_id']??'')===$lineUserId)throw new RuntimeException('LINE_ACCOUNT_ALREADY_LINKED');
            if((int)($account['id']??0)===$userId){
                if(empty($account['active'])||!empty($account['deleted_at']))throw new RuntimeException('ACCOUNT_NOT_FOUND');
                if((string)($account['line_user_id']??'')!==''&&(string)$account['line_user_id']!==$lineUserId)throw new RuntimeException('LINE_ALREADY_LINKED');
                $found=true;
            }
        }
        if(!$found)throw new RuntimeException('ACCOUNT_NOT_FOUND');
        foreach($data['users'] as &$account)if((int)($account['id']??0)===$userId){$account['line_user_id']=$lineUserId;$account['line_linked_at']=date('c');break;}unset($account);
        $data['audit'][]=['at'=>date('c'),'action'=>'line_account_linked','by'=>$userId];
        return $data;
    });
    echo json_encode(['ok'=>true,'linked'=>true],JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){
    $codes=['LINE_ACCOUNT_ALREADY_LINKED'=>'line_account_in_use','LINE_ALREADY_LINKED'=>'line_already_linked'];
    $code=$codes[$error->getMessage()]??'account_update_failed';
    http_response_code($code==='account_update_failed'?500:409);echo json_encode(['error'=>$code]);
}
