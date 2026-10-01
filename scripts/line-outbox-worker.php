<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/line-outbox.php';
date_default_timezone_set('Asia/Bangkok');
try{
    if(mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')===''){fwrite(STDERR,"LINE Messaging API token is not configured for this CLI process.\n");exit(1);}
    $processed=mrbar_line_outbox_run(null,20);
    echo 'Processed '.$processed." LINE outbox messages.\n";
}catch(Throwable $error){fwrite(STDERR,"LINE outbox worker failed. Check storage permissions and PHP logs.\n");exit(1);}
