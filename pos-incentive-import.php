<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/compat.php';
require_once __DIR__.'/app/pos-incentive.php';

$u = require_permission('employees.manage');
$uid = (int)($u['id'] ?? 0);
$msg = '';
$err = '';
$validDate = fn(string $date) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
$dateFrom = (string)($_POST['date_from'] ?? date('Y-m-01'));
$dateTo = (string)($_POST['date_to'] ?? date('Y-m-t'));
$postedKind = (string)($_POST['report_kind'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (!in_array($postedKind, ['bill_detail', 'menu_sales'], true)) {
            throw new RuntimeException('กรุณาเลือกประเภท Report ที่ต้องการ Import');
        }
        if (!$validDate($dateFrom) || !$validDate($dateTo) || $dateTo < $dateFrom) {
            throw new RuntimeException('กรุณาระบุวันที่เริ่มต้นและวันที่สิ้นสุดของ Report ให้ถูกต้อง');
        }

        $stored = posi_inbox_store_upload($_FILES['pos_file'] ?? []);
        $title = trim((string)($_POST['report_title'] ?? ''));
        if ($title === '') $title = pathinfo((string)$stored['original_name'], PATHINFO_FILENAME);
        $detail = trim((string)($_POST['report_detail'] ?? ''));

        try {
            db_mutate(function ($d) use ($stored, $postedKind, $title, $detail, $dateFrom, $dateTo, $uid) {
                if (!isset($d['pos_upload_inbox']) || !is_array($d['pos_upload_inbox'])) $d['pos_upload_inbox'] = [];
                foreach ($d['pos_upload_inbox'] as $existing) {
                    $sameFile = ($existing['sha256'] ?? '') === $stored['sha256'];
                    $samePeriod = (string)($existing['period_from'] ?? '') === $dateFrom && (string)($existing['period_to'] ?? '') === $dateTo;
                    $sameKind = (string)($existing['report_kind'] ?? 'menu_sales') === $postedKind;
                    $active = in_array((string)($existing['status'] ?? ''), ['uploaded', 'processing', 'completed'], true);
                    if ($sameFile && $samePeriod && $sameKind && $active) {
                        throw new RuntimeException(posi_bill_report_label($postedKind).' ไฟล์เดียวกันในรอบวันที่นี้มีอยู่ในระบบแล้ว');
                    }
                }
                $entry = array_replace($stored, [
                    'id' => next_id($d['pos_upload_inbox']),
                    'report_kind' => $postedKind,
                    'title' => $title,
                    'detail' => $detail,
                    'period_mode' => 'custom',
                    'period_from' => $dateFrom,
                    'period_to' => $dateTo,
                    'status' => 'uploaded',
                    'uploaded_at' => date('c'),
                    'uploaded_by' => $uid,
                    'processed_at' => '',
                    'processed_by' => null,
                    'batch_id' => null,
                    'error' => '',
                ]);
                $d['pos_upload_inbox'][] = $entry;
                $d['audit'][] = [
                    'at' => date('c'),
                    'action' => 'pos_file_uploaded_to_inbox',
                    'inbox_id' => $entry['id'],
                    'report_kind' => $postedKind,
                    'period_from' => $dateFrom,
                    'period_to' => $dateTo,
                    'by' => $uid,
                ];
                return $d;
            });
        } catch (Throwable $uploadError) {
            $path = posi_inbox_dir().'/'.basename((string)$stored['stored_name']);
            if (is_file($path)) @unlink($path);
            throw $uploadError;
        }
        $msg = 'อัปโหลด '.posi_bill_report_label($postedKind).' เข้า Server สำเร็จแล้ว · ทีมทำเงินเดือนเลือกไป Process ต่อที่ขั้นตอน 2';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$d = db_load();
$inboxItems = array_values($d['pos_upload_inbox'] ?? []);
usort($inboxItems, fn($a, $b) => strcmp((string)($b['uploaded_at'] ?? ''), (string)($a['uploaded_at'] ?? '')));
$billItems = array_values(array_filter($inboxItems, fn($item) => (string)($item['report_kind'] ?? 'menu_sales') === 'bill_detail'));
$menuItems = array_values(array_filter($inboxItems, fn($item) => (string)($item['report_kind'] ?? 'menu_sales') === 'menu_sales'));
$billWaiting = count(array_filter($billItems, fn($item) => in_array((string)($item['status'] ?? ''), ['uploaded', 'failed'], true)));
$menuWaiting = count(array_filter($menuItems, fn($item) => in_array((string)($item['status'] ?? ''), ['uploaded', 'failed'], true)));

function posi_import_value(string $kind, string $field, string $fallback = ''): string {
    global $postedKind;
    if ($postedKind !== $kind) return $fallback;
    return (string)($_POST[$field] ?? $fallback);
}

function posi_import_card(string $kind, string $number, string $title, string $copy, string $fileTitle, string $fileHint, string $button, string $dateFrom, string $dateTo): void {
    $theme = $kind === 'bill_detail' ? 'bill' : 'menu';
    ?>
    <section class="posi-card posi-import-card posi-import-type <?=h($theme)?>">
      <div class="posi-card-head">
        <div>
          <small>IMPORT CHANNEL <?=h($number)?></small>
          <h2><?=h($title)?></h2>
          <p><?=h($copy)?></p>
        </div>
        <span class="posi-import-chip">อัปเดี่ยวได้</span>
      </div>
      <form method="post" enctype="multipart/form-data" class="posi-inbox-upload">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <input type="hidden" name="report_kind" value="<?=h($kind)?>">
        <div class="posi-inbox-fields">
          <label><span>ชื่อรายการ Report</span><input name="report_title" value="<?=h(posi_import_value($kind, 'report_title'))?>" placeholder="<?=h($title.' รอบเดือน '.date('m'))?>"></label>
          <label><span>รายละเอียด Report</span><input name="report_detail" value="<?=h(posi_import_value($kind, 'report_detail'))?>" placeholder="เช่น ไฟล์จาก POS เครื่องหลัก / สาขา / รอบนำเข้า"></label>
        </div>
        <div class="posi-inbox-fields">
          <label><span>วันที่เริ่มต้นของ Report *</span><input type="date" name="date_from" value="<?=h($dateFrom)?>" required></label>
          <label><span>วันที่สิ้นสุดของ Report *</span><input type="date" name="date_to" value="<?=h($dateTo)?>" required></label>
        </div>
        <label class="posi-inbox-file">
          <span class="upload-icon"><?=h($number)?></span>
          <div><b><?=h($fileTitle)?></b><small><?=h($fileHint)?></small></div>
          <input type="file" name="pos_file" accept=".xlsx,.csv" required>
        </label>
        <button><?=h($button)?></button>
      </form>
    </section>
    <?php
}

function posi_import_list(string $title, array $items, int $waiting): void {
    ?>
    <section class="posi-card posi-import-waiting">
      <div class="posi-inbox-list-head">
        <div><small>WAITING FOR PAYROLL</small><h3><?=h($title)?></h3></div>
        <span><?=number_format($waiting)?> ไฟล์รอ Process</span>
      </div>
      <?php if (!$items): ?>
        <div class="posi-empty">ยังไม่มีไฟล์ในช่องนี้</div>
      <?php endif; ?>
      <?php foreach (array_slice($items, 0, 8) as $item): ?>
        <article class="posi-inbox-item status-<?=h((string)($item['status'] ?? 'uploaded'))?>">
          <div class="posi-inbox-icon"><?=h(strtoupper((string)($item['ext'] ?? 'POS')))?></div>
          <div class="posi-inbox-info">
            <div><b><?=h((string)($item['title'] ?? $item['original_name'] ?? 'POS Report'))?></b><em><?=h(posi_inbox_status_label((string)($item['status'] ?? 'uploaded')))?></em></div>
            <p><?=h((string)($item['period_from'] ?? '')).' - '.h((string)($item['period_to'] ?? ''))?> · <?=h((string)($item['original_name'] ?? ''))?></p>
            <?php if (!empty($item['error'])): ?><strong><?=h((string)$item['error'])?></strong><?php endif; ?>
          </div>
          <div class="posi-inbox-action"><span><?=h(date('d/m H:i', strtotime((string)($item['uploaded_at'] ?? 'now'))))?></span></div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php
}
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MR BAR — Import File จาก POS</title>
  <link rel="stylesheet" href="assets/admin.css?v=1240">
  <link rel="stylesheet" href="assets/admin-v14.css?v=1240">
  <link rel="stylesheet" href="assets/pos-incentive-v1298.css?v=1298">
  <link rel="stylesheet" href="assets/pos-upload-inbox-v1390.css?v=1481">
  <link rel="stylesheet" href="assets/pos-wizard-strict-v1410.css?v=1410">
  <link rel="stylesheet" href="assets/pos-suite.css?v=1481">
  <link rel="stylesheet" href="assets/pos-import-split-v1481.css?v=1481">
</head>
<body class="admin-v14-page posi-page">
<?php require_once __DIR__.'/app/admin-nav.php'; echo admin_sidebar('incentive', $u); ?>
<main class="posi-shell">
  <header class="posi-head">
    <div>
      <p>PEOPLE / POS INCENTIVE</p>
      <h1>Import File จาก POS</h1>
      <span>หน้านี้สำหรับ IT อัปโหลดไฟล์เข้า Server เท่านั้น · ทีมทำเงินเดือนจะเลือกไฟล์ไป Process ในขั้นตอน 2</span>
    </div>
    <div><a href="pos-incentive.php">เลือกไฟล์ไปคำนวณ →</a></div>
  </header>
  <nav class="pos-suite-nav">
    <a class="active" href="pos-incentive-import.php"><span>1</span><div><b>Import POS</b><small>IT อัปโหลดแยกไฟล์</small></div></a>
    <a href="pos-incentive.php"><span>2</span><div><b>Process ข้อมูล</b><small>เงินเดือนเลือกไฟล์</small></div></a>
    <a href="pos-drinks.php"><span>3</span><div><b>ค่าดื่ม</b><small>PR / Sales</small></div></a>
    <a href="pos-commission.php"><span>4</span><div><b>ค่าคอม</b><small>PR ในทีม Sales</small></div></a>
    <a href="pos-reports.php"><span>5</span><div><b>Report</b><small>สรุป / Sort / เคลียร์</small></div></a>
  </nav>
  <?php if ($msg): ?><div class="posi-notice ok">✓ <?=h($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="posi-notice err">⚠ <?=h($err)?></div><?php endif; ?>

  <section class="posi-import-intro posi-import-intro-split">
    <div>
      <small>IT FILE DROP</small>
      <h2>เลือกช่อง Import ตามชนิดไฟล์</h2>
      <p>ไม่ต้องอัปครบสองไฟล์พร้อมกัน ช่องไหนพร้อมก็อัปช่องนั้นได้ทันที ระบบจะเก็บเป็น Inbox รอทีมทำเงินเดือนเลือกใช้ต่อ</p>
    </div>
    <div class="posi-import-counter">
      <span>รายละเอียดบิล <b><?=number_format($billWaiting)?></b></span>
      <span>ยอดขายตามเมนู <b><?=number_format($menuWaiting)?></b></span>
    </div>
  </section>

  <div class="posi-import-type-grid">
    <?php posi_import_card('bill_detail', '1', 'Import รายละเอียดบิล', 'สำหรับเลขบิล วันที่/เวลา และยอดขาย ใช้เทียบกับรอบเปิดโต๊ะของ Sales', 'เลือกไฟล์ Report รายละเอียดบิล *', 'อัปเฉพาะไฟล์รายละเอียดบิลได้เลย ไม่ต้องแนบไฟล์ยอดขายตามเมนู', 'Import รายละเอียดบิลเข้า Server →', $dateFrom, $dateTo); ?>
    <?php posi_import_card('menu_sales', '2', 'Import ยอดขายตามเมนู', 'สำหรับชื่อเมนู จำนวนขาย และยอดขาย ใช้ต่อในงานค่าดื่มและค่าคอม', 'เลือกไฟล์ Report ยอดขายตามเมนู *', 'อัปเฉพาะไฟล์ยอดขายตามเมนูได้เลย ไม่ต้องแนบไฟล์รายละเอียดบิล', 'Import ยอดขายตามเมนูเข้า Server →', $dateFrom, $dateTo); ?>
  </div>

  <div class="posi-import-type-grid posi-import-waiting-grid">
    <?php posi_import_list('ไฟล์รายละเอียดบิลที่อยู่บน Server', $billItems, $billWaiting); ?>
    <?php posi_import_list('ไฟล์ยอดขายตามเมนูที่อยู่บน Server', $menuItems, $menuWaiting); ?>
  </div>

  <section class="posi-flow-actions">
    <span></span>
    <div><b>จบหน้าที่ IT แล้ว</b><small>ไฟล์ยังไม่ถูก Process หรือคำนวณ จนกว่าทีมทำเงินเดือนจะเลือกต่อในขั้นตอน 2</small></div>
    <a class="next" href="pos-incentive.php">ไปหน้าเลือกไฟล์เพื่อคำนวณ →</a>
  </section>
  <footer>MR BAR POS Import · <?=h((string)(app_config()['pack'] ?? 'MR BAR'))?></footer>
</main>
</body>
</html>
