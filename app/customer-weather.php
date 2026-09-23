<?php
declare(strict_types=1);

/**
 * MR BAR customer weather helper.
 * Optional/live-content layer only. Failure must never block the customer page.
 */

function cw_weather_fetch_url(string $url,int $timeout=3): ?string {
    $context=stream_context_create([
        'http'=>[
            'method'=>'GET',
            'timeout'=>$timeout,
            'header'=>"User-Agent: MRBAR-Customer-Weather/1.0\r\nAccept: application/json\r\n",
            'ignore_errors'=>true,
        ],
        'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
    ]);
    $raw=@file_get_contents($url,false,$context);
    if(is_string($raw)&&$raw!=='')return $raw;
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        if($ch!==false){
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>$timeout,
                CURLOPT_TIMEOUT=>$timeout,
                CURLOPT_FOLLOWLOCATION=>true,
                CURLOPT_MAXREDIRS=>2,
                CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: MRBAR-Customer-Weather/1.0'],
            ]);
            $body=curl_exec($ch);
            $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            curl_close($ch);
            if(is_string($body)&&$body!==''&&$code>=200&&$code<300)return $body;
        }
    }
    return null;
}

function cw_weather_code(int $code): array {
    if($code===0)return ['icon'=>'☀️','label'=>'ท้องฟ้าโปร่ง','tone'=>'clear'];
    if(in_array($code,[1,2],true))return ['icon'=>'🌤️','label'=>'มีเมฆบางส่วน','tone'=>'clear'];
    if($code===3)return ['icon'=>'☁️','label'=>'มีเมฆมาก','tone'=>'cloud'];
    if(in_array($code,[45,48],true))return ['icon'=>'🌫️','label'=>'มีหมอก','tone'=>'cloud'];
    if(in_array($code,[51,53,55,56,57],true))return ['icon'=>'🌦️','label'=>'มีฝนปรอย','tone'=>'rain'];
    if(in_array($code,[61,63,65,66,67,80,81,82],true))return ['icon'=>'🌧️','label'=>'มีฝน','tone'=>'rain'];
    if(in_array($code,[71,73,75,77,85,86],true))return ['icon'=>'❄️','label'=>'อากาศหนาว/มีหิมะ','tone'=>'cold'];
    if(in_array($code,[95,96,99],true))return ['icon'=>'⛈️','label'=>'พายุฝนฟ้าคะนอง','tone'=>'storm'];
    return ['icon'=>'🌙','label'=>'สภาพอากาศคืนนี้','tone'=>'neutral'];
}

function cw_weather_hourly_forecast(array $hourly,array $current,DateTimeImmutable $now,int $limit=8): array {
    $times=is_array($hourly['time']??null)?$hourly['time']:[];
    $temps=is_array($hourly['temperature_2m']??null)?$hourly['temperature_2m']:[];
    $rains=is_array($hourly['precipitation_probability']??null)?$hourly['precipitation_probability']:[];
    $codes=is_array($hourly['weather_code']??null)?$hourly['weather_code']:[];
    $currentRain=0;
    foreach($times as $i=>$time){try{$at=new DateTimeImmutable((string)$time,new DateTimeZone('Asia/Bangkok'));}catch(Throwable $unused){continue;}if($at<=$now&&$at->modify('+1 hour')>$now){$currentRain=max(0,min(100,(int)round((float)($rains[$i]??0))));break;}}
    $currentCode=is_numeric($current['weather_code']??null)?(int)$current['weather_code']:0;
    $forecast=[['time'=>'ตอนนี้','temperature'=>is_numeric($current['temperature_2m']??null)?(float)$current['temperature_2m']:null,'rain_probability'=>$currentRain,'icon'=>cw_weather_code($currentCode)['icon'],'label'=>cw_weather_code($currentCode)['label'],'current'=>true]];
    foreach($times as $i=>$time){
        if(count($forecast)>=$limit)break;
        try{$at=new DateTimeImmutable((string)$time,new DateTimeZone('Asia/Bangkok'));}catch(Throwable $unused){continue;}
        if($at<=$now)continue;
        $code=is_numeric($codes[$i]??null)?(int)$codes[$i]:0;$condition=cw_weather_code($code);
        $forecast[]=['time'=>$at->format('H:i'),'temperature'=>is_numeric($temps[$i]??null)?(float)$temps[$i]:null,'rain_probability'=>max(0,min(100,(int)round((float)($rains[$i]??0)))),'icon'=>$condition['icon'],'label'=>$condition['label'],'current'=>false];
    }
    return $forecast;
}

function cw_weather_thai_date(DateTimeImmutable $date): string {
    $days=['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    $months=[1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return 'วัน'.$days[(int)$date->format('w')].'ที่ '.$date->format('j').' '.$months[(int)$date->format('n')].' '.((int)$date->format('Y')+543);
}

function cw_weather_window(): array {
    $tz=new DateTimeZone('Asia/Bangkok');
    $now=new DateTimeImmutable('now',$tz);
    $today=$now->format('Y-m-d');
    $hour=(int)$now->format('G');
    if($hour<3){
        $start=$now;
        $end=new DateTimeImmutable($today.' 02:00:00',$tz);
        if($now>$end)$end=$now->modify('+2 hours');
    }elseif($hour<18){
        $start=new DateTimeImmutable($today.' 18:00:00',$tz);
        $end=$start->modify('+8 hours');
    }else{
        $start=$now;
        $end=(new DateTimeImmutable($today.' 18:00:00',$tz))->modify('+8 hours');
    }
    return [$start,$end,$now];
}


function cw_weather_scene_key(array $weather): string {
    $level=(string)($weather['level']??'normal');
    $condition=(string)($weather['condition']??'');
    $rain=(int)($weather['rain_probability']??0);
    $hour=(int)date('G');
    $night=($hour>=18||$hour<6);
    $hasRain=(function_exists('mb_strpos')?mb_strpos($condition,'ฝน',0,'UTF-8'):strpos($condition,'ฝน'))!==false;
    $hasCloud=(function_exists('mb_strpos')?mb_strpos($condition,'เมฆ',0,'UTF-8'):strpos($condition,'เมฆ'))!==false;
    $hasFog=(function_exists('mb_strpos')?mb_strpos($condition,'หมอก',0,'UTF-8'):strpos($condition,'หมอก'))!==false;

    if($level==='warning'||$rain>=80)return 'storm';
    if($rain>=45||$hasRain)return 'rain';
    if($hasCloud||$hasFog)return 'cloud';
    return $night?'clear-night':'clear-day';
}

function cw_weather_summary(float $lat,float $lng,int $branchId=0): ?array {
    if($lat<-90||$lat>90||$lng<-180||$lng>180)return null;

    $cacheDir=dirname(__DIR__).'/storage/cache';
    $cacheFile=$cacheDir.'/customer-weather-'.max(0,$branchId).'.json';
    $cacheTtl=1200;
    $payload=null;

    if(is_file($cacheFile)&&time()-(int)@filemtime($cacheFile)<$cacheTtl){
        $cached=@file_get_contents($cacheFile);
        if(is_string($cached)&&$cached!==''){
            $tmp=json_decode($cached,true);
            if(is_array($tmp))$payload=$tmp;
        }
    }

    if(!$payload){
        $query=http_build_query([
            'latitude'=>round($lat,5),
            'longitude'=>round($lng,5),
            'current'=>'temperature_2m,weather_code,wind_speed_10m',
            'hourly'=>'temperature_2m,precipitation_probability,weather_code',
            'forecast_days'=>2,
            'timezone'=>'Asia/Bangkok',
        ],'','&',PHP_QUERY_RFC3986);
        $raw=cw_weather_fetch_url('https://api.open-meteo.com/v1/forecast?'.$query,3);
        if($raw===null)return null;
        $tmp=json_decode($raw,true);
        if(!is_array($tmp)||empty($tmp['current'])||empty($tmp['hourly']))return null;
        $payload=$tmp;
        if(!is_dir($cacheDir))@mkdir($cacheDir,0775,true);
        if(is_dir($cacheDir)&&is_writable($cacheDir))@file_put_contents($cacheFile,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }

    $current=is_array($payload['current']??null)?$payload['current']:[];
    $hourly=is_array($payload['hourly']??null)?$payload['hourly']:[];
    $times=is_array($hourly['time']??null)?$hourly['time']:[];
    $rain=is_array($hourly['precipitation_probability']??null)?$hourly['precipitation_probability']:[];
    $temps=is_array($hourly['temperature_2m']??null)?$hourly['temperature_2m']:[];
    $codes=is_array($hourly['weather_code']??null)?$hourly['weather_code']:[];

    [$start,$end,$now]=cw_weather_window();
    $maxRain=0;$maxRainAt='';$nightTemps=[];$nightCodes=[];
    foreach($times as $i=>$time){
        try{$dt=new DateTimeImmutable((string)$time,new DateTimeZone('Asia/Bangkok'));}catch(Throwable $unused){continue;}
        if($dt<$start||$dt>$end)continue;
        $prob=max(0,min(100,(int)round((float)($rain[$i]??0))));
        if($prob>$maxRain){$maxRain=$prob;$maxRainAt=$dt->format('H:i');}
        if(isset($temps[$i])&&is_numeric($temps[$i]))$nightTemps[]=(float)$temps[$i];
        if(isset($codes[$i])&&is_numeric($codes[$i]))$nightCodes[]=(int)$codes[$i];
    }

    $temp=is_numeric($current['temperature_2m']??null)?(float)$current['temperature_2m']:($nightTemps?reset($nightTemps):null);
    $wind=is_numeric($current['wind_speed_10m']??null)?(float)$current['wind_speed_10m']:null;
    $currentCode=is_numeric($current['weather_code']??null)?(int)$current['weather_code']:($nightCodes?reset($nightCodes):0);
    $currentVisual=cw_weather_code($currentCode);
    $worstCode=$currentCode;
    foreach($nightCodes as $c){if(in_array($c,[95,96,99],true)){$worstCode=$c;break;}if(in_array($c,[61,63,65,66,67,80,81,82],true))$worstCode=$c;}
    $visual=cw_weather_code($worstCode);
    $hourlyForecast=cw_weather_hourly_forecast($hourly,$current,$now,8);

    $level='normal';$title='คืนนี้อากาศกำลังดี';$suggestion='เช็กโต๊ะว่างแล้วจองได้เลย คืนนี้เจอกันที่ MR BAR';
    if(in_array($worstCode,[95,96,99],true)||$maxRain>=80){
        $level='warning';$title='คืนนี้มีโอกาสเจอฝนหนัก';
        $suggestion='แนะนำพกร่มและเผื่อเวลาเดินทางก่อนมาร้าน';
    }elseif($maxRain>=50||$visual['tone']==='rain'){
        $level='suggestion';$title='คืนนี้มีโอกาสฝน';
        $suggestion='พกร่มติดรถไว้และเผื่อเวลาเดินทางอีกนิด';
    }elseif($temp!==null&&$temp>=33){
        $level='suggestion';$title='คืนนี้อากาศค่อนข้างร้อน';
        $suggestion='แต่งตัวสบายๆ และดื่มน้ำระหว่างคืนให้เพียงพอ';
    }elseif($temp!==null&&$temp<=22){
        $level='suggestion';$title='คืนนี้อากาศเย็น';
        $suggestion='ถ้านั่งโซนด้านนอก พกเสื้อคลุมบางๆ ไว้ก็ดี';
    }elseif($maxRain<=20){
        $title='คืนนี้อากาศน่าออกมาเจอกัน';
        $suggestion='โอกาสฝนน้อย วางแผนค่ำคืนนี้ได้สบายขึ้น';
    }

    $sourceTime=(string)($current['time']??'');$updatedAt=preg_match('/T(\d{2}:\d{2})/',$sourceTime,$timeMatch)?$timeMatch[1]:$now->format('H:i');
    $weather=[
        'temperature'=>$temp,
        'wind'=>$wind,
        'rain_probability'=>$maxRain,
        'rain_at'=>$maxRainAt,
        'icon'=>$visual['icon'],
        'condition'=>$visual['label'],
        'current_condition'=>$currentVisual['label'],
        'level'=>$level,
        'title'=>$title,
        'suggestion'=>$suggestion,
        'window'=>$start->format('H:i').'–'.$end->format('H:i'),
        'date_label'=>cw_weather_thai_date($now),
        'now_iso'=>$now->format(DATE_ATOM),
        'hourly_forecast'=>$hourlyForecast,
        'updated_at'=>$updatedAt,
    ];
    $weather['scene']=cw_weather_scene_key($weather);
    return $weather;
}
