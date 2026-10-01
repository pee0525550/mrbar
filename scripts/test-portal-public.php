<?php
require __DIR__.'/../app/portal-public.php';
require_once __DIR__.'/../app/customer-hero-media.php';

function check(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}

$branch=['lat'=>13.7563,'lng'=>100.5018,'address'=>'Bangkok'];
check(portal_map_query($branch)==='13.7563,100.5018','map query should prefer valid coordinates');
check(strpos(portal_map_embed_url($branch),'q=13.7563%2C100.5018')!==false,'map embed should encode coordinates');
check(portal_map_query(['lat'=>null,'lng'=>null,'address'=>'Sukhumvit 1'])==='Sukhumvit 1','map query should fall back to address');
check(portal_map_url([])==='','empty map location should not create a link');

$facebook=portal_facebook_page_url('https://m.facebook.com/example.page/?ref=bookmarks');
check($facebook==='https://www.facebook.com/example.page/','Facebook URL should normalize to the trusted origin');
check(strpos(portal_facebook_embed_url($facebook),'https://www.facebook.com/plugins/page.php?href=')===0,'Facebook embed should use the official plugin endpoint');
check(portal_facebook_page_url('https://facebook.com.evil.example/page')==='','untrusted Facebook host must be rejected');
check(portal_facebook_page_url('javascript:alert(1)')==='','script URL must be rejected');

$hero=chm_active(['customer_hero_media'=>[
    ['id'=>4,'type'=>'video_upload','source'=>'uploads/customer-web/hero-media/clip.mp4','sort_order'=>20,'active'=>1],
    ['id'=>2,'type'=>'image','source'=>'uploads/customer-web/hero-media/photo.jpg','sort_order'=>10,'active'=>1],
    ['id'=>1,'type'=>'image','source'=>'uploads/customer-web/hero-media/hidden.jpg','sort_order'=>1,'active'=>0],
]]);
check(array_column($hero,'id')===[2,4],'Hero Gallery should include active slides in configured order only');
check(chm_public_file_url($hero[0],'source','/shop/priest/')==='/shop/priest/customer-hero-media.php?id=2&field=source&v='.urlencode((string)$hero[0]['updated_at']),'Hero Gallery media should use the branch-scoped public endpoint');
echo "Portal public embeds regression passed.\n";
