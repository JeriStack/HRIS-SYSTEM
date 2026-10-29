<?php
/**
 * Data Structures & Algorithms (DSA) Laboratory Controller
 * Powers the interactive DSA Playground, Step-by-Step Visualizer, and Execution API
 * Employee HRIS & Leave Management System — Phase 12
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Services\DsaTraceService;
use InvalidArgumentException;

class DsaController extends Controller
{
    private DsaTraceService $traceService;

    public function __construct()
    {
        $this->traceService = new DsaTraceService();
    }

    /**
     * Display the interactive DSA Playground (Screen A)
     * Route: GET /dsa
     */
    public function playground(): void
    {
        $topic = isset($_GET['topic']) ? trim((string)$_GET['topic']) : 'bubble';
        $example = DsaTraceService::getExample($topic);
        $complexity = DsaTraceService::getComplexity($topic);

        $this->view('dsa.playground', [
            'pageTitle'    => 'DSA Laboratory & Interactive Playground',
            'activeTab'    => 'playground',
            'currentTopic' => $topic,
            'example'      => $example,
            'complexity'   => $complexity,
            'user'         => currentUser(),
        ], 'hr');
    }

    /**
     * Display the animated DSA Visualizer (Screen B)
     * Route: GET /dsa/visualizer
     */
    public function visualizer(): void
    {
        $topic = isset($_GET['topic']) ? trim((string)$_GET['topic']) : 'quick';
        $example = DsaTraceService::getExample($topic);
        $complexity = DsaTraceService::getComplexity($topic);

        $this->view('dsa.visualizer', [
            'pageTitle'    => 'DSA Step-by-Step Visualizer',
            'activeTab'    => 'visualizer',
            'currentTopic' => $topic,
            'example'      => $example,
            'complexity'   => $complexity,
            'user'         => currentUser(),
        ], 'hr');
    }

    /**
     * Execute algorithm or structure operation and return structured JSON trace.
     * Route: POST /dsa/execute
     */
    public function execute(): void
    {
        // Parse input from JSON body or standard $_POST
        $raw = file_get_contents('php://input');
        $data = !empty($raw) ? json_decode($raw, true) : $_POST;

        if (!is_array($data)) {
            $data = $_POST;
        }

        $category = trim((string)($data['category'] ?? 'sort'));
        $topic    = trim((string)($data['topic'] ?? 'bubble'));

        try {
            $response = match ($category) {
                'sort' => $this->traceService->traceSort(
                    $topic,
                    is_array($data['input'] ?? null) ? $data['input'] : [42, 17, 89, 5, 23, 64]
                ),
                'search' => $this->traceService->traceSearch(
                    $topic,
                    is_array($data['input'] ?? null) ? $data['input'] : [5, 12, 19, 27, 34, 48],
                    $data['target'] ?? 27
                ),
                'stack' => $this->traceService->traceStack(
                    is_array($data['initial'] ?? null) ? $data['initial'] : ['Task 1', 'Task 2'],
                    (string)($data['operation'] ?? 'push'),
                    $data['value'] ?? 'New Item'
                ),
                'queue' => $this->traceService->traceQueue(
                    is_array($data['initial'] ?? null) ? $data['initial'] : ['#LR-001', '#LR-002'],
                    (string)($data['operation'] ?? 'enqueue'),
                    $data['value'] ?? '#LR-003'
                ),
                'linked_list' => $this->traceService->traceLinkedList(
                    is_array($data['initial'] ?? null) ? $data['initial'] : ['Node A', 'Node B'],
                    (string)($data['operation'] ?? 'append'),
                    $data['value'] ?? 'Node C'
                ),
                'hash_table' => $this->traceService->traceHashTable(
                    is_array($data['initial'] ?? null) ? $data['initial'] : [],
                    (string)($data['operation'] ?? 'set'),
                    (string)($data['key'] ?? 'EMP-001'),
                    $data['value'] ?? 'Sample Value'
                ),
                'bst' => $this->traceService->traceBst(
                    is_array($data['keys'] ?? null) ? $data['keys'] : [50, 25, 75],
                    (string)($data['operation'] ?? 'in_order'),
                    $data['target'] ?? 25
                ),
                default => throw new InvalidArgumentException("Invalid execution category '{$category}'."),
            };

            $this->json($response);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }
}
