<?php
declare(strict_types=1);

require __DIR__.'/../app/floor-plan.php';

function floor_plan_check(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

$items=[
    ['id'=>1,'type'=>'table','table_id'=>11,'code'=>'T01','active'=>1],
    ['id'=>2,'type'=>'table','table_id'=>11,'code'=>'T01 copy','active'=>1],
    ['id'=>3,'type'=>'table','table_id'=>12,'code'=>'T02','active'=>1],
];
$duplicates=fp_linked_table_duplicates($items);
floor_plan_check(isset($duplicates[11])&&!isset($duplicates[12]),'duplicate active table references are identified');
floor_plan_check(fp_linked_table_duplicates([ $items[0],array_merge($items[1],['active'=>0]) ])===[],'inactive duplicate does not block publish');

$map=['canvas_width'=>1200,'canvas_height'=>900];
$data=['floor_plan_items'=>[],'tables'=>[['id'=>11,'code'=>'T01','zone'=>'Main','capacity'=>4]]];
$duplicateJson=json_encode([$items[0],$items[1]],JSON_THROW_ON_ERROR);
try{
    fp_sanitize_items_json($duplicateJson,1,$map,$data);
    throw new LogicException('duplicate table reference was accepted');
}catch(RuntimeException $e){
    floor_plan_check(str_contains($e->getMessage(),'เชื่อมโต๊ะซ้ำ'),'duplicate table error is actionable');
}

$validJson=json_encode([$items[0],array_merge($items[1],['active'=>0])],JSON_THROW_ON_ERROR);
$clean=fp_sanitize_items_json($validJson,1,$map,$data);
floor_plan_check(count($clean)===2,'layout with one active table hotspot saves');

$publicData=[
    'floor_plans'=>[['id'=>1,'status'=>'published','published_version_id'=>4]],
    'floor_plan_versions'=>[['id'=>4,'map_id'=>1,'items'=>[$items[0],$items[1],$items[2]]]],
];
$publicItems=fp_public_items($publicData,1);
floor_plan_check(count($publicItems)===1&&$publicItems[0]['table_id']===12,'public map hides ambiguous duplicate table hotspots');

echo "floor plan integrity tests passed\n";
