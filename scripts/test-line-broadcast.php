<?php
declare(strict_types=1);
require __DIR__.'/../app/customer-integrations.php';

$payload=mrbar_line_broadcast_payload("  สวัสดีผู้ติดตาม MR BAR\nแจ้งข่าว  ");
if(($payload['messages'][0]['type']??'')!=='text'||($payload['messages'][0]['text']??'')!=="สวัสดีผู้ติดตาม MR BAR\nแจ้งข่าว"){
    fwrite(STDERR,"Broadcast payload should contain trimmed UTF-8 text\n");exit(1);
}
if(mrbar_line_broadcast_payload(" \n\t")!==null){fwrite(STDERR,"Empty broadcast text must be rejected\n");exit(1);}
if(mrbar_line_broadcast_payload(str_repeat('ก',5000))===null){fwrite(STDERR,"A 5,000-character broadcast should be accepted\n");exit(1);}
if(mrbar_line_broadcast_payload(str_repeat('ก',5001))!==null){fwrite(STDERR,"A broadcast over 5,000 characters must be rejected\n");exit(1);}
if(mrbar_line_broadcast_payload("bad\xFFutf8")!==null){fwrite(STDERR,"Invalid UTF-8 must be rejected\n");exit(1);}

$endpoint=file_get_contents(__DIR__.'/../api/line-broadcast.php');
foreach(["user_can(\$user,'settings.manage'", "\$_SESSION['csrf']", "'/v2/bot/message/broadcast'", "line_broadcast_last_attempt_at", "confirm_phrase"] as $needle){
    if(!str_contains($endpoint,$needle)){fwrite(STDERR,"Broadcast endpoint missing guard: $needle\n");exit(1);}
}
echo "LINE broadcast validation and guards passed.\n";
