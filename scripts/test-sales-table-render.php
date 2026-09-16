<?php
// Render the actual template with fixture data; do not bootstrap or write production storage.
set_error_handler(function($n,$s){throw new RuntimeException($s);});
function h(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function csrf_token():string{return 'fixture';}
require __DIR__.'/../app/sales-table.php';
$source=file_get_contents(__DIR__.'/../sales-table.php');
$template=substr($source,strpos($source,'function st_fields'));
$slug='Priest';$tableId=1;$table=['code'=>'T01'];$branch=['name'=>'Priest'];
$u=['id'=>10,'username'=>'Proxy'];$d=[];$err='';$url='sales-table.php?public_branch=Priest&table_id=1';
$sales=[['id'=>20,'code'=>'S01','name'=>'Sales A','profile'=>['profile_photo'=>'storage/employee-media/a.jpg']]];
$open=null;$canManage=true;$history=[];$_GET=['public_branch'=>'Priest'];
ob_start();eval($template);$html=ob_get_clean();
if(strpos($html,'name="sales_id"')===false||strpos($html,'name="action" value="open"')===false)throw new RuntimeException('Open form missing');
echo "PASS: open screen renders employee card and action\n";
$open=['id'=>1,'revision'=>1,'sales_id'=>20,'sales_label'=>'S01 · Sales A','table_id'=>1,'table_code'=>'T01','opened_at'=>'2026-09-16T21:00:00+07:00','opened_by_label'=>'Proxy','status'=>'open','receipts'=>[]];
$history=[$open];
// Functions were defined in first render, only evaluate markup thereafter.
$template=substr($source,strpos($source,'<!doctype'));
ob_start();eval('?>'.$template);$html=ob_get_clean();
if(strpos($html,'name="receipts"')===false||strpos($html,'name="revision"')===false)throw new RuntimeException('Close guard missing');
echo "PASS: active screen renders receipts and revision guard\n";
$history[0]['status']='closed';$history[0]['receipts']=['RC001'];$history[0]['closed_at']='2026-09-17T01:00:00+07:00';$history[0]['closed_by_label']='Closer';
ob_start();eval('?>'.$template);$html=ob_get_clean();
if(strpos($html,'edit_receipts')===false)throw new RuntimeException('Correction form missing');
echo "PASS: closed history renders manager corrections\n";
