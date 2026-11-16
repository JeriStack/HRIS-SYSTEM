<?php

/**
 * AnnouncementOperationResult DTO
 * HRIS-SYSTEM — Anti-Corruption Layer (ACL)
 */

declare(strict_types=1);

namespace App\Application\Announcement\Adapters;

final class AnnouncementOperationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $statusCode = 200,
        public readonly mixed $data = null,
        public readonly ?string $errorCode = null
    ) {
    }

    public static function ok(string $message, mixed $data = null, int $statusCode = 200): self
    {
        return new self(
            success: true,
            message: $message,
            statusCode: $statusCode,
            data: $data,
            errorCode: null
        );
    }

    public static function fail(string $message, int $statusCode = 400, ?string $errorCode = null): self
    {
        return new self(
            success: false,
            message: $message,
            statusCode: $statusCode,
            data: null,
            errorCode: $errorCode
        );
    }
}
