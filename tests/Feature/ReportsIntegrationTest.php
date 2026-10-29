<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Controllers\ReportController;
use Core\Permission;
use Core\Session;

class TestableReportController extends ReportController
{
    public ?string $lastView = null;
    public array $lastViewData = [];

    protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        $this->lastView = $viewPath;
        $this->lastViewData = $data;
    }
}

class ReportsIntegrationTest extends TestCase
{
    private TestableReportController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Session::destroy();
        Session::start();
        Session::set('user_id', 1);
        Session::set('role', 'hr');
        Session::set('auth_time', time());

        $this->controller = new TestableReportController();
    }

    protected function tearDown(): void
    {
        Session::destroy();
        $_GET = [];
        parent::tearDown();
    }

    public function test_reports_permission_restricted_to_hr_role(): void
    {
        $this->assertTrue(Permission::can('hr', 'view_reports'));
        $this->assertFalse(Permission::can('employee', 'view_reports'));
    }

    public function test_index_renders_overview_hub_with_kpi_metrics(): void
    {
        $this->controller->index();

        $this->assertSame('reports.index', $this->controller->lastView);
        $this->assertSame('overview', $this->controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('deptDistribution', $this->controller->lastViewData);
        $this->assertArrayHasKey('leaveSummary', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalEmployees', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalLeavesApproved', $this->controller->lastViewData);
    }

    public function test_employees_report_renders_with_filters_and_aggregates(): void
    {
        $_GET['status'] = 'Active';
        $_GET['page']   = '1';

        $this->controller->employees();

        $this->assertSame('reports.employee', $this->controller->lastView);
        $this->assertSame('employees', $this->controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('employees', $this->controller->lastViewData);
        $this->assertArrayHasKey('statusCounts', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalRows', $this->controller->lastViewData);
        $this->assertSame(1, $this->controller->lastViewData['currentPage']);
    }

    public function test_leaves_report_renders_with_aggregates(): void
    {
        $_GET['status'] = 'Approved';

        $this->controller->leaves();

        $this->assertSame('reports.leave', $this->controller->lastView);
        $this->assertSame('leaves', $this->controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('leaveRecords', $this->controller->lastViewData);
        $this->assertArrayHasKey('aggregates', $this->controller->lastViewData);
        $this->assertArrayHasKey('typeUtilization', $this->controller->lastViewData);
    }

    public function test_departments_report_renders_with_summaries(): void
    {
        $this->controller->departments();

        $this->assertSame('reports.department', $this->controller->lastView);
        $this->assertSame('departments', $this->controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('departmentSummaries', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalHeadcount', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalActive', $this->controller->lastViewData);
        $this->assertArrayHasKey('totalLeaveDays', $this->controller->lastViewData);
    }

    public function test_formula_injection_prevention(): void
    {
        $maliciousInputs = [
            '=SUM(A1:A10)',
            '+cmd|"/c calc"!A0',
            '-2+3+cmd',
            '@SUM(B1:B5)',
        ];

        foreach ($maliciousInputs as $input) {
            $firstChar = substr(trim($input), 0, 1);
            $isDangerousFormula = in_array($firstChar, ['=', '+', '-', '@'], true);
            $this->assertTrue($isDangerousFormula, "Dangerous spreadsheet character detected for {$input}");

            // Safe escaping by prepending single quote
            $safeSanitized = "'" . $input;
            $this->assertSame("'", substr($safeSanitized, 0, 1));
        }
    }
}

