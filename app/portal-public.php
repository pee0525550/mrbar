<?php
declare(strict_types=1);

function portal_map_query(array $branch): string {
    $lat=$branch['lat']??null;$lng=$branch['lng']??null;
    if(is_numeric($lat)&&is_numeric($lng)&&(float)$lat>=-90&&(float)$lat<=90&&(float)$lng>=-180&&(float)$lng<=180&&((float)$lat!==0.0||(float)$lng!==0.0))return (string)$lat.','.(string)$lng;
    return trim((string)($branch['address']??''));
}
function portal_map_url(array $branch): string {
    $query=portal_map_query($branch);
    return $query!==''?'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query):'';
}
function portal_map_embed_url(array $branch): string {
    $query=portal_map_query($branch);
    return $query!==''?'https://maps.google.com/maps?output=embed&z=15&q='.rawurlencode($query):'';
}
function portal_facebook_page_url(string $value): string {
    $parts=parse_url(trim($value));
    $hosts=['facebook.com','www.facebook.com','m.facebook.com','fb.com','www.fb.com'];
    if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||!in_array(strtolower((string)($parts['host']??'')),$hosts,true)||isset($parts['user'])||isset($parts['pass']))return '';
    $path=(string)($parts['path']??'');
    if($path===''||$path==='/')return '';
    $page='https://www.facebook.com'.$path;
    if(basename($path)==='profile.php'&&!empty($parts['query'])){
        parse_str((string)$parts['query'],$query);
        if(isset($query['id'])&&preg_match('/^[0-9]+$/',(string)$query['id']))$page.='?id='.$query['id'];
    }
    return $page;
}
function portal_facebook_embed_url(string $page): string {
    return $page!==''?'https://www.facebook.com/plugins/page.php?href='.rawurlencode($page).'&tabs=timeline&width=500&height=360&small_header=true&adapt_container_width=true&hide_cover=false&show_facepile=true':'';
}
