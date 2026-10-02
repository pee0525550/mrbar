<?php
require_once __DIR__.'/../app/system-reports.php';
require_once __DIR__.'/../app/pos-reports.php';
function report_check(bool $ok, string $label): void { if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } echo "PASS: $label\n"; }
$data = [
    'users' => [['id'=>1,'display_name'=>'Administrator']],
    'employees' => [['id'=>1,'name'=>'Employee One']],
    'prs' => [['id'=>1,'name'=>'PR One']],
    'reservations' => [
        ['id'=>1,'date'=>'2026-10-02','time'=>'19:00','guest_name'=>'Customer','deposit_amount'=>500,'deposit_status'=>'verified','status'=>'confirmed'],
        ['id'=>2,'date'=>'2026-10-02','time'=>'20:00','guest_name'=>'Pending','deposit_amount'=>300,'deposit_status'=>'pending_review','status'=>'waitlist'],
        ['id'=>3,'date'=>'2026-10-02','deposit_amount'=>900,'deposit_status'=>'verified','status'=>'cancelled']
    ],
    'attendance' => [['id'=>1,'employee_id'=>1,'check_in'=>'2026-10-02T19:00:00+07:00','check_out'=>'2026-10-03T02:00:00+07:00','attendance_state'=>'provisional','payroll_status'=>'pending']],
    'checkins' => [['id'=>1,'pr_id'=>1,'created_at'=>'2026-10-02T19:00:00+07:00']],
    'time_correction_requests' => [['id'=>1,'employee_id'=>1,'requested_at'=>'2026-10-02T19:00:00+07:00','status'=>'pending']],
    'substitute_requests' => [['id'=>1,'original_employee_id'=>1,'date'=>'2026-10-02','status'=>'pending']],
    'daily_closes' => [['id'=>1,'date'=>'2026-10-02','checkins'=>7,'completed'=>5,'cancelled'=>2]],
    'sales_table_sessions' => [['id'=>1,'sales_id'=>1,'opened_at'=>'2026-10-02T19:00:00+07:00']],
    'pos_import_batches' => [['id'=>1,'status'=>'active','period_start'=>'2026-10-01','period_end'=>'2026-10-31','total_sales'=>1000]],
    'audit' => [['at'=>'2026-10-02','action'=>'update','after'=>['name'=>'Updated','password_hash'=>'private-value','line_token'=>'hidden-value']]],
    'line_outbox' => [['id'=>'synthetic-uuid','kind'=>'receipt','created_at'=>strtotime('2026-10-02'),'status'=>'failed','http_status'=>503,'attempts'=>2]],
];
$rows = sr_build_rows($data);
$find = static fn($type) => array_values(array_filter($rows, static fn($row) => $row['type'] === $type))[0];
report_check($find('Attendance')['title'] === 'Employee One', 'employee identity cannot collide with a user id');
report_check(str_contains($find('Check-in Job')['detail'], 'PR One'), 'PR identity uses the PR namespace');
report_check($find('Substitute')['title'] === 'Employee One', 'substitute resolves the original employee');
report_check($find('Time Correction')['day'] === '2026-10-02', 'correction uses requested_at');
report_check($find('Reservation')['amount'] === 500.0, 'reservation reports current deposit field');
report_check($find('Daily Close')['amount'] === null && $find('Daily Close')['count'] === 7, 'daily close is an operations snapshot, not invented revenue');
report_check($find('Table Session')['amount'] === null, 'missing sales amount is not a zero');
report_check($find('Attendance')['risk'] === 1, 'provisional payroll remains flagged after checkout');
report_check($find('LINE Delivery')['ref'] === '#synthetic-uuid' && str_contains($find('LINE Delivery')['detail'], '503'), 'LINE delivery has real reference and status without payload');
report_check(!str_contains($find('Audit Log')['detail'], 'private-value') && !str_contains($find('Audit Log')['detail'], 'hidden-value'), 'audit strips nested secret fields');
$summary = sr_financial_summary($data, '2026-10-02', '2026-10-02');
report_check($summary['deposit_verified']['amount'] === 500.0 && $summary['deposit_pending']['amount'] === 300.0, 'cancelled deposits excluded and pending separated');
report_check($summary['menu_sales']['amount'] === 1000.0, 'overlapping imported periods retain full totals, not guessed daily proration');
report_check($summary['daily_close']['completed'] === 5 && $summary['sales_table']['amount_known'] === 0, 'snapshot and monetary coverage are explicit');
report_check(sr_financial_summary($data, '', '')['menu_sales']['amount'] === 1000.0, 'all-time financial filter works');
$filters=['module'=>'all','type'=>'Reservation','status'=>'active','from'=>'2026-10-02','to'=>'2026-10-02','q'=>'','sort'=>'date','dir'=>'asc'];
report_check(count(sr_filter_rows($rows,$filters)) === 2, 'type/date/active filters include waitlist and confirmed');
report_check(sr_valid_day('2024-02-29') && !sr_valid_day('2026-02-29') && !sr_valid_day('garbage'), 'strict calendar dates');
report_check(sr_csv_cell(' =HYPERLINK("bad")')[0] === "'" && sr_csv_cell('@name')[0] === "'" && sr_csv_cell(-20) === -20, 'CSV escapes text formulas and preserves numeric values');
$rounds=[['id'=>1,'core'=>'drinks','status'=>'saved','rows'=>[['employee_id'=>1,'name'=>'Old Name','amount'=>100]]],['id'=>2,'core'=>'commission','status'=>'saved','rows'=>[['employee_id'=>1,'name'=>'New Name','amount'=>50]]],['id'=>3,'core'=>'drinks','status'=>'void','rows'=>[['employee_id'=>1,'name'=>'Old Name','amount'=>900]]]];
$employees=posr_employee_summary($rounds,['status'=>'all']);
report_check(count($employees) === 1 && $employees[0]['total'] === 150.0, 'renamed employee totals merge by id; void rounds excluded');
