<?php
declare(strict_types=1);

require __DIR__.'/../app/pos-incentive.php';

$cases = [
    'accepts a normal date' => ['2026-09-24', true],
    'accepts leap day in leap year' => ['2024-02-29', true],
    'rejects impossible day' => ['2026-02-30', false],
    'rejects leap day in non-leap year' => ['2025-02-29', false],
    'rejects out-of-range month' => ['2026-13-01', false],
    'rejects wrong format' => ['24/09/2026', false],
];

$failures = [];
foreach ($cases as $name => [$date, $expected]) {
    $passed = posi_valid_date($date) === $expected;
    echo ($passed ? '[PASS] ' : '[FAIL] ').$name.PHP_EOL;
    if (!$passed) $failures[] = $name;
}

if ($failures) {
    fwrite(STDERR, 'Date validation tests failed: '.implode(', ', $failures).PHP_EOL);
    exit(1);
}

echo "POS import date validation tests passed.".PHP_EOL;
