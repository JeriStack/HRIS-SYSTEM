<?php

/**
 * AnnouncementAttachmentService
 * Reuses SupportAttachmentService validation & non-public randomized storage primitives
 * scoped to `storage/uploads/announcement_attachments`.
 */

declare(strict_types=1);

namespace App\Services;

final class AnnouncementAttachmentService
{
    private SupportAttachmentService $delegate;

    /**
     * @param string|null                 $storageDir           Override base directory (mainly for testing)
     * @param (callable(string): bool)|null $uploadedFileVerifier Override is_uploaded_file check for unit tests
     * @param int|null                    $maxBytes             Override max attachment byte limit
     * @param int|null                    $maxFiles             Override max attachment count
     */
    public function __construct(
        ?string $storageDir = null,
        ?callable $uploadedFileVerifier = null,
        ?int $maxBytes = null,
        ?int $maxFiles = null
    ) {
        $root = dirname(__DIR__, 2);
        $defaultDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcement_attachments';

        $this->delegate = new SupportAttachmentService(
            $storageDir ?? $defaultDir,
            $uploadedFileVerifier,
            $maxBytes ?? (int)config('announcements.attachment_max_bytes', 5 * 1024 * 1024),
            $maxFiles ?? (int)config('announcements.max_attachments_per_announcement', 3)
        );
    }

    public function getMaxBytes(): int
    {
        return $this->delegate->getMaxBytes();
    }

    public function getMaxFilesPerAnnouncement(): int
    {
        return $this->delegate->getMaxFilesPerSubmission();
    }

    /**
     * @param array<string, mixed>|null $filesInput
     * @return array<int, array{name:string, type:string, tmp_name:string, error:int, size:int}>
     */
    public static function normalizeFilesArray(?array $filesInput): array
    {
        return SupportAttachmentService::normalizeFilesArray($filesInput);
    }

    /**
     * @param array<int, array{name:string, type?:string, tmp_name:string, error:int, size:int}> $files
     * @return array<int, array{tmp_name:string, original_filename:string, mime_type:string, ext:string, file_size_bytes:int}>
     */
    public function validateBatch(array $files): array
    {
        return $this->delegate->validateBatch($files);
    }

    /**
     * @param array<int, array{tmp_name:string, original_filename:string, mime_type:string, ext:string, file_size_bytes:int}> $validatedFiles
     * @return array<int, array<string, mixed>>
     */
    public function storeBatch(int $announcementId, array $validatedFiles): array
    {
        return $this->delegate->storeBatch($announcementId, $validatedFiles);
    }

    /**
     * @param array<int, string> $relativePaths
     */
    public function rollbackStoredFiles(array $relativePaths): void
    {
        $this->delegate->rollbackStoredFiles($relativePaths);
    }

    public function resolveAuthorizedFilePath(?string $relativePath): ?string
    {
        return $this->delegate->resolveAuthorizedFilePath($relativePath);
    }
}
