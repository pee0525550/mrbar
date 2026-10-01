<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/customer-integrations.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$config=mrbar_line_login_config();
echo json_encode(['configured'=>$config['ready'],'liff_id'=>$config['liff_id']],JSON_UNESCAPED_SLASHES);
