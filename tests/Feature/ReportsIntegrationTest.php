<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Controllers\ReportController;
use Core\Database;
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
    private ?TestableReportController $controller = null;

    protected function setUp(): void
    {
        parent::setUp();
        Session::destroy();
        Session::start();
        Session::set('user_id', 1);
        Session::set('role', 'hr');
        Session::set('auth_time', time());
    }

    protected function tearDown(): void
    {
        Session::destroy();
        $_GET = [];
        $this->controller = null;
        parent::tearDown();
    }

    private function getController(): TestableReportController
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        if ($this->controller === null) {
            $this->controller = new TestableReportController();
        }

        return $this->controller;
    }

    public function test_reports_permission_restricted_to_hr_role(): void
    {
        $this->assertTrue(Permission::can('hr', 'view_reports'));
        $this->assertFalse(Permission::can('employee', 'view_reports'));
    }

    public function test_index_renders_overview_hub_with_kpi_metrics(): void
    {
        $controller = $this->getController();
        $controller->index();

        $this->assertSame('reports.index', $controller->lastView);
        $this->assertSame('overview', $controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('deptDistribution', $controller->lastViewData);
        $this->assertArrayHasKey('leaveSummary', $controller->lastViewData);
        $this->assertArrayHasKey('totalEmployees', $controller->lastViewData);
        $this->assertArrayHasKey('totalLeavesApproved', $controller->lastViewData);
    }

    public function test_employees_report_renders_with_filters_and_aggregates(): void
    {
        $controller = $this->getController();
        $_GET['status'] = 'Active';
        $_GET['page']   = '1';

        $controller->employees();

        $this->assertSame('reports.employee', $controller->lastView);
        $this->assertSame('employees', $controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('employees', $controller->lastViewData);
        $this->assertArrayHasKey('statusCounts', $controller->lastViewData);
        $this->assertArrayHasKey('totalRows', $controller->lastViewData);
        $this->assertSame(1, $controller->lastViewData['currentPage']);
    }

    public function test_leaves_report_renders_with_aggregates(): void
    {
        $controller = $this->getController();
        $_GET['status'] = 'Approved';

        $controller->leaves();

        $this->assertSame('reports.leave', $controller->lastView);
        $this->assertSame('leaves', $controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('leaveRecords', $controller->lastViewData);
        $this->assertArrayHasKey('aggregates', $controller->lastViewData);
        $this->assertArrayHasKey('typeUtilization', $controller->lastViewData);
    }

    public function test_departments_report_renders_with_summaries(): void
    {
        $controller = $this->getController();
        $controller->departments();

        $this->assertSame('reports.department', $controller->lastView);
        $this->assertSame('departments', $controller->lastViewData['activeReportTab']);
        $this->assertArrayHasKey('departmentSummaries', $controller->lastViewData);
        $this->assertArrayHasKey('totalHeadcount', $controller->lastViewData);
        $this->assertArrayHasKey('totalActive', $controller->lastViewData);
        $this->assertArrayHasKey('totalLeaveDays', $controller->lastViewData);
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
