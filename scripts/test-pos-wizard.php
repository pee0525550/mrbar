<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = file_get_contents($root . '/pos-incentive.php');
$css = file_get_contents($root . '/assets/pos-wizard-strict-v1410.css');
$failures = [];

$checks = [
    'server clamps requested step' => strpos($php, '$step=min($requestedStep,$maxStep)') !== false,
    'suite navigation replaces duplicate wizard strip' => strpos($php, '<nav class="pos-suite-nav">') !== false && strpos($php, '<section class="posi-workflow posi-wizard-nav"') === false,
    'period is compact after step one' => strpos($php, 'if($step>1):?><section class="posi-period-locked"') !== false,
    'sales selection confirms step two' => strpos($php, '&sort_confirmed=1') !== false,
    'final requires review guard' => strpos($php, 'empty($_POST[\'flow_ready\'])') !== false,
    'final save is only final action' => strpos($php, 'บันทึกข้อมูลและ Final รอบนี้') !== false,
    'later steps hide date picker' => strpos($css, '.posi-wizard-step-2 .posi-period-picker') !== false,
    'old duplicate result actions hidden' => strpos($css, '.posi-results-stage .card-actions{display:none!important}') !== false,
];

foreach ($checks as $name => $passed) {
    echo ($passed ? "[PASS] " : "[FAIL] ") . $name . PHP_EOL;
    if (!$passed) $failures[] = $name;
}

if ($failures) {
    fwrite(STDERR, 'Wizard checks failed: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo "Strict POS wizard regression checks passed." . PHP_EOL;
