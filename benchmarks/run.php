<?php
/**
 * Benchmark CLI Execution Entry Point
 * HRIS-SYSTEM — Phase 15: Performance Analysis and Optimization
 *
 * Usage:
 *   php benchmarks/run.php
 *   php benchmarks/run.php --mode=search
 *   php benchmarks/run.php --mode=sort
 *   php benchmarks/run.php --sizes=10,100,500,1000,5000,10000 --seed=42
 */

require_once __DIR__ . '/../core/bootstrap.php';

use App\Dsa\Benchmarks\BenchmarkRunner;
use App\Dsa\Benchmarks\BenchmarkReporter;
use App\Dsa\Benchmarks\DatasetGenerator;

// Parse CLI options
$options = getopt('', ['mode::', 'sizes::', 'seed::', 'output::']);

$mode   = $options['mode'] ?? 'all';
$seed   = isset($options['seed']) ? (int)$options['seed'] : 42;
$outDir = $options['output'] ?? (__DIR__ . '/results');

// Parse custom dataset sizes if provided
$customSizes = null;
if (isset($options['sizes'])) {
    $sizesList = array_map('intval', explode(',', $options['sizes']));
    $customSizes = [];
    foreach ($sizesList as $s) {
        $customSizes["DS-{$s}"] = $s;
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "  HRIS-SYSTEM — Phase 15: DSA Benchmark Execution Suite\n";
echo "  Runtime: PHP " . PHP_VERSION . " | Monotonic Timer: hrtime(true)\n";
echo "  Seed: {$seed} | Mode: {$mode} | Output: {$outDir}\n";
echo str_repeat('=', 80) . "\n\n";

$runner = new BenchmarkRunner($seed, $customSizes);
$runner->setProgressCallback(function (string $msg) {
    echo "  [INFO] " . date('H:i:s') . " - {$msg}\n";
});

$startTime = hrtime(true);

$results = match ($mode) {
    'search' => $runner->runSearchBenchmarks(),
    'sort'   => $runner->runSortBenchmarks(),
    'all'    => $runner->runAll(),
    default  => $runner->runAll(),
};

$elapsedTotalSec = round((hrtime(true) - $startTime) / 1_000_000_000, 2);

echo "\n" . str_repeat('-', 80) . "\n";
echo "  Execution completed in {$elapsedTotalSec}s across " . count($results) . " benchmark runs.\n";
echo "  Exporting outputs...\n";

// 1. Export JSON & CSV
$jsonPath = "{$outDir}/raw/benchmark_results.json";
$csvPath  = "{$outDir}/raw/benchmark_results.csv";
BenchmarkReporter::exportJson($results, $jsonPath);
BenchmarkReporter::exportCsv($results, $csvPath);
echo "  -> Raw JSON:  {$jsonPath}\n";
echo "  -> Raw CSV:   {$csvPath}\n";

// 2. Export Summary Markdown Tables
$tablesMd = BenchmarkReporter::generateMarkdownTables($results);
$tablesPath = "{$outDir}/tables/benchmark_tables.md";
$tablesDir = dirname($tablesPath);
if (!is_dir($tablesDir)) {
    mkdir($tablesDir, 0755, true);
}
file_put_contents($tablesPath, $tablesMd);
echo "  -> Tables:    {$tablesPath}\n";

// 3. Export SVG Charts
$chartDir = "{$outDir}/graphs";
$charts = BenchmarkReporter::generateSvgCharts($results, $chartDir);
foreach ($charts as $key => $cPath) {
    echo "  -> Graph:     {$cPath}\n";
}

echo str_repeat('=', 80) . "\n";
echo "  Benchmark suite finished successfully. All outputs verified.\n\n";

