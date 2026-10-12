<?php
/**
 * Data Structures & Algorithms (DSA) Laboratory Controller
 * Interactive demonstrations of custom Queue, Stack, BST, and Sorting algorithms
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use App\Dsa\Structures\Queue;
use App\Dsa\Structures\Stack;
use App\Dsa\Structures\BinarySearchTree;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\BinarySearch;

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
        $sampleRequests = ['#LR-001 (Maria Santos)', '#LR-002 (David Cruz)', '#LR-003 (Juan Dela Cruz)'];
        foreach ($sampleRequests as $item) {
            $queue->enqueue($item);
        }

        // 2. Live Demonstration of LIFO Activity Stack
        $stack = new Stack();
        $sampleLogs = ['User admin logged in', 'Created Department [IT]', 'Updated Employee EMP004', 'Approved Leave #LR-001'];
        foreach ($sampleLogs as $log) {
            $stack->push($log);
        }

        // 3. Live Demonstration of Binary Search Tree
        $bst = new BinarySearchTree();
        $bstItems = ['EMP050', 'EMP025', 'EMP075', 'EMP010', 'EMP040', 'EMP060', 'EMP090'];
        foreach ($bstItems as $empId) {
            $bst->insert($empId, "Record {$empId}");
        }

        // 4. Algorithm Benchmarks
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
            'pageTitle'      => 'DSA Laboratory & Algorithm Benchmarks',
            'queue'          => $queue,
            'stack'          => $stack,
            'bst'            => $bst,
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
