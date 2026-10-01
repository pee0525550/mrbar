<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function line_login_json(int $status,array $payload): void {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    header('Allow: POST');
    line_login_json(405,['error'=>'method_not_allowed']);
}
if(!auth_csrf_valid((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??'')))line_login_json(419,['error'=>'csrf_expired']);

$identity=mrbar_line_verify_liff_identity(trim((string)($_POST['id_token']??'')));
if($identity===null)line_login_json(422,['error'=>'line_token_invalid']);
$returnKey=in_array((string)($_POST['return_to']??''),['time','income','sales_table'],true)?(string)$_POST['return_to']:'';
$accountMatch=mrbar_line_staff_login_match(db_load_global(),$identity['sub']);
if($accountMatch['status']==='ambiguous')line_login_json(409,['error'=>'line_account_ambiguous']);
if($accountMatch['status']!=='matched')line_login_json(403,['error'=>'line_account_not_linked']);
$user=$accountMatch['user'];

try{
    db_mutate_global(function(array $data)use($user):array{
        $match=mrbar_line_staff_login_match($data,(string)($user['line_user_id']??''));
        if($match['status']!=='matched'||(int)($match['user']['id']??0)!==(int)($user['id']??0))throw new RuntimeException('LINE_ACCOUNT_NOT_LINKED');
        $data['audit'][]=['at'=>date('c'),'action'=>'line_login','user_id'=>(int)$user['id']];
        return $data;
    });
}catch(Throwable $error){
    line_login_json(409,['error'=>'line_account_not_linked']);
}

establish_session($user);
line_login_json(200,['ok'=>true,'redirect'=>login_success_target($user,$returnKey)]);
