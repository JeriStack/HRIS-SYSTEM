<?php

/**
 * AnnouncementTarget Domain Entity
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Entities;

use App\Domain\Announcement\Exceptions\InvalidAnnouncementTargetException;
use InvalidArgumentException;

final class AnnouncementTarget
{
    public const TYPE_ALL        = 'all';
    public const TYPE_DEPARTMENT = 'department';

    private string $targetType;
    private ?int $departmentId;
    private ?string $departmentName;

    public function __construct(
        string $targetType,
        ?int $departmentId = null,
        ?string $departmentName = null
    ) {
        $normalized = strtolower(trim($targetType));
        if (!in_array($normalized, [self::TYPE_ALL, self::TYPE_DEPARTMENT], true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid announcement target type '%s'. Allowed: all, department.",
                $targetType
            ));
        }

        if ($normalized === self::TYPE_ALL) {
            $departmentId = null;
        } else {
            if ($departmentId === null || $departmentId <= 0) {
                throw InvalidAnnouncementTargetException::emptyTargets();
            }
        }

        $this->targetType = $normalized;
        $this->departmentId = $departmentId;
        $this->departmentName = $departmentName;
    }

    public static function allEmployees(): self
    {
        return new self(self::TYPE_ALL, null, 'All Active Employees');
    }

    public static function forDepartment(int $departmentId, ?string $departmentName = null): self
    {
        return new self(self::TYPE_DEPARTMENT, $departmentId, $departmentName);
    }

    public function targetType(): string
    {
        return $this->targetType;
    }

    public function departmentId(): ?int
    {
        return $this->departmentId;
    }

    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    public function isAllEmployees(): bool
    {
        return $this->targetType === self::TYPE_ALL;
    }

    public function isDepartment(): bool
    {
        return $this->targetType === self::TYPE_DEPARTMENT;
    }

    public function matchesEmployee(?int $employeeDepartmentId, string $employmentStatus = 'Active'): bool
    {
        if (strcasecmp(trim($employmentStatus), 'Active') !== 0) {
            return false;
        }

        if ($this->targetType === self::TYPE_ALL) {
            return true;
        }

        return $employeeDepartmentId !== null
            && $this->departmentId !== null
            && $employeeDepartmentId === $this->departmentId;
    }
}
