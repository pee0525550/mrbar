<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/reservation-sales.php';
require_once __DIR__.'/app/customer-integrations.php';
require_once __DIR__.'/app/reservation-payments.php';
require_once __DIR__.'/app/reservation-request.php';
require_once __DIR__.'/app/line-outbox.php';

function reservation_line_delivery_label(string $status): string {
    return ['sent'=>'LINE รับคำขอแล้ว','queued'=>'รอส่ง','processing'=>'กำลังส่ง','retrying'=>'รอลองใหม่','failed'=>'ส่งไม่สำเร็จ','expired'=>'เกินเวลาลองใหม่','cancelled'=>'หยุดส่ง','disabled'=>'ปิดแจ้งเตือน','not_linked'=>'ยังไม่ผูก LINE','not_configured'=>'ยังไม่ตั้งค่า Token','not_applicable'=>'ไม่เกี่ยวข้อง'][$status]??'ยังไม่มีผลส่ง';
}

function reservation_line_job_error_label(string $code): string {
    return ['network_or_server'=>'เครือข่ายหรือ LINE ขัดข้อง','attempts_exhausted'=>'ครบจำนวนครั้งที่ลองส่ง','retry_window_expired'=>'เกินเวลาส่งซ้ำอย่างปลอดภัย','reservation_closed'=>'รายการจองปิดแล้ว','receipt_superseded'=>'มีการยืนยันการจองแล้ว','confirmation_withdrawn'=>'การยืนยันถูกเปลี่ยนสถานะ','confirmation_disabled'=>'ปิดการแจ้งยืนยัน','recipient_permission_changed'=>'ผู้รับไม่มีสิทธิ์แล้ว','recipient_changed'=>'บัญชี LINE ของผู้รับเปลี่ยนไป','recipient_removed'=>'ไม่พบบัญชีผู้รับ','line_http_400'=>'LINE ไม่ยอมรับข้อมูลข้อความหรือผู้รับ','line_http_401'=>'Token ไม่ถูกต้องหรือหมดอายุ','line_http_403'=>'LINE ปฏิเสธสิทธิ์การส่ง','line_http_429'=>'ถึงขีดจำกัดการส่งของ LINE'][$code]??'';
}

$u=require_any_permission(['reservations.view','reservations.manage']);
$d=db_load();
$canManage=user_can($u,'reservations.manage',$d);
$canSeat=user_can($u,'reservations.seat',$d);
$message='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='reservation_create'){
            if(!$canManage)throw new RuntimeException('ไม่มี Permission: reservations.manage');
            $fields=reservation_validate_request($_POST,$d,false);
            $name=$fields['guest_name'];$phone=$fields['phone'];$date=$fields['date'];$time=$fields['time'];$party=$fields['party_size'];
            $tableId=max(0,(int)($_POST['table_id']??0));
            $note=$fields['note'];
            $salesRaw=trim((string)($_POST['sales_employee_id']??''));
            $createdReservationId=0;
            $committedData=db_mutate(function(array $data)use($name,$phone,$date,$time,$party,$tableId,$note,$salesRaw,$u,&$createdReservationId):array{
                $table=null;
                if($tableId>0){
                    foreach($data['tables']??[] as $candidate)if((int)($candidate['id']??0)===$tableId){$table=$candidate;break;}
                    if(!$table||empty($table['active'])||($table['status']??'available')!=='available')throw new RuntimeException('โต๊ะนี้ไม่พร้อมใช้งาน กรุณาเลือก Waitlist หรือโต๊ะอื่น');
                    foreach($data['checkins']??[] as $checkin)if((int)($checkin['table_id']??0)===$tableId&&!in_array((string)($checkin['status']??''),['completed','cancelled'],true))throw new RuntimeException('โต๊ะนี้มี Check-in ที่ยังไม่ปิด');
                }
                $sales=reservation_sales_selection($data,$salesRaw);
                $reservation=['id'=>next_id($data['reservations']),'guest_name'=>$name,'phone'=>$phone,'party_size'=>$party,'date'=>$date,'time'=>$time,'table_id'=>$tableId?:null,'note'=>$note,'status'=>$tableId?'booked':'waitlist','source'=>'staff','created_at'=>date('c'),'updated_at'=>date('c'),'seated_checkin_id'=>null];
                foreach($sales as $key=>$value)$reservation[$key]=$value;
                customer_crm_upsert_from_reservation($data,$reservation);
                $data['reservations'][]=$reservation;
                mrbar_notify_booking_created($data,$reservation);
                mrbar_line_enqueue_booking($data,$reservation);
                $createdReservationId=(int)$reservation['id'];
                audit_permission($data,$u,'reservation_created',['reservation_id'=>$reservation['id'],'sales_employee_id'=>$reservation['sales_employee_id']??null]);
                return $data;
            });
            $message='เพิ่มรายการจองเรียบร้อย';
        }elseif($action==='reservation_status'){
            if(!$canManage)throw new RuntimeException('ไม่มี Permission: reservations.manage');
            $id=max(0,(int)($_POST['id']??0));
            $status=(string)($_POST['status']??'');
            if(!in_array($status,['booked','waitlist','confirmed','cancelled','no_show'],true))throw new RuntimeException('สถานะการจองไม่ถูกต้อง');
            $previousStatus='';$updatedReservation=null;
            $committedData=db_mutate(function(array $data)use($id,$status,$u,&$previousStatus,&$updatedReservation):array{
                $found=false;
                foreach($data['reservations'] as &$reservation)if((int)($reservation['id']??0)===$id){
                    if(($reservation['status']??'')==='seated')throw new RuntimeException('รายการนี้รับลูกค้าเข้าร้านแล้ว ไม่สามารถแก้สถานะย้อนหลัง');
                    if(in_array($status,['booked','confirmed'],true)&&!mrbar_reservation_deposit_cleared($reservation))throw new RuntimeException('ต้องตรวจและรับรองสลิปมัดจำก่อนยืนยันโต๊ะ');
                    $previousStatus=(string)($reservation['status']??'waitlist');
                    $reservation['status']=$status;$reservation['updated_at']=date('c');$updatedReservation=$reservation;$found=true;break;
                }
                unset($reservation);
                if(!$found)throw new RuntimeException('ไม่พบรายการจอง');
                if(mrbar_should_send_customer_reservation_confirmation($updatedReservation,$previousStatus,$status))mrbar_line_enqueue_confirmation($data,$updatedReservation);
                audit_permission($data,$u,'reservation_'.$status,['reservation_id'=>$id]);
                return $data;
            });
            $message='อัปเดตสถานะการจองแล้ว';
        }elseif($action==='line_delivery_retry'){
            if(!$canManage)throw new RuntimeException('ไม่มี Permission: reservations.manage');
            $jobId=(string)($_POST['job_id']??'');
            db_mutate(function(array $data)use($jobId,$u):array{
                $current=null;foreach($data['users']??[] as $account)if((int)$account['id']===(int)$u['id']){$current=$account;break;}
                if(!$current||empty($current['active'])||!empty($current['deleted_at'])||!user_can($current,'reservations.manage',$data))throw new RuntimeException('ไม่มี Permission: reservations.manage');
                mrbar_line_outbox_retry($data,$jobId,time());
                audit_permission($data,$u,'reservation_line_retry',['job_id'=>$jobId]);return $data;
            });
            $message='นำข้อความเดิมกลับเข้าคิวส่ง LINE แล้ว';
        }elseif($action==='deposit_review'){
            if(!$canManage)throw new RuntimeException('ไม่มี Permission: reservations.manage');
            $id=max(0,(int)($_POST['id']??0));
            $review=(string)($_POST['deposit_status']??'');
            if(!in_array($review,['verified','rejected'],true))throw new RuntimeException('ผลตรวจสลิปไม่ถูกต้อง');
            db_mutate(function(array $data)use($id,$review,$u):array{
                $found=false;$branchId=(int)($data['_branch_context']['id']??$data['meta']['active_branch_id']??0);
                foreach($data['reservations'] as &$reservation)if((int)($reservation['id']??0)===$id){
                    if(($reservation['deposit_status']??'')!=='pending_review')throw new RuntimeException('สลิปนี้ไม่อยู่ในคิวตรวจแล้ว');
                    if(!mrbar_reservation_slip_absolute_path((string)($reservation['deposit_slip_path']??''),$branchId))throw new RuntimeException('ไม่พบไฟล์สลิปที่ปลอดภัยสำหรับตรวจ');
                    $reservation['deposit_status']=$review;$reservation['deposit_reviewed_at']=date('c');$reservation['deposit_reviewed_by']=(int)($u['id']??0);$reservation['updated_at']=date('c');
                    $found=true;break;
                }
                unset($reservation);
                if(!$found)throw new RuntimeException('ไม่พบรายการจอง');
                audit_permission($data,$u,'reservation_deposit_'.$review,['reservation_id'=>$id]);
                return $data;
            });
            $message=$review==='verified'?'รับรองสลิปแล้ว · หากพร้อม ให้เปลี่ยนสถานะการจองเป็นยืนยันแล้ว':'ปฏิเสธสลิปแล้ว';
        }elseif($action==='seat_reservation'){
            if(!$canSeat)throw new RuntimeException('ไม่มี Permission: reservations.seat');
            $id=max(0,(int)($_POST['id']??0));$tableId=max(0,(int)($_POST['table_id']??0));
            db_mutate(function(array $data)use($id,$tableId,$u):array{
                $reservationIndex=null;$tableIndex=null;
                foreach($data['reservations']??[] as $index=>$row)if((int)($row['id']??0)===$id){$reservationIndex=$index;break;}
                foreach($data['tables']??[] as $index=>$row)if((int)($row['id']??0)===$tableId){$tableIndex=$index;break;}
                if($reservationIndex===null)throw new RuntimeException('ไม่พบรายการจอง');
                if($tableIndex===null)throw new RuntimeException('ไม่พบโต๊ะ');
                if(empty($data['tables'][$tableIndex]['active'])||($data['tables'][$tableIndex]['status']??'')!=='available')throw new RuntimeException('โต๊ะนี้ไม่พร้อมรับลูกค้า');
                foreach($data['checkins']??[] as $checkin)if((int)($checkin['table_id']??0)===$tableId&&!in_array((string)($checkin['status']??''),['completed','cancelled'],true))throw new RuntimeException('โต๊ะนี้มี Check-in ที่ยังไม่ปิด');
                $reservation=$data['reservations'][$reservationIndex];
                if(in_array((string)($reservation['status']??''),['seated','cancelled','no_show'],true))throw new RuntimeException('รายการจองนี้ไม่สามารถรับเข้าร้านได้');
                if(!mrbar_reservation_deposit_cleared($reservation))throw new RuntimeException('ต้องตรวจและรับรองสลิปมัดจำก่อนรับลูกค้าเข้าร้าน');
                $checkinId=next_id($data['checkins']);$checkinTicket=ticket();
                $data['checkins'][]=['id'=>$checkinId,'ticket'=>$checkinTicket,'guest_name'=>$reservation['guest_name'],'phone'=>$reservation['phone']??'','table_id'=>$tableId,'status'=>'pending','pr_id'=>null,'created_at'=>date('c'),'updated_at'=>date('c'),'assigned_at'=>null,'accepted_at'=>null,'started_at'=>null,'completed_at'=>null,'rejected_at'=>null,'service_note'=>'','assigned_by'=>null,'reservation_id'=>$id,'booking_sales_selection'=>$reservation['sales_selection']??'','booking_sales_pr_id'=>$reservation['sales_pr_id']??null,'booking_sales_employee_id'=>$reservation['sales_employee_id']??null,'booking_sales_code_snapshot'=>$reservation['sales_code_snapshot']??'','booking_sales_name_snapshot'=>$reservation['sales_name_snapshot']??'','booking_sales_role_snapshot'=>$reservation['sales_role_snapshot']??'','table_history'=>[]];
                $data['tables'][$tableIndex]['status']='occupied';
                $data['reservations'][$reservationIndex]['status']='seated';$data['reservations'][$reservationIndex]['table_id']=$tableId;$data['reservations'][$reservationIndex]['seated_checkin_id']=$checkinId;$data['reservations'][$reservationIndex]['updated_at']=date('c');
                op_notify($data,null,'staff','reservation_seated','ลูกค้าจองมาถึงแล้ว · '.$checkinTicket,['checkin_id'=>$checkinId]);
                audit_permission($data,$u,'reservation_seated',['reservation_id'=>$id,'checkin_id'=>$checkinId,'table_id'=>$tableId]);
                return $data;
            });
            $message='รับลูกค้าเข้าร้านและสร้าง Check-in แล้ว';
        }else throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
    }catch(Throwable $exception){$error=$exception->getMessage();}
    $d=db_load();
}
mrbar_line_outbox_after_response((int)($d['_branch_context']['id']??0));
$lineJobs=$canManage?array_slice(array_reverse($d['line_outbox']??[]),0,30):[];
$lineTokenReady=mrbar_integration_env('MRBAR_LINE_CHANNEL_ACCESS_TOKEN')!=='';
$reservationById=[];foreach($d['reservations']??[] as $row)$reservationById[(int)$row['id']]=$row;

$today=date('Y-m-d');
$filterStatus=(string)($_GET['status']??'all');
$search=trim((string)($_GET['q']??''));
$filterDate=(string)($_GET['date']??'');
$reservations=$d['reservations']??[];
usort($reservations,static fn($a,$b)=>strcmp((string)($a['date']??'').(string)($a['time']??''),(string)($b['date']??'').(string)($b['time']??'')));
$reservationTotal=count($reservations);
$todayCount=count(array_filter($reservations,static fn($row)=>(string)($row['date']??'')===$today&&!in_array((string)($row['status']??''),['cancelled','no_show'],true)));
$waitCount=count(array_filter($reservations,static fn($row)=>in_array((string)($row['status']??''),['waitlist','booked'],true)));
$seatedCount=count(array_filter($reservations,static fn($row)=>(string)($row['status']??'')==='seated'));
$visibleReservations=array_values(array_filter($reservations,static function($row)use($filterStatus,$search,$filterDate):bool{
    if($filterStatus!=='all'&&(string)($row['status']??'')!==$filterStatus)return false;
    if($filterDate!==''&&(string)($row['date']??'')!==$filterDate)return false;
    if($search!==''&&stripos(implode(' ',[(string)($row['guest_name']??''),(string)($row['phone']??''),(string)($row['requested_table_code']??'')]),$search)===false)return false;
    return true;
}));
$salesOptions=reservation_sales_options($d);
$freeTables=array_values(array_filter($d['tables']??[],static fn($table)=>!empty($table['active'])&&($table['status']??'available')==='available'));
$visiblePaymentRows=array_values(array_filter($visibleReservations,static fn($row)=>in_array((string)($row['payment_mode']??'none'),['slip_manual','slip_api'],true)));
$tableById=[];foreach($d['tables']??[] as $table)$tableById[(int)($table['id']??0)]=$table;
$statusLabels=['waitlist'=>'รอจัดโต๊ะ','booked'=>'จองแล้ว','confirmed'=>'ยืนยันแล้ว','seated'=>'เข้าร้านแล้ว','cancelled'=>'ยกเลิก','no_show'=>'ไม่มาตามนัด'];
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>การจองโต๊ะ · MR BAR</title>
<link rel="stylesheet" href="assets/admin.css?v=1140"><link rel="stylesheet" href="assets/admin-v14.css?v=1140"><link rel="stylesheet" href="assets/reservations-v14892.css?v=14918"></head>
<body class="admin-v14-page reservations-page">
<?php require_once __DIR__.'/app/admin-nav.php';echo admin_sidebar('reservation',$u);?>
<main class="reservation-main">
    <header class="reservation-header"><div><p class="eyebrow">CUSTOMER EXPERIENCE / RESERVATIONS</p><h1>การจองโต๊ะ</h1><p>จัดการคำขอจอง, Waitlist, ยืนยันสถานะ และรับลูกค้าเข้าร้าน</p></div><div class="reservation-header-actions"><?php if(user_can($u,'settings.manage',$d)):?><a class="reservation-back" href="reservation-settings.php">เงื่อนไขจอง & มัดจำ</a><?php endif;?><a class="reservation-back" href="night-ops.php">Operations <span aria-hidden="true">→</span></a></div></header>
    <?php if($message!==''):?><div class="reservation-flash is-success" role="status">✓ <?=h($message)?></div><?php endif;?>
    <?php if($error!==''):?><div class="reservation-flash is-error" role="alert">! <?=h($error)?></div><?php endif;?>
    <section class="reservation-metrics" aria-label="สรุปรายการจอง"><article><small>รายการทั้งหมด</small><b><?=number_format($reservationTotal)?></b><span>รายการ</span></article><article class="metric-gold"><small>จองวันนี้</small><b><?=number_format($todayCount)?></b><span>รายการที่ยังไม่ยกเลิก</span></article><article class="metric-violet"><small>รอดำเนินการ</small><b><?=number_format($waitCount)?></b><span>Waitlist / จองแล้ว</span></article><article class="metric-green"><small>เข้าร้านแล้ว</small><b><?=number_format($seatedCount)?></b><span>รายการ</span></article></section>
    <?php if($canManage):?><section class="reservation-panel reservation-create"><div class="reservation-section-head"><div><span>NEW BOOKING</span><h2>เพิ่มรายการจอง</h2><p>เลือกโต๊ะเพื่อบันทึกเป็น “จองแล้ว” หรือเว้นว่างเพื่อเข้าคิว Waitlist</p></div></div><form method="post" class="reservation-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="reservation_create"><label class="field-name">ชื่อลูกค้า<input name="guest_name" maxlength="120" required placeholder="ชื่อหรือชื่อกลุ่ม"></label><label>เบอร์โทร<input name="phone" maxlength="30" inputmode="tel" placeholder="เบอร์ติดต่อ"></label><label>จำนวนคน<input type="number" min="1" max="30" name="party_size" value="2" required></label><label>วันที่<input type="date" name="date" min="<?=$today?>" value="<?=$today?>" required></label><label>เวลา<input type="time" name="time" value="19:00" required></label><label>Sales ผู้แนะนำ<select name="sales_employee_id"><option value="none">ไม่มี Sales / ลูกค้าใหม่</option><?php foreach($salesOptions as $sales):?><option value="<?=(int)$sales['employee_id']?>"><?=h((string)$sales['label'])?></option><?php endforeach;?></select></label><label>โต๊ะที่จอง<select name="table_id"><option value="0">Waitlist / ยังไม่ระบุโต๊ะ</option><?php foreach($freeTables as $table):?><option value="<?=(int)$table['id']?>"><?=h((string)$table['code'].' · '.(string)($table['zone']??'Main').' · '.(int)($table['capacity']??0).' ที่นั่ง')?></option><?php endforeach;?></select></label><label class="field-note">หมายเหตุ<input name="note" maxlength="500" placeholder="โซนที่ต้องการ / วันเกิด / รายละเอียดเพิ่มเติม"></label><button type="submit" class="reservation-primary">บันทึกรายการจอง <span aria-hidden="true">→</span></button></form></section><?php endif;?>
    <section class="reservation-panel"><div class="reservation-section-head board-heading"><div><span>BOOKING BOARD</span><h2>รายการจองและ Waitlist</h2><p>เลือกกรองตามสถานะ วัน หรือค้นหาชื่อลูกค้าและเบอร์โทร</p></div><b class="result-count"><?=number_format(count($visibleReservations))?> รายการ</b></div>
        <form method="get" class="reservation-filters"><label class="search-field">ค้นหา<input name="q" value="<?=h($search)?>" placeholder="ชื่อลูกค้า / เบอร์โทร / โต๊ะ"></label><label>วันที่<input type="date" name="date" value="<?=h($filterDate)?>"></label><label>สถานะ<select name="status"><option value="all">ทุกสถานะ</option><?php foreach($statusLabels as $key=>$label):?><option value="<?=h($key)?>" <?=$filterStatus===$key?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label><button class="filter-submit">ค้นหา</button><a class="filter-reset" href="reservations.php">ล้างตัวกรอง</a></form>
        <?php if(!$visibleReservations):?><div class="reservation-empty"><span>▦</span><b>ไม่พบรายการจอง</b><small>ลองเปลี่ยนตัวกรอง หรือเพิ่มรายการจองใหม่</small></div><?php else:?><div class="reservation-list"><?php foreach($visibleReservations as $reservation):$status=(string)($reservation['status']??'waitlist');$assignedTable=$tableById[(int)($reservation['table_id']??0)]??null;$requestedTableCode=(string)($reservation['requested_table_code']??'');$lineSummary=is_array($reservation['line_staff_notification_summary']??null)?$reservation['line_staff_notification_summary']:[];$lineCustomerStatus=(string)($reservation['line_customer_confirmation_status']??$reservation['line_customer_notification_status']??'');$showLineDelivery=$lineSummary!==[]||$lineCustomerStatus!=='';?><article class="reservation-row status-<?=h($status)?>"><div class="reservation-guest"><span class="guest-mark" aria-hidden="true"><?=h(function_exists('mb_substr')?mb_substr((string)($reservation['guest_name']??'?'),0,1):substr((string)($reservation['guest_name']??'?'),0,1))?></span><div><b><?=h((string)($reservation['guest_name']??'ลูกค้า'))?></b><small><?=h((string)($reservation['phone']??'ไม่ระบุเบอร์'))?> · <?=number_format((int)($reservation['party_size']??1))?> คน</small><small>Sales: <?=h(reservation_sales_label($reservation))?></small><?php if($showLineDelivery):?><small class="reservation-line-delivery"><?php if($lineCustomerStatus!==''):?>ลูกค้า LINE: <span class="<?=h($lineCustomerStatus)?>"><?=h(reservation_line_delivery_label($lineCustomerStatus))?></span> · <?php endif;?>ทีมงาน LINE: <?php if((int)($lineSummary['eligible']??0)>0):?>ส่ง <?=number_format((int)($lineSummary['sent']??0))?>/<?=number_format((int)$lineSummary['eligible'])?> · ไม่ผูก <?=number_format((int)($lineSummary['not_linked']??0))?> · ล้มเหลว <?=number_format((int)($lineSummary['failed']??0))?><?php else:?>ไม่พบผู้รับที่มีสิทธิ์<?php endif;?></small><?php endif;?></div></div><div class="reservation-when"><b><?=h((string)($reservation['date']??''))?></b><span><?=h((string)($reservation['time']??''))?></span></div><div class="reservation-table-info"><small>โต๊ะ / โซน</small><b><?php if($assignedTable):?><?=h((string)$assignedTable['code'])?> · <?=h((string)($assignedTable['zone']??'Main'))?><?php elseif($requestedTableCode!==''):?>ขอ <?=h($requestedTableCode)?><?php else:?>ยังไม่ระบุโต๊ะ<?php endif;?></b></div><span class="reservation-status"><?=h($statusLabels[$status]??$status)?></span><?php if($canManage):?><form method="post" class="row-action status-action"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="reservation_status"><input type="hidden" name="id" value="<?=(int)$reservation['id']?>"><select name="status" aria-label="เปลี่ยนสถานะการจอง"><?php foreach(['confirmed','booked','waitlist','no_show','cancelled'] as $option):?><option value="<?=h($option)?>" <?=$status===$option?'selected':''?>><?=h($statusLabels[$option])?></option><?php endforeach;?></select><button type="submit" class="row-button">บันทึก</button></form><?php endif;?><?php if($canSeat&&!in_array($status,['seated','cancelled','no_show'],true)):?><form method="post" class="row-action seat-action"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="seat_reservation"><input type="hidden" name="id" value="<?=(int)$reservation['id']?>"><select name="table_id" required aria-label="เลือกโต๊ะรับลูกค้า"><option value="">เลือกโต๊ะว่าง</option><?php foreach($freeTables as $table):?><option value="<?=(int)$table['id']?>" <?=(int)($reservation['requested_table_id']??0)===(int)$table['id']?'selected':''?>><?=h((string)$table['code'].' · '.(string)($table['zone']??'Main'))?></option><?php endforeach;?></select><button type="submit" class="row-button is-primary">รับลูกค้า</button></form><?php endif;?></article><?php endforeach;?></div><?php endif;?>
    </section>
    <?php if($visiblePaymentRows):?><section class="reservation-panel"><div class="reservation-section-head"><div><span>DEPOSIT REVIEW</span><h2>ตรวจมัดจำและสลิป</h2><p>รับรองสลิปเป็นการรับรองการชำระเท่านั้น การยืนยันโต๊ะยังใช้สถานะการจองด้านบน</p></div><b class="result-count"><?=number_format(count($visiblePaymentRows))?> รายการ</b></div><div class="deposit-review-list"><?php foreach($visiblePaymentRows as $paymentRow):$depositStatus=(string)($paymentRow['deposit_status']??'pending_review');$depositLabels=['pending_review'=>'รอตรวจ','verified'=>'ตรวจผ่าน','rejected'=>'ไม่ผ่าน','not_required'=>'ไม่ต้องชำระ'];?><article class="deposit-review-row"><div><b><?=h((string)($paymentRow['guest_name']??'ลูกค้า'))?> · #<?=(int)($paymentRow['id']??0)?></b><small><?=h((string)($paymentRow['phone']??''))?> · <?=number_format((int)($paymentRow['party_size']??1))?> คน</small></div><div><b><?=h((string)($paymentRow['date']??''))?> · <?=h((string)($paymentRow['time']??''))?></b><small><?=h(mrbar_reservation_payment_mode_label((string)$paymentRow['payment_mode']))?></small></div><div><b><?=number_format((int)($paymentRow['deposit_amount']??0))?> บาท</b><small><?=h($depositLabels[$depositStatus]??$depositStatus)?></small></div><div class="deposit-review-actions"><?php if(!empty($paymentRow['deposit_slip_path'])):?><a href="reservation-slip.php?id=<?=(int)$paymentRow['id']?>" target="_blank" rel="noopener">เปิดสลิป</a><?php endif;?><?php if($canManage&&$depositStatus==='pending_review'):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="deposit_review"><input type="hidden" name="id" value="<?=(int)$paymentRow['id']?>"><input type="hidden" name="deposit_status" value="verified"><button class="approve" type="submit">รับรองสลิป</button></form><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="deposit_review"><input type="hidden" name="id" value="<?=(int)$paymentRow['id']?>"><input type="hidden" name="deposit_status" value="rejected"><button class="reject" type="submit">ปฏิเสธ</button></form><?php endif;?></div></article><?php endforeach;?></div></section><?php endif;?>
    <?php if($lineJobs):?><section class="reservation-panel line-delivery-panel"><div class="reservation-section-head"><div><span>LINE DELIVERY</span><h2>สถานะข้อความ LINE</h2></div></div><div class="line-delivery-list"><?php foreach($lineJobs as $job):$jobReservation=$reservationById[(int)$job['reservation_id']]??[];$jobStatus=(string)$job['status'];$canRetry=in_array($jobStatus,['failed','retrying','not_configured'],true)&&(int)$job['attempts']<5&&((int)$job['first_attempt_at']===0||time()-(int)$job['first_attempt_at']<23*3600)&&time()-(int)$job['created_at']<23*3600&&(int)$job['next_attempt_at']<=time();?><article class="line-delivery-row"><div><b>#<?=(int)$job['reservation_id']?> · <?=h((string)($jobReservation['guest_name']??'รายการจอง'))?></b><small><?=h(['receipt'=>'รับคำขอลูกค้า','confirmation'=>'ยืนยันให้ลูกค้า','staff'=>'แจ้งทีมงาน'][$job['kind']]??'ข้อความ')?></small></div><div><b class="delivery-<?=h($jobStatus)?>"><?=h(reservation_line_delivery_label($jobStatus))?></b><small><?=number_format((int)$job['attempts'])?> ครั้ง<?php if((int)$job['http_status']>0):?> · HTTP <?=(int)$job['http_status']?><?php endif;?><?php if($jobStatus==='retrying'):?> · รอบถัดไป <?=h(date('H:i:s',(int)$job['next_attempt_at']))?><?php endif;?></small><?php if($jobStatus!=='sent'&&($jobError=reservation_line_job_error_label((string)($job['error_code']??'')))!==''):?><small><?=h($jobError)?></small><?php endif;?></div><?php if($canRetry):?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="line_delivery_retry"><input type="hidden" name="job_id" value="<?=h((string)$job['id'])?>"><button class="row-button" type="submit" <?=$lineTokenReady?'':'disabled title="รอตั้งค่า LINE Messaging API"'?>>ลองส่งใหม่</button></form><?php endif;?></article><?php endforeach;?></div></section><?php endif;?>
    <footer class="reservation-footer">MR BAR Reservation Management · สิทธิ์แยกตาม Role และ Permission</footer>
</main></body></html>
