<?php
// Read-only report projections; source records remain unchanged.
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
    return $ts !== false ? date('Y-m-d H:i', $ts) : $text;
}

function sr_day($value): string {
    $text = sr_date($value);
    $day = substr($text, 0, 10);
    return sr_valid_day($day) ? $day : '';
}

function sr_lookup_name(array $d, $id, string $bucket = 'users'): string {
    $id = (int)$id;
    if ($id <= 0) return '';
    foreach ($d[$bucket] ?? [] as $row) {
        if ((int)($row['id'] ?? 0) !== $id) continue;
        return sr_first($row, ['display_name', 'name', 'full_name', 'nickname', 'code'], '#'.$id);
    }
    return '#'.$id;
}

function sr_add(array &$rows, string $module, string $type, string $date, string $title, string $status, ?float $amount, int $count, string $ref, string $detail, int $risk = 0): void {
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
        'count_unit' => ['Reservation' => 'คน', 'CRM Member' => 'ครั้ง', 'Table Directory' => 'ที่นั่ง', 'Table Session' => 'คน', 'POS Import' => 'แถว', 'Bill Matching' => 'บิล', 'Drink Payout' => 'คน', 'Commission Payout' => 'คน', 'Daily Close' => 'งาน', 'LINE Delivery' => 'ครั้งที่ลองส่ง'][$type] ?? 'รายการ',
        'ref' => $ref,
        'detail' => $detail,
        'risk' => $risk,
        '_search' => $search,
    ];
}

function sr_identity_name(array $d, array $row): string {
    foreach (['employee_id' => 'employees', 'user_id' => 'users', 'pr_id' => 'prs'] as $field => $bucket) {
        if ((int)($row[$field] ?? 0) > 0) return sr_lookup_name($d, $row[$field], $bucket);
    }
    return '';
}

function sr_valid_day(string $day): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
    return $date !== false && $date->format('Y-m-d') === $day;
}

function sr_csv_cell($value) {
    if (!is_string($value)) return $value;
    // Spreadsheet formula injection can originate in names, notes and audit fields.
    return preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? "'".$value : $value;
}

function sr_audit_detail(array $row): string {
    $safe = [];
    foreach ($row as $key => $value) {
        if (preg_match('/password|secret|token|pin|cookie|session|credential|hash/i', (string)$key)) continue;
        $safe[] = (string)$key.': '.(is_array($value) ? sr_audit_detail($value) : sr_text($value));
    }
    return implode(' | ', $safe);
}

function sr_build_rows(array $d): array {
    $rows = [];

    foreach ($d['reservations'] ?? [] as $r) {
        $date = sr_first($r, ['date', 'reserved_at', 'created_at']);
        $time = sr_first($r, ['time', 'start_time']);
        $guest = sr_first($r, ['guest_name', 'customer_name', 'name'], 'Reservation');
        $detail = trim(sr_first($r, ['phone']).' '.sr_first($r, ['note', 'source']).' | deposit: '.sr_first($r, ['deposit_status'], 'none'));
        sr_add($rows, 'Customer', 'Reservation', trim(substr($date, 0, 10).' '.$time), $guest, sr_first($r, ['status'], 'active'), sr_num($r['deposit_amount'] ?? $r['deposit'] ?? 0), (int)($r['party_size'] ?? $r['pax'] ?? 0), '#'.(int)($r['id'] ?? 0), $detail, ($r['deposit_status'] ?? '') === 'pending_review' ? 1 : 0);
    }

    foreach ($d['customers'] ?? [] as $c) {
        $name = sr_first($c, ['name', 'display_name', 'full_name'], 'Customer');
        $status = sr_first($c, ['status', 'tier', 'level'], 'active');
        sr_add($rows, 'Customer', 'CRM Member', sr_first($c, ['updated_at', 'created_at', 'last_visit']), $name, $status, sr_num($c['lifetime_value'] ?? $c['total_spend'] ?? 0), (int)($c['visit_count'] ?? $c['bookings'] ?? 0), '#'.(int)($c['id'] ?? 0), sr_first($c, ['phone', 'line_id', 'note']));
    }

    foreach ($d['checkins'] ?? [] as $c) {
        $title = sr_first($c, ['ticket', 'guest_name', 'customer_name'], 'Check-in');
        $pr = sr_lookup_name($d, $c['pr_id'] ?? 0, 'prs');
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
        $amount = isset($s['total_sales']) || isset($s['bill_total']) || isset($s['amount']) ? sr_num($s['total_sales'] ?? $s['bill_total'] ?? $s['amount']) : null;
        $owner = !empty($s['sales_id']) ? sr_lookup_name($d, $s['sales_id'], 'employees') : sr_lookup_name($d, $s['user_id'] ?? 0);
        sr_add($rows, 'Sales', 'Table Session', sr_first($s, ['opened_at', 'created_at', 'closed_at']), sr_first($s, ['table_code', 'table_name'], 'Sales Table'), sr_first($s, ['status'], 'active'), $amount, (int)($s['guest_count'] ?? $s['pax'] ?? 0), '#'.(int)($s['id'] ?? 0), trim('Sales '.$owner.' '.sr_first($s, ['bill_no', 'note'])));
    }

    foreach ($d['prs'] ?? [] as $p) {
        sr_add($rows, 'People', 'PR Profile', sr_first($p, ['updated_at', 'created_at']), sr_first($p, ['code', 'name'], 'PR'), sr_first($p, ['status'], !empty($p['active']) ? 'active' : 'inactive'), 0, 1, '#'.(int)($p['id'] ?? 0), sr_first($p, ['name', 'role', 'phone']));
    }

    foreach ($d['employees'] ?? [] as $e) {
        sr_add($rows, 'People', 'Employee', sr_first($e, ['updated_at', 'created_at', 'start_date']), sr_first($e, ['code', 'name', 'display_name'], 'Employee'), sr_first($e, ['status'], !empty($e['active']) ? 'active' : 'inactive'), sr_num($e['salary'] ?? 0), 1, '#'.(int)($e['id'] ?? 0), sr_first($e, ['position', 'department', 'phone']));
    }

    foreach ($d['attendance'] ?? [] as $a) {
        $name = sr_identity_name($d, $a);
        $status = empty($a['check_out']) ? 'open' : sr_first($a, ['attendance_state', 'status'], 'closed');
        $pending = empty($a['check_out']) || ($a['payroll_status'] ?? '') === 'pending' || in_array($status, ['missing_checkout', 'provisional', 'duration_review'], true);
        sr_add($rows, 'Workforce', 'Attendance', sr_first($a, ['check_in', 'created_at', 'date']), $name !== '' ? $name : 'Attendance', $status, 0, 1, '#'.(int)($a['id'] ?? 0), trim(sr_first($a, ['check_out', 'source', 'note']).' | payroll: '.sr_first($a, ['payroll_status'], 'unknown')), $pending ? 1 : 0);
    }

    foreach ($d['shifts'] ?? [] as $s) {
        $name = sr_identity_name($d, $s);
        sr_add($rows, 'Workforce', 'Shift', sr_first($s, ['date', 'start_at', 'created_at']), $name !== '' ? $name : 'Shift', sr_first($s, ['status'], 'scheduled'), 0, 1, '#'.(int)($s['id'] ?? 0), trim(sr_first($s, ['start_time', 'start_at']).' - '.sr_first($s, ['end_time', 'end_at']).' '.sr_first($s, ['role', 'note'])));
    }

    foreach ($d['leave_requests'] ?? [] as $l) {
        $name = sr_identity_name($d, $l);
        $status = sr_first($l, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Leave', sr_first($l, ['start_date', 'date', 'created_at']), $name !== '' ? $name : 'Leave Request', $status, 0, 1, '#'.(int)($l['id'] ?? 0), trim(sr_first($l, ['type', 'leave_type']).' '.sr_first($l, ['end_date', 'reason'])), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['time_correction_requests'] ?? [] as $r) {
        $name = sr_identity_name($d, $r);
        $status = sr_first($r, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Time Correction', sr_first($r, ['requested_at', 'date', 'created_at']), $name !== '' ? $name : 'Time Correction', $status, 0, 1, '#'.(int)($r['id'] ?? 0), sr_first($r, ['reason', 'note']), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['substitute_requests'] ?? [] as $r) {
        $name = sr_lookup_name($d, $r['original_employee_id'] ?? $r['employee_id'] ?? 0, 'employees');
        $status = sr_first($r, ['status'], 'pending');
        sr_add($rows, 'Workforce', 'Substitute', sr_first($r, ['date', 'created_at']), $name !== '' ? $name : 'Substitute Request', $status, 0, 1, '#'.(int)($r['id'] ?? 0), sr_first($r, ['reason', 'note']), $status === 'pending' ? 1 : 0);
    }

    foreach ($d['daily_closes'] ?? [] as $c) {
        sr_add($rows, 'Reports', 'Daily Close', sr_first($c, ['date', 'closed_at', 'created_at']), 'Daily Close', sr_first($c, ['status'], 'closed'), isset($c['total']) || isset($c['cash_total']) ? sr_num($c['total'] ?? $c['cash_total']) : null, (int)($c['checkins'] ?? 0), '#'.(int)($c['id'] ?? 0), trim('completed '.(int)($c['completed'] ?? 0).' / cancelled '.(int)($c['cancelled'] ?? 0).' '.sr_first($c, ['note'])));
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

    foreach ($d['line_outbox'] ?? [] as $job) {
        $status = (string)($job['status'] ?? 'pending');
        $created = $job['created_at'] ?? '';
        $date = is_numeric($created) ? date('c', (int)$created) : (string)$created;
        sr_add($rows, 'System', 'LINE Delivery', $date, (string)($job['kind'] ?? 'LINE'), $status, 0, (int)($job['attempts'] ?? 0), '#'.(string)($job['id'] ?? ''), 'reservation #'.(int)($job['reservation_id'] ?? 0).' | HTTP '.(int)($job['http_status'] ?? 0), in_array($status, ['sent', 'cancelled'], true) ? 0 : 1);
    }

    foreach ($d['audit'] ?? [] as $a) {
        sr_add($rows, 'System', 'Audit Log', sr_first($a, ['at', 'created_at']), sr_first($a, ['action'], 'audit'), sr_first($a, ['status'], 'logged'), 0, 1, '#'.(int)($a['id'] ?? 0), sr_audit_detail($a));
    }

    return $rows;
}

function sr_filter_rows(array $rows, array $filters): array {
    $module = (string)$filters['module'];
    $status = (string)$filters['status'];
    $from = (string)$filters['from'];
    $to = (string)$filters['to'];
    $q = strtolower(trim((string)$filters['q']));
    $type = (string)($filters['type'] ?? 'all');
    $rows = array_values(array_filter($rows, function ($row) use ($module, $status, $from, $to, $q, $type) {
        if ($module !== 'all' && $row['module'] !== $module) return false;
        if ($type !== 'all' && $row['type'] !== $type) return false;
        if ($status !== 'all') {
            $s = strtolower((string)$row['status']);
            if ($status === 'active' && !in_array($s, ['active', 'open', 'pending', 'scheduled', 'saved', 'unread', 'logged', 'waitlist', 'confirmed', 'seated', 'assigned'], true)) return false;
            if ($status === 'closed' && !in_array($s, ['closed', 'resolved', 'read', 'completed', 'approved'], true)) return false;
            if ($status === 'risk' && (int)$row['risk'] <= 0) return false;
            if (!in_array($status, ['active', 'closed', 'risk'], true) && $s !== $status) return false;
        }
        if ($from !== '' && ($row['day'] === '' || $row['day'] < $from)) return false;
        if ($to !== '' && ($row['day'] === '' || $row['day'] > $to)) return false;
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

function sr_period_overlaps(array $row, string $from, string $to): bool {
    if ($from === '' && $to === '') return true;
    $start = sr_day($row['period_start'] ?? $row['from'] ?? $row['date'] ?? '');
    $end = sr_day($row['period_end'] ?? $row['to'] ?? $row['date'] ?? $start);
    if ($start === '' && $end === '') {
        $start = sr_day($row['opened_at'] ?? $row['imported_at'] ?? $row['saved_at'] ?? $row['closed_at'] ?? $row['created_at'] ?? '');
        $end = $start;
    }
    if ($start === '' && $end === '') return false;
    if ($start === '') $start = $end;
    if ($end === '') $end = $start;
    return ($from === '' || $end >= $from) && ($to === '' || $start <= $to);
}

function sr_financial_summary(array $d, string $from, string $to): array {
    $out = [
        'menu_sales' => ['amount' => 0.0, 'count' => 0],
        'bill_sales' => ['amount' => 0.0, 'count' => 0],
        'sales_table' => ['amount' => 0.0, 'count' => 0, 'amount_known' => 0],
        'daily_close' => ['amount' => 0.0, 'count' => 0, 'completed' => 0, 'cancelled' => 0],
        'drink_payout' => ['amount' => 0.0, 'count' => 0],
        'commission_payout' => ['amount' => 0.0, 'count' => 0],
        'deposit_verified' => ['amount' => 0.0, 'count' => 0],
        'deposit_pending' => ['amount' => 0.0, 'count' => 0],
    ];
    foreach ($d['pos_import_batches'] ?? [] as $row) {
        if (($row['status'] ?? 'active') !== 'active' || !sr_period_overlaps($row, $from, $to)) continue;
        $out['menu_sales']['amount'] += sr_num($row['total_sales'] ?? 0);
        $out['menu_sales']['count']++;
    }
    foreach ($d['pos_bill_batches'] ?? [] as $row) {
        if (($row['status'] ?? 'active') !== 'active' || !sr_period_overlaps($row, $from, $to)) continue;
        $out['bill_sales']['amount'] += sr_num($row['total_sales'] ?? 0);
        $out['bill_sales']['count']++;
    }
    foreach ($d['daily_closes'] ?? [] as $row) {
        if (!sr_period_overlaps($row, $from, $to)) continue;
        $out['daily_close']['amount'] += sr_num($row['total'] ?? $row['cash_total'] ?? 0);
        $out['daily_close']['count']++;
        $out['daily_close']['completed'] += (int)($row['completed'] ?? 0);
        $out['daily_close']['cancelled'] += (int)($row['cancelled'] ?? 0);
    }
    foreach ($d['sales_table_sessions'] ?? [] as $row) {
        if (!sr_period_overlaps($row, $from, $to)) continue;
        $out['sales_table']['amount'] += sr_num($row['total_sales'] ?? $row['bill_total'] ?? $row['amount'] ?? 0);
        $out['sales_table']['count']++;
        if (isset($row['total_sales']) || isset($row['bill_total']) || isset($row['amount'])) $out['sales_table']['amount_known']++;
    }
    foreach ([
        'drink_payout_rounds' => 'drink_payout',
        'commission_payout_rounds' => 'commission_payout',
    ] as $bucket => $key) {
        foreach ($d[$bucket] ?? [] as $row) {
            if (($row['status'] ?? 'saved') === 'void' || !sr_period_overlaps($row, $from, $to)) continue;
            $out[$key]['amount'] += sr_num($row['total'] ?? 0);
            $out[$key]['count']++;
        }
    }
    foreach ($d['reservations'] ?? [] as $row) {
        if (!sr_period_overlaps($row, $from, $to)) continue;
        if (($row['status'] ?? '') === 'cancelled') continue;
        $key = ($row['deposit_status'] ?? '') === 'verified' ? 'deposit_verified' : (($row['deposit_status'] ?? '') === 'pending_review' ? 'deposit_pending' : '');
        if ($key === '') continue;
        $out[$key]['amount'] += sr_num($row['deposit_amount'] ?? $row['deposit'] ?? 0);
        $out[$key]['count']++;
    }
    return $out;
}

function sr_report_row_html(array $row): string {
    $risk = !empty($row['risk']) ? ' class="is-risk"' : '';
return '<tr'.$risk.'><td><span class="sr-module">'.h((string)$row['module']).'</span></td><td>'.h((string)$row['type']).'</td><td>'.h((string)($row['date'] !== '' ? $row['date'] : '-')).'</td><td><b>'.h((string)$row['title']).'</b></td><td><span class="sr-status">'.h((string)$row['status']).'</span></td><td class="sr-money">'.($row['amount'] === null ? '-' : number_format((float)$row['amount'], 2)).'</td><td>'.number_format((int)$row['count']).' '.h((string)$row['count_unit']).'</td><td>'.h((string)$row['ref']).'</td><td>'.h((string)$row['detail']).'</td></tr>';
}
