<?php
/**
 * Comprehensive Automated Verification Suite for Data Structures & Algorithms
 * Employee HRIS & Leave Management System
 */

require_once __DIR__ . '/../core/bootstrap.php';

use App\Dsa\Structures\Queue;
use App\Dsa\Structures\Stack;
use App\Dsa\Structures\LinkedList;
use App\Dsa\Structures\HashTable;
use App\Dsa\Structures\ArrayWrapper;
use App\Dsa\Structures\BinarySearchTree;
use App\Dsa\Algorithms\BinarySearch;
use App\Dsa\Algorithms\LinearSearch;
use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\SelectionSort;
use App\Dsa\Algorithms\RecursionHelper;

$total = 0;
$passed = 0;
$failed = 0;

function assertDsa(string $category, string $desc, bool $condition): void
{
    global $total, $passed, $failed;
    $total++;
    if ($condition) {
        $passed++;
        echo "  [PASS] [{$category}] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] [{$category}] {$desc}\n";
    }
}

echo "====================================================================\n";
echo "   DATA STRUCTURES & ALGORITHMS (DSA) TEST SUITE\n";
echo "====================================================================\n";

// 1. Queue Tests (FIFO)
echo "\n--- 1. FIFO QUEUE TESTS ---\n";
$q = new Queue();
assertDsa('Queue', 'New queue is empty', $q->isEmpty() && $q->size() === 0);
$q->enqueue('Item 1');
$q->enqueue('Item 2');
$q->enqueue('Item 3');
assertDsa('Queue', 'Queue size is 3 after 3 enqueues', $q->size() === 3);
assertDsa('Queue', 'Peek returns front element (Item 1)', $q->peek() === 'Item 1');
assertDsa('Queue', 'Dequeue returns front element (Item 1)', $q->dequeue() === 'Item 1');
assertDsa('Queue', 'Queue size decreases to 2', $q->size() === 2);
assertDsa('Queue', 'Next dequeue returns Item 2', $q->dequeue() === 'Item 2');
assertDsa('Queue', 'Final dequeue returns Item 3', $q->dequeue() === 'Item 3');
assertDsa('Queue', 'Queue is now empty', $q->isEmpty());
assertDsa('Queue', 'Dequeue on empty queue returns null safely', $q->dequeue() === null);

// 2. Stack Tests (LIFO)
echo "\n--- 2. LIFO STACK TESTS ---\n";
$s = new Stack();
assertDsa('Stack', 'New stack is empty', $s->isEmpty() && $s->size() === 0);
$s->push('Log 1');
$s->push('Log 2');
$s->push('Log 3');
assertDsa('Stack', 'Stack size is 3 after 3 pushes', $s->size() === 3);
assertDsa('Stack', 'Peek returns top element (Log 3)', $s->peek() === 'Log 3');
assertDsa('Stack', 'Pop returns top element (Log 3)', $s->pop() === 'Log 3');
assertDsa('Stack', 'Next pop returns Log 2', $s->pop() === 'Log 2');
assertDsa('Stack', 'Final pop returns Log 1', $s->pop() === 'Log 1');
assertDsa('Stack', 'Stack is empty', $s->isEmpty());
assertDsa('Stack', 'Pop on empty stack returns null safely', $s->pop() === null);

// 3. Singly Linked List Tests
echo "\n--- 3. LINKED LIST TESTS ---\n";
$ll = new LinkedList();
assertDsa('LinkedList', 'New list is empty', $ll->isEmpty() && $ll->size() === 0);
$ll->insertLast(['id' => 10, 'name' => 'Leave #10']);
$ll->insertLast(['id' => 20, 'name' => 'Leave #20']);
$ll->insertFirst(['id' => 5, 'name' => 'Leave #5']);
assertDsa('LinkedList', 'List size is 3 after inserts', $ll->size() === 3);
$found = $ll->search(20);
assertDsa('LinkedList', 'Search finds existing record by ID', $found !== null && $found['name'] === 'Leave #20');
assertDsa('LinkedList', 'Search returns null for nonexistent ID', $ll->search(999) === null);
assertDsa('LinkedList', 'Delete head element succeeds', $ll->delete(5));
assertDsa('LinkedList', 'List size is now 2', $ll->size() === 2);
assertDsa('LinkedList', 'Delete middle element succeeds', $ll->delete(10));
assertDsa('LinkedList', 'Delete last element succeeds', $ll->delete(20));
assertDsa('LinkedList', 'List is empty after all deletes', $ll->isEmpty());

// 4. Hash Table with Separate Chaining Tests
echo "\n--- 4. HASH TABLE TESTS ---\n";
$ht = new HashTable(8);
assertDsa('HashTable', 'New hash table is empty', $ht->isEmpty() && $ht->size() === 0);
$ht->insert('EMP001', 'Alice Johnson');
$ht->insert('EMP002', 'Bob Smith');
$ht->insert('EMP003', 'Charlie Brown');
assertDsa('HashTable', 'Size is 3 after insertions', $ht->size() === 3);
assertDsa('HashTable', 'Search retrieves EMP001', $ht->search('EMP001') === 'Alice Johnson');
assertDsa('HashTable', 'Search retrieves EMP002', $ht->search('EMP002') === 'Bob Smith');
$ht->insert('EMP001', 'Alice Updated');
assertDsa('HashTable', 'Re-inserting same key updates value without increasing size', $ht->search('EMP001') === 'Alice Updated' && $ht->size() === 3);
assertDsa('HashTable', 'Search returns null for nonexistent key', $ht->search('EMP999') === null);
assertDsa('HashTable', 'Delete removes existing key', $ht->delete('EMP002'));
assertDsa('HashTable', 'Search returns null for deleted key', $ht->search('EMP002') === null);
assertDsa('HashTable', 'Size decreases to 2 after deletion', $ht->size() === 2);

// 5. ArrayWrapper Tests
echo "\n--- 5. ARRAY WRAPPER TESTS ---\n";
$aw = new ArrayWrapper([100, 200, 300]);
assertDsa('ArrayWrapper', 'Size is 3', $aw->size() === 3);
assertDsa('ArrayWrapper', 'Get element at index 1 is 200', $aw->get(1) === 200);
$aw->insert(150, 1);
assertDsa('ArrayWrapper', 'Insert at index 1 shifts elements', $aw->get(1) === 150 && $aw->get(2) === 200);
assertDsa('ArrayWrapper', 'Delete index 0 removes 100', $aw->delete(0) && $aw->get(0) === 150);

// 6. Binary Search Tree Tests
echo "\n--- 6. BINARY SEARCH TREE TESTS ---\n";
$bst = new BinarySearchTree();
$keys = [50, 30, 70, 20, 40, 60, 80];
foreach ($keys as $k) {
    $bst->insert($k, "Value-{$k}");
}
assertDsa('BST', 'Tree size is 7', $bst->size() === 7);
assertDsa('BST', 'Search finds existing key 40', $bst->search(40) === 'Value-40');
assertDsa('BST', 'Search returns null for nonexistent key 99', $bst->search(99) === null);
$inOrder = $bst->inOrderTraversal();
$inOrderKeys = array_column($inOrder, 'key');
assertDsa('BST', 'In-order traversal yields strictly sorted keys', $inOrderKeys === [20, 30, 40, 50, 60, 70, 80]);
assertDsa('BST', 'Delete node with two children (50) succeeds', $bst->delete(50));
assertDsa('BST', 'Tree size is now 6', $bst->size() === 6);
assertDsa('BST', 'Search for deleted root returns null', $bst->search(50) === null);
$inOrderAfter = array_column($bst->inOrderTraversal(), 'key');
assertDsa('BST', 'In-order traversal remains sorted after deletion', $inOrderAfter === [20, 30, 40, 60, 70, 80]);

// 7. Sorting Algorithms Tests
echo "\n--- 7. SORTING ALGORITHMS TESTS ---\n";
$unsorted = [55, 12, 89, 42, 67, 3, 28, 99, 15];
$expected = [3, 12, 15, 28, 42, 55, 67, 89, 99];

assertDsa('BubbleSort', 'Sorts numeric array in ascending order', BubbleSort::sort($unsorted) === $expected);
assertDsa('QuickSort', 'Sorts numeric array in ascending order', QuickSort::sort($unsorted) === $expected);
assertDsa('MergeSort', 'Sorts numeric array in ascending order', MergeSort::sort($unsorted) === $expected);
assertDsa('InsertionSort', 'Sorts numeric array in ascending order', InsertionSort::sort($unsorted) === $expected);
assertDsa('SelectionSort', 'Sorts numeric array in ascending order', SelectionSort::sort($unsorted) === $expected);

// Edge cases for sorting
$single = [42];
assertDsa('Sort Edge Case', 'Handles single-element array', QuickSort::sort($single) === [42]);
$empty = [];
assertDsa('Sort Edge Case', 'Handles empty array', MergeSort::sort($empty) === []);

// 8. Search Algorithms Tests
echo "\n--- 8. SEARCH ALGORITHMS TESTS ---\n";
$sortedData = [10, 20, 30, 40, 50, 60, 70, 80, 90];
assertDsa('BinarySearch', 'Finds element at beginning (index 0)', BinarySearch::search($sortedData, 10) === 0);
assertDsa('BinarySearch', 'Finds element in middle (index 4)', BinarySearch::search($sortedData, 50) === 4);
assertDsa('BinarySearch', 'Finds element at end (index 8)', BinarySearch::search($sortedData, 90) === 8);
assertDsa('BinarySearch', 'Returns false for missing element', BinarySearch::search($sortedData, 55) === false);

assertDsa('LinearSearch', 'Finds element in unsorted array', LinearSearch::search($unsorted, 67) === 4);
assertDsa('LinearSearch', 'Returns false for missing element', LinearSearch::search($unsorted, 999) === false);

// 9. Recursion Helper Tests
echo "\n--- 9. RECURSION HELPER TESTS ---\n";
assertDsa('Recursion', 'Factorial of 0 is 1', RecursionHelper::factorial(0) === 1);
assertDsa('Recursion', 'Factorial of 5 is 120', RecursionHelper::factorial(5) === 120);
assertDsa('Recursion', 'Fibonacci of 0 is 0', RecursionHelper::fibonacci(0) === 0);
assertDsa('Recursion', 'Fibonacci of 7 is 13', RecursionHelper::fibonacci(7) === 13);
assertDsa('Recursion', 'Recursive binary search finds element', RecursionHelper::binarySearchRecursive($sortedData, 40, 0, count($sortedData) - 1) === 3);

// Summary
echo "\n====================================================================\n";
echo "   DSA TEST RESULTS SUMMARY\n";
echo "====================================================================\n";
echo "Total Tests Run:  {$total}\n";
echo "Passed:           {$passed}\n";
echo "Failed:           {$failed}\n";
echo "Pass Rate:        " . round(($passed / $total) * 100, 2) . "%\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
