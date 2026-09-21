<?php
require __DIR__.'/app/bootstrap.php';

$u = require_permission('reports.view');
$uid = (int)($u['id'] ?? 0);
$isSuper = user_is_super_admin($u);
$canExport = $isSuper || user_can($u, 'reports.export');
$d = db_load();
$msg = '';
$err = '';

function sr_text($value): string {
    if (is_array($value)) return trim(implode(' ', array_map('sr_text', $value)));
    if (is_bool($value)) return $value ? 'yes' : 'no';
    return trim((string)$value);
}

function sr_first(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && sr_text($row[$key]) !== '') return sr_text($row[$key]);
    }
    return $default;
}

function sr_num($value): float {
    if (is_numeric($value)) return (float)$value;
    return 0.0;
}

function sr_date($value): string {
    $text = sr_text($value);
    if ($text === '') return '';
    $ts = strtotime($text);
    return $ts ? date('Y-m-d H:i', $ts) : $text;
}

function sr_day($value): string {
    $text = sr_date($value);
    return $text !== '' ? substr($text, 0, 10) : '';
}

function sr_lookup_name(array $d, $id): string {
    $id = (int)$id;
    if ($id <= 0) return '';
    foreach (['users', 'employees', 'prs', 'customers'] as $bucket) {
        foreach ($d[$bucket] ?? [] as $row) {
            if ((int)($row['id'] ?? 0) !== $id) continue;
            return sr_first($row, ['display_name', 'name', 'full_name', 'nickname', 'code'], '#'.$id);
        }
    }
    return '#'.$id;
}

function sr_add(array &$rows, string $module, string $type, string $date, string $title, string $status, float $amount, int $count, string $ref, string $detail, int $risk = 0): void {
    $search = strtolower($module.' '.$type.' '.$date.' '.$title.' '.$status.' '.$amount.' '.$count.' '.$ref.' '.$detail);
    $rows[] = [
        'module' => $module,
        'type' => $type,
        'date' => $date,
        'day' => sr_day($date),
        'title' => $title,
        'status' => $status !== '' ? $status : '-',
        'amount' => $amount,
        'count' => $count,
        'ref' => $ref,
        'detail' => $detail,
        'risk' => $risk,
        '_search' => $search,
    ];
}

function sr_build_rows(array $d): array {
    $rows = [];

    foreach ($d['reservations'] ?? [] as $r) {
        $date = sr_first($r, ['reserved_at', 'date', 'created_at']);
        $time = sr_first($r, ['time', 'start_time']);
        $guest = sr_first($r, ['guest_name', 'customer_name', 'name'], 'Reservation');
        sr_add($rows, 'Customer', 'Reservation', trim($date.' '.$time), $guest, sr_first($r, ['status'], 'active'), sr_num($r['deposit'] ?? 0), (int)($r['party_size'] ?? $r['pax'] ?? 0), '#'.(int)($r['id'] ?? 0), sr_first($r, ['phone', 'note', 'source']));
    }

    foreach ($d['customers'] ?? [] as $c) {
        $name = sr_first($c, ['name', 'display_name', 'full_name'], 'Customer');
        $status = sr_first($c, ['status', 'tier', 'level'], 'active');
        sr_add($rows, 'Customer', 'CRM Member', sr_first($c, ['updated_at', 'created_at', 'last_visit']), $name, $status, sr_num($c['lifetime_value'] ?? $c['total_spend'] ?? 0), (int)($c['visit_count'] ?? $c['bookings'] ?? 0), '#'.(int)($c['id'] ?? 0), sr_first($c, ['phone', 'line_id', 'note']));
    }

    foreach ($d['checkins'] ?? [] as $c) {
        $title = sr_first($c, ['ticket', 'guest_name', 'customer_name'], 'Check-in');
        $pr = sr_lookup_name($d, $c['pr_id'] ?? 0);
        sr_add($rows, 'Operations', 'Check-in Job', sr_first($c, ['created_at', 'updated_at']), $title, sr_first($c, ['status'], 'active'), sr_num($c['amount'] ?? $c['total'] ?? 0), 1, '#'.(int)($c['id'] ?? 0), trim('PR '.$pr.' '.sr_first($c, ['note', 'remark'])));
    }

    foreach ($d['service_calls'] ?? [] as $s) {
        $open = empty($s['resolved_at']);
        sr_add($rows, 'Operations', 'Service Call', sr_first($s, ['created_at', 'resolved_at']), sr_first($s, ['message', 'type', 'table_code'], 'Service Call'), $open ? 'open' : 'resolved', 0, 1, '#'.(int)($s['id'] ?? 0), sr_first($s, ['note', 'resolved_at']), $open ? 1 : 0);
    }

    foreach ($d['tables'] ?? [] as $t) {
        sr_add($rows, 'Operations', 'Table Directory', sr_first($t, ['updated_at', 'created_at']), sr_first($t, ['code', 'name'], 'Table'), sr_first($t, ['status'], 'active'), 0, (int)($t['capacity'] ?? 0), '#'.(int)($t['id'] ?? 0), sr_first($t, ['zone', 'note']));
    }

    foreach ($d['sales_table_sessions'] ?? [] as $s) {
        $amount = sr_num($s['total_sales'] ?? $s['bill_total'] ?? $s['amount'] ?? 0);
        $owner = sr_lookup_name($d, $s['sales_id'] ?? $s['user_id'] ?? 0);
        sr_add($rows, 'Sales', 'Table Session', sr_first($s, ['opened_at', 'created_at', 'closed_at']), sr_first($s, ['table_code', 'table_name'], 'Sales Table'), sr_first($s, ['status'], 'active'), $amount, (int)($s['guest_count'] ?? $s['pax'] ?? 0), '#'.(int)($s['id'] ?? 0), trim('Sales '.$owner.' '.sr_first($s, ['bill_no', 'note'])));
    }

    foreach ($d['prs'] ?? [] as $p) {
        sr_add($rows, 'People', 'PR Profile', sr_first($p, ['updated_at', 'created_at']), sr_first($p, ['code', 'name'], 'PR'), sr_first($p, ['status'], !empty($p['active']) ? 'active' : 'inactive'), 0, 1, '#'.(int)($p['id'] ?? 0), sr_first($p, ['name', 'role', 'phone']));
    }

    foreach ($d['employees'] ?? [] as $e) {
        sr_add($rows, 'People', 'Employee', sr_first($e, ['updated_at', 'created_at', 'start_date']), sr_first($e, ['code', 'name', 'display_name'], 'Employee'), sr_first($e, ['status'], !empty($e['active']) ? 'active' : 'inactive'), sr_num($e['salary'] ?? 0), 1, '#'.(int)($e['id'] ?? 0), sr_first($e, ['position', 'department', 'phone']));
    }

    foreach ($d['attendance'] ?? [] as $a) {
        $name = sr_lookup_name($d, $a['employee_id'] ?? $a['user_id'] ?? $a['pr_id'] ?? 0);
        $status = empty($a['check_out']) ? 'open' : sr_first($a, ['status'], 'closed');
        sr_add($rows, 'Workforce', 'Attendance', sr_first($a, ['check_in', 'created_at', 'date']), $name !== '' ? $name : 'Attendance', $status, 0, 1, '#'.(int)($a['id'] ?? 0), sr_first($a, ['check_out', 'source', 'note']), $status === 'open' ? 1 : 0);
    }

    foreach ($d['shifts'] ?? [] as $s) {
        $name = sr_lookup_name($d, $s['employee_id'] ?? $s['user_id'] ?? $s['pr_id'] ?? 0);
        sr_add($rows, 'Workforce', 'Shift', sr_first($s, ['date', 'start_at', 'created_at']), $name !== '' ? $name : 'Shift', sr_first($s, ['status'], 'scheduled'), 0, 1, '#'.(int)($s['id'] ?? 0), trim(sr_first($s, ['start_time', 'start_at']).' - '.sr_first($s, ['end_time', 'end_at']).' '.sr_first($s, ['role', 'note'])));
    }

    foreach ($d['leave_requests'] ?? [] as $l) {
        $name = sr_lookup_name($d, $l['employee_id'] ?? $l['user_id'] ?? 0);
        $status = sr_first($l, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Leave', sr_first($l, ['start_date', 'date', 'created_at']), $name !== '' ? $name : 'Leave Request', $status, 0, 1, '#'.(int)($l['id'] ?? 0), trim(sr_first($l, ['type', 'leave_type']).' '.sr_first($l, ['end_date', 'reason'])), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['time_correction_requests'] ?? [] as $r) {
        $name = sr_lookup_name($d, $r['employee_id'] ?? $r['user_id'] ?? 0);
        $status = sr_first($r, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Time Correction', sr_first($r, ['date', 'created_at']), $name !== '' ? $name : 'Time Correction', $status, 0, 1, '#'.(int)($r['id'] ?? 0), sr_first($r, ['reason', 'note']), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['substitute_requests'] ?? [] as $r) {
        $name = sr_lookup_name($d, $r['employee_id'] ?? $r['user_id'] ?? 0);
        $status = sr_first($r, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Substitute', sr_first($r, ['date', 'created_at']), $name !== '' ? $name : 'Substitute Request', $status, 0, 1, '#'.(int)($r['id'] ?? 0), sr_first($r, ['reason', 'note']), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['daily_closes'] ?? [] as $c) {
        sr_add($rows, 'Reports', 'Daily Close', sr_first($c, ['date', 'closed_at', 'created_at']), 'Daily Close', sr_first($c, ['status'], 'closed'), sr_num($c['total'] ?? $c['cash_total'] ?? 0), 1, '#'.(int)($c['id'] ?? 0), sr_first($c, ['note', 'closed_at']));
    }

    foreach ($d['pos_import_batches'] ?? [] as $b) {
        sr_add($rows, 'POS', 'POS Import', sr_first($b, ['imported_at', 'period_start']), sr_first($b, ['source_name', 'filename'], 'POS Import'), sr_first($b, ['status'], 'active'), sr_num($b['total_sales'] ?? 0), (int)($b['rows_imported'] ?? 0), '#'.(int)($b['id'] ?? 0), trim(sr_first($b, ['period_start']).' - '.sr_first($b, ['period_end']).' '.sr_first($b, ['report_type'])));
    }

    foreach ($d['pos_bill_batches'] ?? [] as $b) {
        sr_add($rows, 'POS', 'Bill Matching', sr_first($b, ['imported_at', 'period_start']), sr_first($b, ['filename', 'source_name'], 'Bill Report'), sr_first($b, ['status'], 'active'), sr_num($b['total_sales'] ?? 0), (int)($b['bill_count'] ?? 0), '#'.(int)($b['id'] ?? 0), trim('matched '.(int)($b['matched_sessions'] ?? 0).' '.sr_first($b, ['period_start']).' - '.sr_first($b, ['period_end'])));
    }

    foreach (['drink_payout_rounds' => 'Drink Payout', 'commission_payout_rounds' => 'Commission Payout'] as $bucket => $label) {
        foreach ($d[$bucket] ?? [] as $r) {
            sr_add($rows, 'POS', $label, sr_first($r, ['saved_at', 'created_at', 'from']), $label.' Batch #'.(int)($r['batch_id'] ?? 0), sr_first($r, ['status'], 'saved'), sr_num($r['total'] ?? 0), count($r['rows'] ?? []), '#'.(int)($r['id'] ?? 0), trim(sr_first($r, ['from']).' - '.sr_first($r, ['to']).' '.sr_first($r, ['void_reason'])));
        }
    }

    foreach ($d['notifications'] ?? [] as $n) {
        $open = empty($n['read_at']);
        sr_add($rows, 'System', 'Notification', sr_first($n, ['created_at', 'read_at']), sr_first($n, ['message', 'title'], 'Notification'), $open ? 'unread' : 'read', 0, 1, '#'.(int)($n['id'] ?? 0), sr_first($n, ['role', 'type']), $open ? 1 : 0);
    }

    foreach ($d['audit'] ?? [] as $a) {
        sr_add($rows, 'System', 'Audit Log', sr_first($a, ['at', 'created_at']), sr_first($a, ['action'], 'audit'), sr_first($a, ['status'], 'logged'), 0, 1, '#'.(int)($a['id'] ?? 0), sr_text($a));
    }

    return $rows;
}

function sr_filter_rows(array $rows, array $filters): array {
    $module = (string)$filters['module'];
    $status = (string)$filters['status'];
    $from = (string)$filters['from'];
    $to = (string)$filters['to'];
    $q = strtolower(trim((string)$filters['q']));
    $rows = array_values(array_filter($rows, function ($row) use ($module, $status, $from, $to, $q) {
        if ($module !== 'all' && $row['module'] !== $module) return false;
        if ($status !== 'all') {
            $s = strtolower((string)$row['status']);
            if ($status === 'active' && !in_array($s, ['active', 'open', 'pending', 'scheduled', 'saved', 'unread', 'logged'], true)) return false;
            if ($status === 'closed' && !in_array($s, ['closed', 'resolved', 'read', 'completed', 'approved'], true)) return false;
            if ($status === 'risk' && (int)$row['risk'] <= 0) return false;
            if (!in_array($status, ['active', 'closed', 'risk'], true) && $s !== $status) return false;
        }
        if ($from !== '' && $row['day'] !== '' && $row['day'] < $from) return false;
        if ($to !== '' && $row['day'] !== '' && $row['day'] > $to) return false;
        if ($q !== '' && strpos($row['_search'], $q) === false) return false;
        return true;
    }));

    $sort = in_array((string)$filters['sort'], ['module', 'type', 'date', 'title', 'status', 'amount', 'count', 'ref', 'risk'], true) ? (string)$filters['sort'] : 'date';
    $dir = (string)$filters['dir'] === 'asc' ? 1 : -1;
    usort($rows, function ($a, $b) use ($sort, $dir) {
        $av = $a[$sort] ?? '';
        $bv = $b[$sort] ?? '';
        if (in_array($sort, ['amount', 'count', 'risk'], true)) $cmp = ((float)$av <=> (float)$bv);
        else $cmp = strnatcasecmp((string)$av, (string)$bv);
        if ($cmp === 0) $cmp = strnatcasecmp((string)$a['date'], (string)$b['date']);
        return $cmp * $dir;
    });
    return $rows;
}

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

$filters = [
    'module' => (string)($_GET['module'] ?? 'all'),
    'status' => (string)($_GET['status'] ?? 'all'),
    'from' => (string)($_GET['from'] ?? date('Y-m-01')),
    'to' => (string)($_GET['to'] ?? date('Y-m-d')),
    'q' => (string)($_GET['q'] ?? ''),
    'sort' => (string)($_GET['sort'] ?? 'date'),
    'dir' => (string)($_GET['dir'] ?? 'desc'),
    'limit' => max(25, min(1000, (int)($_GET['limit'] ?? 250))),
];

$allRows = sr_build_rows($d);
$rows = sr_filter_rows($allRows, $filters);
$exportRows = $rows;
$rows = array_slice($rows, 0, (int)$filters['limit']);
$modules = array_values(array_unique(array_map(fn($r) => $r['module'], $allRows)));
sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
$activeCount = count(array_filter($allRows, fn($r) => in_array(strtolower((string)$r['status']), ['active', 'open', 'pending', 'scheduled', 'saved', 'unread'], true)));
$riskCount = count(array_filter($allRows, fn($r) => (int)$r['risk'] > 0));
$totalAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $exportRows));
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
    fputcsv($f, ['Module', 'Type', 'Date', 'Title', 'Status', 'Amount', 'Count', 'Ref', 'Detail']);
    foreach ($exportRows as $row) fputcsv($f, [$row['module'], $row['type'], $row['date'], $row['title'], $row['status'], $row['amount'], $row['count'], $row['ref'], $row['detail']]);
    fclose($f);
    exit;
}

$sortLink = function (string $field, string $label) use ($filters) {
    $q = $filters;
    $q['sort'] = $field;
    $q['dir'] = ($filters['sort'] === $field && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    return '<a href="?'.h(http_build_query($q)).'">'.h($label).($filters['sort'] === $field ? ($filters['dir'] === 'asc' ? ' ↑' : ' ↓') : '').'</a>';
};

$exportUrl = '?'.http_build_query(array_merge($filters, ['export' => 'csv']));
?><!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>System Report Center</title>
  <link rel="stylesheet" href="assets/admin.css?v=1480">
  <link rel="stylesheet" href="assets/admin-v14.css?v=1480">
  <link rel="stylesheet" href="assets/system-reports-v1480.css?v=1480">
</head>
<body class="admin-v14-page">
<?php require_once __DIR__.'/app/admin-nav.php'; echo admin_sidebar('reports', $u); ?>
<main class="sr-shell">
  <header class="sr-hero">
    <div>
      <small>REPORT CENTER / <?=h($branchName)?></small>
      <h1>รายงานรวมทุกฟังก์ชัน</h1>
      <p>รวมข้อมูลจาก Operations, Workforce, Customer CRM, POS, Sales และ System Audit พร้อม Filter, Search, Sort และ Export</p>
    </div>
    <nav>
      <a href="pos-reports.php">POS Reports</a>
      <a href="payroll-attendance.php">Payroll</a>
      <a href="workforce-schedule.php">Schedule</a>
    </nav>
  </header>

  <?php if ($msg): ?><div class="sr-notice ok"><?=h($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="sr-notice err"><?=h($err)?></div><?php endif; ?>

  <section class="sr-kpis">
    <article><span>Total Records</span><b><?=number_format(count($allRows))?></b><small>ก่อนกรอง</small></article>
    <article><span>Filtered</span><b><?=number_format(count($exportRows))?></b><small>แสดง <?=number_format(count($rows))?> แถว</small></article>
    <article><span>Active / Pending</span><b><?=number_format($activeCount)?></b><small>งานที่ยังมีผล</small></article>
    <article><span>Need Attention</span><b><?=number_format($riskCount)?></b><small>เปิดค้าง / รออนุมัติ</small></article>
    <article><span>Amount</span><b><?=number_format($totalAmount, 2)?></b><small>ตามผลกรอง</small></article>
  </section>

  <section class="sr-filters">
    <form method="get">
      <label>Module
        <select name="module">
          <option value="all">ทั้งหมด</option>
          <?php foreach ($modules as $module): ?><option value="<?=h($module)?>" <?=$filters['module'] === $module ? 'selected' : ''?>><?=h($module)?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <option value="all" <?=$filters['status'] === 'all' ? 'selected' : ''?>>ทั้งหมด</option>
          <option value="active" <?=$filters['status'] === 'active' ? 'selected' : ''?>>Active / Pending</option>
          <option value="closed" <?=$filters['status'] === 'closed' ? 'selected' : ''?>>Closed / Complete</option>
          <option value="risk" <?=$filters['status'] === 'risk' ? 'selected' : ''?>>Need Attention</option>
          <option value="void" <?=$filters['status'] === 'void' ? 'selected' : ''?>>Void</option>
          <option value="cleared" <?=$filters['status'] === 'cleared' ? 'selected' : ''?>>Cleared</option>
        </select>
      </label>
      <label>From <input type="date" name="from" value="<?=h($filters['from'])?>"></label>
      <label>To <input type="date" name="to" value="<?=h($filters['to'])?>"></label>
      <label>Limit
        <select name="limit">
          <?php foreach ([50, 100, 250, 500, 1000] as $limit): ?><option value="<?=$limit?>" <?=((int)$filters['limit'] === $limit) ? 'selected' : ''?>><?=$limit?> rows</option><?php endforeach; ?>
        </select>
      </label>
      <label class="sr-wide">Search <input name="q" value="<?=h($filters['q'])?>" placeholder="ชื่อ, เบอร์, เลขบิล, status, module, audit action"></label>
      <input type="hidden" name="sort" value="<?=h($filters['sort'])?>">
      <input type="hidden" name="dir" value="<?=h($filters['dir'])?>">
      <button>Apply</button>
      <a class="sr-reset" href="system-reports.php">Reset</a>
      <?php if ($canExport): ?><a class="sr-export" href="<?=h($exportUrl)?>">Export CSV</a><?php endif; ?>
    </form>
  </section>

  <section class="sr-panel">
    <div class="sr-panel-head">
      <div><small>TOOL SORT</small><h2>ตาราง Report หลัก</h2><p>กดหัวตารางเพื่อเรียงข้อมูลแบบจริงจัง และใช้ Filter ด้านบนเพื่อตัดข้อมูลเป็นชุดงาน</p></div>
      <span><?=number_format(count($rows))?> / <?=number_format(count($exportRows))?> rows</span>
    </div>
    <div class="sr-table-wrap">
      <table class="sr-table">
        <thead>
          <tr>
            <th><?=$sortLink('module', 'Module')?></th>
            <th><?=$sortLink('type', 'Type')?></th>
            <th><?=$sortLink('date', 'Date')?></th>
            <th><?=$sortLink('title', 'Title')?></th>
            <th><?=$sortLink('status', 'Status')?></th>
            <th><?=$sortLink('amount', 'Amount')?></th>
            <th><?=$sortLink('count', 'Count')?></th>
            <th><?=$sortLink('ref', 'Ref')?></th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?><tr><td colspan="9" class="sr-empty">ไม่พบข้อมูลตามตัวกรอง</td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?>
          <tr class="<?=$row['risk'] ? 'is-risk' : ''?>">
            <td><span class="sr-module"><?=h($row['module'])?></span></td>
            <td><?=h($row['type'])?></td>
            <td><?=h($row['date'] !== '' ? $row['date'] : '-')?></td>
            <td><b><?=h($row['title'])?></b></td>
            <td><span class="sr-status"><?=h($row['status'])?></span></td>
            <td class="sr-money"><?=number_format((float)$row['amount'], 2)?></td>
            <td><?=number_format((int)$row['count'])?></td>
            <td><?=h($row['ref'])?></td>
            <td><?=h($row['detail'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

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
</body>
</html>
