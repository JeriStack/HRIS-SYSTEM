<?php
/**
 * PHP_CodeSniffer Baseline Verifier & Generator
 * HRIS-SYSTEM CI/CD Code Quality Automation
 */

declare(strict_types=1);

$baselineFile = __DIR__ . '/../phpcs-baseline.json';
$phpcsBinary = PHP_OS_FAMILY === 'Windows'
    ? __DIR__ . '/../vendor/bin/phpcs.bat'
    : __DIR__ . '/../vendor/bin/phpcs';

if (!file_exists($phpcsBinary)) {
    $phpcsBinary = 'vendor/bin/phpcs';
}

$isGenerate = in_array('--generate-baseline', $argv, true);

// Execute phpcs with JSON report
$cmd = escapeshellcmd($phpcsBinary) . ' --report=json -q';
exec($cmd, $output, $exitCode);

$rawJson = implode("\n", $output);
$data = json_decode($rawJson, true);

if (!is_array($data) || !isset($data['files'])) {
    fwrite(STDERR, "Error: Failed to parse PHP_CodeSniffer JSON output.\n");
    fwrite(STDERR, $rawJson . "\n");
    exit(1);
}

$rootPath = realpath(__DIR__ . '/..') ?: '';
$normalizedRoot = str_replace('\\', '/', rtrim($rootPath, '/\\')) . '/';

$currentErrors = [];
$totalErrors = 0;

foreach ($data['files'] as $absPath => $fileData) {
    $errorCount = (int)($fileData['errors'] ?? 0);
    if ($errorCount > 0) {
        $relPath = str_replace('\\', '/', $absPath);
        if (str_starts_with($relPath, $normalizedRoot)) {
            $relPath = substr($relPath, strlen($normalizedRoot));
        }
        $currentErrors[$relPath] = $errorCount;
        $totalErrors += $errorCount;
    }
}
ksort($currentErrors);

if ($isGenerate) {
    $baselineContent = [
        'generated_at' => date('c'),
        'standard'     => 'PSR-12',
        'total_errors' => $totalErrors,
        'files'        => $currentErrors,
    ];

    file_put_contents(
        $baselineFile,
        json_encode($baselineContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    echo "PHPCS baseline generated with {$totalErrors} errors across " . count($currentErrors) . " files.\n";
    echo "Saved to: phpcs-baseline.json\n";
    exit(0);
}

// Verification mode
if (!file_exists($baselineFile)) {
    fwrite(STDERR, "Error: Baseline file phpcs-baseline.json not found.\n");
    fwrite(STDERR, "Run 'php bin/check-phpcs-baseline.php --generate-baseline' to establish a baseline.\n");
    exit(1);
}

$baseline = json_decode(file_get_contents($baselineFile), true);
$baselineFiles = $baseline['files'] ?? [];
$baselineTotal = (int)($baseline['total_errors'] ?? 0);

$violations = [];
foreach ($currentErrors as $file => $count) {
    $allowed = $baselineFiles[$file] ?? 0;
    if ($count > $allowed) {
        $diff = $count - $allowed;
        $violations[] = sprintf("  - %s: %d errors (baseline allowed %d, +%d new)", $file, $count, $allowed, $diff);
    }
}

if (!empty($violations)) {
    fwrite(STDERR, "❌ PHP_CodeSniffer PSR-12 Baseline Check FAILED!\n");
    fwrite(STDERR, "New or increased coding standard violations detected:\n");
    foreach ($violations as $v) {
        fwrite(STDERR, $v . "\n");
    }
    fwrite(STDERR, "\nTotal errors: {$totalErrors} (baseline allowed {$baselineTotal}).\n");
    fwrite(STDERR, "Fix the violations above before merging.\n");
    exit(1);
}

echo "✅ PHP_CodeSniffer PSR-12 Baseline Check PASSED!\n";
echo "Active errors: {$totalErrors} (within baseline limit of {$baselineTotal}). Zero new violations detected.\n";
exit(0);

