<?php
/**
 * Data Structures & Algorithms (DSA) Laboratory Controller
 * Interactive demonstrations of custom Queue, Stack, LinkedList, HashTable, BST, and Recursion
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Controllers;

use Core\Controller;
use App\Dsa\Structures\Queue;
use App\Dsa\Structures\Stack;
use App\Dsa\Structures\LinkedList;
use App\Dsa\Structures\HashTable;
use App\Dsa\Structures\BinarySearchTree;
use App\Dsa\Algorithms\RecursionHelper;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\BubbleSort;

class DsaController extends Controller
{
    /**
     * Display the DSA Laboratory & Algorithm Demonstrator
     * Route: GET /dsa
     */
    public function playground(): void
    {
        // 1. Live Demonstration of FIFO Leave Queue
        $queue = new Queue();
        $sampleRequests = [
            ['id' => 1, 'name' => 'Maria Santos', 'type' => 'Vacation', 'days' => 3],
            ['id' => 2, 'name' => 'David Cruz', 'type' => 'Sick', 'days' => 1],
            ['id' => 3, 'name' => 'Juan Dela Cruz', 'type' => 'Emergency', 'days' => 2],
        ];
        foreach ($sampleRequests as $item) {
            $queue->enqueue($item);
        }

        // 2. Live Demonstration of LIFO Activity Stack
        $stack = new Stack();
        $sampleLogs = [
            ['id' => 101, 'action' => 'LOGIN', 'desc' => 'User admin authenticated'],
            ['id' => 102, 'action' => 'DEPT_CREATE', 'desc' => 'Created Department [IT]'],
            ['id' => 103, 'action' => 'EMP_UPDATE', 'desc' => 'Updated Employee EMP-004'],
            ['id' => 104, 'action' => 'LEAVE_APPROVE', 'desc' => 'Approved Leave #LR-001'],
        ];
        foreach ($sampleLogs as $log) {
            $stack->push($log);
        }

        // 3. Live Demonstration of Singly Linked List (Sequential History)
        $linkedList = new LinkedList();
        $historyEvents = [
            ['seq' => 1, 'event' => 'Onboarding Initialized', 'date' => '2024-01-15'],
            ['seq' => 2, 'event' => 'Probation Passed', 'date' => '2024-07-15'],
            ['seq' => 3, 'event' => 'Promoted to Senior Engineer', 'date' => '2025-06-01'],
            ['seq' => 4, 'event' => 'Vacation Leave Utilized (5d)', 'date' => '2026-03-10'],
        ];
        foreach ($historyEvents as $evt) {
            $linkedList->append($evt);
        }

        // 4. Live Demonstration of Hash Table (Separate Chaining Lookup)
        $hashTable = new HashTable(16);
        $employees = [
            'EMP-001' => ['name' => 'Alice Alvarez', 'role' => 'HR Director', 'dept' => 'HR'],
            'EMP-002' => ['name' => 'Bob Bautista', 'role' => 'Lead Architect', 'dept' => 'IT'],
            'EMP-003' => ['name' => 'Carol Cruz', 'role' => 'Financial Analyst', 'dept' => 'Finance'],
            'EMP-004' => ['name' => 'David Domingo', 'role' => 'Software Engineer', 'dept' => 'IT'],
        ];
        foreach ($employees as $key => $val) {
            $hashTable->set($key, $val);
        }

        // 5. Live Demonstration of Binary Search Tree & Recursive Traversals
        $bst = new BinarySearchTree();
        $bstItems = ['EMP-050', 'EMP-025', 'EMP-075', 'EMP-010', 'EMP-040', 'EMP-060', 'EMP-090'];
        foreach ($bstItems as $empId) {
            $bst->insert($empId, "Record {$empId}");
        }

        $inOrder = $bst->inOrderTraversal();
        $preOrder = $bst->preOrderTraversal();
        $postOrder = $bst->postOrderTraversal();
        $treeHeight = RecursionHelper::treeHeight($bst->getRoot());

        // 6. Algorithm Benchmarks
        $randomDataset = [];
        for ($i = 0; $i < 200; $i++) {
            $randomDataset[] = rand(1000, 9999);
        }

        $t0 = microtime(true);
        $bubbleSorted = BubbleSort::sort($randomDataset);
        $bubbleTime = round((microtime(true) - $t0) * 1000, 4);

        $t1 = microtime(true);
        $quickSorted = QuickSort::sort($randomDataset);
        $quickTime = round((microtime(true) - $t1) * 1000, 4);

        $t2 = microtime(true);
        $mergeSorted = MergeSort::sort($randomDataset);
        $mergeTime = round((microtime(true) - $t2) * 1000, 4);

        $this->view('dsa.playground', [
            'pageTitle'      => 'DSA Laboratory & Advanced Demonstrations',
            'queue'          => $queue,
            'stack'          => $stack,
            'linkedList'     => $linkedList,
            'hashTable'      => $hashTable,
            'bst'            => $bst,
            'traversals'     => [
                'inOrder'    => $inOrder,
                'preOrder'   => $preOrder,
                'postOrder'  => $postOrder,
                'height'     => $treeHeight,
            ],
            'benchmarks'     => [
                'bubble' => $bubbleTime,
                'quick'  => $quickTime,
                'merge'  => $mergeTime,
                'size'   => count($randomDataset),
            ],
            'user'           => currentUser(),
        ], 'hr');
    }
}
