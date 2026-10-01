<?php
if (!function_exists('admin_sidebar')) {
function admin_sidebar($active, $user=array()) {
    $schema = defined('MRBAR_SCHEMA_VERSION') ? MRBAR_SCHEMA_VERSION : '';
    $appCfg = is_file(__DIR__.'/../config/app.php') ? require __DIR__.'/../config/app.php' : array();
    $appVersion = (string)($appCfg['version'] ?? '');
    $name = is_array($user) ? (string)($user['display_name'] ?? '') : '';
    $activeAliases = array('leaves'=>'approvals','exceptions'=>'approvals','roster'=>'workforce');
    $activeNav = $activeAliases[$active] ?? $active;
    $navData = function_exists('db_load') ? db_load() : array();
    $canOpenItem = static function($item) use ($user, $navData) {
        $required = $item[5] ?? array();
        if (!$required || !function_exists('user_can')) return true;
        foreach ($required as $permission) {
            if ($permission === '@admin') {
                if (($user['role'] ?? '') === 'admin') return true;
            } elseif (user_can($user, $permission, $navData)) {
                return true;
            }
        }
        return false;
    };
    $brandLogo = function_exists('mr_branding_asset_url') ? mr_branding_asset_url('sidebar') : '';
    $groups = array(
        array('icon'=>'⌂', 'label'=>'ศูนย์ควบคุม', 'hint'=>'CONTROL CENTER', 'items'=>array(
            array('dashboard','admin.php','⌂','Dashboard','ภาพรวมร้านวันนี้',['dashboard.view']),
            array('reservation','reservations.php','▦','การจองโต๊ะ','Reservation / Waitlist / รับลูกค้า',['reservations.view','reservations.manage']),
            array('reservation_settings','reservation-settings.php','฿','เงื่อนไขจอง & มัดจำ','นโยบาย / ชำระเงิน / ตรวจสลิป',['settings.manage']),
            array('operations','night-ops.php','✦','Operations','โต๊ะ / Service / ย้ายโต๊ะ',['operations.move_table','operations.quick_floor','reports.view','tables.view']),
            array('tools','admin-tools.php','▦','Operations Tools','QR โต๊ะ Sales',['tables.view','sales_sessions.record','sales_sessions.manage','settings.manage']),
        )),
        array('icon'=>'♙', 'label'=>'ทีมงาน & เวลา', 'hint'=>'PEOPLE & WORKFORCE', 'items'=>array(
            array('employees','employees.php','♙','Employee Center','ประวัติ / ตำแหน่ง / บัญชี / รูปพนักงาน',['employees.view']),
            array('workforce','workforce-schedule.php','▦','ปฏิทินพนักงาน','จัดตารางงาน / Attendance / Leave',['shifts.view','employees.view']),
            array('payroll','payroll-attendance.php','◴','Attendance & Payroll','เวลา / รูป / เงินเดือน',['@admin','payroll.view','attendance.view','reports.view','workforce.view']),
            array('approvals','hr-approval-center.php','✓','HR Approval Center','ลา / แก้เวลา / PR มาแทน / Exception',['@admin','leave.view','leave.manage','workforce.exceptions.view','workforce.exceptions.manage','attendance.view','attendance.manage','substitute.manage','payroll.view','payroll.manage']),
            array('prview','staff-preview.php','◉','MR BAR TIME Preview','ทดสอบหน้าจอพนักงาน / Read-only',['employees.view','pr.manage']),
        )),
        array('icon'=>'◈', 'label'=>'ลูกค้า & ช่องทาง', 'hint'=>'CUSTOMER EXPERIENCE', 'items'=>array(
            array('customers','customers.php','♧','Customer CRM','สมาชิก / VIP / ประวัติจอง / Sales & PR',['customers.view']),
            array('customerweb','customer-web.php','◈','Customer Web','หน้าเว็บ / Content / Booking CTA',['customer_web.view']),
        )),
        array('icon'=>'▤', 'label'=>'รายงาน & ค่าตอบแทน', 'hint'=>'REPORTS & PAYOUTS', 'items'=>array(
            array('incentive','pos-incentive.php','฿','POS Incentive & Commission','Import / ค่าดื่ม / ค่าคอม / Report',['employees.manage']),
            array('reports','system-reports.php','▣','Report Center','รายงานรวม / Sort / Export / Audit',['reports.view']),
        )),
        array('icon'=>'⚙', 'label'=>'ตั้งค่าร้าน & ระบบ', 'hint'=>'STORE & SYSTEM', 'items'=>array(
            array('zonestudio','zone-studio.php','▧','Zone Studio','ผังร้าน 2D / 2.5D / โต๊ะ / Booking Map',['tables.view']),
            array('management','admin-manage.php','◫','บัญชีผู้ใช้ & โต๊ะ','User Login / Table Directory',['users.view','tables.view']),
            array('access','role-permissions.php','🔐','Role & Permission','กำหนดสิทธิ์อย่างละเอียด',['roles.view']),
            array('settings','settings.php','⚙','Settings Center','ร้าน / GPS / ลงเวลา / Security',['settings.view']),
            array('line_settings','line-settings.php','●','LINE Login','ตั้งค่าระบบ / บัญชีที่เชื่อม',['settings.view']),
        )),
    );
    ob_start(); ?>
<script>(function(){try{if(window.matchMedia&&window.matchMedia('(min-width: 901px)').matches&&localStorage.getItem('mrbar_admin_sidebar_compact')==='1'){document.documentElement.classList.add('admin-sidebar-collapsed');}}catch(e){}})();</script>
<link rel="stylesheet" href="assets/admin-layout-v1182.css?v=14919">
<link rel="stylesheet" href="assets/admin-sidebar-groups-v1274.css?v=1274">
<link rel="stylesheet" href="assets/admin-sidebar-groups-v14877.css?v=14877">
<link rel="stylesheet" href="assets/admin-branding-v1276.css?v=1276">
<link rel="stylesheet" href="assets/admin-sidebar-footer-v1305.css?v=1305">
<script src="assets/admin-layout-v1182.js?v=14919" defer></script>
<script src="assets/admin-sidebar-groups-v1274.js?v=1274" defer></script>
<aside class="sidebar admin-v14-sidebar" id="sidebar" aria-label="Admin navigation">
  <button type="button" class="admin-sidebar-collapse" id="adminSidebarCollapse" aria-label="ย่อหรือขยายเมนู" title="ย่อ / ขยายเมนู"><span class="collapse-icon">‹</span></button>
  <a class="brand<?=$brandLogo!==''?' brand-has-logo':''?>" href="admin.php"><?php if($brandLogo!==''):?><img class="admin-brand-logo" src="<?=h($brandLogo)?>" alt="MR BAR" onerror="this.style.display='none';this.parentElement.classList.remove('brand-has-logo')"><?php endif;?><span class="brand-icon">◇</span><span class="brand-copy"><b>MR BAR</b><small>ADMIN CONTROL</small></span></a>
  <nav class="admin-nav-groups">
    <?php foreach($groups as $groupIndex=>$group): $items=array_values(array_filter($group['items'],$canOpenItem));if(!$items)continue;$groupHasActive=false;foreach($items as $groupItem){if($activeNav===$groupItem[0]){$groupHasActive=true;break;}}$groupId='adminNavGroup'.($groupIndex+1); ?>
      <section class="nav-group<?=$groupHasActive?' is-open has-active':''?>" data-nav-group="<?=h((string)$groupIndex)?>">
        <button type="button" class="nav-group-title" aria-expanded="<?=$groupHasActive?'true':'false'?>" aria-controls="<?=h($groupId)?>">
          <span class="nav-group-icon"><?=h($group['icon'])?></span>
          <span class="nav-group-copy"><b><?=h($group['label'])?></b><small><?=h($group['hint'])?></small></span>
          <span class="nav-group-chevron" aria-hidden="true">⌄</span>
        </button>
        <div class="nav-group-items" id="<?=h($groupId)?>" aria-hidden="<?=$groupHasActive?'false':'true'?>">
          <?php foreach($items as $item): $isActive=($activeNav===$item[0]); ?>
          <a class="<?=$isActive?'active ':''?><?=$item[0]==='settings'?'settings-featured':''?>" href="<?=h($item[1])?>">
            <span><?=h($item[2])?></span><b><?=h($item[3])?></b><small><?=h($item[4])?></small>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="nav-account-zone">
      <span class="nav-account-title">บัญชีปัจจุบัน</span>
      <a class="admin-signout" href="account.php"><span>●</span><b>บัญชีของฉัน</b><small>โปรไฟล์ / LINE / ความปลอดภัย</small></a>
      <a class="admin-signout" href="logout.php"><span>↗</span><b>ออกจากระบบ</b><small><?=h($name!==''?$name:'Sign out')?></small></a>
    </div>
  </nav>
  <div class="version-card admin-v14-version"><span>SYSTEM READY</span><b><?=h($appVersion!==''?'v'.$appVersion:'Admin')?></b><small><?= $schema!=='' ? 'Schema v'.h((string)$schema) : 'Admin UI' ?></small><em>● LIVE</em></div>
</aside>
<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>
<button type="button" class="admin-mobile-launcher" id="adminMobileLauncher" aria-label="เปิดเมนูผู้ดูแล"><span>☰</span></button>
<?php return ob_get_clean();
}
}
