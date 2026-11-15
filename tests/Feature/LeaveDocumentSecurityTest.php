<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Leave;
use App\Services\ConfidentialLeaveService;
use App\Services\LeaveDocumentService;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Security & privacy verification for leave supporting document uploads, downloads,
 * and confidential statutory leave access control / audit logging.
 */
class LeaveDocumentSecurityTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $tempUploadRoot;
    private string $tempStoreDir;
    private array $tempFiles = [];

    private int $deptId = 0;
    private int $posId = 0;
    private int $ownerEmpId = 0;
    private int $otherEmpId = 0;
    private int $ownerUserId = 0;
    private int $otherUserId = 0;
    private int $adminUserId = 0;
    private int $vawcTypeId = 0;
    private int $createdTypeId = 0;
    private int $requestId = 0;

    protected function setUp(): void
    {
        $this->tempUploadRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp_ldoc_test_' . bin2hex(random_bytes(4));
        $this->tempStoreDir = $this->tempUploadRoot . DIRECTORY_SEPARATOR . 'vault';
        mkdir($this->tempStoreDir, 0750, true);

        $db = Database::getInstance();
        if ($db->isConnected()) {
            $this->pdo = $db->getConnection();
        }
        ConfidentialLeaveService::resetCache();
    }

    protected function tearDown(): void
    {
        ConfidentialLeaveService::resetCache();

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->removeDirectoryRecursive($this->tempUploadRoot);

        if ($this->pdo === null) {
            return;
        }

        $empIds = array_filter([$this->ownerEmpId, $this->otherEmpId]);
        if (!empty($empIds)) {
            $inEmp = implode(',', array_map('intval', $empIds));
            $this->pdo->exec("DELETE FROM leave_sensitive_access_logs WHERE leave_request_id IN (SELECT id FROM leave_requests WHERE employee_id IN ({$inEmp}))");
            $this->pdo->exec("DELETE FROM leave_request_documents WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM leave_requests WHERE employee_id IN ({$inEmp})");
        }
        if ($this->createdTypeId > 0) {
            $this->pdo->exec("UPDATE leave_types SET active_policy_version_id = NULL WHERE id = {$this->createdTypeId}");
            $this->pdo->exec("DELETE FROM leave_policy_versions WHERE leave_type_id = {$this->createdTypeId}");
            $this->pdo->exec("DELETE FROM leave_types WHERE id = {$this->createdTypeId}");
        }
        $userIds = array_filter([$this->ownerUserId, $this->otherUserId]);
        if (!empty($userIds)) {
            $inUsers = implode(',', array_map('intval', $userIds));
            $this->pdo->exec("DELETE FROM users WHERE id IN ({$inUsers})");
        }
        if (!empty($empIds)) {
            $inEmp = implode(',', array_map('intval', $empIds));
            $this->pdo->exec("DELETE FROM employees WHERE id IN ({$inEmp})");
        }
        if ($this->posId > 0) {
            $this->pdo->exec("DELETE FROM positions WHERE id = {$this->posId}");
        }
        if ($this->deptId > 0) {
            $this->pdo->exec("DELETE FROM departments WHERE id = {$this->deptId}");
        }
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function createTempFile(string $content): string
    {
        $path = $this->tempUploadRoot . DIRECTORY_SEPARATOR . 'src_' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function test_document_service_rejects_php_svg_oversized_and_accepts_valid_magic_bytes(): void
    {
        $service = new LeaveDocumentService(
            storageDir: $this->tempStoreDir,
            uploadedFileVerifier: static fn (string $p): bool => is_file($p),
            maxBytes: 1024
        );

        // 1. Disguised PHP shell named shell.pdf
        $phpTmp = $this->createTempFile("<?php echo 'pwned'; ?>");
        $resPhp = $service->validateUploadedFile([
            'name'     => '../../evil.php.pdf',
            'tmp_name' => $phpTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($phpTmp),
        ]);
        $this->assertFalse($resPhp['ok']);
        $this->assertStringContainsString('Only PDF, JPEG, PNG, and WebP', (string)$resPhp['error']);

        // 2. Spoofed SVG XML payload
        $svgTmp = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $resSvg = $service->validateUploadedFile([
            'name'     => 'cert.svg',
            'tmp_name' => $svgTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($svgTmp),
        ]);
        $this->assertFalse($resSvg['ok']);
        $this->assertStringContainsString('Only PDF, JPEG, PNG, and WebP', (string)$resSvg['error']);

        // 3. Oversized file exceeding maxBytes (1024 bytes)
        $bigPdfTmp = $this->createTempFile('%PDF-1.4' . "\n" . str_repeat('0', 2048));
        $resBig = $service->validateUploadedFile([
            'name'     => 'too-big.pdf',
            'tmp_name' => $bigPdfTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($bigPdfTmp),
        ]);
        $this->assertFalse($resBig['ok']);
        $this->assertStringContainsString('exceeds maximum size', (string)$resBig['error']);

        // 4. Valid minimal PNG image (generated cleanly via GD if available, else PDF)
        $validPdfTmp = $this->createTempFile("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $resPdf = $service->validateUploadedFile([
            'name'     => '../subdir/Court Order (Final)!.pdf',
            'tmp_name' => $validPdfTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($validPdfTmp),
        ]);
        $this->assertTrue($resPdf['ok'], $resPdf['error'] ?? '');
        $this->assertSame('application/pdf', $resPdf['mime']);
        $this->assertStringNotContainsString('..', (string)$resPdf['original_name']);

        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(2, 2);
            $pngPath = $this->createTempFile('');
            imagepng($img, $pngPath);
            imagedestroy($img);

            $resPng = $service->validateUploadedFile([
                'name'     => 'med-cert.png',
                'tmp_name' => $pngPath,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($pngPath),
            ]);
            $this->assertTrue($resPng['ok']);
            $this->assertSame('image/png', $resPng['mime']);
        }
    }

    public function test_confidential_document_storage_access_control_and_audit_logs(): void
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('Database connection required for document ACL audit test.');
        }

        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'ldoc_sec')")
            ->execute(['n' => 'Sec Dept ' . bin2hex(random_bytes(3))]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'ldoc_sec')")
            ->execute(['n' => 'Sec Pos ' . bin2hex(random_bytes(3)), 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();

        $sfx = bin2hex(random_bytes(3));
        foreach (['owner' => &$this->ownerEmpId, 'other' => &$this->otherEmpId] as $key => &$eid) {
            $this->pdo->prepare("
                INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
                VALUES (:no, :fn, 'User', :em, '1991-01-01', 'Female', :d, :p, '2090-01-01', 'Active')
            ")->execute([
                'no' => "SEC-{$key}-{$sfx}",
                'fn' => ucfirst($key),
                'em' => "sec-{$key}-{$sfx}@test.local",
                'd'  => $this->deptId,
                'p'  => $this->posId,
            ]);
            $eid = (int)$this->pdo->lastInsertId();
        }
        unset($eid);

        $this->pdo->prepare("INSERT INTO users (employee_id, username, email, password, role, status) VALUES (?, ?, ?, ?, 'employee', 'Active')")
            ->execute([$this->ownerEmpId, "own_{$sfx}", "own-{$sfx}@test.local", password_hash('Pass123!', PASSWORD_BCRYPT)]);
        $this->ownerUserId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO users (employee_id, username, email, password, role, status) VALUES (?, ?, ?, ?, 'employee', 'Active')")
            ->execute([$this->otherEmpId, "oth_{$sfx}", "oth-{$sfx}@test.local", password_hash('Pass123!', PASSWORD_BCRYPT)]);
        $this->otherUserId = (int)$this->pdo->lastInsertId();

        $this->adminUserId = (int)($this->pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'Active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

        $leaveModel = new Leave($this->pdo);
        $existingVawcId = (int)$this->pdo->query("SELECT id FROM leave_types WHERE leave_code = 'VAWC' LIMIT 1")->fetchColumn();
        if ($existingVawcId > 0) {
            $this->vawcTypeId = $existingVawcId;
        } else {
            $this->createdTypeId = $leaveModel->createLeaveType([
                'leave_name'        => 'VAWC Sec ' . $sfx,
                'leave_code'        => 'VAWC',
                'classification'    => 'Statutory',
                'benefit_category'  => 'StatutoryEvent',
                'entitlement_model' => 'event_based',
                'default_days'      => 10,
                'default_unit'      => 'working_days',
                'is_paid'           => 1,
                'is_confidential'   => 1,
                'status'            => 'Active',
            ]);
            $this->vawcTypeId = $this->createdTypeId;
        }

        $this->requestId = $leaveModel->createRequest([
            'employee_id'      => $this->ownerEmpId,
            'leave_type_id'    => $this->vawcTypeId,
            'start_date'       => '2096-04-02',
            'end_date'         => '2096-04-03',
            'number_of_days'   => 2,
            'entitlement_unit' => 'working_days',
            'is_paid'          => 1,
            'is_confidential'  => 1,
            'reason'           => 'Confidential BPO filing',
        ]);

        $docService = new LeaveDocumentService(
            storageDir: $this->tempStoreDir,
            uploadedFileVerifier: static fn (string $p): bool => is_file($p)
        );

        $pdfTmp = $this->createTempFile("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $stored = $docService->storeForRequest(
            $this->pdo,
            $this->requestId,
            $this->ownerEmpId,
            [
                'name'     => 'bpo-order.pdf',
                'tmp_name' => $pdfTmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($pdfTmp),
            ],
            'protection_order',
            $this->ownerUserId,
            true
        );
        $this->assertTrue($stored['ok']);
        $docId = (int)$stored['id'];

        // Verify stored filename is randomized outside public/
        $row = $this->pdo->query("SELECT stored_filename, storage_relative_path FROM leave_request_documents WHERE id = {$docId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertStringStartsWith("ldoc-{$this->requestId}-", (string)$row['stored_filename']);
        $this->assertNotSame('bpo-order.pdf', $row['stored_filename']);

        // Unauthenticated -> 401
        $unauth = $docService->resolveAuthorizedDownload($this->pdo, $docId, null);
        $this->assertFalse($unauth['ok']);
        $this->assertSame(401, $unauth['status']);

        // Another employee -> 403 + logged denied
        $otherAttempt = $docService->resolveAuthorizedDownload(
            $this->pdo,
            $docId,
            ['id' => $this->otherUserId, 'role' => 'employee', 'employee_id' => $this->otherEmpId]
        );
        $this->assertFalse($otherAttempt['ok']);
        $this->assertSame(403, $otherAttempt['status']);

        $deniedCount = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM leave_sensitive_access_logs WHERE document_id = {$docId} AND accessor_user_id = {$this->otherUserId} AND access_granted = 0"
        )->fetchColumn();
        $this->assertSame(1, $deniedCount);

        // Owner -> 200 + logged allowed
        $ownerAttempt = $docService->resolveAuthorizedDownload(
            $this->pdo,
            $docId,
            ['id' => $this->ownerUserId, 'role' => 'employee', 'employee_id' => $this->ownerEmpId]
        );
        $this->assertTrue($ownerAttempt['ok']);
        $this->assertSame(200, $ownerAttempt['status']);

        // Cleared Admin -> 200
        if ($this->adminUserId > 0) {
            $adminAttempt = $docService->resolveAuthorizedDownload(
                $this->pdo,
                $docId,
                ['id' => $this->adminUserId, 'role' => 'admin', 'employee_id' => null]
            );
            $this->assertTrue($adminAttempt['ok']);
            $this->assertSame(200, $adminAttempt['status']);
        }
    }
}
