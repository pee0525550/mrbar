<?php
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=60, must-revalidate');
$host=strtolower(preg_replace('/:\\d+$/','',trim((string)($_SERVER['HTTP_HOST']??'')))??'');
$secureBase=in_array($host,['mrbarsupport.com','www.mrbarsupport.com'],true)?'https://'.$host.'/':'./';
$manifest=[
  'id'=>$secureBase.'time.php',
  'name'=>'MR BAR TIME',
  'short_name'=>'MR BAR TIME',
  'description'=>'ลงเวลาเข้า-ออกงาน MR BAR พร้อม Trusted Device, PIN, GPS และ Camera verification',
  'lang'=>'th',
  'dir'=>'ltr',
  'start_url'=>$secureBase.'time.php?source=pwa',
  'scope'=>$secureBase,
  'display'=>'standalone',
  'display_override'=>['standalone','minimal-ui'],
  'orientation'=>'portrait-primary',
  'background_color'=>'#070812',
  'theme_color'=>'#090617',
  'categories'=>['business','productivity'],
  'icons'=>[
    ['src'=>'assets/icons/mrbar-time-192.png?v=12721','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
    ['src'=>'assets/icons/mrbar-time-512.png?v=12721','sizes'=>'512x512','type'=>'image/png','purpose'=>'any'],
    ['src'=>'assets/icons/mrbar-time-maskable-512.png?v=12721','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'],
  ],
  'shortcuts'=>[
    ['name'=>'ลงเวลาเข้า / ออกงาน','short_name'=>'ลงเวลา','url'=>$secureBase.'time.php?source=shortcut','icons'=>[['src'=>'assets/icons/mrbar-time-192.png?v=12721','sizes'=>'192x192','type'=>'image/png']]],
  ],
];
echo json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
