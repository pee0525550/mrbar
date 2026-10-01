<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/reservation-payments.php';
$u=require_any_permission(['reservations.view','reservations.manage']);
$d=db_load();
$reservationId=max(0,(int)($_GET['id']??0));
$reservation=null;
foreach($d['reservations']??[] as $row)if((int)($row['id']??0)===$reservationId){$reservation=$row;break;}
if(!$reservation){http_response_code(404);exit('ไม่พบรายการจอง');}
$branchId=(int)($d['_branch_context']['id']??$d['meta']['active_branch_id']??0);
$file=mrbar_reservation_slip_absolute_path((string)($reservation['deposit_slip_path']??''),$branchId);
if(!$file){http_response_code(404);exit('ไม่พบไฟล์สลิป');}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($file);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit('ไฟล์สลิปไม่รองรับ');}
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($file));
header('Content-Disposition: inline; filename="reservation-slip.'.($mime==='image/jpeg'?'jpg':($mime==='image/png'?'png':'webp')).'"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($file);
