<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';

function notification_assert(bool $condition,string $message):void{
    if(!$condition){fwrite(STDERR,'FAIL: '.$message.PHP_EOL);exit(1);}
    echo 'PASS: '.$message.PHP_EOL;
}

$staff=['id'=>17,'role'=>'staff'];
$peer=['id'=>18,'role'=>'staff'];
$data=['notifications'=>[
    ['id'=>4,'user_id'=>17,'role'=>'staff','read_at'=>null],
    ['id'=>5,'user_id'=>0,'role'=>'staff','read_at'=>null],
]];
notification_assert(count(op_unread_notifications($data,$staff))===2,'recipient sees direct and role notifications before acknowledgement');
notification_assert(op_acknowledge_notification($data,$staff,4),'direct notification can be acknowledged by its recipient');
notification_assert(count(op_unread_notifications($data,$staff))===1,'acknowledged notification stays cleared for that user');
notification_assert(count(op_unread_notifications($data,$peer))===1,'another user does not inherit a direct notification');
notification_assert(op_acknowledge_notification($data,$staff,5),'role notification can be acknowledged per user');
notification_assert(count(op_unread_notifications($data,$staff))===0,'role notification remains acknowledged for the same user');
notification_assert(count(op_unread_notifications($data,$peer))===1,'role notification remains visible to another user');
notification_assert(!op_acknowledge_notification($data,$peer,4),'a user cannot acknowledge another user’s direct notification');
echo "Notification acknowledgement tests passed.\n";
