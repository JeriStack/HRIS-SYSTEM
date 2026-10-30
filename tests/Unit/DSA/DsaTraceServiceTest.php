<?php

namespace Tests\Unit\DSA;

use PHPUnit\Framework\TestCase;
use App\Services\DsaTraceService;
use InvalidArgumentException;

class DsaTraceServiceTest extends TestCase
{
    private DsaTraceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DsaTraceService();
    }

    public function testGetComplexityReturnsAllTopics(): void
    {
        $topics = ['bubble', 'selection', 'insertion', 'merge', 'quick', 'linear', 'binary', 'stack', 'queue', 'linked_list', 'hash_table', 'bst'];

        foreach ($topics as $topic) {
            $cmp = DsaTraceService::getComplexity($topic);
            $this->assertArrayHasKey('name', $cmp);
            $this->assertArrayHasKey('best', $cmp);
            $this->assertArrayHasKey('avg', $cmp);
            $this->assertArrayHasKey('worst', $cmp);
            $this->assertArrayHasKey('space', $cmp);
            $this->assertArrayHasKey('notes', $cmp);
            $this->assertNotEmpty($cmp['name']);
        }

        $fallback = DsaTraceService::getComplexity('unknown_topic');
        $this->assertSame('N/A', $fallback['best']);
    }

    public function testGetExampleReturnsValidData(): void
    {
        $sortExample = DsaTraceService::getExample('quick');
        $this->assertArrayHasKey('input', $sortExample);
        $this->assertIsArray($sortExample['input']);

        $searchExample = DsaTraceService::getExample('binary');
        $this->assertArrayHasKey('input', $searchExample);
        $this->assertArrayHasKey('target', $searchExample);

        $stackExample = DsaTraceService::getExample('stack');
        $this->assertArrayHasKey('initial', $stackExample);
        $this->assertArrayHasKey('operation', $stackExample);

        $htExample = DsaTraceService::getExample('hash_table');
        $this->assertArrayHasKey('initial', $htExample);
        $this->assertArrayHasKey('key', $htExample);
    }

    public function testTraceSortAllAlgorithmsProducesCorrectlySortedOutput(): void
    {
        $algorithms = ['bubble', 'selection', 'insertion', 'merge', 'quick'];
        $input = [64, 34, 25, 12, 22, 11, 90];
        $expected = [11, 12, 22, 25, 34, 64, 90];

        foreach ($algorithms as $algo) {
            $res = $this->service->traceSort($algo, $input);
            $this->assertTrue($res['success'], "Trace failed for {$algo}");
            $this->assertSame($expected, $res['sorted'], "Sorted mismatch for {$algo}");
            $this->assertNotEmpty($res['steps'], "Steps empty for {$algo}");
            $this->assertGreaterThan(0, $res['stats']['comparisons'], "0 comparisons for {$algo}");
            $this->assertSame('complete', end($res['steps'])['type']);
        }
    }

    public function testBubbleSortEarlyExitTrace(): void
    {
        $alreadySorted = [1, 2, 3, 4, 5];
        $res = $this->service->traceSort('bubble', $alreadySorted);

        $this->assertTrue($res['success']);
        $stepTypes = array_column($res['steps'], 'type');
        $this->assertContains('early_exit', $stepTypes);
    }

    public function testTraceSortInputTruncation(): void
    {
        $oversized = range(50, 1, -1); // 50 items, max is 30
        $res = $this->service->traceSort('quick', $oversized);

        $this->assertCount(DsaTraceService::MAX_INPUT_SIZE, $res['original']);
        $this->assertCount(DsaTraceService::MAX_INPUT_SIZE, $res['sorted']);
    }

    public function testTraceSortThrowsOnInvalidAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->traceSort('bogosort', [3, 2, 1]);
    }

    public function testTraceLinearSearchFoundAndNotFound(): void
    {
        $data = [10, 50, 30, 70, 20];

        // Found
        $foundRes = $this->service->traceSearch('linear', $data, 30);
        $this->assertTrue($foundRes['success']);
        $this->assertTrue($foundRes['found']);
        $this->assertSame(2, $foundRes['index']);
        $this->assertSame(3, $foundRes['stats']['comparisons']);

        // Not Found
        $missingRes = $this->service->traceSearch('linear', $data, 999);
        $this->assertTrue($missingRes['success']);
        $this->assertFalse($missingRes['found']);
        $this->assertFalse($missingRes['index']);
        $this->assertSame(5, $missingRes['stats']['comparisons']);
    }

    public function testTraceBinarySearchFoundAndNotFound(): void
    {
        $sortedData = [10, 20, 30, 40, 50, 60, 70];

        // Found
        $foundRes = $this->service->traceSearch('binary', $sortedData, 50);
        $this->assertTrue($foundRes['success']);
        $this->assertTrue($foundRes['found']);
        $this->assertSame(4, $foundRes['index']);
        $this->assertNotEmpty($foundRes['steps']);

        // Not Found
        $missingRes = $this->service->traceSearch('binary', $sortedData, 25);
        $this->assertTrue($missingRes['success']);
        $this->assertFalse($missingRes['found']);
        $this->assertFalse($missingRes['index']);
    }

    public function testTraceBinarySearchAutoSortsUnsortedInput(): void
    {
        $unsorted = [50, 20, 70, 10, 40];
        $res = $this->service->traceSearch('binary', $unsorted, 20);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['found']);
        $this->assertSame([10, 20, 40, 50, 70], $res['dataset']);
        $this->assertSame(1, $res['index']);
    }

    public function testTraceStackOperations(): void
    {
        // Push
        $pushRes = $this->service->traceStack(['A', 'B'], 'push', 'C');
        $this->assertSame(['A', 'B', 'C'], $pushRes['after']);
        $this->assertSame(3, $pushRes['size']);

        // Pop
        $popRes = $this->service->traceStack(['A', 'B', 'C'], 'pop');
        $this->assertSame(['A', 'B'], $popRes['after']);
        $this->assertSame('C', $popRes['result']);

        // Peek
        $peekRes = $this->service->traceStack(['X', 'Y'], 'peek');
        $this->assertSame('Y', $peekRes['result']);
        $this->assertSame(['X', 'Y'], $peekRes['after']);

        // Clear
        $clearRes = $this->service->traceStack(['X', 'Y'], 'clear');
        $this->assertSame([], $clearRes['after']);
        $this->assertSame(0, $clearRes['size']);
    }

    public function testTraceQueueOperations(): void
    {
        // Enqueue
        $enqRes = $this->service->traceQueue(['1', '2'], 'enqueue', '3');
        $this->assertSame(['1', '2', '3'], $enqRes['after']);
        $this->assertSame(3, $enqRes['size']);

        // Dequeue
        $deqRes = $this->service->traceQueue(['1', '2', '3'], 'dequeue');
        $this->assertSame(['2', '3'], $deqRes['after']);
        $this->assertSame('1', $deqRes['result']);

        // Peek
        $peekRes = $this->service->traceQueue(['A', 'B'], 'peek');
        $this->assertSame('A', $peekRes['result']);

        // Clear
        $clearRes = $this->service->traceQueue(['A', 'B'], 'clear');
        $this->assertSame([], $clearRes['after']);
        $this->assertSame(0, $clearRes['size']);
    }

    public function testTraceLinkedListOperations(): void
    {
        // Append
        $appRes = $this->service->traceLinkedList(['A', 'B'], 'append', 'C');
        $this->assertSame(['A', 'B', 'C'], $appRes['after']);

        // Prepend
        $prepRes = $this->service->traceLinkedList(['B', 'C'], 'prepend', 'A');
        $this->assertSame(['A', 'B', 'C'], $prepRes['after']);

        // Remove
        $remRes = $this->service->traceLinkedList(['A', 'B', 'C'], 'remove', 'B');
        $this->assertSame(['A', 'C'], $remRes['after']);
        $this->assertTrue($remRes['result']);

        // Find
        $findRes = $this->service->traceLinkedList(['A', 'B', 'C'], 'find', 'B');
        $this->assertNotNull($findRes['result']);
    }

    public function testTraceHashTableOperations(): void
    {
        $initial = [
            ['key' => 'EMP-01', 'val' => 'Alice'],
            ['key' => 'EMP-02', 'val' => 'Bob'],
        ];

        // Set
        $setRes = $this->service->traceHashTable($initial, 'set', 'EMP-03', 'Charlie');
        $this->assertTrue($setRes['success']);
        $this->assertSame(3, $setRes['size']);

        // Get
        $getRes = $this->service->traceHashTable($initial, 'get', 'EMP-02');
        $this->assertSame('Bob', $getRes['result']);

        // ContainsKey
        $containsRes = $this->service->traceHashTable($initial, 'containsKey', 'EMP-01');
        $this->assertTrue($containsRes['result']);

        // Remove
        $remRes = $this->service->traceHashTable($initial, 'remove', 'EMP-01');
        $this->assertTrue($remRes['result']);
        $this->assertSame(1, $remRes['size']);
    }

    public function testTraceBstOperationsAndTraversals(): void
    {
        $keys = [50, 25, 75, 10, 40, 60, 90];

        // In-Order Traversal (LNR) produces sorted keys
        $inOrderRes = $this->service->traceBst($keys, 'in_order');
        $this->assertTrue($inOrderRes['success']);
        $traversedKeys = array_column($inOrderRes['traversal'], 'key');
        $this->assertSame([10, 25, 40, 50, 60, 75, 90], $traversedKeys);

        // Pre-Order Traversal (NLR)
        $preOrderRes = $this->service->traceBst($keys, 'pre_order');
        $preKeys = array_column($preOrderRes['traversal'], 'key');
        $this->assertSame(50, $preKeys[0]); // Root visited first

        // Post-Order Traversal (LRN)
        $postOrderRes = $this->service->traceBst($keys, 'post_order');
        $postKeys = array_column($postOrderRes['traversal'], 'key');
        $this->assertSame(50, end($postKeys)); // Root visited last

        // Insert
        $insertRes = $this->service->traceBst($keys, 'insert', 45);
        $this->assertSame(8, $insertRes['size']);

        // Search
        $searchRes = $this->service->traceBst($keys, 'search', 40);
        $this->assertNotNull($searchRes['result']);
    }
}
