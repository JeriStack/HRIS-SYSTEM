<?php

/**
 * SupportAttachmentService
 * Handles MIME/magic-byte validation, executable prevention, non-public randomized storage,
 * SHA-256 integrity hashing, and safe authorized download resolution.
 */

namespace App\Services;

use finfo;
use RuntimeException;

final class SupportAttachmentService
{
    public const SAFE_RELATIVE_PATH_PATTERN = '/^(20\d{2})\/(0[1-9]|1[0-2])\/supdoc-\d+-[a-f0-9]{32}\.(pdf|jpg|png|webp|txt)$/';

    /** @var array<int, string> */
    private const FORBIDDEN_FILENAME_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'html', 'htm', 'xhtml', 'svg', 'xml',
        'js', 'mjs', 'cjs', 'exe', 'dll', 'bat', 'cmd',
        'ps1', 'sh', 'bash', 'zsh', 'py', 'rb', 'pl', 'cgi', 'asp', 'aspx', 'jsp',
    ];

    private string $storageDir;
    private int $maxBytes;
    private int $maxFilesPerSubmission;

    /** @var array<string, string> */
    private array $allowedMimes;

    /** @var callable(string): bool */
    private $uploadedFileVerifier;

    /**
     * @param string|null               $storageDir           Override base directory (mainly for testing)
     * @param callable(string): bool|null $uploadedFileVerifier Override is_uploaded_file check for unit tests
     * @param int|null                  $maxBytes             Override max attachment byte limit
     * @param int|null                  $maxFiles             Override max attachment count per post
     */
    public function __construct(
        ?string $storageDir = null,
        ?callable $uploadedFileVerifier = null,
        ?int $maxBytes = null,
        ?int $maxFiles = null
    ) {
        $root = dirname(__DIR__, 2);
        $defaultDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'support_attachments';
        $this->storageDir = rtrim($storageDir ?? $defaultDir, '/\\');
        $this->maxBytes = $maxBytes ?? (int)config('support.attachment_max_bytes', 5 * 1024 * 1024);
        $this->maxFilesPerSubmission = $maxFiles ?? (int)config('support.max_attachments_per_submission', 3);
        $this->allowedMimes = (array)config('support.attachment_allowed_mimes', [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'text/plain'      => 'txt',
        ]);
        $this->uploadedFileVerifier = $uploadedFileVerifier ?? static fn (string $path): bool => is_uploaded_file($path);
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    public function getMaxFilesPerSubmission(): int
    {
        return $this->maxFilesPerSubmission;
    }

    /**
     * Normalize PHP single-file or multi-file $_FILES entry into a clean list of file entries.
     *
     * @param array<string, mixed>|null $filesInput Raw $_FILES['attachments'] array
     * @return array<int, array{name:string, type:string, tmp_name:string, error:int, size:int}>
     */
    public static function normalizeFilesArray(?array $filesInput): array
    {
        if (!$filesInput || !isset($filesInput['name'])) {
            return [];
        }

        $normalized = [];
        if (is_array($filesInput['name'])) {
            foreach ($filesInput['name'] as $idx => $name) {
                $err = (int)($filesInput['error'][$idx] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE && trim((string)$name) === '') {
                    continue;
                }
                $normalized[] = [
                    'name'     => (string)$name,
                    'type'     => (string)($filesInput['type'][$idx] ?? ''),
                    'tmp_name' => (string)($filesInput['tmp_name'][$idx] ?? ''),
                    'error'    => $err,
                    'size'     => (int)($filesInput['size'][$idx] ?? 0),
                ];
            }
        } else {
            $err = (int)($filesInput['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_NO_FILE || trim((string)$filesInput['name']) !== '') {
                $normalized[] = [
                    'name'     => (string)$filesInput['name'],
                    'type'     => (string)($filesInput['type'] ?? ''),
                    'tmp_name' => (string)($filesInput['tmp_name'] ?? ''),
                    'error'    => $err,
                    'size'     => (int)($filesInput['size'] ?? 0),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Validate a batch of normalized uploaded files before persisting anything to disk or DB.
     *
     * @param array<int, array{name:string, type?:string, tmp_name:string, error:int, size:int}> $files
     * @return array<int, array{tmp_name:string, original_filename:string, mime_type:string, ext:string, file_size_bytes:int}>
     */
    public function validateBatch(array $files): array
    {
        if (count($files) > $this->maxFilesPerSubmission) {
            throw new RuntimeException(
                sprintf('You may attach at most %d files per submission.', $this->maxFilesPerSubmission)
            );
        }

        $validated = [];
        foreach ($files as $entry) {
            $validated[] = $this->validateSingleFile($entry);
        }

        return $validated;
    }

    /**
     * Validate a single uploaded file entry by extension hygiene, size, finfo MIME, and magic bytes.
     *
     * @param array{name:string, type?:string, tmp_name:string, error:int, size:int} $entry
     * @return array{tmp_name:string, original_filename:string, mime_type:string, ext:string, file_size_bytes:int}
     */
    public function validateSingleFile(array $entry): array
    {
        $error = (int)($entry['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $tmpPath = (string)($entry['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !($this->uploadedFileVerifier)($tmpPath)) {
            throw new RuntimeException('Invalid or untrusted file upload source.');
        }

        $rawName = basename(str_replace('\\', '/', (string)($entry['name'] ?? 'attachment')));
        $cleanOriginalName = preg_replace('/[^\w.\- ()]/u', '_', $rawName) ?: 'attachment';
        $cleanOriginalName = mb_substr($cleanOriginalName, 0, 200);

        $segments = array_values(array_filter(explode('.', strtolower($rawName)), static fn (string $s): bool => $s !== ''));
        if (count($segments) < 2) {
            throw new RuntimeException('Uploaded files must include a valid file extension.');
        }

        // Reject any dangerous double-extension or executable extension anywhere in the filename
        foreach (array_slice($segments, 1) as $extSegment) {
            if (in_array($extSegment, self::FORBIDDEN_FILENAME_EXTENSIONS, true)) {
                throw new RuntimeException('Executable, script, or active web file types are prohibited.');
            }
        }

        $declaredExt = end($segments);
        $normalizedDeclared = $declaredExt === 'jpeg' ? 'jpg' : $declaredExt;
        if (!in_array($normalizedDeclared, array_values($this->allowedMimes), true)) {
            throw new RuntimeException('Unsupported file extension. Allowed formats: PDF, JPG, PNG, WEBP, or TXT.');
        }

        $actualSize = (int)filesize($tmpPath);
        if ($actualSize <= 0 || $actualSize > $this->maxBytes) {
            $maxMb = round($this->maxBytes / (1024 * 1024), 1);
            throw new RuntimeException("Each attachment must be non-empty and under {$maxMb} MB.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string)($finfo->file($tmpPath) ?: '');
        if (!isset($this->allowedMimes[$detectedMime])) {
            throw new RuntimeException('The uploaded file content does not match a permitted document or image format.');
        }

        $expectedExt = $this->allowedMimes[$detectedMime];
        if ($normalizedDeclared !== $expectedExt) {
            throw new RuntimeException('File extension does not match the detected file content type.');
        }

        $this->verifyContentSignature($tmpPath, $detectedMime);

        return [
            'tmp_name'          => $tmpPath,
            'original_filename' => $cleanOriginalName,
            'mime_type'         => $detectedMime,
            'ext'               => $expectedExt,
            'file_size_bytes'   => $actualSize,
        ];
    }

    /**
     * Store a pre-validated upload batch under storage/uploads/support_attachments/YYYY/MM/.
     *
     * @param int $ticketId
     * @param array<int, array{tmp_name:string, original_filename:string, mime_type:string, ext:string, file_size_bytes:int}> $validatedFiles
     * @return array<int, array<string, mixed>>
     */
    public function storeBatch(int $ticketId, array $validatedFiles): array
    {
        if ($validatedFiles === []) {
            return [];
        }

        $yearMonth = date('Y') . '/' . date('m');
        $targetDir = $this->storageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $yearMonth);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to initialize secure support attachment storage.');
        }

        $stored = [];
        try {
            foreach ($validatedFiles as $item) {
                $randomHex = bin2hex(random_bytes(16));
                $storedFilename = sprintf('supdoc-%d-%s.%s', $ticketId, $randomHex, $item['ext']);
                $relativePath = $yearMonth . '/' . $storedFilename;
                $destPath = $targetDir . DIRECTORY_SEPARATOR . $storedFilename;

                $moved = is_uploaded_file($item['tmp_name'])
                    ? move_uploaded_file($item['tmp_name'], $destPath)
                    : (@rename($item['tmp_name'], $destPath) || (@copy($item['tmp_name'], $destPath) && @unlink($item['tmp_name'])));

                if (!$moved || !is_file($destPath)) {
                    throw new RuntimeException('Failed to store uploaded support attachment safely.');
                }

                if (DIRECTORY_SEPARATOR === '/') {
                    @chmod($destPath, 0640);
                }
                $checksum = hash_file('sha256', $destPath);
                if ($checksum === false) {
                    throw new RuntimeException('Failed to compute attachment integrity checksum.');
                }

                $stored[] = [
                    'original_filename'     => $item['original_filename'],
                    'stored_filename'       => $storedFilename,
                    'storage_relative_path' => $relativePath,
                    'mime_type'             => $item['mime_type'],
                    'file_size_bytes'       => $item['file_size_bytes'],
                    'sha256_checksum'       => $checksum,
                ];
            }
        } catch (\Throwable $e) {
            $this->rollbackStoredFiles(array_column($stored, 'storage_relative_path'));
            throw $e;
        }

        return $stored;
    }

    /**
     * Clean up stored attachment files on disk if a database transaction fails.
     *
     * @param array<int, string> $relativePaths
     */
    public function rollbackStoredFiles(array $relativePaths): void
    {
        foreach ($relativePaths as $rel) {
            $resolved = $this->resolveAuthorizedFilePath($rel);
            if ($resolved !== null && is_file($resolved)) {
                @unlink($resolved);
            }
        }
    }

    /**
     * Verify relative path syntax and strict base-directory containment before reading bytes.
     */
    public function resolveAuthorizedFilePath(?string $relativePath): ?string
    {
        $clean = str_replace('\\', '/', trim((string)$relativePath));
        if ($clean === '' || preg_match(self::SAFE_RELATIVE_PATH_PATTERN, $clean) !== 1) {
            return null;
        }

        $baseReal = realpath($this->storageDir);
        if ($baseReal === false || !is_dir($baseReal)) {
            return null;
        }

        $candidate = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
        $fileReal = realpath($candidate);
        $canRead = $fileReal !== false && is_file($fileReal) && (
            DIRECTORY_SEPARATOR === '/'
                ? is_readable($fileReal)
                : (($fh = @fopen($fileReal, 'rb')) !== false && @fclose($fh))
        );
        if (!$canRead) {
            return null;
        }

        $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($fileReal, $prefix)) {
            return null;
        }

        return $fileReal;
    }

    /**
     * Verify deep binary / structural content signatures matching the detected MIME type.
     */
    private function verifyContentSignature(string $tmpPath, string $mime): void
    {
        $head = file_get_contents($tmpPath, false, null, 0, 2048);
        if ($head === false || $head === '') {
            throw new RuntimeException('Unable to inspect uploaded file content.');
        }

        if ($mime === 'application/pdf') {
            if (!str_contains($head, '%PDF-')) {
                throw new RuntimeException('Invalid PDF signature detected.');
            }
            return;
        }

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($tmpPath);
            $expectedType = match ($mime) {
                'image/jpeg' => IMAGETYPE_JPEG,
                'image/png'  => IMAGETYPE_PNG,
                'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
                default      => 0,
            };
            if ($info === false || ($info[2] ?? 0) !== $expectedType || ($info[0] ?? 0) <= 0 || ($info[1] ?? 0) <= 0) {
                throw new RuntimeException('Corrupted or mismatched image binary structure.');
            }
            return;
        }

        if ($mime === 'text/plain') {
            $full = file_get_contents($tmpPath);
            if ($full === false || str_contains($full, "\0") || !mb_check_encoding($full, 'UTF-8')) {
                throw new RuntimeException('Text attachments must contain clean UTF-8 text without binary data.');
            }
            $lowerHead = strtolower(ltrim($head));
            foreach (['<?php', '<?=', '<!doctype html', '<html', '<script', '<svg'] as $badPrefix) {
                if (str_contains($lowerHead, $badPrefix)) {
                    throw new RuntimeException('Active markup or script content is not permitted in text attachments.');
                }
            }
        }
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the maximum allowed size.',
            UPLOAD_ERR_PARTIAL   => 'File upload was interrupted before completion.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not persist uploaded file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server security extension.',
            default => 'Failed to process uploaded attachment.',
        };
    }
}
