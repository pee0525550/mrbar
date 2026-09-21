<?php
declare(strict_types=1);

function mrbar_request_is_https(?array $server=null): bool {
    $server=$server??$_SERVER;
    $https=strtolower(trim((string)($server['HTTPS']??'')));
    if($https!==''&&$https!=='off'&&$https!=='0')return true;
    if((int)($server['SERVER_PORT']??0)===443)return true;
    if(strtolower(trim((string)($server['REQUEST_SCHEME']??'')))==='https')return true;
    foreach(explode(',',(string)($server['HTTP_X_FORWARDED_PROTO']??'')) as $proto){
        if(strtolower(trim($proto))==='https')return true;
    }
    return in_array(strtolower(trim((string)($server['HTTP_X_FORWARDED_SSL']??''))),['on','1','true'],true);
}

function mrbar_admin_preview_requested(?array $query=null): bool {
    $query=$query??$_GET;
    return (string)($query['admin_staff_preview']??'')==='1'||(string)($query['admin_pr_preview']??'')==='1';
}

function mrbar_safe_request_host(?array $server=null): string {
    $server=$server??$_SERVER;
    $host=trim((string)($server['HTTP_HOST']??'mrbarsupport.com'));
    return preg_match('/\A(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::\d{1,5})?\z/i',$host)?$host:'mrbarsupport.com';
}

function mrbar_safe_request_uri(string $fallback='/',?array $server=null): string {
    $server=$server??$_SERVER;
    $uri=(string)($server['REQUEST_URI']??$fallback);
    if($uri===''||$uri[0]!=='/'||str_starts_with($uri,'//'))return $fallback;
    return str_replace(["\r","\n"],'',$uri);
}

function mrbar_require_https(string $fallbackUri='/',bool $allowAdminPreview=false): void {
    if(mrbar_request_is_https()||($allowAdminPreview&&mrbar_admin_preview_requested()))return;
    header('Location: https://'.mrbar_safe_request_host().mrbar_safe_request_uri($fallbackUri),true,302);
    exit;
}
