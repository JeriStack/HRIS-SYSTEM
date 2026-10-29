<?php
/**
 * Benchmark Operation Counter for Instrumentation
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

class OperationCounter
{
    public int $comparisons = 0;
    public int $swaps = 0;
    public int $moves = 0;
    public int $probes = 0;
    public int $partitions = 0;
    public int $recursionDepth = 0;
    public int $maxRecursionDepth = 0;

    public function reset(): void
    {
        $this->comparisons = 0;
        $this->swaps = 0;
        $this->moves = 0;
        $this->probes = 0;
        $this->partitions = 0;
        $this->recursionDepth = 0;
        $this->maxRecursionDepth = 0;
    }

    public function enterRecursion(): void
    {
        $this->recursionDepth++;
        if ($this->recursionDepth > $this->maxRecursionDepth) {
            $this->maxRecursionDepth = $this->recursionDepth;
        }
    }

    public function leaveRecursion(): void
    {
        $this->recursionDepth--;
    }

    public function toArray(): array
    {
        return [
            'comparisons'         => $this->comparisons,
            'swaps'               => $this->swaps,
            'moves'               => $this->moves,
            'probes'              => $this->probes,
            'partitions'          => $this->partitions,
            'max_recursion_depth' => $this->maxRecursionDepth,
        ];
    }
}

