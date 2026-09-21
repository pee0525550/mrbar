<?php
declare(strict_types=1);

/* MR BAR v1.29.0 — POS Incentive / Commission Engine
 * Additive data only. Attendance/Payroll remains the source of base wage.
 */

function posi_norm(string $v): string {
    $v=trim($v);if($v==='')return '';
    if(function_exists('mb_strtolower'))$v=mb_strtolower($v,'UTF-8');else $v=strtolower($v);
    return preg_replace('/[^a-z0-9ก-๙]+/u','',$v)??'';
}
function posi_money($v): float {return max(0,(float)str_replace([',',' '],['',''],(string)$v));}
function posi_employee_cfg(array $e): array {
    $cfg=is_array($e['pos_incentive']??null)?$e['pos_incentive']:[];
    $aliases=$cfg['aliases']??[];if(is_string($aliases))$aliases=preg_split('/[\r\n,]+/u',$aliases)?:[];if(!is_array($aliases))$aliases=[];
    $aliases=array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x),$aliases),fn($x)=>$x!=='')));
    $position=(string)($e['position']??'staff');
    return array_replace([
        'enabled'=>in_array($position,['sales','pr'],true)?1:0,
        'team_code'=>'','team_lead'=>0,'aliases'=>$aliases,'sales_rate_override'=>'0','note'=>'',
    ],$cfg,['aliases'=>$aliases]);
}
function posi_rule_defaults(array $r=[]): array {
    $scope=(string)($r['scope_type']??'store');if(!in_array($scope,['store','team','employee'],true))$scope='store';
    $payout=(string)($r['payout_type']??'per_drink');if(!in_array($payout,['per_drink','percent_sales','fixed_bonus'],true))$payout='per_drink';
    return array_replace([
        'id'=>0,'month'=>date('Y-m'),'name'=>'Monthly Incentive','enabled'=>1,'scope_type'=>$scope,'scope_value'=>'',
        'target_sales'=>'0','payout_type'=>$payout,'reward_rate'=>'0','note'=>'','created_at'=>date('c'),'updated_at'=>date('c'),'created_by'=>null,'updated_by'=>null,
    ],$r,['scope_type'=>$scope,'payout_type'=>$payout]);
}
function posi_rules(array $d,string $month=''): array {
    $out=[];foreach($d['pos_incentive_rules']??[] as $r){if(!is_array($r))continue;$r=posi_rule_defaults($r);if($month!==''&&(string)$r['month']!==$month)continue;$out[]=$r;}
    usort($out,fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);return $out;
}
function posi_month_valid(string $month): bool {return (bool)preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/',$month);}
function posi_status_label(string $s): string {return ['estimate'=>'ประมาณการ','locked'=>'ยังไม่ปลดล็อก','unlocked'=>'ปลดล็อกแล้ว','final'=>'Final'][$s]??$s;}
function posi_month_bounds(string $month): array {$month=posi_month_valid($month)?$month:date('Y-m');$from=$month.'-01';return [$from,date('Y-m-t',strtotime($from))];}
function posi_employee_aliases(array $e): array {
    $cfg=posi_employee_cfg($e);$pf=is_array($e['profile']??null)?$e['profile']:[];$raw=[(string)($e['code']??''),(string)($e['name']??''),(string)($pf['display_name']??''),(string)($pf['nickname']??'')];
    foreach($cfg['aliases'] as $a)$raw[]=(string)$a;$out=[];foreach($raw as $a){$n=posi_norm($a);if($n!=='')$out[$n]=$a;}return $out;
}
function posi_alias_index(array $d): array {
    $index=[];foreach($d['employees']??[] as $e){$cfg=posi_employee_cfg($e);if(empty($cfg['enabled'])||empty($e['active']))continue;foreach(posi_employee_aliases($e) as $n=>$raw){if(!isset($index[$n]))$index[$n]=[];$index[$n][]=(int)$e['id'];}}
    return $index;
}
function posi_match_employee(array $d,string $itemName): array {
    $needle=posi_norm($itemName);if($needle==='')return ['employee_id'=>null,'method'=>'unmatched','alias'=>''];$index=posi_alias_index($d);
    if(isset($index[$needle])&&count(array_unique($index[$needle]))===1)return ['employee_id'=>(int)$index[$needle][0],'method'=>'exact','alias'=>$needle];
    $best='';$ids=[];foreach($index as $alias=>$empIds){if(strlen($alias)<3||strpos($needle,$alias)===false)continue;if(strlen($alias)>strlen($best)){$best=$alias;$ids=$empIds;}elseif(strlen($alias)===strlen($best)&&$alias===$best)$ids=array_merge($ids,$empIds);}
    $ids=array_values(array_unique(array_map('intval',$ids)));if($best!==''&&count($ids)===1)return ['employee_id'=>$ids[0],'method'=>'contains','alias'=>$best];
    return ['employee_id'=>null,'method'=>'unmatched','alias'=>''];
}
function posi_employee_by_id(array $d,int $id): ?array {foreach($d['employees']??[] as $e)if((int)($e['id']??0)===$id)return $e;return null;}
function posi_active_rows(array $d,string $month=''): array {
    [$from,$to]=posi_month_bounds($month?:date('Y-m'));$out=[];foreach($d['pos_sales_rows']??[] as $r){if(empty($r['active']))continue;$date=(string)($r['sale_date']??'');if($date<$from||$date>$to)continue;$out[]=$r;}return $out;
}
function posi_active_rows_period(array $d,string $from,string $to): array {
    $out=[];foreach($d['pos_sales_rows']??[] as $row){if(empty($row['active']))continue;$date=(string)($row['sale_date']??'');if($date<$from||$date>$to)continue;$out[]=$row;}return $out;
}
function posi_period_summary_dates(array $d,string $from,string $to): array {
    $rows=posi_active_rows_period($d,$from,$to);$sales=0.0;$qty=0.0;$drinkUnits=0.0;$dUnits=0.0;$mUnits=0.0;$first='';$through='';$matched=0;$unmatched=0;
    foreach($rows as $row){$sales+=(float)($row['net_sales']??0);$qty+=(float)($row['qty']??0);$date=(string)($row['sale_date']??'');if($first===''||$date<$first)$first=$date;if($through===''||$date>$through)$through=$date;if(posi_row_is_candidate($row)){$units=(float)($row['qty']??0);$drinkUnits+=$units;if(($row['drink_code']??'')==='D')$dUnits+=$units;elseif(($row['drink_code']??'')==='M')$mUnits+=$units;if(!empty($row['employee_id']))$matched++;else $unmatched++;}}
    return ['month'=>substr($to,0,7),'from'=>$from,'to'=>$to,'rows'=>count($rows),'actual_sales'=>$sales,'qty'=>$qty,'drink_units'=>$drinkUnits,'d_units'=>$dUnits,'m_units'=>$mUnits,'first_date'=>$first,'through_date'=>$through,'projected_sales'=>$sales,'projection_factor'=>1.0,'projection_confidence'=>'range','matched_rows'=>$matched,'unmatched_rows'=>$unmatched];
}

function posi_period_summary(array $d,string $month): array {
    [$from,$to]=posi_month_bounds($month);$rows=posi_active_rows($d,$month);$sales=0.0;$qty=0.0;$drinkUnits=0.0;$dUnits=0.0;$mUnits=0.0;$first='';$through='';$matched=0;$unmatched=0;
    foreach($rows as $r){$sales+=(float)($r['net_sales']??0);$qty+=(float)($r['qty']??0);$date=(string)($r['sale_date']??'');if($first===''||$date<$first)$first=$date;if($through===''||$date>$through)$through=$date;if(posi_row_is_candidate($r)){$u=(float)($r['qty']??0);$drinkUnits+=$u;if(($r['drink_code']??'')==='D')$dUnits+=$u;elseif(($r['drink_code']??'')==='M')$mUnits+=$u;if(!empty($r['employee_id']))$matched++;else $unmatched++;}}
    $days=(int)date('t',strtotime($from));$isCurrent=$month===date('Y-m');$factor=1.0;$confidence='final';
    if($isCurrent&&$through!==''){$day=max(1,(int)substr($through,8,2));$factor=$days/$day;$confidence=($first!==''&&(int)substr($first,8,2)<=3)?'high':'medium';}
    $projected=$sales*$factor;
    return ['month'=>$month,'from'=>$from,'to'=>$to,'rows'=>count($rows),'actual_sales'=>$sales,'qty'=>$qty,'drink_units'=>$drinkUnits,'d_units'=>$dUnits,'m_units'=>$mUnits,'first_date'=>$first,'through_date'=>$through,'projected_sales'=>$projected,'projection_factor'=>$factor,'projection_confidence'=>$confidence,'matched_rows'=>$matched,'unmatched_rows'=>$unmatched];
}
function posi_scope_sales(array $d,string $month,array $rule): float {
    $scope=(string)$rule['scope_type'];$value=(string)($rule['scope_value']??'');$sum=0.0;
    foreach(posi_active_rows($d,$month) as $r){if($scope==='store'){$sum+=(float)($r['net_sales']??0);continue;}$eid=(int)($r['employee_id']??0);if($eid<=0)continue;$e=posi_employee_by_id($d,$eid);if(!$e)continue;$cfg=posi_employee_cfg($e);if($scope==='team'&&strcasecmp((string)$cfg['team_code'],$value)===0)$sum+=(float)($r['net_sales']??0);if($scope==='employee'&&$eid===(int)$value)$sum+=(float)($r['net_sales']??0);}
    return $sum;
}
function posi_rule_for_employee(array $d,array $employee,string $month): ?array {
    $cfg=posi_employee_cfg($employee);$eid=(int)($employee['id']??0);$team=(string)$cfg['team_code'];$best=null;$score=-1;
    foreach(posi_rules($d,$month) as $r){if(empty($r['enabled']))continue;$s=-1;if($r['scope_type']==='employee'&&(int)$r['scope_value']===$eid)$s=30;elseif($r['scope_type']==='team'&&$team!==''&&strcasecmp((string)$r['scope_value'],$team)===0)$s=20;elseif($r['scope_type']==='store')$s=10;if($s>$score){$score=$s;$best=$r;}}
    return $best;
}
function posi_closing(array $d,string $month): ?array {foreach(array_reverse($d['pos_incentive_closings']??[]) as $c)if((string)($c['month']??'')===$month&&($c['status']??'final')==='final')return $c;return null;}
function posi_employee_month_result(array $d,array $employee,string $month): array {
    $cfg=posi_employee_cfg($employee);$eid=(int)($employee['id']??0);$position=(string)($employee['position']??'staff');$summary=posi_period_summary($d,$month);$units=0.0;$dUnits=0.0;$mUnits=0.0;$sales=0.0;
    foreach(posi_active_rows($d,$month) as $r)if((int)($r['employee_id']??0)===$eid&&posi_row_is_candidate($r)){$u=(float)($r['qty']??0);$units+=$u;$sales+=(float)($r['net_sales']??0);if(($r['drink_code']??'')==='D')$dUnits+=$u;elseif(($r['drink_code']??'')==='M')$mUnits+=$u;}
    $rule=posi_rule_for_employee($d,$employee,$month);$rate=0.0;$target=0.0;$scopeActual=0.0;$unlocked=true;$potential=0.0;$recognized=0.0;$status='estimate';$ruleName='';
    if($position==='pr'||!empty($employee['pr_id'])){$rate=max(0,(float)($employee['pr_compensation']['drink_rate']??0));if(function_exists('posi_r4_pr_rate')){$r4PrRate=posi_r4_pr_rate($d,$month,(float)$summary['actual_sales']);if($r4PrRate>=0)$rate=$r4PrRate;}$potential=$units*$rate;$recognized=$potential;$ruleName='PR Drink Rate';}
    elseif($position==='sales'&&!empty($cfg['enabled'])){
        if($rule){$ruleName=(string)$rule['name'];$target=max(0,(float)$rule['target_sales']);$scopeActual=posi_scope_sales($d,$month,$rule);$unlocked=$target<=0||$scopeActual>=$target;$rate=max(0,(float)($cfg['sales_rate_override']??0));if($rate<=0)$rate=max(0,(float)$rule['reward_rate']);$payout=(string)$rule['payout_type'];if($payout==='percent_sales')$potential=$sales*($rate/100);elseif($payout==='fixed_bonus')$potential=$rate;else $potential=$units*$rate;$recognized=$unlocked?$potential:0.0;}else{$rate=max(0,(float)($cfg['sales_rate_override']??0));$potential=$units*$rate;$recognized=$potential;$ruleName=$rate>0?'Employee Rate Override':'ยังไม่มี Rule';}
    }
    $projectedUnits=$units*(float)$summary['projection_factor'];$projectedSales=$sales*(float)$summary['projection_factor'];$projectedPotential=$potential;
    if($position==='pr'||!empty($employee['pr_id']))$projectedPotential=$projectedUnits*$rate;elseif($rule){$payout=(string)$rule['payout_type'];if($payout==='percent_sales')$projectedPotential=$projectedSales*($rate/100);elseif($payout==='fixed_bonus')$projectedPotential=$rate;else $projectedPotential=$projectedUnits*$rate;}
    $projectedScope=$scopeActual*(float)$summary['projection_factor'];$projectedUnlock=$rule?($target<=0||$projectedScope>=$target):true;
    $closing=posi_closing($d,$month);if($closing){foreach($closing['results']??[] as $x)if((int)($x['employee_id']??0)===$eid){$recognized=(float)($x['recognized_pay']??0);$potential=(float)($x['potential_pay']??$recognized);$units=(float)($x['units']??$units);$sales=(float)($x['sales']??$sales);$status='final';$unlocked=!empty($x['unlocked']);$rate=(float)($x['rate']??$rate);break;}}
    elseif($position==='pr'||!empty($employee['pr_id']))$status='estimate';elseif($rule&&$unlocked)$status='unlocked';elseif($rule&&!$unlocked)$status='locked';
    return ['employee_id'=>$eid,'month'=>$month,'enabled'=>!empty($cfg['enabled']),'position'=>$position,'team_code'=>(string)$cfg['team_code'],'team_lead'=>!empty($cfg['team_lead']),'units'=>$units,'d_units'=>$dUnits,'m_units'=>$mUnits,'sales'=>$sales,'rate'=>$rate,'rule'=>$rule,'rule_name'=>$ruleName,'target_sales'=>$target,'scope_actual'=>$scopeActual,'unlocked'=>$unlocked,'potential_pay'=>$potential,'recognized_pay'=>$recognized,'projected_units'=>$projectedUnits,'projected_sales'=>$projectedSales,'projected_scope_sales'=>$projectedScope,'projected_unlock'=>$projectedUnlock,'projected_potential_pay'=>$projectedPotential,'status'=>$status,'through_date'=>$summary['through_date'],'projection_confidence'=>$summary['projection_confidence']];
}
function posi_month_results(array $d,string $month): array {
    $out=[];foreach($d['employees']??[] as $e){$cfg=posi_employee_cfg($e);$pos=(string)($e['position']??'');if(empty($e['active'])||!in_array($pos,['sales','pr'],true)||empty($cfg['enabled']))continue;$out[]=posi_employee_month_result($d,$e,$month);}usort($out,fn($a,$b)=>(float)$b['recognized_pay']<=>(float)$a['recognized_pay']);return $out;
}
function posi_row_is_candidate(array $r): bool {if(array_key_exists('incentive_candidate',$r))return !empty($r['incentive_candidate']);return true;}
function posi_unmatched_items(array $d,string $month,int $limit=30): array {$agg=[];foreach(posi_active_rows($d,$month) as $r){if(!posi_row_is_candidate($r)||!empty($r['employee_id']))continue;$name=trim((string)($r['item_name']??''));if($name==='')$name='(ไม่มีชื่อเมนู)';if(!isset($agg[$name]))$agg[$name]=['item_name'=>$name,'rows'=>0,'qty'=>0.0,'sales'=>0.0];$agg[$name]['rows']++;$agg[$name]['qty']+=(float)($r['qty']??0);$agg[$name]['sales']+=(float)($r['net_sales']??0);}usort($agg,fn($a,$b)=>(float)$b['sales']<=>(float)$a['sales']);return array_slice($agg,0,$limit);}
function posi_remap_rows(array &$d,string $month): int {$changed=0;[$from,$to]=posi_month_bounds($month);if(!isset($d['pos_sales_rows'])||!is_array($d['pos_sales_rows']))return 0;foreach($d['pos_sales_rows'] as &$r){if(empty($r['active']))continue;$date=(string)($r['sale_date']??'');if($date<$from||$date>$to||!posi_row_is_candidate($r))continue;$m=posi_match_employee($d,(string)($r['item_name']??''));$new=$m['employee_id'];if((int)($r['employee_id']??0)!==(int)($new??0)||($r['match_method']??'')!==$m['method']){$r['employee_id']=$new;$r['match_method']=$m['method'];$r['matched_alias']=$m['alias'];$changed++;}}unset($r);return $changed;}

function posi_parse_number($v): float {$s=trim((string)$v);$s=str_replace([',','฿','THB',' '],['','','',''],$s);$s=preg_replace('/[^0-9.\-]/','',$s)??'';return is_numeric($s)?(float)$s:0.0;}
function posi_parse_date($v): string {
    $s=trim((string)$v);if($s==='')return '';
    if(is_numeric($s)&&((float)$s)>20000&&((float)$s)<90000){$ts=(int)round((((float)$s)-25569)*86400);return gmdate('Y-m-d',$ts);}
    if(preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/',$s,$m)){$y=(int)$m[3];if($y>2400)$y-=543;if(checkdate((int)$m[2],(int)$m[1],$y))return sprintf('%04d-%02d-%02d',$y,(int)$m[2],(int)$m[1]);}
    if(preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/',$s,$m)){$y=(int)$m[1];if($y>2400)$y-=543;if(checkdate((int)$m[2],(int)$m[3],$y))return sprintf('%04d-%02d-%02d',$y,(int)$m[2],(int)$m[3]);}
    $ts=strtotime($s);return $ts?date('Y-m-d',$ts):'';
}
function posi_col_index(string $ref): int {$letters=preg_replace('/[^A-Z]/','',strtoupper($ref))??'';$n=0;for($i=0;$i<strlen($letters);$i++)$n=$n*26+(ord($letters[$i])-64);return max(0,$n-1);}
function posi_headers(array $row): array {
    $out=[];$seen=[];foreach(array_values($row) as $i=>$v){$h=trim((string)$v);if($h==='')$h='Column '.($i+1);$base=$h;$n=2;while(isset($seen[$h]))$h=$base.' '.$n++;$seen[$h]=1;$out[]=$h;}return $out;
}
function posi_extract_table(array $rows,int $limit=0): array {
    if(!$rows)throw new RuntimeException('ไม่พบข้อมูลในไฟล์');
    $need=['รหัสสินค้า','ชื่อสินค้า','สินค้า','product','กลุ่ม','หมวดสินค้า','จำนวนการขาย','จำนวนขาย','quantity','ราคาสุทธิ','ยอดขายสุทธิ','net sales','เลขบิล','เลขที่บิล','receipt no','โต๊ะ','วันที่','เวลา'];
    $best=0;$bestScore=-1;$scan=min(12,count($rows));
    for($ri=0;$ri<$scan;$ri++){$score=0;foreach($rows[$ri] as $cell){$n=posi_norm((string)$cell);if($n==='')continue;foreach($need as $word){$w=posi_norm($word);if($n===$w||strpos($n,$w)!==false){$score++;break;}}}if($score>$bestScore){$bestScore=$score;$best=$ri;}}
    if($bestScore<2)$best=0;
    $headers=posi_headers($rows[$best]);$data=array_slice($rows,$best+1);
    while($data&&count(array_filter(end($data),fn($v)=>trim((string)$v)!==''))===0)array_pop($data);
    if($limit>0)$data=array_slice($data,0,$limit);
    return ['headers'=>$headers,'rows'=>$data,'header_row'=>$best+1];
}
function posi_parse_csv_file(string $path,int $limit=0): array {
    $fh=fopen($path,'rb');if(!$fh)throw new RuntimeException('เปิดไฟล์ CSV ไม่ได้');$first=fgets($fh);if($first===false){fclose($fh);throw new RuntimeException('ไฟล์ CSV ว่าง');}$counts=[','=>substr_count($first,','),"\t"=>substr_count($first,"\t"),';'=>substr_count($first,';')];arsort($counts);$delimiter=(string)array_key_first($counts);rewind($fh);$rows=[];while(($row=fgetcsv($fh,0,$delimiter))!==false){if(isset($row[0]))$row[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$row[0])??(string)$row[0];$rows[]=$row;}fclose($fh);return posi_extract_table($rows,$limit);
}
function posi_parse_xlsx_file(string $path,int $limit=0): array {
    $getter=null;$closer=function(){};
    if(class_exists('ZipArchive')){$zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('เปิดไฟล์ XLSX ไม่ได้');$getter=function(string $name)use($zip){return $zip->getFromName($name);};$closer=function()use($zip){$zip->close();};}
    elseif(class_exists('PharData')){try{$phar=new PharData($path);}catch(Throwable $e){throw new RuntimeException('Server อ่าน XLSX ไม่ได้ · กรุณาใช้ CSV หรือเปิด Zip/Phar extension');}$getter=function(string $name)use($phar){if(!isset($phar[$name]))return false;$p=$phar[$name]->getPathname();$raw=@file_get_contents($p);return $raw===false?false:$raw;};}
    else throw new RuntimeException('Server ไม่มี ZipArchive/PharData สำหรับอ่าน XLSX · กรุณาใช้ CSV');
    $shared=[];$ss=$getter('xl/sharedStrings.xml');if($ss!==false){$xml=@simplexml_load_string($ss);if($xml){foreach($xml->si as $si){$txt='';if(isset($si->t))$txt=(string)$si->t;else foreach($si->r as $r)$txt.=(string)$r->t;$shared[]=$txt;}}}
    $sheetName='';for($i=1;$i<=50;$i++){$candidate='xl/worksheets/sheet'.$i.'.xml';$raw=$getter($candidate);if($raw!==false){$sheetName=$candidate;$sheet=$raw;break;}}if($sheetName===''){$closer();throw new RuntimeException('ไม่พบ Worksheet ใน XLSX');}$closer();if($sheet===false)throw new RuntimeException('อ่าน Worksheet ไม่ได้');$xml=@simplexml_load_string($sheet);if(!$xml)throw new RuntimeException('Worksheet XML ไม่ถูกต้อง');
    $rows=[];foreach($xml->sheetData->row as $row){$cells=[];$max=-1;foreach($row->c as $c){$idx=posi_col_index((string)$c['r']);$max=max($max,$idx);$type=(string)$c['t'];$val='';if($type==='inlineStr'){$val=(string)$c->is->t;if($val===''&&isset($c->is->r))foreach($c->is->r as $r)$val.=(string)$r->t;}else{$v=(string)$c->v;if($type==='s')$val=$shared[(int)$v]??'';elseif($type==='b')$val=((string)$v==='1')?'1':'0';else $val=$v;}$cells[$idx]=$val;}$line=[];for($j=0;$j<=$max;$j++)$line[]=$cells[$j]??'';$rows[]=$line;}
    return posi_extract_table($rows,$limit);
}
function posi_parse_file(string $path,string $ext,int $limit=0): array {$ext=strtolower($ext);if($ext==='csv')return posi_parse_csv_file($path,$limit);if($ext==='xlsx')return posi_parse_xlsx_file($path,$limit);throw new RuntimeException('รองรับไฟล์ .xlsx และ .csv เท่านั้น');}
function posi_guess_columns(array $headers): array {
    $sets=[
        'date'=>['date','datetime','date time','วันที่','วันที่/เวลา','วันขาย','วันที่ขาย','sale date','sale datetime','transaction date','transaction time','business date','closed at','เวลา'],
        'item'=>['item','menu','ชื่อเมนู','ชื่อสินค้า','สินค้า','product','item name'],
        'group'=>['กลุ่ม','กลุ่มสินค้า','group','product group'],
        'category'=>['หมวดสินค้า','หมวดหมู่','category','product category'],
        'qty'=>['qty','quantity','จำนวน','จำนวนขาย','จำนวนการขาย','units'],
        'sales'=>['ราคาสุทธิ','ยอดขายสุทธิ','ยอดขาย','ยอดสุทธิ','ยอดชำระ','ยอดรวม','net sales','net amount','net total','sales amount','amount','total','grand total','paid amount'],
        'cost'=>['ต้นทุน ต้นทุนเฉลี่ย x จำนวนการขาย','ต้นทุนรวม','total cost'],
        'profit'=>['กำไร ยอดรวม - ต้นทุน - ส่วนลด','กำไรรวม','profit'],
        'ref'=>['bill','bill no','bill number','receipt','receipt no','receipt number','invoice','invoice no','transaction','transaction no','reference','reference no','doc no','บิล','เลขที่บิล','เลขบิล','ใบเสร็จ','เลขใบเสร็จ','เลขที่ใบเสร็จ','เลขที่ใบกำกับ'],
        'table'=>['table','table no','table number','โต๊ะ','หมายเลขโต๊ะ']
    ];$out=array_fill_keys(array_keys($sets),-1);
    // Prefer exact header matches first so "รหัสสินค้า" cannot steal the "ชื่อสินค้า" mapping.
    foreach($headers as $i=>$h){$n=posi_norm((string)$h);foreach($sets as $key=>$words){if($out[$key]>=0)continue;foreach($words as $w){if($n===posi_norm($w)){$out[$key]=(int)$i;break;}}}}
    foreach($headers as $i=>$h){$n=posi_norm((string)$h);foreach($sets as $key=>$words){if($out[$key]>=0)continue;if($key==='item'&&(strpos($n,'รหัส')!==false||strpos($n,'code')!==false))continue;foreach($words as $w){$wn=posi_norm($w);if($wn!==''&&strpos($n,$wn)!==false){$out[$key]=(int)$i;break;}}}}
    return $out;
}
function posi_report_type(array $guess): string {return ($guess['date']??-1)<0&&($guess['item']??-1)>=0&&($guess['qty']??-1)>=0&&($guess['sales']??-1)>=0?'product_summary':'transaction';}
function posi_candidate_info(string $item,string $group='',string $category=''): array {
    $code='';if(preg_match('/^\s*([DM])\s+/iu',$item,$m))$code=strtoupper($m[1]);$g=posi_norm($group);$c=posi_norm($category);
    $eligible=$code!==''&&(in_array($g,['pr','sales'],true)||preg_match('/^(pr|sales)[dm]/',$c));
    if($group===''&&$category===''&&$code!=='')$eligible=true;
    return ['eligible'=>$eligible,'drink_code'=>$eligible?$code:''];
}
function posi_preview_summary(array $parsed,array $map): array {
    $out=['rows'=>0,'net_sales'=>0.0,'candidate_rows'=>0,'candidate_units'=>0.0,'candidate_sales'=>0.0,'d_units'=>0.0,'m_units'=>0.0];
    foreach($parsed['rows'] as $raw){$get=function(string $k)use($raw,$map){$i=(int)($map[$k]??-1);return $i>=0?($raw[$i]??''):'';};$item=trim((string)$get('item'));if($item===''||strcasecmp(trim((string)($raw[0]??'')),'Total')===0)continue;$out['rows']++;$qty=max(0,posi_parse_number($get('qty')));$sales=posi_parse_number($get('sales'));$out['net_sales']+=$sales;$info=posi_candidate_info($item,(string)$get('group'),(string)$get('category'));if(!$info['eligible'])continue;$out['candidate_rows']++;$out['candidate_units']+=$qty;$out['candidate_sales']+=$sales;$out[strtolower($info['drink_code']).'_units']+=$qty;}
    return $out;
}


function posi_void_bill_batch(array &$d,int $batchId,int $userId): array {
    foreach($d['pos_bill_batches']??[] as &$b){if((int)($b['id']??0)!==$batchId)continue;if(($b['status']??'active')==='void')throw new RuntimeException('Batch รายละเอียดบิลนี้ถูกยกเลิกแล้ว');$b['status']='void';$b['voided_at']=date('c');$b['voided_by']=$userId;if(!isset($d['pos_bill_rows'])||!is_array($d['pos_bill_rows']))$d['pos_bill_rows']=[];foreach($d['pos_bill_rows'] as &$r)if((int)($r['batch_id']??0)===$batchId)$r['active']=0;unset($r);if(!isset($d['sales_table_sessions'])||!is_array($d['sales_table_sessions']))$d['sales_table_sessions']=[];foreach($d['sales_table_sessions'] as &$s)if((int)($s['matched_bill_batch_id']??0)===$batchId){$s['match_status']='pending';$s['matched_net_sales']=null;unset($s['matched_bill_batch_id'],$s['matched_at']);}unset($s);$d['audit'][]=['at'=>date('c'),'action'=>'pos_bill_batch_voided','batch_id'=>$batchId,'by'=>$userId];$out=$b;unset($b);return $out;}unset($b);throw new RuntimeException('ไม่พบ Batch รายละเอียดบิล');
}

function posi_bill_report_label(string $kind): string {return $kind==='bill_detail'?'รายงานรายละเอียดบิล':'รายงานยอดขายตามเมนู';}
function posi_bill_map_relaxed(array $parsed,array $map): array {
    if(($map['ref']??-1)>=0&&($map['sales']??-1)>=0&&($map['date']??-1)>=0)return $map;
    $headers=$parsed['headers']??[];$rows=array_slice($parsed['rows']??[],0,80);$max=count($headers);
    foreach($rows as $row)$max=max($max,count($row));
    $scores=[];
    for($i=0;$i<$max;$i++){
        $header=posi_norm((string)($headers[$i]??''));$scores[$i]=['ref'=>0,'date'=>0,'sales'=>0];
        if(preg_match('/(bill|receipt|invoice|reference|transaction|doc|บิล|ใบเสร็จ|เลขที่|เลขใบ)/iu',$header))$scores[$i]['ref']+=8;
        if(preg_match('/(date|time|วันที่|เวลา|วันขาย)/iu',$header))$scores[$i]['date']+=8;
        if(preg_match('/(sales|amount|total|net|paid|ยอด|สุทธิ|รวม|ชำระ|ราคา)/iu',$header))$scores[$i]['sales']+=8;
        foreach($rows as $row){
            $value=trim((string)($row[$i]??''));if($value===''||mb_strtolower($value,'UTF-8')==='total')continue;
            $date=posi_parse_date($value);if($date!=='')$scores[$i]['date']+=3;
            $num=posi_parse_number($value);if($num>0)$scores[$i]['sales']+=2;
            if($date===''&&preg_match('/[0-9]/u',$value)&&!is_numeric(str_replace([',',' '],'',$value))&&mb_strlen($value,'UTF-8')<=50)$scores[$i]['ref']+=2;
        }
    }
    foreach(['ref','date','sales'] as $key){
        if(($map[$key]??-1)>=0)continue;
        $best=-1;$bestScore=0;
        foreach($scores as $idx=>$score){
            if($key==='sales'&&($idx===(int)($map['ref']??-1)||$idx===(int)($map['date']??-1)))continue;
            if($key==='ref'&&($idx===(int)($map['sales']??-1)||$idx===(int)($map['date']??-1)))continue;
            if($score[$key]>$bestScore){$bestScore=$score[$key];$best=(int)$idx;}
        }
        if($best>=0&&$bestScore>=3)$map[$key]=$best;
    }
    return $map;
}
function posi_commit_bill_import(array $d,array $entry,array $parsed,array $map,int $userId): array {
    $map=posi_bill_map_relaxed($parsed,$map);
    if(($map['ref']??-1)<0||($map['sales']??-1)<0)throw new RuntimeException('ไฟล์รายละเอียดบิลต้องมีเลขบิลหรือใบเสร็จ และยอดขาย');
    if(!isset($d['pos_bill_batches'])||!is_array($d['pos_bill_batches']))$d['pos_bill_batches']=[];
    if(!isset($d['pos_bill_rows'])||!is_array($d['pos_bill_rows']))$d['pos_bill_rows']=[];
    foreach($d['pos_bill_batches'] as $old)if(($old['sha256']??'')===($entry['sha256']??'')&&($old['status']??'active')==='active')throw new RuntimeException('ไฟล์รายละเอียดบิลนี้ Process แล้ว');
    $batchId=next_id($d['pos_bill_batches']);$agg=[];
    foreach($parsed['rows'] as $raw){
        $get=fn(string $k)=>(int)($map[$k]??-1)>=0?($raw[(int)$map[$k]]??''):'';
        $receipt=mb_strtoupper(trim((string)$get('ref')),'UTF-8');if($receipt===''||mb_strtolower($receipt,'UTF-8')==='total')continue;
        $amount=posi_parse_number($get('sales'));$dateRaw=trim((string)$get('date'));$saleDate=posi_parse_date($dateRaw);if($saleDate==='')$saleDate=posi_parse_date($entry['period_to']??$entry['period_from']??'');
        $table=trim((string)$get('table'));if(!isset($agg[$receipt]))$agg[$receipt]=['receipt_no'=>$receipt,'sale_date'=>$saleDate,'sale_datetime_raw'=>$dateRaw,'table_code'=>$table,'net_sales'=>0.0,'source_rows'=>0];
        $agg[$receipt]['net_sales']+=$amount;$agg[$receipt]['source_rows']++;
        if($agg[$receipt]['sale_date']===''&&$saleDate!=='')$agg[$receipt]['sale_date']=$saleDate;
        if($agg[$receipt]['table_code']===''&&$table!=='')$agg[$receipt]['table_code']=$table;
    }
    if(!$agg)throw new RuntimeException('ไม่พบรายการเลขบิลในไฟล์');
    foreach($agg as $row)$d['pos_bill_rows'][]=array_merge(['id'=>next_id($d['pos_bill_rows']),'batch_id'=>$batchId,'active'=>1],$row);
    $matchedSessions=0;$matchedReceipts=0;if(!isset($d['sales_table_sessions'])||!is_array($d['sales_table_sessions']))$d['sales_table_sessions']=[];
    foreach($d['sales_table_sessions'] as &$session){
        $receipts=array_values(array_filter(array_map(fn($v)=>mb_strtoupper(trim((string)$v),'UTF-8'),$session['receipts']??[])));
        if(!$receipts)continue;$sum=0.0;$found=0;
        foreach($receipts as $receipt)if(isset($agg[$receipt])){$sum+=(float)$agg[$receipt]['net_sales'];$found++;}
        if($found>0){$session['matched_net_sales']=$sum;$session['match_status']=$found===count($receipts)?'matched':'partial';$session['matched_bill_batch_id']=$batchId;$session['matched_at']=date('c');$matchedSessions++;$matchedReceipts+=$found;}
    }unset($session);
    $batch=['id'=>$batchId,'sha256'=>(string)$entry['sha256'],'filename'=>(string)$entry['original_name'],'period_start'=>(string)$entry['period_from'],'period_end'=>(string)$entry['period_to'],'status'=>'active','bill_count'=>count($agg),'total_sales'=>array_sum(array_column($agg,'net_sales')),'matched_sessions'=>$matchedSessions,'matched_receipts'=>$matchedReceipts,'imported_at'=>date('c'),'imported_by'=>$userId];
    $d['pos_bill_batches'][]=$batch;$d['audit'][]=['at'=>date('c'),'action'=>'pos_bill_report_processed','batch_id'=>$batchId,'bills'=>count($agg),'matched_sessions'=>$matchedSessions,'by'=>$userId];
    return [$d,$batch];
}

function posi_inbox_dir(): string {$dir=__DIR__.'/../storage/pos-upload-inbox';if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('สร้างพื้นที่เก็บไฟล์ POS ไม่ได้');return $dir;}
function posi_inbox_store_upload(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('อัปโหลดไฟล์ POS ไม่สำเร็จ');$size=(int)($file['size']??0);if($size<=0||$size>20*1024*1024)throw new RuntimeException('ไฟล์ POS ต้องไม่เกิน 20MB');
    $original=trim((string)($file['name']??'pos.xlsx'));$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));if(!in_array($ext,['xlsx','csv'],true))throw new RuntimeException('รองรับ Excel .xlsx และ CSV เท่านั้น');
    $stored=bin2hex(random_bytes(16)).'.'.$ext;$path=posi_inbox_dir().'/'.$stored;if(!move_uploaded_file((string)$file['tmp_name'],$path))throw new RuntimeException('บันทึกไฟล์เข้า POS Inbox ไม่ได้');@chmod($path,0660);
    return ['original_name'=>$original,'stored_name'=>$stored,'ext'=>$ext,'size'=>$size,'sha256'=>hash_file('sha256',$path)];
}
function posi_inbox_path(array $entry): string {
    $stored=basename((string)($entry['stored_name']??''));if($stored===''||$stored!==(string)($entry['stored_name']??''))throw new RuntimeException('ชื่อไฟล์ใน POS Inbox ไม่ถูกต้อง');$path=posi_inbox_dir().'/'.$stored;if(!is_file($path))throw new RuntimeException('ไม่พบไฟล์ต้นฉบับใน POS Inbox');return $path;
}
function posi_inbox_status_label(string $status): string {return ['uploaded'=>'รอดำเนินการ','processing'=>'กำลังประมวลผล','completed'=>'สำเร็จ','failed'=>'ผิดพลาด','archived'=>'เก็บถาวร'][$status]??$status;}

function posi_stage_dir(): string {$dir=__DIR__.'/../storage/pos-import-staging';if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('สร้างโฟลเดอร์ staging POS ไม่ได้');return $dir;}
function posi_stage_upload(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('อัปโหลดไฟล์ POS ไม่สำเร็จ');$size=(int)($file['size']??0);if($size<=0||$size>20*1024*1024)throw new RuntimeException('ไฟล์ POS ต้องไม่เกิน 20MB');$name=(string)($file['name']??'pos.xlsx');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['xlsx','csv'],true))throw new RuntimeException('รองรับ Excel .xlsx และ CSV เท่านั้น');$token=bin2hex(random_bytes(16));$path=posi_stage_dir().'/'.$token.'.'.$ext;if(!move_uploaded_file((string)$file['tmp_name'],$path))throw new RuntimeException('บันทึกไฟล์ staging ไม่ได้');@chmod($path,0660);$stage=['token'=>$token,'path'=>$path,'ext'=>$ext,'name'=>$name,'sha256'=>hash_file('sha256',$path),'created_at'=>time()];$_SESSION['posi_stage'][$token]=$stage;return $stage;
}
function posi_stage_get(string $token): array {$s=$_SESSION['posi_stage'][$token]??null;if(!is_array($s)||empty($s['path'])||!is_file((string)$s['path'])||((int)($s['created_at']??0)<time()-7200))throw new RuntimeException('ไฟล์ Preview หมดอายุ กรุณาอัปโหลดใหม่');return $s;}
function posi_stage_clear(string $token): void {$s=$_SESSION['posi_stage'][$token]??null;if(is_array($s)&&!empty($s['path'])&&is_file((string)$s['path']))@unlink((string)$s['path']);unset($_SESSION['posi_stage'][$token]);}
function posi_build_normalized_rows(array $d,array $parsed,array $map,array $meta=[]): array {
    $rows=[];$invalid=0;$ignored=0;$candidates=0;$rowNo=(int)($parsed['header_row']??1);$reportType=(string)($meta['report_type']??posi_report_type($map));$fallbackDate=posi_parse_date($meta['period_end']??'');
    foreach($parsed['rows'] as $raw){$rowNo++;$get=function(string $k)use($raw,$map){$idx=(int)($map[$k]??-1);return $idx>=0?($raw[$idx]??''):'';};$item=trim((string)$get('item'));if($item===''||strcasecmp(trim((string)($raw[0]??'')),'Total')===0)continue;
        $date=$reportType==='product_summary'?$fallbackDate:posi_parse_date($get('date'));$qty=(int)($map['qty']??-1)>=0?max(0,posi_parse_number($get('qty'))):1.0;$sales=posi_parse_number($get('sales'));$ref=trim((string)$get('ref'));$group=trim((string)$get('group'));$category=trim((string)$get('category'));
        if($date===''){$invalid++;continue;}$info=$reportType==='product_summary'?posi_candidate_info($item,$group,$category):['eligible'=>true,'drink_code'=>(preg_match('/^\s*([DM])\s+/iu',$item,$dc)?strtoupper($dc[1]):'')];$m=$info['eligible']?posi_match_employee($d,$item):['employee_id'=>null,'method'=>'ignored','alias'=>''];if($info['eligible'])$candidates++;else $ignored++;
        $rows[]=['sale_date'=>$date,'bill_ref'=>$ref,'item_name'=>$item,'item_group'=>$group,'item_category'=>$category,'qty'=>$qty,'net_sales'=>$sales,'employee_id'=>$m['employee_id'],'match_method'=>$m['method'],'matched_alias'=>$m['alias'],'incentive_candidate'=>$info['eligible']?1:0,'drink_code'=>$info['drink_code'],'report_type'=>$reportType,'source_row_no'=>$rowNo];
    }
    return ['rows'=>$rows,'invalid'=>$invalid,'candidate_rows'=>$candidates,'ignored_rows'=>$ignored,'report_type'=>$reportType];
}
function posi_commit_import(array &$d,array $stage,array $parsed,array $map,array $meta,int $userId): array {
    $source=trim((string)($meta['source_name']??'POS'));if($source==='')$source='POS';$mode=(string)($meta['import_mode']??'period');if(!in_array($mode,['period','mtd_snapshot'],true))$mode='period';$periodStart=posi_parse_date($meta['period_start']??'');$periodEnd=posi_parse_date($meta['period_end']??'');if($periodStart===''||$periodEnd===''||$periodEnd<$periodStart)throw new RuntimeException('ช่วงวันที่ Import ไม่ถูกต้อง');$month=substr($periodEnd,0,7);if(posi_closing($d,$month))throw new RuntimeException('เดือนนี้ Final แล้ว กรุณา Reopen ก่อน Import ข้อมูลใหม่');
    foreach($d['pos_import_batches']??[] as $b)if(($b['sha256']??'')===$stage['sha256']&&($b['status']??'active')==='active'&&(string)($b['period_start']??'')===$periodStart&&(string)($b['period_end']??'')===$periodEnd)throw new RuntimeException('ไฟล์นี้ Process สำเร็จแล้วในรอบวันที่เดียวกัน');
    $built=posi_build_normalized_rows($d,$parsed,$map,$meta);$normalized=[];foreach($built['rows'] as $r)if($r['sale_date']>=$periodStart&&$r['sale_date']<=$periodEnd)$normalized[]=$r;if(!$normalized)throw new RuntimeException('ไม่พบแถวข้อมูลที่อยู่ในช่วงวันที่เลือก');
    if(!isset($d['pos_import_batches'])||!is_array($d['pos_import_batches']))$d['pos_import_batches']=[];if(!isset($d['pos_sales_rows'])||!is_array($d['pos_sales_rows']))$d['pos_sales_rows']=[];
    $batchId=next_id($d['pos_import_batches']);$superseded=[];if($mode==='mtd_snapshot'){foreach($d['pos_import_batches'] as &$b){if(($b['status']??'active')!=='active'||($b['import_mode']??'')!=='mtd_snapshot'||($b['source_name']??'')!==$source||($b['month']??'')!==$month)continue;$b['status']='superseded';$b['superseded_at']=date('c');$b['superseded_by']=$batchId;$superseded[]=(int)$b['id'];}unset($b);if($superseded)foreach($d['pos_sales_rows'] as &$r)if(in_array((int)($r['batch_id']??0),$superseded,true)){$r['active']=0;$r['superseded_by']=$batchId;}unset($r);}
    $existingRefs=[];if($mode==='period')foreach($d['pos_sales_rows'] as $r){if(empty($r['active'])||($r['source_name']??'')!==$source||substr((string)($r['sale_date']??''),0,7)!==$month)continue;$ref=trim((string)($r['bill_ref']??''));if($ref==='')continue;$key=hash('sha256',posi_norm($ref).'|'.(string)$r['sale_date'].'|'.posi_norm((string)$r['item_name']).'|'.(string)$r['qty'].'|'.(string)$r['net_sales']);$existingRefs[$key]=1;}
    $nextRow=next_id($d['pos_sales_rows']);$inserted=0;$duplicates=0;$total=0.0;$matched=0;$unmatched=0;$ignored=0;foreach($normalized as $r){$ref=trim((string)$r['bill_ref']);$dedup='';if($mode==='period'&&$ref!==''){$dedup=hash('sha256',posi_norm($ref).'|'.$r['sale_date'].'|'.posi_norm($r['item_name']).'|'.$r['qty'].'|'.$r['net_sales']);if(isset($existingRefs[$dedup])){$duplicates++;continue;}$existingRefs[$dedup]=1;}$r['id']=$nextRow++;$r['batch_id']=$batchId;$r['source_name']=$source;$r['import_mode']=$mode;$r['active']=1;$r['row_hash']=$dedup!==''?$dedup:hash('sha256',$stage['sha256'].'|'.$r['source_row_no']);$r['created_at']=date('c');$d['pos_sales_rows'][]=$r;$inserted++;$total+=(float)$r['net_sales'];if(posi_row_is_candidate($r)){if(!empty($r['employee_id']))$matched++;else $unmatched++;}else $ignored++;}
    $batch=['id'=>$batchId,'source_name'=>$source,'filename'=>(string)$stage['name'],'sha256'=>(string)$stage['sha256'],'ext'=>(string)$stage['ext'],'import_mode'=>$mode,'month'=>$month,'period_start'=>$periodStart,'period_end'=>$periodEnd,'status'=>'active','rows_imported'=>$inserted,'rows_invalid'=>(int)$built['invalid'],'rows_duplicate'=>$duplicates,'total_sales'=>$total,'matched_rows'=>$matched,'unmatched_rows'=>$unmatched,'candidate_rows'=>$matched+$unmatched,'ignored_rows'=>$ignored,'report_type'=>(string)$built['report_type'],'imported_at'=>date('c'),'imported_by'=>$userId,'supersedes'=>$superseded];$d['pos_import_batches'][]=$batch;$d['audit'][]=['at'=>date('c'),'action'=>'pos_import_committed','batch_id'=>$batchId,'month'=>$month,'rows'=>$inserted,'total_sales'=>$total,'by'=>$userId];return $batch;
}
function posi_void_batch(array &$d,int $batchId,int $userId): array {if(!isset($d['pos_import_batches'])||!is_array($d['pos_import_batches']))throw new RuntimeException('ไม่พบ Import Batch');foreach($d['pos_import_batches'] as &$b){if((int)($b['id']??0)!==$batchId)continue;$month=(string)($b['month']??'');if($month!==''&&posi_closing($d,$month))throw new RuntimeException('เดือนนี้ Final แล้ว กรุณา Reopen ก่อนยกเลิก Batch');if(($b['status']??'active')==='void')throw new RuntimeException('Batch นี้ถูก Void แล้ว');$b['status']='void';$b['voided_at']=date('c');$b['voided_by']=$userId;if(isset($d['pos_sales_rows'])&&is_array($d['pos_sales_rows']))foreach($d['pos_sales_rows'] as &$r)if((int)($r['batch_id']??0)===$batchId)$r['active']=0;unset($r);$d['audit'][]=['at'=>date('c'),'action'=>'pos_import_batch_voided','batch_id'=>$batchId,'month'=>$month,'by'=>$userId];$out=$b;unset($b);return $out;}unset($b);throw new RuntimeException('ไม่พบ Import Batch');}
function posi_sales_display_name(array $d,array $row): string {
    $employeeId=(int)($row['employee_id']??0);
    if($employeeId>0){$employee=posi_employee_by_id($d,$employeeId);if($employee)return workforce_employee_label($employee);}
    $name=trim((string)($row['item_name']??''));
    $name=preg_replace('/^\s*[DM]\s+/iu','',$name)??$name;
    foreach($d['branches']??[] as $branch){
        foreach(['name','display_name','slug','code'] as $key){$suffix=trim((string)($branch[$key]??''));if($suffix==='')continue;$name=preg_replace('/\s+'.preg_quote($suffix,'/').'\s*$/iu','',$name)??$name;}
    }
    return trim($name)!==''?trim($name):(string)($row['item_name']??'ไม่ทราบชื่อ');
}
function posi_sales_units(array $d,string $month,string $from='',string $to=''): array {
    $out=[];$sourceRows=$from!==''&&$to!==''?posi_active_rows_period($d,$from,$to):posi_active_rows($d,$month);
    foreach($sourceRows as $row){
        if(posi_norm((string)($row['item_group']??''))!=='sales')continue;
        $name=posi_sales_display_name($d,$row);$employeeId=(int)($row['employee_id']??0);
        $key=$employeeId>0?'employee:'.$employeeId:'name:'.posi_norm($name);
        if(!isset($out[$key]))$out[$key]=['employee_id'=>$employeeId,'sales_name'=>$name,'units'=>0.0,'source_rows'=>0];
        $out[$key]['units']+=(float)($row['qty']??0);$out[$key]['source_rows']++;
    }
    $rows=array_values($out);usort($rows,fn($a,$b)=>[(string)$a['sales_name']]<=>[(string)$b['sales_name']]);return $rows;
}
function posi_sales_commission_tiers(array $rule): array {
    $tiers=[];$source=is_array($rule['tiers']??null)?$rule['tiers']:[];
    if(!$source&&((float)($rule['rate_per_unit']??0)>0||(float)($rule['minimum_units']??0)>0))$source=[['store_minimum'=>0,'personal_minimum'=>0,'minimum_units'=>$rule['minimum_units']??0,'rate_per_unit'=>$rule['rate_per_unit']??0]];
    foreach($source as $index=>$tier){if(!is_array($tier))continue;$tiers[]=['id'=>(int)($tier['id']??($index+1)),'store_minimum'=>max(0,(float)($tier['store_minimum']??0)),'personal_minimum'=>max(0,(float)($tier['personal_minimum']??0)),'minimum_units'=>max(0,(float)($tier['minimum_units']??0)),'rate_per_unit'=>max(0,(float)($tier['rate_per_unit']??0))];}
    usort($tiers,fn($a,$b)=>[(float)$b['store_minimum'],(float)$b['personal_minimum'],(float)$b['minimum_units'],(float)$b['rate_per_unit']]<=>[(float)$a['store_minimum'],(float)$a['personal_minimum'],(float)$a['minimum_units'],(float)$a['rate_per_unit']]);return $tiers;
}
function posi_sales_commission_rule(array $d,string $month,string $from='',string $to=''): array {
    $defaults=['month'=>$month,'period_from'=>$from,'period_to'=>$to,'tiers'=>[],'personal_sales_by_key'=>[],'note'=>'','updated_at'=>'','updated_by'=>null];
    foreach($d['sales_commission_rules']??[] as $rule){if($from!==''&&$to!==''&&(string)($rule['period_from']??'')===$from&&(string)($rule['period_to']??'')===$to){$result=array_replace($defaults,$rule);$result['tiers']=posi_sales_commission_tiers($result);return $result;}if($from===''&&(string)($rule['month']??'')===$month){$result=array_replace($defaults,$rule);$result['tiers']=posi_sales_commission_tiers($result);return $result;}}return $defaults;
}
function posi_sales_personal_sales(array $d,int $employeeId,string $from,string $to): float {
    if($employeeId<=0)return 0.0;foreach($d['pos_r4_employee_inputs']??[] as $input)if((int)($input['employee_id']??0)===$employeeId&&(string)($input['period_from']??'')===$from&&(string)($input['period_to']??'')===$to)return max(0,(float)($input['personal_sales']??0));return 0.0;
}
function posi_sales_commission_results(array $d,string $month,array $rule,string $from='',string $to=''): array {
    $tiers=posi_sales_commission_tiers($rule);$storeSales=(float)(($from!==''&&$to!==''?posi_period_summary_dates($d,$from,$to):posi_period_summary($d,$month))['actual_sales']??0);$rows=[];
    foreach(posi_sales_units($d,$month,$from,$to) as $sales){$units=(float)$sales['units'];$salesKey=(int)$sales['employee_id']>0?'employee:'.(int)$sales['employee_id']:'name:'.posi_norm((string)$sales['sales_name']);$manualPersonal=is_array($rule['personal_sales_by_key']??null)?$rule['personal_sales_by_key']:[];$personal=array_key_exists($salesKey,$manualPersonal)?max(0,(float)$manualPersonal[$salesKey]):posi_sales_personal_sales($d,(int)$sales['employee_id'],$from,$to);$sales['sales_key']=$salesKey;$matched=null;foreach($tiers as $tier)if($storeSales>=(float)$tier['store_minimum']&&$personal>=(float)$tier['personal_minimum']&&$units>=(float)$tier['minimum_units']){$matched=$tier;break;}$rate=$matched?(float)$matched['rate_per_unit']:0.0;$sales['store_sales']=$storeSales;$sales['personal_sales']=$personal;$sales['eligible']=$matched!==null;$sales['matched_tier']=$matched;$sales['rate_per_unit']=$rate;$sales['base_commission']=$units*$rate;$sales['bonus']=0.0;$sales['commission']=$sales['base_commission'];$rows[]=$sales;}
    usort($rows,fn($a,$b)=>[(float)$b['commission'],(float)$b['units']]<=>[(float)$a['commission'],(float)$a['units']]);return $rows;
}

function posi_role_commission_rule(array $d,string $from,string $to): array {
    $defaults=['period_from'=>$from,'period_to'=>$to,'pr_rate_d'=>0.0,'pr_rate_m'=>0.0,'sales_own_rate_d'=>0.0,'sales_own_rate_m'=>0.0,'sales_team_rate_d'=>0.0,'sales_team_rate_m'=>0.0,'team_sales_map'=>[],'updated_at'=>'','updated_by'=>null];
    foreach(array_reverse($d['role_commission_rules']??[]) as $rule)if((string)($rule['period_from']??'')===$from&&(string)($rule['period_to']??'')===$to)return array_replace($defaults,$rule);return $defaults;
}
function posi_category_team_code(string $category): string {
    $raw=trim(preg_replace('/\s+/u',' ',$category)??'');if($raw==='')return '';
    $team=trim((string)(preg_replace('/^(?:PR|SALES)(?:\s*[-_\/]\s*|\s+)/iu','',$raw)??''));
    $team=trim((string)(preg_replace('/^(?:D|M)(?:\s*[-_\/]\s*|\s+)/iu','',$team)??''));
    return strtoupper($team!==''?$team:$raw);
}
function posi_row_role(array $row,?array $employee=null): string {
    $group=posi_norm((string)($row['item_group']??''));$category=posi_norm((string)($row['item_category']??''));
    if($group==='pr'||strpos($category,'pr')===0)return 'pr';if($group==='sales'||strpos($category,'sales')===0)return 'sales';
    $position=(string)($employee['position']??'');return in_array($position,['pr','sales'],true)?$position:'';
}
function posi_role_commission_results(array $d,array $rule,string $from,string $to): array {
    $people=[];$unmapped=[];$teamPr=[];$teamPrSales=[];$rawTeamMap=is_array($rule['team_sales_map']??null)?$rule['team_sales_map']:[];$teamMap=[];foreach($rawTeamMap as $teamCode=>$salesId){$normalizedTeam=posi_category_team_code((string)$teamCode);if($normalizedTeam!==''&&(int)$salesId>0)$teamMap[$normalizedTeam]=(int)$salesId;}
    $activeRows=posi_active_rows_period($d,$from,$to);$branchId=(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??1);$branch=null;foreach($d['branches']??[] as $branchRow)if((int)($branchRow['id']??0)===$branchId){$branch=$branchRow;break;}$branch=$branch??['name'=>'','slug'=>'','code'=>''];$branchText=mb_strtolower(implode(' ',[(string)($branch['name']??''),(string)($branch['slug']??''),(string)($branch['code']??'')]),'UTF-8');$branchTokens=array_values(array_filter(array_map('posi_norm',preg_split('/[^a-z0-9ก-๙]+/u',$branchText)?:[]),fn($token)=>mb_strlen($token)>=3&&!in_array($token,['club','ktv','branch','exclusive'],true)));$detected=[];foreach($activeRows as $candidate){$candidateTeam=posi_category_team_code((string)($candidate['item_category']??''));if($candidateTeam!=='')$detected[$candidateTeam]=true;}$branchTeams=[];foreach(array_keys($detected) as $candidateTeam){$normalized=posi_norm($candidateTeam);foreach($branchTokens as $token)if(strpos($normalized,$token)!==false){$branchTeams[$candidateTeam]=true;break;}}
    $makePerson=static function(array $employee,string $role): array {$eid=(int)$employee['id'];return ['employee_id'=>$eid,'name'=>(string)($employee['name']??$employee['code']??('#'.$eid)),'role'=>$role,'team_code'=>'','team_lead'=>false,'own_d'=>0.0,'own_m'=>0.0,'own_sales'=>0.0,'team_pr_d'=>0.0,'team_pr_m'=>0.0,'team_pr_sales'=>0.0,'own_commission'=>0.0,'team_commission'=>0.0,'total_commission'=>0.0];};
    foreach($activeRows as $row){
        if(!posi_row_is_candidate($row))continue;$rowTeam=posi_category_team_code((string)($row['item_category']??''));if($branchTeams&&!isset($branchTeams[$rowTeam]))continue;$employee=posi_employee_by_id($d,(int)($row['employee_id']??0));$role=posi_row_role($row,$employee);if(!in_array($role,['pr','sales'],true))continue;
        $units=max(0,(float)($row['qty']??0));$rowSales=max(0,(float)($row['net_sales']??0));$drink=in_array((string)($row['drink_code']??''),['D','M'],true)?(string)$row['drink_code']:'D';$team=posi_category_team_code((string)($row['item_category']??''));
        if(!$employee){$key=(string)($row['item_name']??'ไม่ทราบชื่อ');if(!isset($unmapped[$key]))$unmapped[$key]=['name'=>$key,'role'=>$role,'units'=>0.0];$unmapped[$key]['units']+=$units;if($role==='pr'&&$team!==''){$teamPr[$team][$drink]=($teamPr[$team][$drink]??0)+$units;$teamPrSales[$team]=($teamPrSales[$team]??0)+$rowSales;}continue;}
        $eid=(int)$employee['id'];if(!isset($people[$eid]))$people[$eid]=$makePerson($employee,$role);$people[$eid]['own_'.strtolower($drink)]+=$units;$people[$eid]['own_sales']+=$rowSales;
        if($role==='pr'&&$team!==''){$teamPr[$team][$drink]=($teamPr[$team][$drink]??0)+$units;$teamPrSales[$team]=($teamPrSales[$team]??0)+$rowSales;}
    }
    foreach($teamMap as $team=>$salesId){$salesId=(int)$salesId;if($salesId<=0||!isset($teamPr[$team]))continue;$employee=posi_employee_by_id($d,$salesId);if(!$employee||(string)($employee['position']??'')!=='sales')continue;if(!isset($people[$salesId]))$people[$salesId]=$makePerson($employee,'sales');$people[$salesId]['team_lead']=true;$people[$salesId]['team_code']=trim($people[$salesId]['team_code'].' / '.$team,' /');$people[$salesId]['team_pr_d']+=(float)($teamPr[$team]['D']??0);$people[$salesId]['team_pr_m']+=(float)($teamPr[$team]['M']??0);$people[$salesId]['team_pr_sales']+=(float)($teamPrSales[$team]??0);}
    foreach($people as &$person){if($person['role']==='pr')$person['own_commission']=$person['own_d']*(float)$rule['pr_rate_d']+$person['own_m']*(float)$rule['pr_rate_m'];else{$person['own_commission']=$person['own_d']*(float)$rule['sales_own_rate_d']+$person['own_m']*(float)$rule['sales_own_rate_m'];$person['team_commission']=$person['team_pr_d']*(float)$rule['sales_team_rate_d']+$person['team_pr_m']*(float)$rule['sales_team_rate_m'];}$person['total_commission']=$person['own_commission']+$person['team_commission'];}unset($person);
    $rows=array_values($people);usort($rows,fn($a,$b)=>(float)$b['total_commission']<=>(float)$a['total_commission']);ksort($teamPr);
    return ['rows'=>$rows,'unmapped'=>array_values($unmapped),'team_pr_units'=>$teamPr,'team_pr_sales'=>$teamPrSales,'team_sales_map'=>$teamMap];
}

function posi_close_month(array &$d,string $month,int $userId): array {if(posi_closing($d,$month))throw new RuntimeException('เดือนนี้ถูก Final แล้ว หากต้องแก้ให้ Reopen ก่อน');$results=posi_month_results($d,$month);$summary=posi_period_summary($d,$month);if(!$results)throw new RuntimeException('ไม่มีพนักงาน Sales/PR ที่เปิด Incentive ในเดือนนี้');$id=next_id($d['pos_incentive_closings']??[]);$snap=[];foreach($results as $r)$snap[]=['employee_id'=>(int)$r['employee_id'],'units'=>(float)$r['units'],'sales'=>(float)$r['sales'],'rate'=>(float)$r['rate'],'unlocked'=>!empty($r['unlocked']),'potential_pay'=>(float)$r['potential_pay'],'recognized_pay'=>(float)$r['recognized_pay'],'rule_name'=>(string)$r['rule_name']];$c=['id'=>$id,'month'=>$month,'status'=>'final','summary'=>$summary,'results'=>$snap,'closed_at'=>date('c'),'closed_by'=>$userId];$d['pos_incentive_closings'][]=$c;$d['audit'][]=['at'=>date('c'),'action'=>'pos_incentive_month_finalized','month'=>$month,'closing_id'=>$id,'by'=>$userId];return $c;}
function posi_reopen_month(array &$d,string $month,int $userId): int {$n=0;if(!isset($d['pos_incentive_closings'])||!is_array($d['pos_incentive_closings']))return 0;foreach($d['pos_incentive_closings'] as &$c)if((string)($c['month']??'')===$month&&($c['status']??'')==='final'){$c['status']='reopened';$c['reopened_at']=date('c');$c['reopened_by']=$userId;$n++;}unset($c);if($n)$d['audit'][]=['at'=>date('c'),'action'=>'pos_incentive_month_reopened','month'=>$month,'count'=>$n,'by'=>$userId];return $n;}
