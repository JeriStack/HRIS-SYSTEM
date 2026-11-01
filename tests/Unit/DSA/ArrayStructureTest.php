<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\ArrayStructure;
use App\Dsa\Structures\ArrayWrapper;
use PHPUnit\Framework\TestCase;

class ArrayStructureTest extends TestCase
{
    public function test_empty_array_behavior(): void
    {
        $arr = new ArrayStructure();
        $this->assertInstanceOf(ArrayWrapper::class, $arr);
        $this->assertTrue($arr->isEmpty());
        $this->assertSame(0, $arr->size());
        $this->assertCount(0, $arr);
        $this->assertSame([], $arr->toArray());
        $this->assertNull($arr->get(0));
        $this->assertNull($arr->get(-1));
        $this->assertFalse($arr->update(0, 'x'));
        $this->assertFalse($arr->delete(0));
        $this->assertNull($arr->removeAt(0));
        $this->assertFalse($arr->search('anything'));
    }

    public function test_add_append_and_insert_with_manual_shifting(): void
    {
        $arr = new ArrayStructure(['EMP-001', 'EMP-003']);
        $arr->append('EMP-004');
        $this->assertTrue($arr->insert(1, 'EMP-002'));

        $this->assertSame(4, $arr->size());
        $this->assertSame(['EMP-001', 'EMP-002', 'EMP-003', 'EMP-004'], $arr->toArray());

        // Insert at head (0) and tail (size)
        $this->assertTrue($arr->insert(0, 'EMP-000'));
        $this->assertTrue($arr->insert(5, 'EMP-005'));
        $this->assertSame(['EMP-000', 'EMP-001', 'EMP-002', 'EMP-003', 'EMP-004', 'EMP-005'], $arr->toArray());

        // Out-of-bounds insertions fail safely
        $this->assertFalse($arr->insert(-1, 'INVALID'));
        $this->assertFalse($arr->insert(99, 'INVALID'));
    }

    public function test_get_update_set_and_delete(): void
    {
        $arr = new ArrayStructure(['Alice', 'Bob', 'Charlie', 'David']);
        $this->assertSame('Bob', $arr->get(1));

        $this->assertTrue($arr->update(1, 'Bobby'));
        $this->assertSame('Bobby', $arr->get(1));

        $this->assertTrue($arr->set(2, 'Charles'));
        $this->assertSame('Charles', $arr->get(2));

        // Delete index 1 ('Bobby') -> shifts remaining elements left
        $this->assertTrue($arr->delete(1));
        $this->assertSame(['Alice', 'Charles', 'David'], $arr->toArray());
        $this->assertSame(3, $arr->size());

        // removeAt returns the deleted element
        $removed = $arr->removeAt(1);
        $this->assertSame('Charles', $removed);
        $this->assertSame(['Alice', 'David'], $arr->toArray());
    }

    public function test_traverse_search_find_filter_and_sort(): void
    {
        $employees = [
            ['id' => 1, 'employee_no' => 'EMP-003', 'last_name' => 'Zamora', 'employment_status' => 'Active'],
            ['id' => 2, 'employee_no' => 'EMP-001', 'last_name' => 'Alvarez', 'employment_status' => 'Inactive'],
            ['id' => 3, 'employee_no' => 'EMP-002', 'last_name' => 'Mendoza', 'employment_status' => 'Active'],
        ];

        $arr = new ArrayStructure($employees);

        // Traverse with callback
        $nos = $arr->traverse(fn(array $e, int $i): string => "{$i}:{$e['employee_no']}");
        $this->assertSame(['0:EMP-003', '1:EMP-001', '2:EMP-002'], $nos);

        // Search by attribute
        $this->assertSame(1, $arr->search('EMP-001', 'employee_no'));
        $this->assertFalse($arr->search('EMP-999', 'employee_no'));

        // Find by attribute or predicate
        $found = $arr->find('EMP-002', 'employee_no');
        $this->assertIsArray($found);
        $this->assertSame('Mendoza', $found['last_name']);

        // Filter active employees without mutating original
        $active = $arr->filter(fn(array $e): bool => $e['employment_status'] === 'Active');
        $this->assertSame(2, $active->size());
        $this->assertSame(3, $arr->size());

        // Sort by employee_no ascending without mutating original
        $sorted = $arr->sort('merge', 'employee_no', 'asc');
        $this->assertSame(['EMP-001', 'EMP-002', 'EMP-003'], array_column($sorted->toArray(), 'employee_no'));
        $first = $arr->get(0);
        $this->assertIsArray($first);
        $this->assertSame('EMP-003', $first['employee_no']);
    }
}
