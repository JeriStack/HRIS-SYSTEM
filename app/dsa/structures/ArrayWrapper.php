<?php
/**
 * Custom Dynamic Array Data Structure Wrapper
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

use OutOfBoundsException;

class ArrayWrapper
{
    private array $elements = [];

    public function __construct(array $initial = [])
    {
        $this->elements = array_values($initial);
    }

    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= count($this->elements)) {
            throw new OutOfBoundsException("Index {$index} is out of bounds.");
        }
        return $this->elements[$index];
    }

    public function insert(mixed $item, ?int $index = null): void
    {
        if ($index === null || $index >= count($this->elements)) {
            $this->elements[] = $item;
            return;
        }
        if ($index < 0) {
            $index = 0;
        }
        array_splice($this->elements, $index, 0, [$item]);
    }

    public function delete(int $index): bool
    {
        if ($index < 0 || $index >= count($this->elements)) {
            return false;
        }
        array_splice($this->elements, $index, 1);
        return true;
    }

    public function traverse(): array
    {
        return $this->elements;
    }

    public function size(): int
    {
        return count($this->elements);
    }

    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    public function toArray(): array
    {
        return $this->elements;
    }
}
