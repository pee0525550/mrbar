<?php
require_once __DIR__.'/pos-incentive.php';
require_once __DIR__.'/pos-cores.php';

function posr_core_label(string $core): string { return $core==='drinks'?'ค่าดื่ม':'ค่าคอม'; }

function posr_rounds(array $d,bool $includeVoid=true): array {
    $out=[];
    foreach(['drinks'=>'drink_payout_rounds','commission'=>'commission_payout_rounds'] as $core=>$bucket){
        foreach($d[$bucket]??[] as $round){
            $status=(string)($round['status']??'saved');
            if(!$includeVoid&&$status==='void')continue;
            $round['core']=$core;$round['core_label']=posr_core_label($core);$round['status']=$status;
            $out[]=$round;
        }
    }
    return $out;
}

function posr_filter_sort(array $rounds,array $q): array {
    $core=(string)($q['core']??'all');$status=(string)($q['status']??'saved');
    $from=(string)($q['from']??'');$to=(string)($q['to']??'');$search=mb_strtolower(trim((string)($q['q']??'')));
    $rows=array_values(array_filter($rounds,function($r)use($core,$status,$from,$to,$search){
        if($core!=='all'&&($r['core']??'')!==$core)return false;
        if($status!=='all'&&($r['status']??'saved')!==$status)return false;
        if($from!==''&&(string)($r['to']??'')<$from)return false;
        if($to!==''&&(string)($r['from']??'')>$to)return false;
        if($search!==''){
            $hay=mb_strtolower(implode(' ',[(string)($r['id']??''),(string)($r['batch_id']??''),(string)($r['core_label']??''),(string)($r['from']??''),(string)($r['to']??'')]));
            foreach($r['rows']??[] as $line)$hay.=' '.mb_strtolower((string)($line['name']??'').' '.(string)($line['teams']??''));
            if(mb_strpos($hay,$search)===false)return false;
        }
        return true;
    }));
    $sort=(string)($q['sort']??'saved_at');$allowed=['saved_at','from','to','total','core_label','batch_id'];if(!in_array($sort,$allowed,true))$sort='saved_at';
    $dir=(string)($q['dir']??'desc')==='asc'?1:-1;
    usort($rows,function($a,$b)use($sort,$dir){$av=$a[$sort]??'';$bv=$b[$sort]??'';if($sort==='total'||$sort==='batch_id')$cmp=(float)$av<=>(float)$bv;else $cmp=strcmp((string)$av,(string)$bv);return $cmp*$dir;});
    return $rows;
}

function posr_employee_summary(array $rounds,array $q): array {
    $sum=[];
    foreach(posr_filter_sort($rounds,$q) as $round)foreach($round['rows']??[] as $r){
        $id=(int)($r['employee_id']??0);$key=$id.'|'.($r['name']??'');
        if(!isset($sum[$key]))$sum[$key]=['employee_id'=>$id,'name'=>(string)($r['name']??''),'role'=>(string)($r['role']??''),'d'=>0.0,'m'=>0.0,'drinks'=>0.0,'commission'=>0.0,'total'=>0.0];
        $amount=(float)($r['amount']??0);$sum[$key]['d']+=(float)($r['d']??0);$sum[$key]['m']+=(float)($r['m']??0);
        $sum[$key][$round['core']]+=$amount;$sum[$key]['total']+=$amount;
    }
    $rows=array_values($sum);$sort=(string)($q['employee_sort']??'total');$allowed=['name','role','d','m','drinks','commission','total'];if(!in_array($sort,$allowed,true))$sort='total';
    $dir=(string)($q['employee_dir']??'desc')==='asc'?1:-1;
    usort($rows,fn($a,$b)=>(is_numeric($a[$sort])?((float)$a[$sort]<=>(float)$b[$sort]):strcmp((string)$a[$sort],(string)$b[$sort]))*$dir);
    return $rows;
}

function posr_void_round(array $d,string $core,int $roundId,int $uid,string $reason): array {
    $bucket=pc_bucket($core);$reason=trim($reason);if($reason==='')throw new RuntimeException('กรุณาระบุเหตุผล');
    if(!isset($d[$bucket])||!is_array($d[$bucket]))$d[$bucket]=[];
    $found=false;foreach($d[$bucket] as &$r){
        if((int)($r['id']??0)!==$roundId)continue;
        if(($r['status']??'saved')==='void')throw new RuntimeException('รอบนี้ถูกยกเลิกแล้ว');
        $r['status']='void';$r['voided_at']=date('c');$r['voided_by']=$uid;$r['void_reason']=$reason;$found=true;break;
    }unset($r);if(!$found)throw new RuntimeException('ไม่พบรอบที่เลือก');
    $d['audit'][]=['at'=>date('c'),'action'=>$core.'_payout_voided','round_id'=>$roundId,'reason'=>$reason,'by'=>$uid];return $d;
}

function posr_clear_batch(array $d,int $batchId,int $uid,string $reason): array {
    $reason=trim($reason);if($reason==='')throw new RuntimeException('กรุณาระบุเหตุผล');
    foreach(posr_rounds($d,false) as $r)if((int)($r['batch_id']??0)===$batchId)throw new RuntimeException('Report นี้มีรอบค่าดื่มหรือค่าคอมที่บันทึกอยู่ กรุณายกเลิกรอบก่อน');
    posi_void_batch($d,$batchId,$uid);
    if(!isset($d['pos_upload_inbox'])||!is_array($d['pos_upload_inbox']))$d['pos_upload_inbox']=[];
    foreach($d['pos_upload_inbox'] as &$entry)if((int)($entry['batch_id']??0)===$batchId){
        $entry['status']='cleared';$entry['cleared_at']=date('c');$entry['cleared_by']=$uid;$entry['clear_reason']=$reason;
    }unset($entry);
    $d['audit'][]=['at'=>date('c'),'action'=>'pos_report_cleared','batch_id'=>$batchId,'reason'=>$reason,'by'=>$uid];return $d;
}
