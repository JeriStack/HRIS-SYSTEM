<?php
/**
 * Benchmark Result Model
 * Captures statistical timings, operation counts, and memory measurements
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

class BenchmarkResult
{
    public string $runId;
    public string $timestamp;
    public string $category; // 'search' | 'sort' | 'structure'
    public string $algorithm;
    public string $datasetId;
    public int $datasetSize;
    public string $arrangement; // 'random', 'sorted', 'reverse_sorted', 'nearly_sorted', 'duplicate_heavy'
    public string $scenario;    // 'beginning', 'middle', 'end', 'miss', or 'n/a'
    public int $repetitions;
    public float $minTimeNs;
    public float $medianTimeNs;
    public float $meanTimeNs;
    public float $maxTimeNs;
    public float $stdDevNs;
    public int $comparisons;
    public int $swaps;
    public int $moves;
    public int $probes;
    public int $memoryPeakBytes;
    public int $memoryAllocatedBytes;
    public bool $isCorrect;

    public function __construct(array $data)
    {
        $this->runId                = $data['run_id'] ?? uniqid('run_', true);
        $this->timestamp            = $data['timestamp'] ?? date('c');
        $this->category             = $data['category'] ?? 'general';
        $this->algorithm            = $data['algorithm'] ?? 'unknown';
        $this->datasetId            = $data['dataset_id'] ?? 'DS-000';
        $this->datasetSize          = (int)($data['dataset_size'] ?? 0);
        $this->arrangement          = $data['arrangement'] ?? 'random';
        $this->scenario             = $data['scenario'] ?? 'n/a';
        $this->repetitions          = (int)($data['repetitions'] ?? 1);
        $this->minTimeNs            = (float)($data['min_time_ns'] ?? 0.0);
        $this->medianTimeNs         = (float)($data['median_time_ns'] ?? 0.0);
        $this->meanTimeNs           = (float)($data['mean_time_ns'] ?? 0.0);
        $this->maxTimeNs            = (float)($data['max_time_ns'] ?? 0.0);
        $this->stdDevNs             = (float)($data['std_dev_ns'] ?? 0.0);
        $this->comparisons          = (int)($data['comparisons'] ?? 0);
        $this->swaps                = (int)($data['swaps'] ?? 0);
        $this->moves                = (int)($data['moves'] ?? 0);
        $this->probes               = (int)($data['probes'] ?? 0);
        $this->memoryPeakBytes      = (int)($data['memory_peak_bytes'] ?? 0);
        $this->memoryAllocatedBytes = (int)($data['memory_allocated_bytes'] ?? 0);
        $this->isCorrect            = (bool)($data['is_correct'] ?? true);
    }

    public function getMedianTimeMs(): float
    {
        return round($this->medianTimeNs / 1_000_000, 4);
    }

    public function getMedianTimeUs(): float
    {
        return round($this->medianTimeNs / 1_000, 2);
    }

    public function toArray(): array
    {
        return [
            'run_id'                 => $this->runId,
            'timestamp'              => $this->timestamp,
            'category'               => $this->category,
            'algorithm'              => $this->algorithm,
            'dataset_id'             => $this->datasetId,
            'dataset_size'           => $this->datasetSize,
            'arrangement'            => $this->arrangement,
            'scenario'               => $this->scenario,
            'repetitions'            => $this->repetitions,
            'min_time_ns'            => $this->minTimeNs,
            'median_time_ns'         => $this->medianTimeNs,
            'mean_time_ns'           => $this->meanTimeNs,
            'max_time_ns'            => $this->maxTimeNs,
            'std_dev_ns'             => $this->stdDevNs,
            'median_time_ms'         => $this->getMedianTimeMs(),
            'median_time_us'         => $this->getMedianTimeUs(),
            'comparisons'            => $this->comparisons,
            'swaps'                  => $this->swaps,
            'moves'                  => $this->moves,
            'probes'                 => $this->probes,
            'memory_peak_bytes'      => $this->memoryPeakBytes,
            'memory_allocated_bytes' => $this->memoryAllocatedBytes,
            'is_correct'             => $this->isCorrect,
        ];
    }
}

