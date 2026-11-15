<?php

/**
 * Profile Picture Validation, Cropping & Secure Storage Service
 * Stores employee avatars outside web-executable directories with randomized
 * filenames and serves verified binary streams through authenticated routes.
 */

namespace App\Services;

use finfo;
use PDO;

final class ProfilePictureService
{
    private const SAFE_FILENAME_PATTERN = '/^emp-\d+-[a-f0-9]{32}\.(jpg|png|webp)$/';

    private string $storageDir;
    private int $maxBytes;
    private int $minWidth;
    private int $minHeight;
    private int $maxWidth;
    private int $maxHeight;
    private int $outputSize;

    /** @var array<string, string> */
    private array $allowedMimes;

    /** @var callable(string): bool */
    private $uploadedFileVerifier;

    /**
     * @param string|null $storageDir Storage directory override (mainly for testing)
     * @param callable(string): bool|null $uploadedFileVerifier Optional upload checker override
     * @param int|null $maxBytes Optional max upload bytes override
     */
    public function __construct(
        ?string $storageDir = null,
        ?callable $uploadedFileVerifier = null,
        ?int $maxBytes = null
    ) {
        $root = dirname(__DIR__, 2);
        $this->storageDir = rtrim($storageDir ?? ($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars'), '/\\');
        $this->maxBytes = $maxBytes ?? (int)config('profile.avatar_max_bytes', 5 * 1024 * 1024);
        $this->minWidth = (int)config('profile.avatar_min_width', 64);
        $this->minHeight = (int)config('profile.avatar_min_height', 64);
        $this->maxWidth = (int)config('profile.avatar_max_width', 6000);
        $this->maxHeight = (int)config('profile.avatar_max_height', 6000);
        $this->outputSize = (int)config('profile.avatar_output_size', 512);
        $this->allowedMimes = (array)config('profile.avatar_allowed_mimes', [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ]);
        $this->uploadedFileVerifier = $uploadedFileVerifier ?? static fn (string $path): bool => is_uploaded_file($path);
    }

    /**
     * Return configurable max upload size in bytes.
     */
    public function getMaxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Build the authenticated HTTP URL for an employee's avatar when stored safely.
     */
    public static function avatarUrl(?int $employeeId, ?string $filename): ?string
    {
        if ($employeeId === null || $employeeId <= 0 || !self::isSafeFilename($filename)) {
            return null;
        }

        $version = substr(hash('sha256', (string)$filename), 0, 12);
        $path = '/media/avatars/' . $employeeId . '?v=' . $version;
        return function_exists('url') ? url($path) : $path;
    }

    /**
     * Validate whether a stored database token conforms strictly to the randomized pattern.
     */
    public static function isSafeFilename(?string $filename): bool
    {
        $clean = trim((string)$filename);
        return $clean !== '' && preg_match(self::SAFE_FILENAME_PATTERN, $clean) === 1;
    }

    /**
     * Validate an uploaded PHP $_FILES entry without trusting client name or MIME header.
     *
     * @param array<string, mixed> $file
     * @return array{ok: bool, error?: string, mime?: string, ext?: string, width?: int, height?: int, image_type?: int, tmp_path?: string}
     */
    public function validateUploadedFile(array $file): array
    {
        $uploadErr = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $this->mapUploadError($uploadErr)];
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if (!$this->isReadableFile($tmpPath)) {
            return ['ok' => false, 'error' => 'No readable image file was received.'];
        }

        if (!($this->uploadedFileVerifier)($tmpPath)) {
            return ['ok' => false, 'error' => 'Invalid upload source rejected by server security checks.'];
        }

        $size = @filesize($tmpPath);
        if ($size === false || $size <= 0) {
            return ['ok' => false, 'error' => 'Uploaded image file is empty.'];
        }
        if ($size > $this->maxBytes) {
            $limitMb = number_format($this->maxBytes / (1024 * 1024), 1);
            return ['ok' => false, 'error' => "Image exceeds the maximum allowed size of {$limitMb} MB."];
        }

        // Verify actual content MIME via finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string)$finfo->file($tmpPath));
        if (!isset($this->allowedMimes[$detectedMime])) {
            return ['ok' => false, 'error' => 'Unsupported file format. Only JPEG, PNG, and WebP images are allowed.'];
        }

        // Verify binary magic signature explicitly
        if (!$this->matchesBinarySignature($tmpPath, $detectedMime)) {
            return ['ok' => false, 'error' => 'Image file header is malformed or does not match an accepted image format.'];
        }

        // Verify structural image metadata and dimensions
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return ['ok' => false, 'error' => 'Malformed or corrupted image content cannot be processed.'];
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        $imageType = (int)($imageInfo[2] ?? 0);

        $expectedImageType = match ($detectedMime) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png'  => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
            default      => 0,
        };

        if ($imageType !== $expectedImageType) {
            return ['ok' => false, 'error' => 'Image encoding mismatch detected. Upload a valid JPEG, PNG, or WebP photo.'];
        }

        if ($width < $this->minWidth || $height < $this->minHeight) {
            return ['ok' => false, 'error' => "Profile picture must be at least {$this->minWidth}x{$this->minHeight} pixels."];
        }

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            return ['ok' => false, 'error' => "Profile picture dimensions exceed the {$this->maxWidth}x{$this->maxHeight} pixel limit."];
        }

        return [
            'ok'         => true,
            'mime'       => $detectedMime,
            'ext'        => $this->allowedMimes[$detectedMime],
            'width'      => $width,
            'height'     => $height,
            'image_type' => $imageType,
            'tmp_path'   => $tmpPath,
        ];
    }

    /**
     * Validate, optionally crop/re-encode, and store an uploaded profile picture safely.
     *
     * @param int $employeeId Authenticated owner or HR-managed employee ID
     * @param array<string, mixed> $file Uploaded $_FILES entry
     * @param array{x?: float|int, y?: float|int, size?: float|int}|null $crop Optional square crop rectangle in source pixels
     * @return array{ok: bool, filename?: string, error?: string}
     */
    public function store(int $employeeId, array $file, ?array $crop = null): array
    {
        if ($employeeId <= 0) {
            return ['ok' => false, 'error' => 'Invalid employee profile target.'];
        }

        $validation = $this->validateUploadedFile($file);
        if (!$validation['ok']) {
            return $validation;
        }

        $this->ensureStorageDirectory();

        $ext = (string)$validation['ext'];
        $mime = (string)$validation['mime'];
        $tmpPath = (string)$validation['tmp_path'];
        $srcW = (int)$validation['width'];
        $srcH = (int)$validation['height'];

        $filename = sprintf('emp-%d-%s.%s', $employeeId, bin2hex(random_bytes(16)), $ext);
        $destination = $this->storageDir . DIRECTORY_SEPARATOR . $filename;

        // Re-encode & center/custom crop through GD when extension is enabled (strips EXIF/embedded payloads)
        $written = false;
        if ($this->canUseGdReencode($mime)) {
            $written = $this->reencodeWithGd($tmpPath, $destination, $mime, $srcW, $srcH, $crop);
        }

        if (!$written) {
            // Safe binary copy fallback when GD extension is unavailable in runtime PHP
            $rawBytes = @file_get_contents($tmpPath);
            if ($rawBytes === false || $rawBytes === '') {
                return ['ok' => false, 'error' => 'Unable to read uploaded image stream.'];
            }
            if (@file_put_contents($destination, $rawBytes, LOCK_EX) === false) {
                return ['ok' => false, 'error' => 'Unable to persist uploaded image file safely.'];
            }
        }

        @chmod($destination, 0640);

        // Final post-write verification against storage containment & image readability
        if (!$this->resolveStoredFilePath($filename)) {
            @unlink($destination);
            return ['ok' => false, 'error' => 'Stored profile image failed integrity verification.'];
        }

        return [
            'ok'       => true,
            'filename' => $filename,
        ];
    }

    /**
     * Resolve the verified real filesystem path for a stored avatar filename.
     * Prevents directory traversal by matching strict filename regex and comparing realpaths.
     */
    public function resolveStoredFilePath(?string $filename): ?string
    {
        if (!self::isSafeFilename($filename)) {
            return null;
        }

        $baseDir = realpath($this->storageDir);
        if ($baseDir === false) {
            return null;
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . basename((string)$filename);
        $resolved = realpath($candidate);
        if ($resolved === false || !$this->isReadableFile($resolved)) {
            return null;
        }

        $normalizedBase = rtrim(str_replace('\\', '/', $baseDir), '/') . '/';
        $normalizedTarget = str_replace('\\', '/', $resolved);
        if (!str_starts_with($normalizedTarget, $normalizedBase)) {
            return null;
        }

        return $resolved;
    }

    /**
     * Detect actual MIME type of an already stored verified file.
     */
    public function detectStoredMime(string $resolvedPath): ?string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($resolvedPath));
        return isset($this->allowedMimes[$mime]) ? $mime : null;
    }

    /**
     * Safely delete an obsolete avatar file after confirming it stays within storage bounds
     * and is no longer referenced by any employee record.
     */
    public function deleteStoredFile(?string $filename, ?PDO $pdo = null, ?int $excludeEmployeeId = null): bool
    {
        $resolved = $this->resolveStoredFilePath($filename);
        if ($resolved === null) {
            return false;
        }

        if ($pdo !== null) {
            try {
                if ($excludeEmployeeId !== null) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE profile_picture = :pic AND id != :emp_id");
                    $stmt->execute(['pic' => $filename, 'emp_id' => $excludeEmployeeId]);
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE profile_picture = :pic");
                    $stmt->execute(['pic' => $filename]);
                }
                if ((int)$stmt->fetchColumn() > 0) {
                    return false;
                }
            } catch (\Throwable $e) {
                error_log('[ProfilePictureService::deleteStoredFile] Reference check failed: ' . $e->getMessage());
                return false;
            }
        }

        return @unlink($resolved);
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0750, true);
        }

        $parentDir = dirname($this->storageDir);
        $htaccess = $parentDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_dir($parentDir) && !is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Options -Indexes -ExecCGI\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
            );
        }
    }

    /**
     * Confirm a path points to a readable regular file using fopen (more reliable on Windows ACL temp dirs).
     */
    private function isReadableFile(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        fclose($fh);
        return true;
    }

    private function matchesBinarySignature(string $path, string $mime): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $bytes = @fread($fh, 16);
        @fclose($fh);

        if ($bytes === false || strlen($bytes) < 12) {
            return false;
        }

        return match ($mime) {
            'image/jpeg' => substr($bytes, 0, 3) === "\xFF\xD8\xFF",
            'image/png'  => substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n",
            'image/webp' => substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP',
            default      => false,
        };
    }

    private function canUseGdReencode(string $mime): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'image/png'  => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            default      => false,
        };
    }

    /**
     * Crop and resize image using GD cleanly while preserving alpha for PNG/WebP.
     *
     * @param array{x?: float|int, y?: float|int, size?: float|int}|null $crop
     */
    private function reencodeWithGd(
        string $tmpPath,
        string $destPath,
        string $mime,
        int $srcW,
        int $srcH,
        ?array $crop
    ): bool {
        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png'  => @imagecreatefrompng($tmpPath),
            'image/webp' => @imagecreatefromwebp($tmpPath),
            default      => false,
        };

        if ($srcImg === false) {
            return false;
        }

        $minSide = min($srcW, $srcH);
        $cropX = max(0, (int)round((float)($crop['x'] ?? (($srcW - $minSide) / 2))));
        $cropY = max(0, (int)round((float)($crop['y'] ?? (($srcH - $minSide) / 2))));
        $cropSize = (int)round((float)($crop['size'] ?? $minSide));

        if ($cropSize <= 0) {
            $cropSize = $minSide;
        }
        $cropSize = min($cropSize, $srcW - $cropX, $srcH - $cropY);
        if ($cropSize < 32) {
            $cropX = (int)(($srcW - $minSide) / 2);
            $cropY = (int)(($srcH - $minSide) / 2);
            $cropSize = $minSide;
        }

        $targetDim = min($this->outputSize, max($cropSize, $this->minWidth));
        $destImg = @imagecreatetruecolor($targetDim, $targetDim);
        if ($destImg === false) {
            @imagedestroy($srcImg);
            return false;
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($destImg, false);
            imagesavealpha($destImg, true);
            $transparent = imagecolorallocatealpha($destImg, 0, 0, 0, 127);
            imagefill($destImg, 0, 0, $transparent);
        }

        $resampled = imagecopyresampled(
            $destImg,
            $srcImg,
            0,
            0,
            $cropX,
            $cropY,
            $targetDim,
            $targetDim,
            $cropSize,
            $cropSize
        );

        $saved = false;
        if ($resampled) {
            $saved = match ($mime) {
                'image/jpeg' => @imagejpeg($destImg, $destPath, 88),
                'image/png'  => @imagepng($destImg, $destPath, 6),
                'image/webp' => @imagewebp($destImg, $destPath, 88),
                default      => false,
            };
        }

        @imagedestroy($srcImg);
        @imagedestroy($destImg);

        return $saved;
    }

    private function mapUploadError(int $code): string
    {
        $limitMb = number_format($this->maxBytes / (1024 * 1024), 1);

        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Uploaded file exceeds the {$limitMb} MB size limit.",
            UPLOAD_ERR_PARTIAL   => 'The uploaded image was only partially received. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'Please select a JPEG, PNG, or WebP image to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Server temporary upload storage is unavailable.',
            default => 'Unable to process uploaded profile picture.',
        };
    }
}
