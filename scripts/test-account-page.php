<?php
declare(strict_types=1);
$_SERVER['SCRIPT_NAME']='/account.php';
$_SERVER['REQUEST_METHOD']='GET';
session_start();
require __DIR__.'/../app/db.php';
$data=db_load_global();
$account=null;
foreach($data['users']??[] as $row)if(!empty($row['active'])&&empty($row['deleted_at'])){$account=$row;break;}
if(!$account){fwrite(STDERR,"No active account available for smoke test\n");exit(1);}
$_SESSION['user']=['id'=>(int)$account['id'],'username'=>(string)$account['username'],'display_name'=>(string)$account['display_name'],'role'=>(string)$account['role'],'active'=>1,'branch_ids'=>$account['branch_ids']??[1]];
ob_start();
require __DIR__.'/../account.php';
$html=ob_get_clean();
foreach(['บัญชีของฉัน','เปลี่ยนรหัสผ่าน','Employee Center'] as $fragment){
    if($fragment==='Employee Center'&&($account['role']??'')!=='admin')continue;
    if(!str_contains($html,$fragment)){fwrite(STDERR,"Missing account UI: $fragment\n");exit(1);}
}
$lineReady=mrbar_line_login_config($data)['ready'];
$lineExpected=$lineReady?'line-link.php?flow=link':'LINE Login ยังไม่พร้อมใช้งาน';
if(!str_contains($html,$lineExpected)){fwrite(STDERR,"Missing account LINE state: $lineExpected\n");exit(1);}
if(str_contains($html,(string)($account['line_user_id']??'')?:'__unlinked__')){fwrite(STDERR,"LINE user ID leaked into account page\n");exit(1);}
echo "Account page smoke test passed.\n";
