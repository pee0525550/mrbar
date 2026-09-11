<?php
declare(strict_types=1);

/** MR BAR Zone Studio / Floor Plan helpers — v1.28.2
 *  Uses additive JSON buckets so legacy production data remains compatible.
 */
function fp_map_defaults(array $m): array {
    $d=[
        'id'=>0,'branch_id'=>null,'name'=>'Floor Plan','canvas_width'=>1200,'canvas_height'=>900,
        'background_media_id'=>0,'background_opacity'=>35,'background_locked'=>1,'layout_mode'=>'objects',
        'view_mode'=>'2d','grid_size'=>20,'snap_enabled'=>1,'customer_enabled'=>1,'status'=>'draft',
        'created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>null,'updated_by'=>null,
        'published_at'=>null,'published_by'=>null,'published_version_id'=>0,
    ];
    $m=array_merge($d,$m);
    $m['id']=(int)$m['id'];$m['branch_id']=empty($m['branch_id'])?null:(int)$m['branch_id'];
    $m['canvas_width']=max(600,min(4000,(int)$m['canvas_width']));
    $m['canvas_height']=max(500,min(4000,(int)$m['canvas_height']));
    $m['background_media_id']=max(0,(int)$m['background_media_id']);
    $m['background_opacity']=max(0,min(100,(int)$m['background_opacity']));
    $m['background_locked']=!empty($m['background_locked'])?1:0;
    $m['layout_mode']=in_array((string)($m['layout_mode']??'objects'),['objects','image_hotspot'],true)?(string)$m['layout_mode']:'objects';
    $m['view_mode']=in_array((string)$m['view_mode'],['2d','2.5d'],true)?(string)$m['view_mode']:'2d';
    $m['grid_size']=max(5,min(100,(int)$m['grid_size']));
    $m['snap_enabled']=!empty($m['snap_enabled'])?1:0;$m['customer_enabled']=!empty($m['customer_enabled'])?1:0;
    $m['status']=in_array((string)$m['status'],['draft','published','archived'],true)?(string)$m['status']:'draft';
    return $m;
}
function fp_item_defaults(array $i): array {
    $d=[
        'id'=>0,'map_id'=>0,'type'=>'decor','code'=>'','name'=>'','table_id'=>0,'zone'=>'',
        'x'=>100,'y'=>100,'w'=>120,'h'=>80,'rotation'=>0,'z_index'=>10,'locked'=>0,'active'=>1,'customer_visible'=>1,
        'reservable'=>0,'capacity'=>0,'min_spend'=>'0','shape'=>'rect','preset'=>'basic','seat_count'=>0,'seat_layout'=>'auto','seat_style'=>'chair','seat_color'=>'#6f7890','seat_size'=>18,
        'material'=>'classic','shadow_level'=>1,'color'=>'#7b5cff','label_color'=>'#ffffff','border_color'=>'#ffffff','border_width'=>2,'opacity'=>100,'radius'=>14,'label_visible'=>1,'flip_x'=>0,'flip_y'=>0,
        'zone_type'=>'main','vip'=>0,'booking_start'=>'','booking_end'=>'','booking_days'=>'1,2,3,4,5,6,0','capacity_limit'=>0,'sort_order'=>100,
        'description'=>'','booking_note'=>'','customer_note'=>'','group_id'=>'','created_at'=>date('c'),'updated_at'=>date('c')
    ];
    $i=array_merge($d,$i);$i['id']=(int)$i['id'];$i['map_id']=(int)$i['map_id'];$i['table_id']=max(0,(int)$i['table_id']);
    foreach(['x','y','w','h','rotation','z_index','capacity','seat_count','seat_size','border_width','opacity','radius','capacity_limit','sort_order','shadow_level'] as $k)$i[$k]=(int)$i[$k];
    $i['w']=max(24,min(2000,$i['w']));$i['h']=max(24,min(2000,$i['h']));$i['rotation']=max(-359,min(359,$i['rotation']));
    $i['locked']=!empty($i['locked'])?1:0;$i['active']=!empty($i['active'])?1:0;$i['customer_visible']=!empty($i['customer_visible'])?1:0;$i['reservable']=!empty($i['reservable'])?1:0;$i['label_visible']=!empty($i['label_visible'])?1:0;$i['flip_x']=!empty($i['flip_x'])?1:0;$i['flip_y']=!empty($i['flip_y'])?1:0;$i['vip']=!empty($i['vip'])?1:0;
    $types=['zone','table','chair','stool','bench','sofa','stage','pool','bar','cashier','toilet','entrance','exit','walkway','wall','pillar','plant','decor','text','partition','lamp','welcome','dj'];
    if(!in_array((string)$i['type'],$types,true))$i['type']='decor';
    $shapes=['rect','round','oval','long','sofa','booth','counter','standing','line','zigzag','label','lbooth','chair','stool','bench'];if(!in_array((string)$i['shape'],$shapes,true))$i['shape']='rect';
    $seatLayouts=['auto','top-bottom','around','one-side','booth','left-right'];if(!in_array((string)$i['seat_layout'],$seatLayouts,true))$i['seat_layout']='auto';
    $seatStyles=['chair','stool','sofa','bench','luxury'];if(!in_array((string)$i['seat_style'],$seatStyles,true))$i['seat_style']='chair';
    $materials=['classic','wood','black','gold','glass','neon','velvet'];if(!in_array((string)$i['material'],$materials,true))$i['material']='classic';
    $zoneTypes=['main','vip','stage','pool','karaoke','outdoor','private','bar'];if(!in_array((string)$i['zone_type'],$zoneTypes,true))$i['zone_type']='main';
    if(!preg_match('/^[a-z0-9_-]{1,50}$/i',(string)$i['preset']))$i['preset']='basic';
    foreach(['color'=>'#7b5cff','label_color'=>'#ffffff','border_color'=>'#ffffff','seat_color'=>'#6f7890'] as $ck=>$cv)if(!preg_match('/^#[0-9a-f]{6}$/i',(string)$i[$ck]))$i[$ck]=$cv;
    $i['border_width']=max(0,min(14,$i['border_width']));$i['opacity']=max(10,min(100,$i['opacity']));$i['radius']=max(0,min(120,$i['radius']));$i['seat_count']=max(0,min(30,$i['seat_count']));$i['seat_size']=max(10,min(40,$i['seat_size']));$i['capacity_limit']=max(0,min(999,$i['capacity_limit']));$i['sort_order']=max(0,min(9999,$i['sort_order']));$i['shadow_level']=max(0,min(3,$i['shadow_level']));
    foreach(['booking_start','booking_end'] as $tk){$v=trim((string)$i[$tk]);$i[$tk]=preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/',$v)?$v:'';}
    $days=array_values(array_unique(array_filter(array_map('intval',explode(',',(string)$i['booking_days'])),fn($v)=>$v>=0&&$v<=6)));sort($days);$i['booking_days']=implode(',',$days?:[0,1,2,3,4,5,6]);
    $i['min_spend']=(string)max(0,(float)$i['min_spend']);
    return $i;
}
function fp_maps(array $d): array {$out=[];foreach($d['floor_plans']??[] as $m)if(is_array($m))$out[]=fp_map_defaults($m);usort($out,fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));return $out;}
function fp_map(array $d,int $id): ?array {foreach(fp_maps($d) as $m)if((int)$m['id']===$id)return $m;return null;}
function fp_items(array $d,int $mapId): array {$out=[];foreach($d['floor_plan_items']??[] as $i)if(is_array($i)&&(int)($i['map_id']??0)===$mapId)$out[]=fp_item_defaults($i);usort($out,fn($a,$b)=>(int)$a['z_index']<=>(int)$b['z_index']);return $out;}
function fp_versions(array $d,int $mapId): array {$out=[];foreach($d['floor_plan_versions']??[] as $v)if((int)($v['map_id']??0)===$mapId)$out[]=$v;usort($out,fn($a,$b)=>(int)($b['version_no']??0)<=>(int)($a['version_no']??0));return $out;}
function fp_next_id(array $rows): int {$n=0;foreach($rows as $r)$n=max($n,(int)($r['id']??0));return $n+1;}
function fp_find_table(array $d,int $tableId): ?array {foreach($d['tables']??[] as $t)if((int)($t['id']??0)===$tableId)return $t;return null;}
function fp_branch_name(array $d,$id): string {if(!$id)return 'ทุกสาขา / Default';foreach($d['branches']??[] as $b)if((int)($b['id']??0)===(int)$id)return (string)($b['code']??'BR').' · '.(string)($b['name']??'Branch');return 'Branch #'.(int)$id;}
function fp_published_plan(array $d,?int $branchId=null,bool $customerOnly=false): ?array {
    $candidates=[];
    foreach(fp_maps($d) as $current){
        if($current['status']!=='published')continue;
        $live=$current;$pvid=(int)($current['published_version_id']??0);
        if($pvid>0){foreach($d['floor_plan_versions']??[] as $v)if((int)($v['id']??0)===$pvid&&(int)($v['map_id']??0)===(int)$current['id']){$live=fp_map_defaults((array)($v['map']??[]));$live['id']=(int)$current['id'];$live['status']='published';$live['published_at']=$current['published_at']??($v['created_at']??null);$live['published_by']=$current['published_by']??($v['created_by']??null);$live['published_version_id']=$pvid;$live['_live_version_id']=$pvid;break;}}
        if($customerOnly&&empty($live['customer_enabled']))continue;if($branchId!==null&&$live['branch_id']!==null&&(int)$live['branch_id']!==$branchId)continue;$candidates[]=$live;
    }
    if(!$candidates)return null;usort($candidates,function($a,$b)use($branchId){$aa=($branchId!==null&&(int)($a['branch_id']??0)===$branchId)?1:0;$bb=($branchId!==null&&(int)($b['branch_id']??0)===$branchId)?1:0;if($aa!==$bb)return $bb<=>$aa;return strcmp((string)($b['published_at']??$b['updated_at']),(string)($a['published_at']??$a['updated_at']));});return $candidates[0];
}
function fp_public_plan(array $d,?int $branchId=null): ?array {return fp_published_plan($d,$branchId,true);}
function fp_published_items(array $d,int $mapId,bool $customerOnly=false): array {$m=fp_map($d,$mapId);$pvid=(int)($m['published_version_id']??0);$src=null;if($pvid>0){foreach($d['floor_plan_versions']??[] as $v)if((int)($v['id']??0)===$pvid&&(int)($v['map_id']??0)===$mapId){$src=(array)($v['items']??[]);break;}}if($src===null)$src=fp_items($d,$mapId);$out=[];foreach($src as $i){$i=fp_item_defaults((array)$i);if(empty($i['active']))continue;if($customerOnly&&empty($i['customer_visible']))continue;$out[]=$i;}usort($out,fn($a,$b)=>(int)$a['z_index']<=>(int)$b['z_index']);return $out;}
function fp_public_items(array $d,int $mapId): array {return fp_published_items($d,$mapId,true);}
function fp_media_url(array $d,int $mediaId,string $prefix='custumers/'): string {if($mediaId<=0)return '';foreach($d['customer_media']??[] as $m)if((int)($m['id']??0)===$mediaId)return $prefix.'customer-media.php?id='.$mediaId.'&v='.urlencode((string)($m['updated_at']??$mediaId));return '';}
function fp_table_state(array $d,array $item): array {
    $t=!empty($item['table_id'])?fp_find_table($d,(int)$item['table_id']):null;
    $code=trim((string)($t['code']??$item['code']??''));$zone=trim((string)($t['zone']??$item['zone']??''));$capacity=(int)($t['capacity']??$item['capacity']??0);
    $status=(string)($t['status']??'available');if(!$t&&$item['type']==='table')$status='mockup';
    $active=$t?(!empty($t['active'])):!empty($item['active']);$canBook=$item['type']==='table'&&!empty($item['reservable'])&&$active&&($status==='available'||$status==='mockup')&&!empty($item['table_id']);
    return ['table'=>$t,'code'=>$code,'zone'=>$zone,'capacity'=>$capacity,'status'=>$status,'can_book'=>$canBook];
}
function fp_zone_for_table(array $d,int $tableId): ?array {
    if($tableId<=0)return null;$table=fp_find_table($d,$tableId);$branchId=$table&&isset($table['branch_id'])?(int)$table['branch_id']:null;$plan=fp_public_plan($d,$branchId?:null);if(!$plan)return null;$items=fp_public_items($d,(int)$plan['id']);$zoneKey='';foreach($items as $item)if($item['type']==='table'&&(int)($item['table_id']??0)===$tableId){$zoneKey=trim((string)($item['zone']??''));break;}if($zoneKey==='')return null;foreach($items as $item)if($item['type']==='zone'&&strcasecmp(trim((string)($item['name']?:$item['code'])),$zoneKey)===0)return $item;return null;
}
function fp_zone_booking_allowed(array $zone,string $date,string $time): bool {
    if(empty($zone['active'])||empty($zone['customer_visible'])||empty($zone['reservable']))return false;$ts=strtotime($date.' 12:00:00');if($ts===false)return false;$dow=(int)date('w',$ts);$days=array_map('intval',explode(',',(string)($zone['booking_days']??'0,1,2,3,4,5,6')));if(!in_array($dow,$days,true))return false;$start=trim((string)($zone['booking_start']??''));$end=trim((string)($zone['booking_end']??''));if($start===''||$end==='')return true;$toMin=function(string $v): int {$p=array_map('intval',explode(':',$v));return ($p[0]??0)*60+($p[1]??0);};$m=$toMin($time);$s=$toMin($start);$e=$toMin($end);return $s===$e||($s<$e?($m>=$s&&$m<=$e):($m>=$s||$m<=$e));
}
function fp_sanitize_items_json(string $json,int $mapId,array $map,array $d): array {
    $raw=json_decode($json,true);if(!is_array($raw))throw new RuntimeException('ข้อมูล Layout ไม่ถูกต้อง');if(count($raw)>500)throw new RuntimeException('Layout มีวัตถุมากเกิน 500 ชิ้น');
    $out=[];$next=fp_next_id($d['floor_plan_items']??[]);$seen=[];$occupied=[];foreach($d['floor_plan_items']??[] as $existing)if((int)($existing['map_id']??0)!==$mapId)$occupied[(int)($existing['id']??0)]=1;
    foreach($raw as $r){if(!is_array($r))continue;$r['map_id']=$mapId;$id=(int)($r['id']??0);if($id<=0||isset($seen[$id])||isset($occupied[$id]))$id=$next++;$seen[$id]=1;$r['id']=$id;$i=fp_item_defaults($r);
        $i['x']=max(0,min((int)$map['canvas_width']-20,$i['x']));$i['y']=max(0,min((int)$map['canvas_height']-20,$i['y']));
        if($i['table_id']>0){$t=fp_find_table($d,$i['table_id']);if(!$t)throw new RuntimeException('มีวัตถุโต๊ะที่อ้างอิง Table ID ที่ไม่พบ');$i['code']=(string)($t['code']??$i['code']);$i['zone']=(string)($t['zone']??$i['zone']);$i['capacity']=(int)($t['capacity']??$i['capacity']);if((int)$i['seat_count']<=0)$i['seat_count']=$i['capacity'];}
        if($i['type']==='table'&&(int)$i['seat_count']<=0)$i['seat_count']=max(1,(int)$i['capacity']);
        $i['updated_at']=date('c');$out[]=$i;
    }
    return $out;
}
function fp_mockup_items(int $mapId,array $tables=[]): array {
    $items=[];$id=1;$add=function($type,$code,$name,$x,$y,$w,$h,$color,$extra=[])use(&$items,&$id,$mapId){$items[]=fp_item_defaults(array_merge(['id'=>$id++,'map_id'=>$mapId,'type'=>$type,'code'=>$code,'name'=>$name,'x'=>$x,'y'=>$y,'w'=>$w,'h'=>$h,'color'=>$color,'z_index'=>$type==='zone'?1:20],$extra));};
    $add('zone','K','KARAOKE',720,40,420,315,'#8d4cff',['customer_visible'=>1,'description'=>'ห้อง K / Private']);
    foreach([[735,55,'K.1'],[835,55,'K.2'],[935,55,'K.3'],[1035,55,'K.4'],[835,175,'K.6'],[955,175,'K.7'],[835,265,'K.5'],[955,265,'K.8']] as $k=>$v)$add('decor',$v[2],$v[2],$v[0],$v[1],86,72,'#d96f75',['shape'=>'sofa']);
    $add('stage','STAGE','STAGE',390,350,260,140,'#ef6b55',['customer_visible'=>1]);
    $add('pool','POOL','POOL',430,620,210,260,'#40cbd5',['customer_visible'=>1,'shape'=>'long']);
    $add('entrance','ENTRANCE','ENTRANCE',1000,690,150,80,'#ffd05b',['customer_visible'=>1]);
    $add('toilet','WC','RESTROOM',40,160,160,190,'#a7b0c4',['customer_visible'=>1]);
    $add('walkway','PATH','MAIN WALKWAY',700,520,390,72,'#69748c',['customer_visible'=>0,'shape'=>'line']);
    $tableMap=[];foreach($tables as $t)$tableMap[strtoupper((string)($t['code']??''))]=$t;
    $positions=[[330,500],[450,500],[570,500],[690,500],[370,770],[520,770],[700,690],[830,690],[940,610],[1040,610],[800,820],[960,820]];
    foreach($positions as $n=>$p){$code='T'.str_pad((string)($n+1),2,'0',STR_PAD_LEFT);$t=$tableMap[$code]??null;$cap=(int)($t['capacity']??4);$round=$n%3===0;$add('table',$code,$code,$p[0],$p[1],$round?110:145,$round?110:86,'#56698d',['shape'=>$round?'round':'rect','preset'=>$round?'table_round_4':'table_rect_4','table_id'=>(int)($t['id']??0),'zone'=>(string)($t['zone']??'Main'),'capacity'=>$cap,'seat_count'=>$cap,'seat_layout'=>$round?'around':'top-bottom','reservable'=>1,'customer_visible'=>1,'radius'=>$round?55:14]);}
    $add('plant','TREE','TREE',535,185,150,150,'#55b873',['customer_visible'=>1,'shape'=>'round']);
    return $items;
}
