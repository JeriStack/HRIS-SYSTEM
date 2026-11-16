<?php

/**
 * AnnouncementContent Domain Value Object
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\ValueObjects;

use InvalidArgumentException;

final class AnnouncementContent
{
    public const MIN_LENGTH = 5;
    public const MAX_LENGTH = 20000;

    private string $value;

    public function __construct(string $value)
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($normalized === '') {
            throw new InvalidArgumentException('Announcement content is required and cannot be empty.');
        }

        $len = function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized);
        if ($len < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Announcement content must be at least %d characters long.',
                self::MIN_LENGTH
            ));
        }

        if ($len > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Announcement content cannot exceed %d characters.',
                self::MAX_LENGTH
            ));
        }

        $this->value = $normalized;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function preview(int $maxLength = 160): string
    {
        $singleLine = trim(preg_replace('/\s+/u', ' ', $this->value) ?? $this->value);
        $len = function_exists('mb_strlen') ? mb_strlen($singleLine, 'UTF-8') : strlen($singleLine);
        if ($len <= $maxLength) {
            return $singleLine;
        }

        $slice = function_exists('mb_substr')
            ? mb_substr($singleLine, 0, max(1, $maxLength - 1), 'UTF-8')
            : substr($singleLine, 0, max(1, $maxLength - 1));

        return rtrim($slice) . '…';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
