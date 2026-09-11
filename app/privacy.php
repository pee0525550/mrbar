<?php
declare(strict_types=1);

function privacy_setting_on(array $settings,string $key,string $default='0'): bool {
    return (string)($settings[$key]??$default)==='1';
}

function privacy_policy_version(array $settings): string {
    $version=trim((string)($settings['customer_privacy_policy_version']??''));
    return $version!==''?$version:'2026-09-03';
}

function privacy_category_flags(array $settings): array {
    return [
        'functional'=>privacy_setting_on($settings,'customer_cookie_functional_enabled','1'),
        'analytics'=>privacy_setting_on($settings,'customer_cookie_analytics_enabled','0'),
        'marketing'=>privacy_setting_on($settings,'customer_cookie_marketing_enabled','0'),
    ];
}

function privacy_find_consent(array $d,string $id): ?array {
    if(!preg_match('/^[a-f0-9]{32}$/',$id))return null;
    foreach(array_reverse($d['privacy_consents']??[]) as $consent){
        if(hash_equals((string)($consent['id']??''),$id))return $consent;
    }
    return null;
}

function privacy_current_consent(array $d): ?array {
    $id=(string)($_COOKIE['mrbar_consent_id']??'');
    if($id==='')return null;
    $consent=privacy_find_consent($d,$id);
    if(!$consent)return null;
    $settings=$d['settings']??[];
    if((string)($consent['policy_version']??'')!==privacy_policy_version($settings))return null;
    return $consent;
}

function privacy_preferences(?array $consent,array $settings): array {
    $enabled=privacy_category_flags($settings);
    return [
        'necessary'=>true,
        'functional'=>$enabled['functional']&&$consent&&!empty($consent['functional']),
        'analytics'=>$enabled['analytics']&&$consent&&!empty($consent['analytics']),
        'marketing'=>$enabled['marketing']&&$consent&&!empty($consent['marketing']),
    ];
}

function privacy_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||((string)($_SERVER['SERVER_PORT']??'')==='443');
}

function privacy_set_consent_cookie(string $id,int $days): void {
    $days=max(1,min(365,$days));
    setcookie('mrbar_consent_id',$id,[
        'expires'=>time()+($days*86400),
        'path'=>'/',
        'secure'=>privacy_cookie_secure(),
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    $_COOKIE['mrbar_consent_id']=$id;
}

function privacy_request_hash(string $value,string $context): string {
    if($value==='')return '';
    return hash('sha256',$context.'|'.$value);
}

function privacy_log_prune(array $items,int $retentionDays,int $maxItems=5000): array {
    $retentionDays=max(30,min(1095,$retentionDays));
    $cutoff=time()-($retentionDays*86400);
    $items=array_values(array_filter($items,function($item)use($cutoff){
        $ts=strtotime((string)($item['created_at']??''));
        return $ts===false||$ts>=$cutoff;
    }));
    if(count($items)>$maxItems)$items=array_slice($items,-$maxItems);
    return $items;
}
