<?php
declare(strict_types=1);

require __DIR__.'/../app/customer-weather.php';

function weather_expect(bool $condition,string $message): void {
    if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
    fwrite(STDOUT,"PASS: {$message}\n");
}

$now=new DateTimeImmutable('2026-09-23 16:30:00',new DateTimeZone('Asia/Bangkok'));
$hourly=[
    'time'=>['2026-09-23T16:00','2026-09-23T17:00','2026-09-23T18:00','2026-09-23T19:00'],
    'temperature_2m'=>[31,30,29,28],
    'precipitation_probability'=>[10,25,55,80],
    'weather_code'=>[2,3,61,95],
];
$forecast=cw_weather_hourly_forecast($hourly,['temperature_2m'=>31.4,'weather_code'=>2],$now,4);
weather_expect(count($forecast)===4,'hourly strip respects its item limit');
weather_expect($forecast[0]['time']==='ตอนนี้'&&$forecast[0]['current'],'hourly strip starts with current conditions');
weather_expect($forecast[1]['time']==='17:00'&&$forecast[1]['temperature']===30.0,'hourly strip includes the next forecast hour');
weather_expect($forecast[2]['rain_probability']===55,'hourly strip shows per-hour rain probability');
weather_expect(cw_weather_thai_date($now)==='วันพุธที่ 23 กันยายน 2569','weather date uses Thai weekday, month, and Buddhist year');

echo "Customer weather regression passed.\n";
