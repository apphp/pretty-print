<?php

declare(strict_types=1);

use function Apphp\PrettyPrint\pprint;

require __DIR__ . '/../vendor/autoload.php';

/**
 * PrettyPrint benchmark.
 *
 * Examples:
 *   php benchmarks/benchmark.php
 *   php benchmarks/benchmark.php --preset=100k --dry-run
 *   php benchmarks/benchmark.php --rows=2000 --cols=2000 --max-cells=5000000
 */

$options = getopt('', [
    'preset::',
    'rows::',
    'cols::',
    'max-cells::',
    'dry-run',
]);

$presets = [
    'small' => ['rows' => 1_000, 'cols' => 1_000],
    '10k' => ['rows' => 10_000, 'cols' => 10_000],
    '100k' => ['rows' => 100_000, 'cols' => 100_000],
    '1m' => ['rows' => 1_000_000, 'cols' => 1_000_000],
];

$preset = (string)($options['preset'] ?? 'small');
if (!isset($presets[$preset])) {
    fwrite(STDERR, "Unknown preset '{$preset}'. Available: " . implode(', ', array_keys($presets)) . PHP_EOL);
    exit(1);
}

$rows = isset($options['rows']) ? max(0, (int)$options['rows']) : $presets[$preset]['rows'];
$cols = isset($options['cols']) ? max(0, (int)$options['cols']) : $presets[$preset]['cols'];
$maxCells = isset($options['max-cells']) ? max(1, (int)$options['max-cells']) : 5_000_000;
$dryRun = array_key_exists('dry-run', $options);

$cells = $rows * $cols;
$estimatedBytes = $cells * 16; // rough lower-bound for zval + array overhead is much higher in practice
$estimatedGiB = $estimatedBytes / 1024 / 1024 / 1024;

echo 'PrettyPrint Benchmark' . PHP_EOL;
echo '---------------------' . PHP_EOL;
echo "Preset: {$preset}" . PHP_EOL;
echo "Shape: " . number_format($rows) . " x " . number_format($cols) . PHP_EOL;
echo "Cells: " . number_format($cells) . PHP_EOL;
echo 'Estimated raw payload (very optimistic): ' . number_format($estimatedGiB, 2) . ' GiB' . PHP_EOL;
echo 'Memory limit: ' . ini_get('memory_limit') . PHP_EOL;

if ($dryRun || $cells > $maxCells) {
    echo PHP_EOL;
    if ($dryRun) {
        echo "Dry-run enabled: skipping materialization and pprint call." . PHP_EOL;
    } else {
        echo "Skipped: cells ({$cells}) exceed --max-cells={$maxCells}." . PHP_EOL;
    }
    echo 'Tip: use smaller --rows/--cols or raise --max-cells carefully.' . PHP_EOL;
    exit(0);
}

$startBuild = hrtime(true);
$matrix = buildMatrix($rows, $cols);
$buildMs = (hrtime(true) - $startBuild) / 1_000_000;

$peakAfterBuild = memory_get_peak_usage(true);

$startPrint = hrtime(true);
$out = pprint(
    $matrix,
    headRows: 1,
    tailRows: 1,
    headCols: 2,
    tailCols: 2,
    return: true,
    end: ''
);
$printMs = (hrtime(true) - $startPrint) / 1_000_000;

$peakAfterPrint = memory_get_peak_usage(true);

unset($matrix, $out);

echo PHP_EOL;
echo 'Results' . PHP_EOL;
echo '-------' . PHP_EOL;
echo 'Build time: ' . number_format($buildMs, 2) . ' ms' . PHP_EOL;
echo 'Format time: ' . number_format($printMs, 2) . ' ms' . PHP_EOL;
echo 'Peak memory after build: ' . formatBytes($peakAfterBuild) . PHP_EOL;
echo 'Peak memory after format: ' . formatBytes($peakAfterPrint) . PHP_EOL;

function buildMatrix(int $rows, int $cols): array
{
    $matrix = [];
    for ($r = 0; $r < $rows; $r++) {
        $row = [];
        for ($c = 0; $c < $cols; $c++) {
            $row[] = ($r + $c) % 1000;
        }
        $matrix[] = $row;
    }

    return $matrix;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $i = 0;
    $value = (float)$bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }

    return number_format($value, 2) . ' ' . $units[$i];
}
