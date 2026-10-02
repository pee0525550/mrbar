<?php
require __DIR__.'/app/bootstrap.php';

$u = require_permission('reports.view');
$uid = (int)($u['id'] ?? 0);
$isSuper = user_is_super_admin($u);
$canExport = $isSuper || user_can($u, 'reports.export');
$canAudit = $isSuper || user_can($u, 'audit.view');
$d = db_load();
$msg = '';
$err = '';

require_once __DIR__.'/app/system-reports.php';

function sr_clear_reports(array $data, string $scope, int $uid, string $reason): array {
    $summary = ['cache' => 0, 'pos_batches' => 0, 'bill_batches' => 0, 'locked' => 0];
    $lockedBatchIds = [];
    $clearedImportIds = [];
    $clearedBillIds = [];
    foreach (['drink_payout_rounds', 'commission_payout_rounds'] as $bucket) {
        foreach ($data[$bucket] ?? [] as $round) {
            if (($round['status'] ?? 'saved') !== 'void') $lockedBatchIds[(int)($round['batch_id'] ?? 0)] = true;
        }
    }

    if (in_array($scope, ['report_cache', 'all_report_artifacts'], true)) {
        foreach (['report_cache', 'report_snapshots', 'report_exports', 'generated_reports'] as $bucket) {
            if (!isset($data[$bucket]) || !is_array($data[$bucket])) continue;
            $summary['cache'] += count($data[$bucket]);
            $data[$bucket] = [];
        }
    }

    if (in_array($scope, ['pos_unlocked_reports', 'all_report_artifacts'], true)) {
        if (isset($data['pos_import_batches']) && is_array($data['pos_import_batches'])) {
            foreach ($data['pos_import_batches'] as &$batch) {
                $batchId = (int)($batch['id'] ?? 0);
                if (($batch['status'] ?? 'active') !== 'active') continue;
                if (isset($lockedBatchIds[$batchId])) {
                    $summary['locked']++;
                    continue;
                }
                $batch['status'] = 'cleared';
                $batch['cleared_at'] = date('c');
                $batch['cleared_by'] = $uid;
                $batch['clear_reason'] = $reason;
                $summary['pos_batches']++;
                $clearedImportIds[$batchId] = true;
            }
            unset($batch);
        }
        if (isset($data['pos_sales_rows']) && is_array($data['pos_sales_rows'])) {
            foreach ($data['pos_sales_rows'] as &$row) {
                $batchId = (int)($row['batch_id'] ?? 0);
                if (isset($clearedImportIds[$batchId])) $row['active'] = 0;
            }
            unset($row);
        }
        if (isset($data['pos_bill_batches']) && is_array($data['pos_bill_batches'])) {
            foreach ($data['pos_bill_batches'] as &$batch) {
                if (($batch['status'] ?? 'active') !== 'active') continue;
                $batchId = (int)($batch['id'] ?? 0);
                $batch['status'] = 'void';
                $batch['voided_at'] = date('c');
                $batch['voided_by'] = $uid;
                $batch['clear_reason'] = $reason;
                $summary['bill_batches']++;
                $clearedBillIds[$batchId] = true;
            }
            unset($batch);
        }
        if (isset($data['pos_bill_rows']) && is_array($data['pos_bill_rows'])) {
            foreach ($data['pos_bill_rows'] as &$row) {
                $batchId = (int)($row['batch_id'] ?? $row['bill_batch_id'] ?? 0);
                if (isset($clearedBillIds[$batchId])) $row['active'] = 0;
            }
            unset($row);
        }
        if (isset($data['sales_table_sessions']) && is_array($data['sales_table_sessions'])) {
            foreach ($data['sales_table_sessions'] as &$session) {
                $batchId = (int)($session['matched_bill_batch_id'] ?? $session['pos_bill_batch_id'] ?? 0);
                if (!isset($clearedBillIds[$batchId])) continue;
                unset($session['matched_bill_batch_id'], $session['matched_bill_id'], $session['pos_bill_batch_id']);
                if (($session['bill_match_status'] ?? '') === 'matched') $session['bill_match_status'] = 'pending';
            }
            unset($session);
        }
    }

    if (!isset($data['audit']) || !is_array($data['audit'])) $data['audit'] = [];
    $data['audit'][] = ['at' => date('c'), 'action' => 'system_reports_clear', 'scope' => $scope, 'summary' => $summary, 'reason' => $reason, 'by' => $uid];
    $data['_sr_clear_summary'] = $summary;
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ((string)($_POST['action'] ?? '') !== 'clear_reports') throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        if (!$isSuper) throw new RuntimeException('ใช้ได้เฉพาะ Super Admin เท่านั้น');
        $scope = (string)($_POST['scope'] ?? 'report_cache');
        if (!in_array($scope, ['report_cache', 'pos_unlocked_reports', 'all_report_artifacts'], true)) throw new RuntimeException('Scope ไม่ถูกต้อง');
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason, 'UTF-8') < 10) throw new RuntimeException('กรุณาใส่เหตุผลอย่างน้อย 10 ตัวอักษร');
        if ((string)($_POST['confirm_scope'] ?? '') !== 'REPORT-CLEAR') throw new RuntimeException('ชั้นที่ 1: พิมพ์ REPORT-CLEAR ให้ถูกต้อง');
        if ((string)($_POST['confirm_phrase'] ?? '') !== 'CLEAR REPORT') throw new RuntimeException('ชั้นที่ 2: พิมพ์ CLEAR REPORT ให้ถูกต้อง');
        $nameCheck = trim((string)($_POST['confirm_user'] ?? ''));
        $validNames = array_filter([sr_text($u['display_name'] ?? ''), sr_text($u['username'] ?? ''), sr_text($u['email'] ?? '')]);
        if ($nameCheck === '' || !in_array($nameCheck, $validNames, true)) throw new RuntimeException('ชั้นที่ 3: ชื่อผู้ใช้ไม่ตรงกับบัญชีที่ล็อกอิน');
        foreach (['understand_1', 'understand_2', 'understand_3'] as $box) {
            if (empty($_POST[$box])) throw new RuntimeException('กรุณาติ๊กยืนยันความเข้าใจให้ครบทุกข้อ');
        }
        $summary = [];
        $mutated = db_mutate(function ($data) use ($scope, $uid, $reason, &$summary) {
            $data = sr_clear_reports($data, $scope, $uid, $reason);
            $summary = $data['_sr_clear_summary'] ?? [];
            unset($data['_sr_clear_summary']);
            return $data;
        });
        $d = $mutated;
        $msg = 'เคลียร์ Report แบบ Soft Clear แล้ว: cache '.(int)($summary['cache'] ?? 0).', POS '.(int)($summary['pos_batches'] ?? 0).', Bill '.(int)($summary['bill_batches'] ?? 0).', locked '.(int)($summary['locked'] ?? 0);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$view = (string)($_GET['view'] ?? 'overview');
if (!in_array($view, ['overview', 'records', 'audit'], true)) $view = 'overview';
if ($view === 'audit' && !$canAudit) { http_response_code(403); exit('Forbidden'); }
$filters = [
    'module' => (string)($_GET['module'] ?? 'all'),
    'type' => (string)($_GET['type'] ?? 'all'),
    'status' => (string)($_GET['status'] ?? 'all'),
    'from' => (string)($_GET['from'] ?? date('Y-m-01')),
    'to' => (string)($_GET['to'] ?? date('Y-m-d')),
    'q' => (string)($_GET['q'] ?? ''),
    'sort' => (string)($_GET['sort'] ?? 'date'),
    'dir' => (string)($_GET['dir'] ?? 'desc'),
];
$invalidDates = ($filters['from'] !== '' && !sr_valid_day($filters['from'])) || ($filters['to'] !== '' && !sr_valid_day($filters['to'])) || ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']);
if ($invalidDates) {
    if (isset($_GET['export']) || isset($_GET['ajax'])) { http_response_code(422); exit('Invalid date range'); }
    $err = 'ช่วงวันที่ไม่ถูกต้อง ระบบแสดงช่วงเดือนปัจจุบัน กรุณาเลือกวันที่ใหม่';
    $filters['from'] = date('Y-m-01');
    $filters['to'] = date('Y-m-d');
}
if ($view === 'overview') { $filters['module'] = 'all'; $filters['type'] = 'all'; $filters['status'] = 'all'; $filters['q'] = ''; }

$allRows = sr_build_rows($d);
if (!$canAudit) $allRows = array_values(array_filter($allRows, static fn($row) => $row['type'] !== 'Audit Log'));
$periodFilters = $filters;
$periodFilters['module'] = 'all';
$periodFilters['status'] = 'all';
$periodFilters['q'] = '';
$periodFilters['type'] = 'all';
$periodRows = sr_filter_rows($allRows, $periodFilters);
$auditRows = array_values(array_filter($periodRows, fn($r) => $r['type'] === 'Audit Log'));
$moduleSummary = [];
foreach ($periodRows as $row) {
    $key = (string)$row['module'];
    if (!isset($moduleSummary[$key])) $moduleSummary[$key] = ['count' => 0, 'risk' => 0];
    $moduleSummary[$key]['count']++;
    if ((int)$row['risk'] > 0) $moduleSummary[$key]['risk']++;
}
uasort($moduleSummary, fn($a, $b) => $b['count'] <=> $a['count']);
$typeSummary = [];
foreach ($periodRows as $row) {
    if ($row['type'] === 'Audit Log') continue;
    $key = (string)$row['type'];
    $typeSummary[$key] = ($typeSummary[$key] ?? 0) + 1;
}
arsort($typeSummary);
$financial = sr_financial_summary($d, $filters['from'], $filters['to']);
$activeCount = count(array_filter($periodRows, fn($r) => in_array(strtolower((string)$r['status']), ['active', 'open', 'pending', 'scheduled', 'saved', 'unread'], true)));
$riskCount = count(array_filter($periodRows, fn($r) => (int)$r['risk'] > 0));
$recordRows = array_values(array_filter($allRows, fn($r) => $r['type'] !== 'Audit Log'));
$auditAllRows = array_values(array_filter($allRows, fn($r) => $r['type'] === 'Audit Log'));
$listFilters = $filters;
if ($view === 'audit') { $listFilters['module'] = 'all'; $listFilters['type'] = 'all'; }
$exportRows = sr_filter_rows($view === 'audit' ? $auditAllRows : $recordRows, $listFilters);
$pageSize = in_array((int)($_GET['page_size'] ?? 20), [20, 50, 100], true) ? (int)($_GET['page_size'] ?? 20) : 20;
$filters['page_size'] = $pageSize;
$offset = max(0, (int)($_GET['offset'] ?? 0));
$offset = min($offset, max(0, count($exportRows) - 1));
$printView = (string)($_GET['print'] ?? '') === '1';
if ($printView) { $offset = 0; $pageSize = 2000; }
$rows = array_slice($exportRows, $offset, $pageSize);
$modules = array_values(array_unique(array_map(fn($r) => $r['module'], $allRows)));
sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
$types = array_values(array_unique(array_column($recordRows, 'type')));
sort($types, SORT_NATURAL | SORT_FLAG_CASE);
$undatedCount = count(array_filter($recordRows, static fn($row) => $row['day'] === ''));
$coverage = [];
foreach ($recordRows as $row) {
    $key = $row['type'];
    if (!isset($coverage[$key])) $coverage[$key] = ['module' => $row['module'], 'total' => 0, 'period' => 0, 'undated' => 0, 'risk' => 0];
    $coverage[$key]['total']++;
    if ($row['day'] === '') $coverage[$key]['undated']++;
}
foreach ($periodRows as $row) {
    if ($row['type'] === 'Audit Log') continue;
    $coverage[$row['type']]['period']++;
    $coverage[$row['type']]['risk'] += (int)$row['risk'] > 0 ? 1 : 0;
}
ksort($coverage, SORT_NATURAL | SORT_FLAG_CASE);
$branchName = function_exists('branch_current') ? (string)(branch_current($d)['name'] ?? 'MR BAR') : 'MR BAR';

if (($_GET['export'] ?? '') === 'csv') {
    if (!$canExport) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="system-report-'.date('Ymd-His').'.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Module', 'Type', 'Date', 'Title', 'Status', 'Amount', 'Count', 'Count Unit', 'Ref', 'Detail'], ',', '"', '');
    foreach ($exportRows as $row) fputcsv($f, array_map('sr_csv_cell', [$row['module'], $row['type'], $row['date'], $row['title'], $row['status'], $row['amount'], $row['count'], $row['count_unit'], $row['ref'], $row['detail']]), ',', '"', '');
    fclose($f);
    exit;
}

if (($_GET['ajax'] ?? '') === 'rows') {
    $safeRows = array_map(static function ($row) {
        unset($row['_search'], $row['day']);
        return $row;
    }, $rows);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo json_encode(['rows' => $safeRows, 'offset' => $offset, 'total' => count($exportRows), 'has_more' => ($offset + count($rows)) < count($exportRows)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$sortLink = function (string $field, string $label) use ($filters, $view) {
    $q = $filters;
    $q['view'] = $view;
    $q['sort'] = $field;
    $q['dir'] = ($filters['sort'] === $field && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    return '<a href="?'.h(http_build_query($q)).'">'.h($label).($filters['sort'] === $field ? ($filters['dir'] === 'asc' ? ' ↑' : ' ↓') : '').'</a>';
};

$exportUrl = '?'.http_build_query(array_merge($filters, ['view' => $view, 'export' => 'csv']));
$printUrl = '?'.http_build_query(array_merge($filters, ['view' => $view === 'overview' ? 'records' : $view, 'print' => '1']));
$viewUrl = function (string $target) use ($filters): string {
    $params = [
        'view' => $target,
        'from' => $filters['from'],
        'to' => $filters['to'],
        'module' => 'all',
        'status' => 'all',
        'q' => '',
        'sort' => $filters['sort'],
        'dir' => $filters['dir'],
    ];
    return '?'.h(http_build_query($params));
};
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>System Report Center</title>
  <link rel="stylesheet" href="assets/admin.css?v=1480">
  <link rel="stylesheet" href="assets/admin-v14.css?v=1480">
  <link rel="stylesheet" href="assets/system-reports-v1480.css?v=1480">
  <link rel="stylesheet" href="assets/system-reports-theme-v14870.css?v=14870">
  <link rel="stylesheet" href="assets/system-reports-dashboard-v14872.css?v=14872">
  <link rel="stylesheet" href="assets/report-tools-v14920.css?v=14920">
</head>
<body class="admin-v14-page">
<?php require_once __DIR__.'/app/admin-nav.php'; echo admin_sidebar('reports', $u); ?>
<main id="srReport" class="sr-shell sr-dashboard-page">
  <header class="sr-hero">
    <div>
      <small>MR BAR / MANAGEMENT REPORTS · <?=h($branchName)?></small>
      <h1>ศูนย์รายงาน</h1>
      <p>ภาพรวมการดำเนินงานและตัวเลขทางการเงิน แยกตามแหล่งข้อมูล พร้อมรายละเอียดและ Audit Log</p>
    </div>
    <nav>
      <a href="pos-reports.php">POS Reports</a>
      <a href="payroll-attendance.php">Payroll</a>
      <a href="workforce-schedule.php">Schedule</a>
    </nav>
  </header>

  <?php if ($msg): ?><div class="sr-notice ok"><?=h($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="sr-notice err"><?=h($err)?></div><?php endif; ?>

  <nav class="sr-view-tabs" aria-label="มุมมองรายงาน">
    <a class="<?=$view==='overview'?'active':''?>" href="<?=$viewUrl('overview')?>"><span>ภาพรวม</span><small>Dashboard</small></a>
    <a class="<?=$view==='records'?'active':''?>" href="<?=$viewUrl('records')?>"><span>รายการข้อมูล</span><small>Records</small></a>
    <?php if ($canAudit): ?><a class="<?=$view==='audit'?'active':''?>" href="<?=$viewUrl('audit')?>"><span>Audit Log</span><small><?=number_format(count($auditRows))?> events</small></a><?php endif; ?>
  </nav>

  <section class="sr-filters">
    <nav class="sr-period-tools" aria-label="ช่วงวันที่">
      <?php foreach (['วันนี้' => [date('Y-m-d'), date('Y-m-d')], '7 วัน' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')], 'เดือนนี้' => [date('Y-m-01'), date('Y-m-d')], 'เดือนก่อน' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))], 'ทั้งหมด' => ['', '']] as $label => $range): ?>
      <a href="?<?=h(http_build_query(array_merge($filters, ['view' => $view, 'from' => $range[0], 'to' => $range[1]])))?>"><?=h($label)?></a>
      <?php endforeach; ?>
    </nav>
    <form method="get" class="sr-filter-form">
      <input type="hidden" name="view" value="<?=h($view)?>">
      <label class="sr-date-filter">ตั้งแต่ <input type="date" name="from" value="<?=h($filters['from'])?>"></label>
      <label class="sr-date-filter">ถึง <input type="date" name="to" value="<?=h($filters['to'])?>"></label>
      <?php if ($view !== 'overview'): ?>
      <label class="sr-module-filter">Module
        <select name="module">
          <option value="all">ทั้งหมด</option>
          <?php foreach ($modules as $module): ?><option value="<?=h($module)?>" <?=($view==='audit'?'System':$filters['module']) === $module ? 'selected' : ''?>><?=h($module)?></option><?php endforeach; ?>
        </select>
      </label>
      <?php if ($view !== 'audit'): ?><label>ประเภทรายงาน<select name="type"><option value="all">ทั้งหมด</option><?php foreach ($types as $type): ?><option value="<?=h($type)?>" <?=$filters['type'] === $type ? 'selected' : ''?>><?=h($type)?></option><?php endforeach; ?></select></label><?php endif; ?>
      <label class="sr-status-filter">สถานะ
        <select name="status">
          <option value="all" <?=$filters['status'] === 'all' ? 'selected' : ''?>>ทั้งหมด</option>
          <option value="active" <?=$filters['status'] === 'active' ? 'selected' : ''?>>Active / Pending</option>
          <option value="closed" <?=$filters['status'] === 'closed' ? 'selected' : ''?>>Closed / Complete</option>
          <option value="risk" <?=$filters['status'] === 'risk' ? 'selected' : ''?>>Need Attention</option>
          <option value="void" <?=$filters['status'] === 'void' ? 'selected' : ''?>>Void</option>
          <option value="cleared" <?=$filters['status'] === 'cleared' ? 'selected' : ''?>>Cleared</option>
        </select>
      </label>
      <label class="sr-wide">ค้นหา <input name="q" value="<?=h($filters['q'])?>" placeholder="ชื่อ, เบอร์, เลขบิล, สถานะ หรือ Audit action"></label>
      <label>รายการต่อชุด<select name="page_size"><?php foreach ([20, 50, 100] as $size): ?><option value="<?=$size?>" <?=$filters['page_size'] === $size ? 'selected' : ''?>><?=$size?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <input type="hidden" name="sort" value="<?=h($filters['sort'])?>">
      <input type="hidden" name="dir" value="<?=h($filters['dir'])?>">
      <button>ใช้ตัวกรอง</button>
      <a class="sr-reset" href="?view=<?=h($view)?>">ล้างตัวกรอง</a>
      <?php if ($canExport): ?><a class="sr-export" href="<?=h($exportUrl)?>">Export CSV</a><?php endif; ?>
      <a class="sr-reset" href="<?=h($printUrl)?>" target="_blank" rel="noopener">พิมพ์ / PDF</a>
    </form>
  </section>
  <p class="sr-report-context"><?=h($branchName)?> · <?=h($filters['from'] ?: 'ไม่จำกัดวันเริ่ม')?> – <?=h($filters['to'] ?: 'ไม่จำกัดวันสิ้นสุด')?> · อัปเดต <?=date('d/m/Y H:i')?><?php if ($undatedCount > 0): ?> · ข้อมูลไม่ระบุวันที่ <?=number_format($undatedCount)?> รายการ (ดูได้เมื่อเลือกทั้งหมด)<?php endif; ?></p>
  <?php if ($printView): ?><div class="sr-print-actions"><button type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button><?php if (count($exportRows) > 2000): ?><p>แสดง 2,000 รายการแรก กรุณาส่งออก CSV สำหรับข้อมูลทั้งหมด</p><?php endif; ?></div><?php endif; ?>

  <?php if ($view === 'overview'): ?>
  <section class="sr-overview-kpis">
    <article><span>รายการในช่วงนี้</span><b><?=number_format(count($periodRows))?></b><small><?=h($filters['from'])?> – <?=h($filters['to'])?></small></article>
    <article><span>กำลังดำเนินการ</span><b><?=number_format($activeCount)?></b><small>Active / Pending</small></article>
    <article class="attention"><span>ต้องติดตาม</span><b><?=number_format($riskCount)?></b><small>เปิดค้าง / รออนุมัติ</small></article>
    <article><span>Audit events</span><b><?=number_format(count($auditRows))?></b><small>บันทึกระบบในช่วงนี้</small></article>
  </section>

  <section class="sr-finance">
    <div class="sr-section-title"><div><small>FINANCIAL SNAPSHOT</small><h2>สรุปตัวเลขการเงินแยกตามแหล่ง</h2></div><span>ไม่รวมยอดข้ามประเภท</span></div>
    <div class="sr-finance-grid">
      <article><small>ยอดขาย POS เมนู</small><b><?=number_format($financial['menu_sales']['amount'],2)?></b><span><?=number_format($financial['menu_sales']['count'])?> batches</span></article>
      <article><small>ยอดขาย Report บิล</small><b><?=number_format($financial['bill_sales']['amount'],2)?></b><span><?=number_format($financial['bill_sales']['count'])?> batches</span></article>
      <article><small>ยอดในข้อมูล Sales Table</small><b><?=$financial['sales_table']['amount_known'] > 0 ? number_format($financial['sales_table']['amount'],2) : '—'?></b><span><?=number_format($financial['sales_table']['count'])?> รอบ · มียอดระบุ <?=number_format($financial['sales_table']['amount_known'])?> รอบ</span></article>
      <article><small>งานสำเร็จใน Snapshot ปิดรอบ</small><b><?=number_format($financial['daily_close']['completed'])?></b><span><?=number_format($financial['daily_close']['count'])?> วัน · ยกเลิก <?=number_format($financial['daily_close']['cancelled'])?> งาน</span></article>
      <article class="outflow"><small>ค่าดื่มที่บันทึกรอบแล้ว</small><b><?=number_format($financial['drink_payout']['amount'],2)?></b><span><?=number_format($financial['drink_payout']['count'])?> รอบ</span></article>
      <article class="outflow"><small>ค่าคอมที่บันทึกรอบแล้ว</small><b><?=number_format($financial['commission_payout']['amount'],2)?></b><span><?=number_format($financial['commission_payout']['count'])?> รอบ</span></article>
      <article><small>มัดจำรับรองแล้ว</small><b><?=number_format($financial['deposit_verified']['amount'],2)?></b><span><?=number_format($financial['deposit_verified']['count'])?> รายการ · ตามวันจอง</span></article>
      <article><small>มัดจำรอตรวจสลิป</small><b><?=number_format($financial['deposit_pending']['amount'],2)?></b><span><?=number_format($financial['deposit_pending']['count'])?> รายการ · ยังไม่ใช่ยอดรับรอง</span></article>
    </div>
    <p class="sr-finance-note">ยอดจากแต่ละแหล่งไม่นำมาบวกรวมกัน · POS และค่าตอบแทนรวมเต็มรอบที่คาบเกี่ยววันที่เลือก ไม่เฉลี่ยเป็นยอดรายวัน · รอบที่บันทึกแล้วไม่ใช่หลักฐานการโอนจ่าย</p>
  </section>

  <section class="sr-report-grid">
    <article class="sr-panel sr-module-report">
      <div class="sr-panel-head"><div><small>ACTIVITY BY MODULE</small><h2>กิจกรรมแยกตามหมวด</h2><p>จำนวนรายการในช่วงวันที่เลือก</p></div><span><?=number_format(count($moduleSummary))?> modules</span></div>
      <div class="sr-module-bars">
        <?php $maxModuleCount=max(1,...array_values(array_map(fn($v)=>(int)$v['count'],$moduleSummary?:[['count'=>0]]))); foreach($moduleSummary as $module=>$summary):?>
        <div class="sr-module-bar"><div><b><?=h($module)?></b><span><?=number_format($summary['count'])?> รายการ<?=!empty($summary['risk'])?' · ติดตาม '.number_format($summary['risk']):''?></span></div><i><span style="width:<?=max(2,(int)round($summary['count']/$maxModuleCount*100))?>%"></span></i></div>
        <?php endforeach; if(!$moduleSummary):?><p class="sr-empty">ไม่มีข้อมูลในช่วงวันที่เลือก</p><?php endif;?>
      </div>
    </article>
    <article class="sr-panel sr-type-report">
      <div class="sr-panel-head"><div><small>RECORD MIX</small><h2>ประเภทข้อมูลที่พบ</h2><p>เรียงตามจำนวนรายการ</p></div><a href="<?=$viewUrl('records')?>">ดูรายละเอียด →</a></div>
      <div class="sr-type-list"><?php $topTypes=array_slice($typeSummary,0,8,true);$maxType=max(1,...array_values($topTypes?:[0]));foreach($topTypes as $type=>$count):?><div><span><?=h($type)?></span><b><?=number_format($count)?></b><i><span style="width:<?=max(2,(int)round($count/$maxType*100))?>%"></span></i></div><?php endforeach;if(!$topTypes):?><p class="sr-empty">ไม่มีข้อมูลในช่วงวันที่เลือก</p><?php endif;?></div>
    </article>
  </section>

  <section class="sr-coverage">
    <div class="sr-section-title"><div><small>DATA COVERAGE</small><h2>แหล่งข้อมูลในรายงาน</h2></div></div>
    <div class="sr-table-wrap"><table class="sr-table"><thead><tr><th>หมวด / ประเภท</th><th>ในช่วงที่เลือก</th><th>ต้องติดตาม</th><th>ไม่ระบุวันที่</th><th>ข้อมูลทั้งหมด</th></tr></thead><tbody>
      <?php foreach ($coverage as $type => $source): ?><tr><td><a href="?<?=h(http_build_query(['view' => 'records', 'type' => $type, 'from' => $filters['from'], 'to' => $filters['to']]))?>"><?=h($source['module'].' / '.$type)?></a></td><td><?=number_format($source['period'])?></td><td><?=number_format($source['risk'])?></td><td><?=number_format($source['undated'])?></td><td><?=number_format($source['total'])?></td></tr><?php endforeach; ?>
      <?php if (!$coverage): ?><tr><td colspan="5">ยังไม่มีข้อมูลรายงาน</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>

  <?php if ($canAudit): ?><section class="sr-panel sr-recent-audit">
    <div class="sr-panel-head"><div><small>RECENT SYSTEM ACTIVITY</small><h2>Audit Log ล่าสุด</h2><p>คงบันทึกตรวจสอบไว้แยกจากรายงานการดำเนินงาน</p></div><a href="<?=$viewUrl('audit')?>">เปิด Audit Log ทั้งหมด →</a></div>
    <?php $recentAudit=$auditRows;usort($recentAudit,fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));$recentAudit=array_slice($recentAudit,0,6);?>
    <div class="sr-audit-list"><?php if(!$recentAudit):?><p class="sr-empty">ไม่มี Audit Log ในช่วงวันที่เลือก</p><?php endif;foreach($recentAudit as $audit):?><article><time><?=h((string)($audit['date']?:'-'))?></time><b><?=h((string)$audit['title'])?></b><span><?=h((string)$audit['detail'])?></span></article><?php endforeach;?></div>
  </section>
  <?php endif; ?>
  <?php else: ?>
  <section class="sr-panel sr-data-panel">
    <div class="sr-panel-head"><div><small><?=$view==='audit'?'SYSTEM AUDIT':'REPORT DETAILS'?></small><h2><?=$view==='audit'?'Audit Log':'รายการข้อมูลรายงาน'?></h2><p><?=$view==='audit'?'ประวัติการกระทำในระบบที่ตรวจสอบย้อนหลังได้':'ค้นหา กรอง และเรียงรายการจากข้อมูลแต่ละโมดูล'?></p></div><span><b data-row-count><?=number_format(min(count($exportRows),$offset+count($rows)))?></b> / <?=number_format(count($exportRows))?> รายการ</span></div>
    <div class="sr-table-wrap">
      <table class="sr-table">
        <thead><tr>
          <th><?=$sortLink('module','Module')?></th><th><?=$sortLink('type','Type')?></th><th><?=$sortLink('date','Date')?></th><th><?=$sortLink('title','Title')?></th><th><?=$sortLink('status','Status')?></th><th><?=$sortLink('amount','Amount')?></th><th><?=$sortLink('count','Count')?></th><th><?=$sortLink('ref','Ref')?></th><th>Detail</th>
        </tr></thead>
        <tbody id="srRows" data-total="<?=count($exportRows)?>" data-offset="<?=$offset+count($rows)?>" data-page-size="<?=$filters['page_size']?>" data-view="<?=h($view)?>">
          <?php if (!$rows): ?><tr><td colspan="9" class="sr-empty">ไม่พบข้อมูลตามตัวกรอง</td></tr><?php endif; ?>
          <?php foreach ($rows as $row) echo sr_report_row_html($row); ?>
        </tbody>
      </table>
    </div>
    <div class="sr-load-more-wrap"><button type="button" class="sr-load-more" id="srLoadMore" <?=$printView || $offset+count($rows)>=count($exportRows)?'hidden':''?>>โหลดเพิ่มอีก <?=$filters['page_size']?> รายการ</button><span id="sr-load-state" role="status" aria-live="polite"></span></div>
  </section>
  <?php endif; ?>

  <?php if ($isSuper): ?>
  <section class="sr-super">
    <details>
      <summary>Advanced</summary>
      <div class="sr-danger">
        <div>
          <small>SUPER ADMIN ONLY</small>
          <h2>Hidden Clear Report Tool</h2>
          <p>เป็น Soft Clear: ไม่ลบข้อมูลหลักของลูกค้า/พนักงาน/ตารางงาน แต่จะเคลียร์ cache หรือ mark report artifacts ตาม scope ที่เลือก พร้อมบันทึก Audit Log</p>
        </div>
        <form method="post" data-clear-report-form>
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="clear_reports">
          <label>Scope
            <select name="scope" required>
              <option value="report_cache">Report cache only</option>
              <option value="pos_unlocked_reports">POS reports ที่ยังไม่ lock payout</option>
              <option value="all_report_artifacts">Cache + POS unlocked reports</option>
            </select>
          </label>
          <label>Reason <textarea name="reason" required minlength="10" placeholder="ระบุเหตุผลอย่างน้อย 10 ตัวอักษร"></textarea></label>
          <label>ชั้นที่ 1 <input name="confirm_scope" required placeholder="พิมพ์ REPORT-CLEAR"></label>
          <label>ชั้นที่ 2 <input name="confirm_phrase" required placeholder="พิมพ์ CLEAR REPORT"></label>
          <label>ชั้นที่ 3 <input name="confirm_user" required placeholder="พิมพ์ชื่อบัญชี: <?=h(sr_text($u['display_name'] ?? $u['username'] ?? ''))?>"></label>
          <label class="sr-check"><input type="checkbox" name="understand_1" value="1" required> เข้าใจว่าเป็นเครื่องมือระดับสูง</label>
          <label class="sr-check"><input type="checkbox" name="understand_2" value="1" required> เข้าใจว่าจะมีผลกับ Report artifacts ตาม scope</label>
          <label class="sr-check"><input type="checkbox" name="understand_3" value="1" required> ตรวจสอบ branch และช่วงข้อมูลแล้ว</label>
          <button class="sr-danger-button">Soft Clear Report</button>
        </form>
      </div>
    </details>
  </section>
  <?php endif; ?>
</main>
<script src="assets/system-reports-v1480.js?v=1480" defer></script>
<script src="assets/system-reports-dashboard-v14872.js?v=14920" defer></script>
</body>
</html>
