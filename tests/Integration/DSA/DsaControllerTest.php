<?php

namespace Tests\Integration\DSA;

use App\Controllers\DsaController;
use Core\Session;
use PHPUnit\Framework\TestCase;

class TestableDsaController extends DsaController
{
    public mixed $lastJsonData = null;
    public int $lastStatusCode = 200;
    public ?string $lastView = null;
    public array $lastViewData = [];

    protected function json(mixed $data, int $statusCode = 200): void
    {
        $this->lastJsonData = $data;
        $this->lastStatusCode = $statusCode;
    }

    protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        $this->lastView = $viewPath;
        $this->lastViewData = $data;
    }
}

class DsaControllerTest extends TestCase
{
    private TestableDsaController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Session::destroy();
        Session::start();
        Session::set('user_id', 1);
        Session::set('role', 'hr');
        Session::set('auth_time', time());

        $this->controller = new TestableDsaController();
    }

    protected function tearDown(): void
    {
        Session::destroy();
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    public function testPlaygroundRendersCorrectViewAndTopic(): void
    {
        $_GET['topic'] = 'quick';
        $this->controller->playground();

        $this->assertSame('dsa.playground', $this->controller->lastView);
        $this->assertSame('quick', $this->controller->lastViewData['currentTopic']);
        $this->assertSame('playground', $this->controller->lastViewData['activeTab']);
        $this->assertArrayHasKey('complexity', $this->controller->lastViewData);
    }

    public function testVisualizerRendersCorrectView(): void
    {
        $_GET['topic'] = 'merge';
        $this->controller->visualizer();

        $this->assertSame('dsa.visualizer', $this->controller->lastView);
        $this->assertSame('merge', $this->controller->lastViewData['currentTopic']);
        $this->assertSame('visualizer', $this->controller->lastViewData['activeTab']);
        $this->assertArrayHasKey('example', $this->controller->lastViewData);
    }

    public function testExecuteSortActionViaPost(): void
    {
        $_POST = [
            'category' => 'sort',
            'topic'    => 'bubble',
            'input'    => [30, 10, 20],
        ];

        $this->controller->execute();

        $this->assertSame(200, $this->controller->lastStatusCode);
        $this->assertTrue($this->controller->lastJsonData['success']);
        $this->assertSame([10, 20, 30], $this->controller->lastJsonData['sorted']);
        $this->assertNotEmpty($this->controller->lastJsonData['steps']);
    }

    public function testExecuteSearchActionViaPost(): void
    {
        $_POST = [
            'category' => 'search',
            'topic'    => 'linear',
            'input'    => [15, 25, 35, 45],
            'target'   => 35,
        ];

        $this->controller->execute();

        $this->assertSame(200, $this->controller->lastStatusCode);
        $this->assertTrue($this->controller->lastJsonData['success']);
        $this->assertTrue($this->controller->lastJsonData['found']);
        $this->assertSame(2, $this->controller->lastJsonData['index']);
    }

    public function testExecuteStackActionViaPost(): void
    {
        $_POST = [
            'category'  => 'stack',
            'operation' => 'push',
            'initial'   => ['Alpha', 'Beta'],
            'value'     => 'Gamma',
        ];

        $this->controller->execute();

        $this->assertSame(200, $this->controller->lastStatusCode);
        $this->assertTrue($this->controller->lastJsonData['success']);
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->controller->lastJsonData['after']);
        $this->assertSame(3, $this->controller->lastJsonData['size']);
    }

    public function testExecuteInvalidCategoryReturns400(): void
    {
        $_POST = [
            'category' => 'invalid_cat',
        ];

        $this->controller->execute();

        $this->assertSame(400, $this->controller->lastStatusCode);
        $this->assertFalse($this->controller->lastJsonData['success']);
        $this->assertStringContainsString("Invalid execution category", $this->controller->lastJsonData['error']);
    }

    public function testExecuteInvalidTopicReturns400(): void
    {
        $_POST = [
            'category' => 'sort',
            'topic'    => 'quantum_sort',
        ];

        $this->controller->execute();

        $this->assertSame(400, $this->controller->lastStatusCode);
        $this->assertFalse($this->controller->lastJsonData['success']);
        $this->assertStringContainsString("Unsupported sorting algorithm", $this->controller->lastJsonData['error']);
    }
}

