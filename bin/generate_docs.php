<?php
/**
 * OpenAPI Specification Generation CLI Script
 * Employee HRIS & Leave Management System
 *
 * Usage: php bin/generate_docs.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;
use Symfony\Component\Yaml\Yaml;

echo "===========================================================" . PHP_EOL;
echo " HRIS Enterprise OpenAPI Specification Generator" . PHP_EOL;
echo "===========================================================" . PHP_EOL;

$appDir = dirname(__DIR__) . '/app';
$outputDir = dirname(__DIR__) . '/docs/api';
$yamlFile = $outputDir . '/openapi.yaml';
$jsonFile = $outputDir . '/openapi.json';

if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        fwrite(STDERR, "Error: Failed to create target output directory: {$outputDir}" . PHP_EOL);
        exit(1);
    }
}

echo "Scanning source code in: {$appDir}..." . PHP_EOL;

$startTime = microtime(true);

try {
    $generator = new Generator();
    $openapi = $generator->generate([$appDir]);

    if (!$openapi) {
        fwrite(STDERR, "Error: Generator did not return a valid OpenAPI object." . PHP_EOL);
        exit(1);
    }

    $yamlContent = $openapi->toYaml();
    $jsonContent = $openapi->toJson();

    file_put_contents($yamlFile, $yamlContent);
    file_put_contents($jsonFile, $jsonContent);

    $duration = round(microtime(true) - $startTime, 3);
    $tagCount = is_countable($openapi->tags) ? count($openapi->tags) : 0;
    $pathCount = is_countable($openapi->paths) ? count($openapi->paths) : 0;

    echo "OpenAPI specification generated successfully in {$duration}s" . PHP_EOL;
    echo "  - Title       : " . ($openapi->info->title ?? 'N/A') . PHP_EOL;
    echo "  - Version     : " . ($openapi->info->version ?? 'N/A') . PHP_EOL;
    echo "  - Tags        : {$tagCount}" . PHP_EOL;
    echo "  - Paths       : {$pathCount}" . PHP_EOL;
    echo "  - YAML Output : {$yamlFile} (" . number_format(strlen($yamlContent)) . " bytes)" . PHP_EOL;
    echo "  - JSON Output : {$jsonFile} (" . number_format(strlen($jsonContent)) . " bytes)" . PHP_EOL;
    echo "===========================================================" . PHP_EOL;

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error generating OpenAPI documentation: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
