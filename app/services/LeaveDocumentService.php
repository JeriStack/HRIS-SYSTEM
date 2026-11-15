<?php

/**
 * Secure Leave Supporting Document Validation, Storage & Streaming Service
 * Validates server-side MIME type + magic bytes (PDF/JPEG/PNG/WebP), blocks
 * executables and path traversal, generates randomized filenames outside webroot
 * under storage/uploads/leave_documents/, tracks SHA-256 checksums, and logs
 * downloads/view access via leave_sensitive_access_logs.
 */

declare(strict_types=1);

namespace App\Services;

use finfo;
use PDO;
use RuntimeException;
use Throwable;

final class LeaveDocumentService
{
    private const SAFE_REL_PATH_PATTERN = '/^\d{4}\/\d{2}\/ldoc-\d+-[a-f0-9]{32}\.(pdf|jpg|png|webp)$/';

    private string $storageDir;
    private int $maxBytes;

    /** @var array<string, string> */
    private array $allowedMimes = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    /** @var callable(string): bool */
    private $uploadedFileVerifier;

    /**
     * @param string|null $storageDir
     * @param callable(string): bool|null $uploadedFileVerifier
     * @param int|null $maxBytes
     */
    public function __construct(
        ?string $storageDir = null,
        ?callable $uploadedFileVerifier = null,
        ?int $maxBytes = null
    ) {
        $root = dirname(__DIR__, 2);
        $this->storageDir = rtrim(
            $storageDir ?? ($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'leave_documents'),
            '/\\'
        );
        $this->maxBytes = $maxBytes ?? (int)(function_exists('config') ? config('leave.document_max_bytes', 5 * 1024 * 1024) : (5 * 1024 * 1024));
        $this->uploadedFileVerifier = $uploadedFileVerifier ?? static fn (string $path): bool => is_uploaded_file($path);
    }

    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Validate a single $_FILES entry by magic header, finfo MIME, and size limits.
     *
     * @param array<string, mixed> $file
     * @return array{ok: bool, error?: string, mime?: string, ext?: string, tmp_path?: string, original_name?: string, size?: int}
     */
    public function validateUploadedFile(array $file): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->mapUploadError($err)];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath) || !($this->uploadedFileVerifier)($tmpPath)) {
            return ['ok' => false, 'error' => 'Invalid or unreadable uploaded file.'];
        }

        $size = filesize($tmpPath);
        if ($size === false || $size <= 0) {
            return ['ok' => false, 'error' => 'Uploaded document cannot be empty.'];
        }
        if ($size > $this->maxBytes) {
            return ['ok' => false, 'error' => sprintf('Document exceeds maximum size of %.1f MB.', $this->maxBytes / (1024 * 1024))];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower(trim((string)$finfo->file($tmpPath)));
        if (!array_key_exists($detectedMime, $this->allowedMimes)) {
            return ['ok' => false, 'error' => 'Only PDF, JPEG, PNG, and WebP supporting documents are permitted.'];
        }

        if (!$this->matchesMagicBytes($tmpPath, $detectedMime)) {
            return ['ok' => false, 'error' => 'File signature does not match an approved PDF or image format.'];
        }

        $rawOrig = (string)($file['name'] ?? 'supporting-document.' . $this->allowedMimes[$detectedMime]);
        $cleanOrig = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($rawOrig)) ?: 'document.' . $this->allowedMimes[$detectedMime];
        $cleanOrig = substr(trim($cleanOrig), 0, 200);

        return [
            'ok'            => true,
            'mime'          => $detectedMime,
            'ext'           => $this->allowedMimes[$detectedMime],
            'tmp_path'      => $tmpPath,
            'original_name' => $cleanOrig,
            'size'          => (int)$size,
        ];
    }

    /**
     * Store a validated document outside webroot and insert its metadata record.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function storeForRequest(
        PDO $pdo,
        int $leaveRequestId,
        int $employeeId,
        array $file,
        string $documentCategory = 'supporting_document',
        ?int $uploaderUserId = null,
        bool $isConfidential = false
    ): array {
        $validation = $this->validateUploadedFile($file);
        if (empty($validation['ok'])) {
            return $validation;
        }

        $ext = (string)$validation['ext'];
        $mime = (string)$validation['mime'];
        $tmpPath = (string)$validation['tmp_path'];
        $origName = (string)$validation['original_name'];
        $size = (int)$validation['size'];

        $checksum = hash_file('sha256', $tmpPath);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            return ['ok' => false, 'error' => 'Unable to compute document integrity checksum.'];
        }

        $subDir = date('Y') . '/' . date('m');
        $targetDir = $this->storageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subDir);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to prepare secure document storage directory.');
        }

        $storedName = sprintf('ldoc-%d-%s.%s', $leaveRequestId, bin2hex(random_bytes(16)), $ext);
        $relPath = $subDir . '/' . $storedName;
        $absPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

        if (!is_uploaded_file($tmpPath)) {
            // Test-verifier fallback copy + unlink when unit tests inject synthetic temp files
            if (!@copy($tmpPath, $absPath)) {
                return ['ok' => false, 'error' => 'Failed to persist uploaded supporting document.'];
            }
            @chmod($absPath, 0640);
        } elseif (!@move_uploaded_file($tmpPath, $absPath)) {
            return ['ok' => false, 'error' => 'Failed to store uploaded supporting document securely.'];
        } else {
            @chmod($absPath, 0640);
        }

        $stmt = $pdo->prepare("
            INSERT INTO leave_request_documents (
                leave_request_id, employee_id, uploaded_by_user_id, document_category,
                original_filename, stored_filename, storage_relative_path,
                mime_type, file_size_bytes, sha256_checksum, is_confidential
            ) VALUES (
                :lr_id, :emp_id, :uploader, :cat,
                :orig, :stored, :rel,
                :mime, :bytes, :sha, :conf
            )
        ");
        $stmt->execute([
            'lr_id'    => $leaveRequestId,
            'emp_id'   => $employeeId,
            'uploader' => $uploaderUserId,
            'cat'      => substr(preg_replace('/[^a-zA-Z0-9_]/', '', $documentCategory) ?: 'supporting_document', 0, 50),
            'orig'     => $origName,
            'stored'   => $storedName,
            'rel'      => $relPath,
            'mime'     => $mime,
            'bytes'    => $size,
            'sha'      => $checksum,
            'conf'     => $isConfidential ? 1 : 0,
        ]);

        return [
            'ok'       => true,
            'id'       => (int)$pdo->lastInsertId(),
            'filename' => $origName,
            'mime'     => $mime,
            'size'     => $size,
            'checksum' => $checksum,
        ];
    }

    /**
     * List attached documents for a leave request without exposing server paths.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDocumentsForRequest(PDO $pdo, int $leaveRequestId): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT id, leave_request_id, employee_id, document_category,
                       original_filename, mime_type, file_size_bytes,
                       is_confidential, review_status, created_at
                FROM leave_request_documents
                WHERE leave_request_id = :id
                ORDER BY id ASC
            ");
            $stmt->execute(['id' => $leaveRequestId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Authorize and resolve a protected document download.
     *
     * @param array<string, mixed>|null $viewerUser
     * @return array{ok: bool, status?: int, error?: string, path?: string, mime?: string, download_name?: string}
     */
    public function resolveAuthorizedDownload(
        PDO $pdo,
        int $documentId,
        ?array $viewerUser,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        if ($viewerUser === null) {
            return ['ok' => false, 'status' => 401, 'error' => 'Authentication required.'];
        }

        $stmt = $pdo->prepare("
            SELECT lrd.*, lt.leave_code, lt.is_confidential AS type_is_confidential, lr.is_confidential AS req_is_confidential
            FROM leave_request_documents lrd
            INNER JOIN leave_requests lr ON lr.id = lrd.leave_request_id
            INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lrd.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $documentId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            return ['ok' => false, 'status' => 404, 'error' => 'Supporting document not found.'];
        }

        $viewerRole = strtolower((string)($viewerUser['role'] ?? ''));
        $viewerEmpId = (int)($viewerUser['employee_id'] ?? 0);
        $isOwner = $viewerEmpId > 0 && $viewerEmpId === (int)$doc['employee_id'];
        $isHrOrAdmin = in_array($viewerRole, ['admin', 'hr'], true);

        if (!$isOwner && !$isHrOrAdmin) {
            ConfidentialLeaveService::logSensitiveAccess(
                $pdo,
                (int)$doc['leave_request_id'],
                $documentId,
                (int)($viewerUser['id'] ?? 0) ?: null,
                'download_document',
                false,
                $ipAddress,
                $userAgent
            );
            return ['ok' => false, 'status' => 403, 'error' => 'You are not authorized to access this document.'];
        }

        $isConfidential = !empty($doc['is_confidential'])
            || !empty($doc['req_is_confidential'])
            || ConfidentialLeaveService::isConfidentialType($doc);

        if ($isConfidential && !$isOwner && !ConfidentialLeaveService::userHasConfidentialPermission($viewerUser, $pdo)) {
            ConfidentialLeaveService::logSensitiveAccess(
                $pdo,
                (int)$doc['leave_request_id'],
                $documentId,
                (int)($viewerUser['id'] ?? 0) ?: null,
                'download_document',
                false,
                $ipAddress,
                $userAgent
            );
            return ['ok' => false, 'status' => 403, 'error' => 'Confidential leave clearance is required to access this attachment.'];
        }

        $rel = str_replace('\\', '/', (string)$doc['storage_relative_path']);
        if (preg_match(self::SAFE_REL_PATH_PATTERN, $rel) !== 1 || str_contains($rel, '..')) {
            return ['ok' => false, 'status' => 404, 'error' => 'Invalid document reference.'];
        }

        $baseReal = realpath($this->storageDir);
        $fullPath = realpath($this->storageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if ($baseReal === false || $fullPath === false || !is_file($fullPath)) {
            return ['ok' => false, 'status' => 404, 'error' => 'Document file no longer exists in storage.'];
        }

        $normBase = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
        $normFull = str_replace('\\', '/', $fullPath);
        if (!str_starts_with($normFull, $normBase)) {
            return ['ok' => false, 'status' => 403, 'error' => 'Access denied.'];
        }

        ConfidentialLeaveService::logSensitiveAccess(
            $pdo,
            (int)$doc['leave_request_id'],
            $documentId,
            (int)($viewerUser['id'] ?? 0) ?: null,
            'download_document',
            true,
            $ipAddress,
            $userAgent
        );

        return [
            'ok'            => true,
            'status'        => 200,
            'path'          => $fullPath,
            'mime'          => (string)$doc['mime_type'],
            'download_name' => (string)$doc['original_filename'],
        ];
    }

    private function matchesMagicBytes(string $path, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            $fh = @fopen($path, 'rb');
            if ($fh === false) {
                return false;
            }
            $header = (string)fread($fh, 5);
            fclose($fh);
            return $header === '%PDF-';
        }

        $imgInfo = @getimagesize($path);
        if (!is_array($imgInfo) || empty($imgInfo[0]) || empty($imgInfo[1])) {
            return false;
        }

        $typeMap = [
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
        ];

        return isset($typeMap[$mime]) && ((int)$imgInfo[2] === $typeMap[$mime]);
    }

    private function mapUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded document exceeds the allowed file size limit.',
            UPLOAD_ERR_PARTIAL => 'Document upload was interrupted; please try again.',
            UPLOAD_ERR_NO_FILE => 'No supporting document file was attached.',
            default => 'Unable to process the uploaded supporting document.',
        };
    }
}
