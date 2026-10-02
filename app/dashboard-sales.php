<?php
declare(strict_types=1);

function dash_sales(array $data, string $source, int $batchId = 0): array {
    $source = $source === 'menu' ? 'menu' : 'bills';
    $batches = array_values(array_filter($data[$source === 'menu' ? 'pos_import_batches' : 'pos_bill_batches'] ?? [], static fn($b) => ($b['status'] ?? 'active') === 'active'));
    usort($batches, static fn($a,$b) => strcmp((string)($b['imported_at'] ?? ''),(string)($a['imported_at'] ?? '')) ?: (int)$b['id'] <=> (int)$a['id']);
    $batch = null;
    foreach ($batches as $candidate) if ($batchId === 0 || (int)$candidate['id'] === $batchId) { $batch = $candidate; break; }
    $result = ['source'=>$source,'batches'=>$batches,'batch'=>$batch,'total'=>null,'count'=>0,'qty'=>0.0,'average'=>null,'series'=>[],'items'=>[],'undated'=>0,'summary'=>false,'difference'=>null];
    if (!$batch) return $result;
    $result['summary'] = $source === 'menu' && ($batch['report_type'] ?? '') === 'product_summary';
    $rows = array_values(array_filter($data[$source === 'menu' ? 'pos_sales_rows' : 'pos_bill_rows'] ?? [], static fn($r) => (int)($r['batch_id'] ?? 0) === (int)$batch['id'] && !empty($r['active'])));
    // A single batch is the source of truth: never add bill and menu views together.
    if (!$rows) return $result;
    $result['total'] = 0.0;
    $dates = []; $items = [];
    foreach ($rows as $row) {
        $amount = (float)($row['net_sales'] ?? 0);
        $result['total'] += $amount; $result['count']++;
        $result['qty'] += (float)($row['qty'] ?? 0);
        $day = substr((string)($row['sale_date'] ?? ''),0,10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)) $result['undated']++;
        elseif (!$result['summary']) $dates[$day] = ($dates[$day] ?? 0) + $amount;
        if ($source === 'menu') {
            $name = (string)($row['item_name'] ?? 'ไม่ระบุเมนู');
            if (!isset($items[$name])) $items[$name] = ['name'=>$name,'amount'=>0.0,'qty'=>0.0];
            $items[$name]['amount'] += $amount; $items[$name]['qty'] += (float)($row['qty'] ?? 0);
        }
    }
    $result['average'] = $source === 'bills' ? $result['total']/$result['count'] : null;
    ksort($dates); foreach ($dates as $day=>$amount) $result['series'][] = ['label'=>$day,'value'=>$amount];
    usort($items,static fn($a,$b) => $b['amount'] <=> $a['amount']);
    $result['items'] = array_slice($items,0,8);
    if (isset($batch['total_sales'])) $result['difference'] = round($result['total']-(float)$batch['total_sales'],2);
    return $result;
}
