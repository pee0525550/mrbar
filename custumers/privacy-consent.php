<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/privacy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
    exit;
}

try{
    $givenToken=(string)($_POST['csrf']??'');$sessionToken=(string)($_SESSION['csrf']??'');
    if($givenToken===''||$sessionToken===''||!hash_equals($sessionToken,$givenToken))throw new RuntimeException('Security token expired. Please reload and try again.');
    $d=db_load();
    $settings=$d['settings']??[];
    if(!privacy_setting_on($settings,'customer_privacy_enabled','1')){
        echo json_encode(['ok'=>true,'disabled'=>true,'preferences'=>['necessary'=>true,'functional'=>true,'analytics'=>false,'marketing'=>false]],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $enabled=privacy_category_flags($settings);
    $mode=(string)($_POST['mode']??'preferences');
    if(!in_array($mode,['all','necessary','preferences'],true))$mode='preferences';

    $functional=false;$analytics=false;$marketing=false;
    if($mode==='all'){
        $functional=$enabled['functional'];
        $analytics=$enabled['analytics'];
        $marketing=$enabled['marketing'];
    }elseif($mode==='preferences'){
        $functional=$enabled['functional']&&((string)($_POST['functional']??'0')==='1');
        $analytics=$enabled['analytics']&&((string)($_POST['analytics']??'0')==='1');
        $marketing=$enabled['marketing']&&((string)($_POST['marketing']??'0')==='1');
    }

    $policyVersion=privacy_policy_version($settings);
    $id=bin2hex(random_bytes(16));
    $now=date('c');
    $retention=max(30,min(1095,(int)($settings['customer_privacy_log_retention_days']??365)));
    $context=$policyVersion.'|'.$id;
    $record=[
        'id'=>$id,
        'policy_version'=>$policyVersion,
        'necessary'=>1,
        'functional'=>$functional?1:0,
        'analytics'=>$analytics?1:0,
        'marketing'=>$marketing?1:0,
        'source'=>'customer_web',
        'created_at'=>$now,
        'ip_hash'=>privacy_request_hash((string)($_SERVER['REMOTE_ADDR']??''),$context),
        'ua_hash'=>privacy_request_hash((string)($_SERVER['HTTP_USER_AGENT']??''),$context),
    ];

    db_mutate(function($x)use($record,$retention){
        $x['privacy_consents']=privacy_log_prune($x['privacy_consents']??[],$retention);
        $x['privacy_consents'][]=$record;
        if(count($x['privacy_consents'])>5000)$x['privacy_consents']=array_slice($x['privacy_consents'],-5000);
        return $x;
    });

    $days=max(1,min(365,(int)($settings['customer_privacy_consent_days']??183)));
    privacy_set_consent_cookie($id,$days);

    echo json_encode([
        'ok'=>true,
        'policy_version'=>$policyVersion,
        'preferences'=>[
            'necessary'=>true,
            'functional'=>$functional,
            'analytics'=>$analytics,
            'marketing'=>$marketing,
        ],
        'saved_at'=>$now,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'consent_save_failed','message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
