<?php
if (!function_exists('admin_sidebar')) {
function admin_sidebar($active, $user=array()) {
    $schema = defined('MRBAR_SCHEMA_VERSION') ? MRBAR_SCHEMA_VERSION : '';
    $appCfg = is_file(__DIR__.'/../config/app.php') ? require __DIR__.'/../config/app.php' : array();
    $appVersion = (string)($appCfg['version'] ?? '');
    $name = is_array($user) ? (string)($user['display_name'] ?? '') : '';
    $brandLogo = function_exists('mr_branding_asset_url') ? mr_branding_asset_url('sidebar') : '';
    $groups = array(
        array('icon'=>'⌂', 'label'=>'ศูนย์ควบคุม', 'hint'=>'CONTROL CENTER', 'items'=>array(
            array('dashboard','admin.php','⌂','Dashboard','ภาพรวมร้านวันนี้'),
            array('operations','night-ops.php','✦','Operations','จอง / Waitlist / โต๊ะ / Service'),
        )),
        array('icon'=>'♙', 'label'=>'ทีมงาน & บุคลากร', 'hint'=>'PEOPLE', 'items'=>array(
            array('employees','employees.php','♙','Employee Center','ประวัติ / ตำแหน่ง / บัญชี / รูปพนักงาน'),
            array('drinks','pos-drinks.php','฿','ค่าดื่ม','ดื่มส่วนตัว PR / Sales'),
            array('commission','pos-commission.php','฿','ค่าคอม','ค่าคอมทีม / ยอดเชียร์ลูกค้า'),
            array('prview','staff-preview.php','◉','MR BAR TIME Preview','ทดสอบหน้าจอพนักงาน / Read-only'),
        )),
        array('icon'=>'◴', 'label'=>'ตารางงาน & เวลา', 'hint'=>'WORKFORCE', 'items'=>array(
            array('workforce','workforce-schedule.php','▦','Workforce Schedule','กะงานพนักงาน / ปฏิทิน'),
            array('roster','shift-roster.php','✣','Shift Template & Auto Roster','แม่แบบกะ / จัดกะหลายคน / ตรวจชน'),
            array('payroll','payroll-attendance.php','◴','Attendance & Payroll','เวลา / รูป / เงินเดือน'),
            array('exceptions','workforce-exceptions.php','⚠','Workforce Exceptions','Missing Check-out / คนมาแทน'),
            array('leaves','admin-leaves.php','☂','Leave Requests','อนุมัติลา / ประวัติลา'),
        )),
        array('icon'=>'◈', 'label'=>'รายงาน & ช่องทางลูกค้า', 'hint'=>'REPORTS & CUSTOMER', 'items'=>array(
            array('customers','customers.php','♧','Customer CRM','สมาชิก / VIP / ประวัติจอง / Sales & PR'),
            array('tools','admin-tools.php','▦','Tools & Reports','QR / Shift / Reports'),
            array('customerweb','customer-web.php','◈','Customer Web','หน้าเว็บ / Content / Booking CTA'),
            array('zonestudio','zone-studio.php','▧','Zone Studio','ผังร้าน 2D / 2.5D / โต๊ะ / Booking Map'),
        )),
        array('icon'=>'⚙', 'label'=>'ระบบ & การจัดการ', 'hint'=>'SYSTEM ADMIN', 'items'=>array(
            array('access','role-permissions.php','🔐','Role & Permission','กำหนดสิทธิ์อย่างละเอียด'),
            array('management','admin-manage.php','◫','บัญชีผู้ใช้ & โต๊ะ','User Login / Table Directory'),
            array('settings','settings.php','⚙','Settings Center','ร้าน / GPS / ลงเวลา / Security'),
        )),
    );
    ob_start(); ?>
<script>(function(){try{if(window.matchMedia&&window.matchMedia('(min-width: 901px)').matches&&localStorage.getItem('mrbar_admin_sidebar_compact')==='1'){document.documentElement.classList.add('admin-sidebar-collapsed');}}catch(e){}})();</script>
<link rel="stylesheet" href="assets/admin-layout-v1182.css?v=1182">
<link rel="stylesheet" href="assets/admin-sidebar-groups-v1274.css?v=1274">
<link rel="stylesheet" href="assets/admin-branding-v1276.css?v=1276">
<link rel="stylesheet" href="assets/admin-sidebar-footer-v1305.css?v=1305">
<script src="assets/admin-layout-v1182.js?v=1182" defer></script>
<script src="assets/admin-sidebar-groups-v1274.js?v=1274" defer></script>
<aside class="sidebar admin-v14-sidebar" id="sidebar" aria-label="Admin navigation">
  <button type="button" class="admin-sidebar-collapse" id="adminSidebarCollapse" aria-label="ย่อหรือขยายเมนู" title="ย่อ / ขยายเมนู"><span class="collapse-icon">‹</span></button>
  <a class="brand<?=$brandLogo!==''?' brand-has-logo':''?>" href="admin.php"><?php if($brandLogo!==''):?><img class="admin-brand-logo" src="<?=h($brandLogo)?>" alt="MR BAR" onerror="this.style.display='none';this.parentElement.classList.remove('brand-has-logo')"><?php endif;?><span class="brand-icon">◇</span><span class="brand-copy"><b>MR BAR</b><small>ADMIN CONTROL</small></span></a>
  <nav class="admin-nav-groups">
    <?php foreach($groups as $groupIndex=>$group): $groupHasActive=false;foreach($group['items'] as $groupItem){if($active===$groupItem[0]){$groupHasActive=true;break;}}$groupId='adminNavGroup'.($groupIndex+1); ?>
      <section class="nav-group<?=$groupHasActive?' is-open has-active':''?>" data-nav-group="<?=h((string)$groupIndex)?>">
        <button type="button" class="nav-group-title" aria-expanded="<?=$groupHasActive?'true':'false'?>" aria-controls="<?=h($groupId)?>">
          <span class="nav-group-icon"><?=h($group['icon'])?></span>
          <span class="nav-group-copy"><b><?=h($group['label'])?></b><small><?=h($group['hint'])?></small></span>
          <span class="nav-group-chevron" aria-hidden="true">⌄</span>
        </button>
        <div class="nav-group-items" id="<?=h($groupId)?>" aria-hidden="<?=$groupHasActive?'false':'true'?>">
          <?php foreach($group['items'] as $item): $isActive=($active===$item[0]); ?>
          <a class="<?=$isActive?'active ':''?><?=$item[0]==='settings'?'settings-featured':''?>" href="<?=h($item[1])?>">
            <span><?=h($item[2])?></span><b><?=h($item[3])?></b><small><?=h($item[4])?></small>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="nav-account-zone">
      <span class="nav-account-title">บัญชีปัจจุบัน</span>
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
