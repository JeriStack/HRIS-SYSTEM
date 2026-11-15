<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SupportAttachmentService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SupportAttachmentSecurityTest extends TestCase
{
    private string $tempStorageDir;
    private string $tempUploadDir;

    /** @var array<int, string> */
    private array $cleanupDirs = [];

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $this->tempStorageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_sup_store_' . $suffix;
        $this->tempUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_sup_up_' . $suffix;
        mkdir($this->tempStorageDir, 0775, true);
        mkdir($this->tempUploadDir, 0775, true);
        $this->cleanupDirs = [$this->tempStorageDir, $this->tempUploadDir];
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupDirs as $dir) {
            $this->removeDirectoryRecursive($dir);
        }
    }

    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
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

    private function makeTempFile(string $filename, string $content): string
    {
        $path = $this->tempUploadDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    private function createService(?int $maxBytes = null, ?int $maxFiles = null): SupportAttachmentService
    {
        return new SupportAttachmentService(
            $this->tempStorageDir,
            static fn (string $path): bool => is_file($path),
            $maxBytes ?? (5 * 1024 * 1024),
            $maxFiles ?? 3
        );
    }

    public function test_rejects_php_and_double_extension_executables(): void
    {
        $svc = $this->createService();
        $phpPayload = $this->makeTempFile('shell.php.jpg', "<?php echo 'pwn'; ?>");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/prohibited|executable|content/i');
        $svc->validateSingleFile([
            'name'     => 'shell.php.jpg',
            'tmp_name' => $phpPayload,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($phpPayload),
        ]);
    }

    public function test_rejects_spoofed_pdf_magic_bytes(): void
    {
        $svc = $this->createService();
        $fakePdf = $this->makeTempFile('fake.pdf', '<html><body><script>alert(1)</script></body></html>');

        $this->expectException(RuntimeException::class);
        $svc->validateSingleFile([
            'name'     => 'fake.pdf',
            'tmp_name' => $fakePdf,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($fakePdf),
        ]);
    }

    public function test_rejects_html_disguised_as_txt(): void
    {
        $svc = $this->createService();
        $htmlAsTxt = $this->makeTempFile('notes.txt', '<!DOCTYPE html><html><script>alert(1)</script></html>');

        $this->expectException(RuntimeException::class);
        $svc->validateSingleFile([
            'name'     => 'notes.txt',
            'tmp_name' => $htmlAsTxt,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($htmlAsTxt),
        ]);
    }

    public function test_enforces_size_and_file_count_limits(): void
    {
        $svc = $this->createService(maxBytes: 64, maxFiles: 2);
        $oversized = $this->makeTempFile('large.txt', str_repeat('A', 128));

        try {
            $svc->validateSingleFile([
                'name'     => 'large.txt',
                'tmp_name' => $oversized,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($oversized),
            ]);
            $this->fail('Expected oversized upload exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('under', strtolower($e->getMessage()));
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/at most 2/i');
        $svc->validateBatch([
            ['name' => 'a.txt', 'tmp_name' => 'x', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['name' => 'b.txt', 'tmp_name' => 'y', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['name' => 'c.txt', 'tmp_name' => 'z', 'error' => UPLOAD_ERR_OK, 'size' => 10],
        ]);
    }

    public function test_stores_valid_pdf_with_randomized_name_and_blocks_traversal(): void
    {
        $svc = $this->createService();
        $pdfPath = $this->makeTempFile('evidence.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        $validated = $svc->validateBatch([[
            'name'     => 'incident-report.pdf',
            'tmp_name' => $pdfPath,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($pdfPath),
        ]]);

        $stored = $svc->storeBatch(42, $validated);
        $this->assertCount(1, $stored);
        $entry = $stored[0];

        $this->assertMatchesRegularExpression('/^supdoc-42-[a-f0-9]{32}\.pdf$/', $entry['stored_filename']);
        $this->assertMatchesRegularExpression(SupportAttachmentService::SAFE_RELATIVE_PATH_PATTERN, $entry['storage_relative_path']);
        $this->assertSame(64, strlen($entry['sha256_checksum']));

        $resolved = $svc->resolveAuthorizedFilePath($entry['storage_relative_path']);
        $this->assertNotNull($resolved);
        $this->assertFileExists($resolved);

        // Path traversal probes must fail closed
        $this->assertNull($svc->resolveAuthorizedFilePath('../../config/database.php'));
        $this->assertNull($svc->resolveAuthorizedFilePath('2026/11/../supdoc-42.pdf'));
        $this->assertNull($svc->resolveAuthorizedFilePath('/etc/passwd'));

        $svc->rollbackStoredFiles([$entry['storage_relative_path']]);
        $this->assertFileDoesNotExist($resolved);
    }
}
