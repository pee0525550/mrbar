<?php
/**
 * MR BAR TIME / Staff App admin preview helper.
 * Production-safe: resolves a virtual employee identity but never changes the real session.
 */
if (!function_exists('staff_preview_requested')) {
function staff_preview_requested(): bool {
    return (string)($_GET['admin_staff_preview'] ?? '') === '1' || (string)($_GET['admin_pr_preview'] ?? '') === '1';
}
function staff_preview_context(): ?array {
    if (!staff_preview_requested()) return null;
    if (!empty($GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT'])) return $GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT'];
    $admin=current_user();
    if(!$admin){header('Location: login.php');exit;}
    $d=db_load();
    if(!function_exists('user_can') && is_file(__DIR__.'/permissions.php')) require_once __DIR__.'/permissions.php';
    if(!function_exists('user_can') || (!user_can($admin,'employees.view',$d) && !user_can($admin,'pr.manage',$d))){
        http_response_code(403);
        echo function_exists('permission_denied_page')?permission_denied_page('employees.view / pr.manage'):'Permission denied';
        exit;
    }
    $token=(string)($_GET['preview_token']??'');
    if($token==='' || !hash_equals(csrf_token(),$token)){
        http_response_code(403);
        exit('MR BAR TIME Preview หมดอายุ กรุณากลับไปหน้า “MR BAR TIME Preview” แล้วโหลดใหม่');
    }
    $employeeId=(int)($_GET['employee_id']??0);
    if($employeeId<=0 && (int)($_GET['pr_id']??0)>0){
        $legacyPrId=(int)$_GET['pr_id'];
        foreach($d['employees']??[] as $e) if((int)($e['pr_id']??0)===$legacyPrId){$employeeId=(int)$e['id'];break;}
    }
    $employee=null;
    foreach($d['employees']??[] as $e) if((int)($e['id']??0)===$employeeId){$employee=$e;break;}
    if(!$employee){http_response_code(404);exit('Employee Profile not found.');}
    $linked=null;$linkedId=(int)($employee['user_id']??0);
    if($linkedId>0) foreach($d['users']??[] as $row) if((int)($row['id']??0)===$linkedId){$linked=$row;break;}
    $position=(string)($employee['position']??'staff');
    $expectedRole=($position==='pr'||!empty($employee['pr_id']))?'pr':($position==='sales'?'sales':'staff');
    $virtualRole=$linked?(string)($linked['role']??$expectedRole):$expectedRole;
    $virtual=[
        'id'=>$linked?(int)$linked['id']:0,
        'username'=>$linked?(string)$linked['username']:('preview-employee-'.$employeeId),
        'role'=>$virtualRole,
        'display_name'=>$linked?(string)$linked['display_name']:(string)($employee['name']??'Staff Preview'),
    ];
    $pr=null;$prId=(int)($employee['pr_id']??0);
    if($prId>0) foreach($d['prs']??[] as $p) if((int)($p['id']??0)===$prId){$pr=$p;break;}
    $route=$expectedRole==='pr'?'pr.php':'employee-time.php';
    $ctx=['admin'=>$admin,'data'=>$d,'employee'=>$employee,'employee_id'=>$employeeId,'user'=>$virtual,'linked_user'=>$linked,'expected_role'=>$expectedRole,'pr'=>$pr,'pr_id'=>$prId,'route'=>$route,'token'=>$token];
    $GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT']=$ctx;
    if($expectedRole==='pr')$GLOBALS['MRBAR_PR_PREVIEW_CONTEXT']=$ctx;
    return $ctx;
}
function staff_preview_is_active(): bool {return !empty($GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT']);}
function staff_preview_params(): array {
    $c=$GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT']??null;if(!$c)return [];
    $params=['admin_staff_preview'=>'1','employee_id'=>(string)$c['employee_id'],'preview_token'=>(string)$c['token']];
    if(($c['expected_role']??'')==='pr' && !empty($c['pr_id'])){$params['admin_pr_preview']='1';$params['pr_id']=(string)$c['pr_id'];}
    return $params;
}
function staff_preview_url(string $path): string {
    $params=staff_preview_params();if(!$params)return $path;
    $parts=parse_url($path);$query=[];if(!empty($parts['query']))parse_str((string)$parts['query'],$query);$query=array_merge($query,$params);
    $base=(string)($parts['path']??$path);$fragment=isset($parts['fragment'])?'#'.$parts['fragment']:'';
    return $base.'?'.http_build_query($query).$fragment;
}
function staff_preview_block_post(): void {
    if(!staff_preview_is_active() || ($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    http_response_code(409);header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>MR BAR TIME Preview · อ่านอย่างเดียว</title><body style="margin:0;background:#070914;color:#fff;font-family:Tahoma,Leelawadee UI,Segoe UI,Arial,sans-serif;padding:8vw"><main style="max-width:680px;margin:auto;background:#10172a;border:1px solid #7a5cff66;border-radius:28px;padding:32px"><div style="font-size:50px">◉</div><h1>MR BAR TIME Preview เป็นโหมดอ่านอย่างเดียว</h1><p style="color:#aab6d8;line-height:1.7">คำสั่งนี้ถูก Block เพื่อป้องกัน Check-in / Check-out / Profile / งาน / การลา หรือข้อมูลจริงถูกเปลี่ยนจากการทดสอบหลังบ้าน</p><button onclick="history.back()" style="border:0;border-radius:14px;padding:14px 18px;background:linear-gradient(135deg,#8b2cff,#258dff);color:white;font-weight:800">← กลับ Preview</button></main></body></html>';
    exit;
}
function staff_preview_banner(): string {
    $c=$GLOBALS['MRBAR_STAFF_PREVIEW_CONTEXT']??null;if(!$c)return '';$e=$c['employee'];
    return '<div class="admin-pr-preview-banner" data-staff-preview-banner data-pr-preview-banner><span>◉</span><div><b>MR BAR TIME PREVIEW · READ ONLY</b><small>'.h((string)($e['code']??'')).' · '.h((string)($e['name']??'')).' — ทดลองหน้าจอได้ แต่ไม่บันทึกข้อมูลจริง</small></div><button type="button" data-staff-preview-close data-pr-preview-close>×</button></div>';
}
}
