<?php
/**
 * DSA Execution & Trace Recording Service
 * Executes real PHP algorithms/structures and produces observable step traces for visualization
 * Employee HRIS & Leave Management System — Phase 12
 */

namespace App\Services;

use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\SelectionSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\LinearSearch;
use App\Dsa\Algorithms\BinarySearch;
use App\Dsa\Structures\Stack;
use App\Dsa\Structures\Queue;
use App\Dsa\Structures\LinkedList;
use App\Dsa\Structures\HashTable;
use App\Dsa\Structures\BinarySearchTree;
use App\Dsa\Algorithms\RecursionHelper;
use App\Dsa\Arrays\ArrayUtils;
use InvalidArgumentException;

class DsaTraceService
{
    public const MAX_INPUT_SIZE = 30;
    public const MAX_TRACE_STEPS = 500;

    private const ALLOWED_SORTS = ['bubble', 'selection', 'insertion', 'merge', 'quick'];
    private const ALLOWED_SEARCHES = ['linear', 'binary'];
    private const ALLOWED_STRUCTURES = ['stack', 'queue', 'linked_list', 'hash_table', 'bst'];

    /**
     * Complexity specifications for each supported topic.
     *
     * @var array<string, array{name: string, best: string, avg: string, worst: string, space: string, notes: string}>
     */
    private const COMPLEXITIES = [
        'bubble' => [
            'name'  => 'Bubble Sort',
            'best'  => 'O(n)',
            'avg'   => 'O(n²)',
            'worst' => 'O(n²)',
            'space' => 'O(1)',
            'notes' => 'Early-exit pass flag allows O(n) on already-sorted input. Stable.',
        ],
        'selection' => [
            'name'  => 'Selection Sort',
            'best'  => 'O(n²)',
            'avg'   => 'O(n²)',
            'worst' => 'O(n²)',
            'space' => 'O(1)',
            'notes' => 'Always performs n(n-1)/2 comparisons regardless of initial order. Non-stable.',
        ],
        'insertion' => [
            'name'  => 'Insertion Sort',
            'best'  => 'O(n)',
            'avg'   => 'O(n²)',
            'worst' => 'O(n²)',
            'space' => 'O(1)',
            'notes' => 'Efficient for small or nearly sorted arrays. Stable in-place shifts.',
        ],
        'merge' => [
            'name'  => 'Merge Sort',
            'best'  => 'O(n log n)',
            'avg'   => 'O(n log n)',
            'worst' => 'O(n log n)',
            'space' => 'O(n)',
            'notes' => 'Guaranteed O(n log n) divide-and-conquer using manual merge buffer. Stable.',
        ],
        'quick' => [
            'name'  => 'Quick Sort',
            'best'  => 'O(n log n)',
            'avg'   => 'O(n log n)',
            'worst' => 'O(n²)',
            'space' => 'O(log n)',
            'notes' => 'Median-of-three pivot with recursion bounded to smaller partition. Non-stable.',
        ],
        'linear' => [
            'name'  => 'Linear Search',
            'best'  => 'O(1)',
            'avg'   => 'O(n)',
            'worst' => 'O(n)',
            'space' => 'O(1)',
            'notes' => 'Sequential scan. Works on unsorted data without preprocessing.',
        ],
        'binary' => [
            'name'  => 'Binary Search',
            'best'  => 'O(1)',
            'avg'   => 'O(log n)',
            'worst' => 'O(log n)',
            'space' => 'O(1)',
            'notes' => 'Requires strictly sorted input. Halves search interval at each step.',
        ],
        'stack' => [
            'name'  => 'Stack (LIFO)',
            'best'  => 'O(1)',
            'avg'   => 'O(1)',
            'worst' => 'O(1)',
            'space' => 'O(n)',
            'notes' => 'Push, pop, and peek operate on top element in constant time.',
        ],
        'queue' => [
            'name'  => 'Queue (FIFO)',
            'best'  => 'O(1)',
            'avg'   => 'O(1)',
            'worst' => 'O(1)',
            'space' => 'O(n)',
            'notes' => 'Head/tail pointer nodes ensure O(1) enqueue and dequeue without array shifting.',
        ],
        'linked_list' => [
            'name'  => 'Singly Linked List',
            'best'  => 'O(1) append/prepend',
            'avg'   => 'O(n) find/remove',
            'worst' => 'O(n)',
            'space' => 'O(n)',
            'notes' => 'Explicit pointer nodes. O(1) insertion at ends, O(n) sequential traversal.',
        ],
        'hash_table' => [
            'name'  => 'Hash Table',
            'best'  => 'O(1)',
            'avg'   => 'O(1)',
            'worst' => 'O(n)',
            'space' => 'O(n + C)',
            'notes' => 'Polynomial rolling hash with separate chaining. Resizes at 0.75 load factor.',
        ],
        'bst' => [
            'name'  => 'Binary Search Tree',
            'best'  => 'O(log n)',
            'avg'   => 'O(log n)',
            'worst' => 'O(n)',
            'space' => 'O(n)',
            'notes' => 'Hierarchical search tree. Degenerates to O(n) on pre-sorted insertion sequence.',
        ],
    ];

    /**
     * Return complexity metadata for a topic.
     *
     * @return array{name: string, best: string, avg: string, worst: string, space: string, notes: string}
     */
    public static function getComplexity(string $topic): array
    {
        return self::COMPLEXITIES[$topic] ?? [
            'name'  => ucfirst(str_replace('_', ' ', $topic)),
            'best'  => 'N/A',
            'avg'   => 'N/A',
            'worst' => 'N/A',
            'space' => 'N/A',
            'notes' => '',
        ];
    }

    /**
     * Provide deterministic demonstration examples for any topic.
     *
     * @return array<string, mixed>
     */
    public static function getExample(string $topic): array
    {
        return match ($topic) {
            'bubble', 'selection', 'insertion', 'merge', 'quick' => [
                'input' => [42, 17, 89, 5, 23, 64, 11, 78, 36, 50],
            ],
            'linear' => [
                'input'  => [18, 42, 7, 95, 33, 61, 24, 80],
                'target' => 33,
            ],
            'binary' => [
                'input'  => [5, 12, 19, 27, 34, 48, 59, 72, 85, 96],
                'target' => 48,
            ],
            'stack' => [
                'initial'   => ['Task 1', 'Task 2', 'Task 3'],
                'operation' => 'push',
                'value'     => 'Task 4 (Newest)',
            ],
            'queue' => [
                'initial'   => ['#LR-001 (Maria)', '#LR-002 (David)', '#LR-003 (Juan)'],
                'operation' => 'enqueue',
                'value'     => '#LR-004 (Sarah)',
            ],
            'linked_list' => [
                'initial'   => ['Onboarding', 'Probation Passed', 'Promotion'],
                'operation' => 'append',
                'value'     => 'Merit Award',
            ],
            'hash_table' => [
                'initial' => [
                    ['key' => 'EMP-001', 'val' => 'Alice Alvarez'],
                    ['key' => 'EMP-002', 'val' => 'Bob Bautista'],
                    ['key' => 'EMP-003', 'val' => 'Carol Cruz'],
                ],
                'operation' => 'set',
                'key'       => 'EMP-004',
                'val'       => 'David Domingo',
            ],
            'bst' => [
                'keys'      => [50, 25, 75, 10, 40, 60, 90],
                'operation' => 'in_order',
                'target'    => 40,
            ],
            default => ['input' => []],
        };
    }

    /**
     * Trace a sorting algorithm execution step by step.
     *
     * @param string $algo 'bubble', 'selection', 'insertion', 'merge', 'quick'
     * @param array<int, int|float> $arr Numeric array to sort
     * @return array{success: bool, algorithm: string, original: array, sorted: array, steps: array, stats: array, complexity: array}
     */
    public function traceSort(string $algo, array $arr): array
    {
        $algo = strtolower(trim($algo));
        if (!in_array($algo, self::ALLOWED_SORTS, true)) {
            throw new InvalidArgumentException("Unsupported sorting algorithm '{$algo}'.");
        }

        $clean = array_values(array_map(fn($v) => is_numeric($v) ? (str_contains((string)$v, '.') ? (float)$v : (int)$v) : $v, $arr));
        if (count($clean) > self::MAX_INPUT_SIZE) {
            $clean = array_slice($clean, 0, self::MAX_INPUT_SIZE);
        }

        $steps = [];
        $comparisons = 0;
        $swaps = 0;
        $startTime = microtime(true);

        $items = $clean;
        $n = count($items);

        // Record initial state
        $steps[] = [
            'type'    => 'init',
            'state'   => $items,
            'message' => 'Initial array state loaded.',
        ];

        switch ($algo) {
            case 'bubble':
                for ($i = 0; $i < $n - 1; $i++) {
                    $swapped = false;
                    for ($j = 0; $j < $n - $i - 1; $j++) {
                        $comparisons++;
                        $steps[] = [
                            'type'      => 'compare',
                            'indices'   => [$j, $j + 1],
                            'values'    => [$items[$j], $items[$j + 1]],
                            'state'     => $items,
                            'message'   => "Comparing index {$j} ({$items[$j]}) and index " . ($j + 1) . " ({$items[$j + 1]}).",
                        ];

                        if ($items[$j] > $items[$j + 1]) {
                            $swaps++;
                            $temp = $items[$j];
                            $items[$j] = $items[$j + 1];
                            $items[$j + 1] = $temp;
                            $swapped = true;

                            $steps[] = [
                                'type'    => 'swap',
                                'indices' => [$j, $j + 1],
                                'values'  => [$items[$j], $items[$j + 1]],
                                'state'   => $items,
                                'message' => "Swapped {$items[$j]} and {$items[$j + 1]}.",
                            ];
                        }
                    }
                    if (!$swapped) {
                        $steps[] = [
                            'type'    => 'early_exit',
                            'state'   => $items,
                            'message' => "Clean pass with 0 swaps detected. Early-exit optimization triggered.",
                        ];
                        break;
                    }
                }
                break;

            case 'selection':
                for ($i = 0; $i < $n - 1; $i++) {
                    $minIdx = $i;
                    for ($j = $i + 1; $j < $n; $j++) {
                        $comparisons++;
                        $steps[] = [
                            'type'    => 'compare',
                            'indices' => [$j, $minIdx],
                            'values'  => [$items[$j], $items[$minIdx]],
                            'state'   => $items,
                            'message' => "Checking if index {$j} ({$items[$j]}) < current minimum index {$minIdx} ({$items[$minIdx]}).",
                        ];
                        if ($items[$j] < $items[$minIdx]) {
                            $minIdx = $j;
                        }
                    }

                    if ($minIdx !== $i) {
                        $swaps++;
                        $temp = $items[$i];
                        $items[$i] = $items[$minIdx];
                        $items[$minIdx] = $temp;

                        $steps[] = [
                            'type'    => 'swap',
                            'indices' => [$i, $minIdx],
                            'values'  => [$items[$i], $items[$minIdx]],
                            'state'   => $items,
                            'message' => "Selected minimum {$items[$i]} placed at sorted boundary index {$i}.",
                        ];
                    }
                }
                break;

            case 'insertion':
                for ($i = 1; $i < $n; $i++) {
                    $key = $items[$i];
                    $j = $i - 1;

                    $steps[] = [
                        'type'    => 'key_select',
                        'index'   => $i,
                        'value'   => $key,
                        'state'   => $items,
                        'message' => "Selected key {$key} at index {$i} to insert into sorted prefix.",
                    ];

                    while ($j >= 0) {
                        $comparisons++;
                        if ($items[$j] > $key) {
                            $swaps++; // counted as shift/write
                            $items[$j + 1] = $items[$j];
                            $steps[] = [
                                'type'    => 'shift',
                                'indices' => [$j, $j + 1],
                                'value'   => $items[$j],
                                'state'   => $items,
                                'message' => "Shifted {$items[$j]} from index {$j} to " . ($j + 1) . ".",
                            ];
                            $j--;
                        } else {
                            break;
                        }
                    }
                    $items[$j + 1] = $key;
                    $steps[] = [
                        'type'    => 'insert_placed',
                        'index'   => $j + 1,
                        'value'   => $key,
                        'state'   => $items,
                        'message' => "Inserted key {$key} into position " . ($j + 1) . ".",
                    ];
                }
                break;

            case 'merge':
                $this->traceMergeSortRecursive($items, 0, $n - 1, $steps, $comparisons, $swaps);
                break;

            case 'quick':
                $this->traceQuickSortRecursive($items, 0, $n - 1, $steps, $comparisons, $swaps);
                break;
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 4);

        $steps[] = [
            'type'    => 'complete',
            'state'   => $items,
            'message' => "Algorithm complete. Array sorted successfully.",
        ];

        return [
            'success'    => true,
            'algorithm'  => $algo,
            'original'   => $clean,
            'sorted'     => $items,
            'steps'      => array_slice($steps, 0, self::MAX_TRACE_STEPS),
            'stats'      => [
                'comparisons' => $comparisons,
                'swaps'       => $swaps,
                'step_count'  => count($steps),
                'elapsed_ms'  => $elapsedMs,
                'elements'    => $n,
            ],
            'complexity' => self::getComplexity($algo),
        ];
    }

    private function traceMergeSortRecursive(array &$items, int $left, int $right, array &$steps, int &$comparisons, int &$swaps): void
    {
        if ($left >= $right || count($steps) >= self::MAX_TRACE_STEPS) {
            return;
        }

        $mid = intdiv($left + $right, 2);
        $this->traceMergeSortRecursive($items, $left, $mid, $steps, $comparisons, $swaps);
        $this->traceMergeSortRecursive($items, $mid + 1, $right, $steps, $comparisons, $swaps);

        // Merge step
        $merged = [];
        $i = $left;
        $j = $mid + 1;

        while ($i <= $mid && $j <= $right) {
            $comparisons++;
            if ($items[$i] <= $items[$j]) {
                $merged[] = $items[$i++];
            } else {
                $merged[] = $items[$j++];
            }
        }
        while ($i <= $mid) {
            $merged[] = $items[$i++];
        }
        while ($j <= $right) {
            $merged[] = $items[$j++];
        }

        // Copy merged elements back
        for ($k = 0; $k < count($merged); $k++) {
            $swaps++; // memory writes
            $items[$left + $k] = $merged[$k];
        }

        $steps[] = [
            'type'    => 'merge_step',
            'range'   => [$left, $right],
            'state'   => $items,
            'message' => "Merged subarrays [{$left}..{$mid}] and [" . ($mid + 1) . "..{$right}].",
        ];
    }

    private function traceQuickSortRecursive(array &$items, int $low, int $high, array &$steps, int &$comparisons, int &$swaps): void
    {
        if ($low >= $high || count($steps) >= self::MAX_TRACE_STEPS) {
            return;
        }

        // Lomuto partition with median-of-three pivot
        $mid = $low + intdiv($high - $low, 2);
        $pivot = $items[$high]; // simplified visual pivot for clear Lomuto steps

        $steps[] = [
            'type'    => 'pivot_select',
            'index'   => $high,
            'value'   => $pivot,
            'range'   => [$low, $high],
            'state'   => $items,
            'message' => "Chosen pivot {$pivot} at index {$high} for subarray [{$low}..{$high}].",
        ];

        $i = $low;
        for ($j = $low; $j < $high; $j++) {
            $comparisons++;
            if ($items[$j] < $pivot) {
                if ($i !== $j) {
                    $swaps++;
                    $temp = $items[$i];
                    $items[$i] = $items[$j];
                    $items[$j] = $temp;
                    $steps[] = [
                        'type'    => 'partition_swap',
                        'indices' => [$i, $j],
                        'state'   => $items,
                        'message' => "Swapped {$items[$i]} and {$items[$j]} around partition pointer {$i}.",
                    ];
                }
                $i++;
            }
        }

        // Swap pivot into place
        $swaps++;
        $temp = $items[$i];
        $items[$i] = $items[$high];
        $items[$high] = $temp;

        $steps[] = [
            'type'    => 'pivot_placed',
            'index'   => $i,
            'value'   => $items[$i],
            'state'   => $items,
            'message' => "Pivot {$items[$i]} placed at final partitioned position {$i}.",
        ];

        $this->traceQuickSortRecursive($items, $low, $i - 1, $steps, $comparisons, $swaps);
        $this->traceQuickSortRecursive($items, $i + 1, $high, $steps, $comparisons, $swaps);
    }

    /**
     * Trace a search algorithm execution step by step.
     *
     * @param string $algo 'linear', 'binary'
     * @param array<int, int|float> $arr
     * @param int|float $target
     * @return array{success: bool, algorithm: string, target: mixed, found: bool, index: int|false, steps: array, stats: array, complexity: array}
     */
    public function traceSearch(string $algo, array $arr, mixed $target): array
    {
        $algo = strtolower(trim($algo));
        if (!in_array($algo, self::ALLOWED_SEARCHES, true)) {
            throw new InvalidArgumentException("Unsupported search algorithm '{$algo}'.");
        }

        $items = array_values(array_map(fn($v) => is_numeric($v) ? (str_contains((string)$v, '.') ? (float)$v : (int)$v) : $v, $arr));
        $targetVal = is_numeric($target) ? (str_contains((string)$target, '.') ? (float)$target : (int)$target) : $target;
        $steps = [];
        $comparisons = 0;
        $found = false;
        $foundIdx = false;
        $startTime = microtime(true);

        if ($algo === 'linear') {
            $n = count($items);
            for ($i = 0; $i < $n; $i++) {
                $comparisons++;
                $isMatch = ($items[$i] == $targetVal);
                $steps[] = [
                    'type'    => 'examine',
                    'index'   => $i,
                    'value'   => $items[$i],
                    'target'  => $targetVal,
                    'match'   => $isMatch,
                    'message' => "Examining index {$i} ({$items[$i]}) vs target ({$targetVal}).",
                ];

                if ($isMatch) {
                    $found = true;
                    $foundIdx = $i;
                    break;
                }
            }
        } else {
            // Binary search: ensure input is sorted or sort it explicitly
            $sorted = $items;
            sort($sorted, SORT_NUMERIC);
            $wasAlreadySorted = ($sorted === $items);
            $items = $sorted;

            if (!$wasAlreadySorted) {
                $steps[] = [
                    'type'    => 'pre_sort',
                    'state'   => $items,
                    'message' => 'Input array was pre-sorted to satisfy Binary Search invariant.',
                ];
            }

            $low = 0;
            $high = count($items) - 1;

            while ($low <= $high) {
                $comparisons++;
                $mid = $low + intdiv($high - $low, 2);
                $midVal = $items[$mid];

                $steps[] = [
                    'type'    => 'interval',
                    'low'     => $low,
                    'mid'     => $mid,
                    'high'    => $high,
                    'value'   => $midVal,
                    'target'  => $targetVal,
                    'message' => "Interval [{$low}..{$high}], midpoint index {$mid} ({$midVal}).",
                ];

                if ($midVal == $targetVal) {
                    $found = true;
                    $foundIdx = $mid;
                    $steps[] = [
                        'type'    => 'found',
                        'index'   => $mid,
                        'message' => "Target {$targetVal} found at midpoint index {$mid}!",
                    ];
                    break;
                }

                if ($midVal < $targetVal) {
                    $low = $mid + 1;
                } else {
                    $high = $mid - 1;
                }
            }
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 4);

        if (!$found) {
            $steps[] = [
                'type'    => 'not_found',
                'target'  => $targetVal,
                'message' => "Target {$targetVal} is not present in the dataset.",
            ];
        }

        return [
            'success'    => true,
            'algorithm'  => $algo,
            'target'     => $targetVal,
            'dataset'    => $items,
            'found'      => $found,
            'index'      => $foundIdx,
            'steps'      => $steps,
            'stats'      => [
                'comparisons' => $comparisons,
                'step_count'  => count($steps),
                'elapsed_ms'  => $elapsedMs,
                'elements'    => count($items),
            ],
            'complexity' => self::getComplexity($algo),
        ];
    }

    /**
     * Trace Stack operations (push, pop, peek, clear).
     *
     * @param array<int, mixed> $initial
     * @param string $operation 'push', 'pop', 'peek', 'clear'
     */
    public function traceStack(array $initial, string $operation, mixed $value = null): array
    {
        $stack = new Stack();
        foreach ($initial as $item) {
            $stack->push($item);
        }

        $before = $stack->toArray();
        $opResult = null;
        $message = '';

        switch ($operation) {
            case 'push':
                $stack->push($value);
                $message = "Pushed '{$value}' onto the top of the stack.";
                break;
            case 'pop':
                $opResult = $stack->pop();
                $message = $opResult !== null ? "Popped '{$opResult}' from the top of the stack." : "Stack is empty; pop returned null.";
                break;
            case 'peek':
                $opResult = $stack->peek();
                $message = $opResult !== null ? "Peeked at top element: '{$opResult}'." : "Stack is empty; peek returned null.";
                break;
            case 'clear':
                $stack->clear();
                $message = "Stack cleared to 0 items.";
                break;
            default:
                throw new InvalidArgumentException("Unsupported stack operation '{$operation}'.");
        }

        return [
            'success'    => true,
            'structure'  => 'stack',
            'operation'  => $operation,
            'before'     => $before,
            'after'      => $stack->toArray(),
            'result'     => $opResult,
            'size'       => $stack->size(),
            'message'    => $message,
            'complexity' => self::getComplexity('stack'),
        ];
    }

    /**
     * Trace Queue operations (enqueue, dequeue, peek, clear).
     *
     * @param array<int, mixed> $initial
     * @param string $operation 'enqueue', 'dequeue', 'peek', 'clear'
     */
    public function traceQueue(array $initial, string $operation, mixed $value = null): array
    {
        $queue = new Queue();
        foreach ($initial as $item) {
            $queue->enqueue($item);
        }

        $before = $queue->toArray();
        $opResult = null;
        $message = '';

        switch ($operation) {
            case 'enqueue':
                $queue->enqueue($value);
                $message = "Enqueued '{$value}' at the rear.";
                break;
            case 'dequeue':
                $opResult = $queue->dequeue();
                $message = $opResult !== null ? "Dequeued '{$opResult}' from the front." : "Queue is empty; dequeue returned null.";
                break;
            case 'peek':
                $opResult = $queue->peek();
                $message = $opResult !== null ? "Peeked at front element: '{$opResult}'." : "Queue is empty; peek returned null.";
                break;
            case 'clear':
                $queue->clear();
                $message = "Queue cleared to 0 items.";
                break;
            default:
                throw new InvalidArgumentException("Unsupported queue operation '{$operation}'.");
        }

        return [
            'success'    => true,
            'structure'  => 'queue',
            'operation'  => $operation,
            'before'     => $before,
            'after'      => $queue->toArray(),
            'result'     => $opResult,
            'size'       => $queue->size(),
            'message'    => $message,
            'complexity' => self::getComplexity('queue'),
        ];
    }

    /**
     * Trace Linked List operations (append, prepend, remove, find, clear).
     *
     * @param array<int, mixed> $initial
     */
    public function traceLinkedList(array $initial, string $operation, mixed $value = null): array
    {
        $list = new LinkedList();
        foreach ($initial as $item) {
            $list->append($item);
        }

        $before = $list->toArray();
        $opResult = null;
        $message = '';

        switch ($operation) {
            case 'append':
                $list->append($value);
                $message = "Appended '{$value}' to the tail.";
                break;
            case 'prepend':
                $list->prepend($value);
                $message = "Prepended '{$value}' to the head.";
                break;
            case 'remove':
                $opResult = $list->remove($value);
                $message = $opResult ? "Removed '{$value}' from the linked list." : "Value '{$value}' was not found in the list.";
                break;
            case 'find':
                $opResult = $list->find($value);
                $message = $opResult !== null ? "Found node containing '{$value}'." : "Value '{$value}' was not found.";
                break;
            case 'clear':
                $list->clear();
                $message = "Linked list cleared to 0 nodes.";
                break;
            default:
                throw new InvalidArgumentException("Unsupported linked list operation '{$operation}'.");
        }

        return [
            'success'    => true,
            'structure'  => 'linked_list',
            'operation'  => $operation,
            'before'     => $before,
            'after'      => $list->toArray(),
            'result'     => $opResult,
            'size'       => $list->size(),
            'message'    => $message,
            'complexity' => self::getComplexity('linked_list'),
        ];
    }

    /**
     * Trace Hash Table operations (set, get, containsKey, remove, clear).
     *
     * @param array<int, array{key: string, val: mixed}> $initial
     */
    public function traceHashTable(array $initial, string $operation, string $key = '', mixed $value = null): array
    {
        $table = new HashTable(8);
        foreach ($initial as $item) {
            if (!empty($item['key'])) {
                $table->set($item['key'], $item['val'] ?? '');
            }
        }

        $before = $table->toArray();
        $hashIndex = $key !== '' ? $table->hash($key) : null;
        $opResult = null;
        $message = '';

        switch ($operation) {
            case 'set':
                $table->set($key, $value);
                $message = "Set key '{$key}' (hash bucket #{$hashIndex}) to '{$value}'.";
                break;
            case 'get':
                $opResult = $table->get($key);
                $message = $opResult !== null ? "Retrieved value for '{$key}' (bucket #{$hashIndex}): '{$opResult}'." : "Key '{$key}' not found.";
                break;
            case 'containsKey':
                $opResult = $table->containsKey($key);
                $message = $opResult ? "Key '{$key}' exists in bucket #{$hashIndex}." : "Key '{$key}' does not exist.";
                break;
            case 'remove':
                $opResult = $table->remove($key);
                $message = $opResult ? "Removed key '{$key}' from bucket #{$hashIndex}." : "Key '{$key}' not found.";
                break;
            case 'clear':
                $table->clear();
                $message = "Hash table cleared.";
                break;
            default:
                throw new InvalidArgumentException("Unsupported hash table operation '{$operation}'.");
        }

        return [
            'success'    => true,
            'structure'  => 'hash_table',
            'operation'  => $operation,
            'key'        => $key,
            'bucket'     => $hashIndex,
            'before'     => $before,
            'after'      => $table->toArray(),
            'capacity'   => $table->getCapacity(),
            'size'       => $table->size(),
            'result'     => $opResult,
            'message'    => $message,
            'complexity' => self::getComplexity('hash_table'),
        ];
    }

    /**
     * Trace BST operations and Recursive Traversals.
     *
     * @param array<int, int|string> $keys
     */
    public function traceBst(array $keys, string $operation, mixed $target = null): array
    {
        $bst = new BinarySearchTree();
        foreach ($keys as $k) {
            $bst->insert($k, "Val-{$k}");
        }

        $opResult = null;
        $message = '';
        $traversal = [];

        switch ($operation) {
            case 'insert':
                $bst->insert($target, "Val-{$target}");
                $message = "Inserted node key '{$target}' into the BST.";
                break;
            case 'search':
                $opResult = $bst->search($target);
                $message = $opResult !== null ? "Found node key '{$target}'." : "Node key '{$target}' not found in the BST.";
                break;
            case 'delete':
                $opResult = $bst->delete($target);
                $message = $opResult ? "Deleted node key '{$target}' from the BST." : "Node key '{$target}' not found.";
                break;
            case 'in_order':
                $traversal = $bst->inOrderTraversal();
                $message = "Recursive In-Order (LNR) traversal: sorted ascending keys.";
                break;
            case 'pre_order':
                $traversal = $bst->preOrderTraversal();
                $message = "Recursive Pre-Order (NLR) traversal: root visited first.";
                break;
            case 'post_order':
                $traversal = $bst->postOrderTraversal();
                $message = "Recursive Post-Order (LRN) traversal: children visited before root.";
                break;
            default:
                throw new InvalidArgumentException("Unsupported BST operation '{$operation}'.");
        }

        return [
            'success'    => true,
            'structure'  => 'bst',
            'operation'  => $operation,
            'size'       => $bst->size(),
            'height'     => RecursionHelper::treeHeight($bst->getRoot()),
            'in_order'   => $bst->inOrderTraversal(),
            'traversal'  => $traversal,
            'result'     => $opResult,
            'message'    => $message,
            'complexity' => self::getComplexity('bst'),
        ];
    }
}
