<?php

/**
 * AnnouncementAttachment Domain Entity
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final class AnnouncementAttachment
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $announcementId,
        private readonly string $originalFilename,
        private readonly string $storedFilename,
        private readonly string $storageRelativePath,
        private readonly string $mimeType,
        private readonly int $fileSize,
        private readonly string $sha256Checksum = '',
        private readonly ?int $uploadedBy = null,
        private readonly ?DateTimeImmutable $createdAt = null
    ) {
        if (trim($this->originalFilename) === '') {
            throw new InvalidArgumentException('Attachment original filename cannot be empty.');
        }
        if (trim($this->storedFilename) === '') {
            throw new InvalidArgumentException('Attachment stored filename cannot be empty.');
        }
        if ($this->fileSize <= 0) {
            throw new InvalidArgumentException('Attachment file size must be greater than zero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function announcementId(): int
    {
        return $this->announcementId;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function storedFilename(): string
    {
        return $this->storedFilename;
    }

    public function storageRelativePath(): string
    {
        return $this->storageRelativePath;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function fileSize(): int
    {
        return $this->fileSize;
    }

    public function sha256Checksum(): string
    {
        return $this->sha256Checksum;
    }

    public function uploadedBy(): ?int
    {
        return $this->uploadedBy;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
