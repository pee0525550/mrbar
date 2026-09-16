<?php
declare(strict_types=1);

function permission_catalog(): array {
    return [
        'dashboard'=>['label'=>'Dashboard & Overview','items'=>[
            'dashboard.view'=>'ดู Dashboard','dashboard.financial'=>'ดูข้อมูลการเงินบน Dashboard',
        ]],
        'operations'=>['label'=>'Operations / Check-in','items'=>[
            'sales_sessions.record'=>'เปิด/ปิดโต๊ะและบันทึก Sales แทน', 'sales_sessions.manage'=>'แก้เจ้าของยอดและใบเสร็จย้อนหลัง',
            'operations.view'=>'ดูคิวงาน','operations.assign_pr'=>'มอบหมาย PR','operations.complete'=>'ปิด/จบงาน','operations.cancel'=>'ยกเลิกงาน','operations.move_table'=>'ย้ายโต๊ะ','operations.quick_floor'=>'สวิตช์สถานะโต๊ะ / PR / เซลแบบด่วน',
        ]],
        'reservations'=>['label'=>'Reservation & Waitlist','items'=>[
            'reservations.view'=>'ดูรายการจอง','reservations.manage'=>'สร้าง/แก้/ยืนยัน/ยกเลิกการจอง','reservations.seat'=>'รับลูกค้าเข้าร้าน',
        ]],
        'employees'=>['label'=>'Employees & Workforce','items'=>[
            'employees.view'=>'ดูข้อมูลพนักงาน','employees.manage'=>'เพิ่ม/แก้ข้อมูลพนักงานและนโยบายลงเวลา',
            'workforce.view'=>'ดูภาพรวม Workforce','workforce.attendance.self'=>'ลงเวลาและดูประวัติของตนเอง','workforce.attendance.manage'=>'จัดการ Attendance ของพนักงาน',
            'workforce.exceptions.view'=>'ดู Missing Check-out / Time Correction','workforce.exceptions.manage'=>'แก้ Missing Check-out / อนุมัติ Time Correction',
        ]],
        'pr'=>['label'=>'PR Management','items'=>[
            'pr.view'=>'ดูข้อมูล PR','pr.manage'=>'เพิ่ม/แก้ PR','attendance.view'=>'ดู Attendance','attendance.manage'=>'แก้ Attendance / Shift',
            'leave.view'=>'ดูคำขอลา','leave.manage'=>'อนุมัติ/ปฏิเสธคำขอลา','substitute.request'=>'ส่งคำขอ PR มาแทน','substitute.manage'=>'อนุมัติ/จัดการ PR มาแทน',
        ]],
        'tables'=>['label'=>'Tables','items'=>[
            'tables.view'=>'ดูสถานะโต๊ะ','tables.manage'=>'เพิ่ม/แก้/ปิดโต๊ะ',
        ]],
        'users'=>['label'=>'Users & Access','items'=>[
            'users.view'=>'ดูบัญชีผู้ใช้','users.manage'=>'เพิ่ม/แก้/เปิดปิดบัญชี','users.reset_password'=>'รีเซ็ตรหัสผ่าน',
            'roles.view'=>'ดู Role & Permission','roles.manage'=>'แก้ Role / Permission / Override',
        ]],
        'settings'=>['label'=>'Settings','items'=>[
            'settings.view'=>'ดู Settings','settings.manage'=>'แก้และบันทึก Settings',
        ]],
        'customers'=>['label'=>'Customer CRM / Member','items'=>[
            'customers.view'=>'ดูข้อมูลลูกค้าและประวัติการจอง','customers.manage'=>'เพิ่ม/แก้ข้อมูลสมาชิก VIP และผู้ดูแล',
        ]],
        'customer_web'=>['label'=>'Customer Web','items'=>[
            'customer_web.view'=>'ดูการตั้งค่าเว็บไซต์ลูกค้า','customer_web.manage'=>'แก้และเผยแพร่เว็บไซต์ลูกค้า',
        ]],
        'reports'=>['label'=>'Reports & Closing','items'=>[
            'reports.view'=>'ดู Reports','reports.export'=>'Export รายงาน','reports.daily_close'=>'ทำ Daily Close',
            'payroll.view'=>'ดูตารางเวลาทำงาน/Payroll','payroll.manage'=>'ตั้งค่าค่าจ้างพนักงาน','payroll.export'=>'Export Payroll / Attendance',
        ]],
        'notifications'=>['label'=>'Notification & Calls','items'=>[
            'notifications.view'=>'ดู Notification','notifications.manage'=>'จัดการ Notification','customer_calls.manage'=>'รับ/ปิด Call จากลูกค้า',
        ]],
        'shifts'=>['label'=>'Shift Scheduling','items'=>[
            'shifts.view'=>'ดูตารางกะ','shifts.manage'=>'สร้าง/แก้กะ',
        ]],
        'system'=>['label'=>'System & Security','items'=>[
            'audit.view'=>'ดู Audit Log','backup.view'=>'ดู Backup','backup.manage'=>'สร้าง/Restore Backup','system.health'=>'ดู System Health',
        ]],
    ];
}
function permission_keys(): array { $out=[]; foreach(permission_catalog() as $g) foreach($g['items'] as $k=>$v)$out[]=$k; return $out; }
function permission_default_roles(): array {
    $all=permission_keys();
    return [
        'admin'=>['name'=>'Admin','description'=>'ผู้ดูแลระบบ','builtin'=>1,'home'=>'admin.php','permissions'=>array_fill_keys($all,1)],
        'staff'=>['name'=>'Staff','description'=>'พนักงานหน้าร้าน','builtin'=>1,'home'=>'dashboard.php','permissions'=>array_fill_keys([
            'sales_sessions.record','dashboard.view','operations.view','operations.assign_pr','operations.complete','operations.cancel','operations.move_table','operations.quick_floor',
            'reservations.view','reservations.manage','reservations.seat','customers.view','customers.manage','pr.view','attendance.view','workforce.attendance.self','workforce.exceptions.view','tables.view','notifications.view','notifications.manage','customer_calls.manage','shifts.view','reports.view'
        ],1)],
        'sales'=>['name'=>'Sales','description'=>'เซล / ผู้แนะนำลูกค้า','builtin'=>1,'home'=>'employee-time.php','permissions'=>array_fill_keys(['sales_sessions.record','operations.quick_floor','tables.view','pr.view','workforce.attendance.self','shifts.view','notifications.view'],1)],
        'pr'=>['name'=>'PR','description'=>'PR / พนักงานบริการ','builtin'=>1,'home'=>'pr.php','permissions'=>array_fill_keys(['sales_sessions.record','dashboard.view','operations.view','tables.view','notifications.view','shifts.view','workforce.attendance.self','substitute.request'],1)],
    ];
}
function permission_slug(string $value): string {
    $v=strtolower(trim($value));$v=preg_replace('/[^a-z0-9_-]+/','_',$v)??'';$v=trim($v,'_-');return substr($v,0,40);
}
function user_is_super_admin(array $u): bool { return !empty($u['super_admin']); }
function permission_fresh_user(array $sessionUser,array $d): array {
    foreach($d['users']??[] as $row) if((int)($row['id']??0)===(int)($sessionUser['id']??0)) return $row;
    return $sessionUser;
}
function user_can(array $u,string $permission,?array $d=null): bool {
    $d=$d??db_load();$u=permission_fresh_user($u,$d);
    if(user_is_super_admin($u)) return true;
    if(!in_array($permission,permission_keys(),true)) return false;
    $over=$u['permission_overrides']??[];
    if(array_key_exists($permission,$over) && ($over[$permission]===0 || $over[$permission]==='0' || $over[$permission]===false)) return false;
    if(array_key_exists($permission,$over) && ($over[$permission]===1 || $over[$permission]==='1' || $over[$permission]===true)) return true;
    $role=(string)($u['role']??'');$roles=$d['roles']??permission_default_roles();
    return !empty($roles[$role]['permissions'][$permission]);
}
function require_permission(string $permission): array {
    $u=current_user();if(!$u){header('Location:login.php');exit;}$d=db_load();
    if(!user_can($u,$permission,$d)){http_response_code(403);echo permission_denied_page($permission);exit;}
    return permission_fresh_user($u,$d);
}
function require_any_permission(array $permissions): array {
    $u=current_user();if(!$u){header('Location:login.php');exit;}$d=db_load();
    foreach($permissions as $p) if(user_can($u,$p,$d)) return permission_fresh_user($u,$d);
    http_response_code(403);echo permission_denied_page(implode(' / ',$permissions));exit;
}
function permission_denied_page(string $p): string {
    $safe=htmlspecialchars($p,ENT_QUOTES,'UTF-8');
    return '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>403 · MR BAR</title><link rel="stylesheet" href="assets/typography.css?v=1.22.4"><body style="margin:0;background:#070912;color:#eaf0ff;font-family:MRBAR Thai,Noto Sans Thai,Tahoma,Leelawadee UI,Thonburi,Segoe UI,Arial,sans-serif;padding:8vw"><div style="max-width:700px;margin:auto;background:#101527;border:1px solid #703cff55;border-radius:24px;padding:32px;box-shadow:0 0 80px #5b2cff22"><div style="font-size:56px">🔐</div><h1>ไม่มีสิทธิ์เข้าถึงส่วนนี้</h1><p style="color:#9aa6ca">Permission ที่ต้องใช้: <code>'.$safe.'</code></p><p>หากจำเป็น ให้ Super Admin เปิดสิทธิ์ใน Role & Permission Center</p><a href="javascript:history.back()" style="color:#75c9ff">← กลับหน้าก่อน</a></div></body></html>';
}
function audit_permission(array &$d,array $u,string $action,array $meta=[]): void {
    $d['audit'][]=array_merge(['at'=>date('c'),'action'=>$action,'by'=>(int)($u['id']??0)],$meta);
}
