<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\LinkedList;
use PHPUnit\Framework\TestCase;

class LinkedListTest extends TestCase
{
    private LinkedList $list;

    protected function setUp(): void
    {
        $this->list = new LinkedList();
    }

    public function test_empty_list_behavior(): void
    {
        $this->assertTrue($this->list->isEmpty());
        $this->assertSame(0, $this->list->size());
        $this->assertSame([], $this->list->toArray());
        $this->assertNull($this->list->getAt(0));
        $this->assertNull($this->list->find('anything'));
        $this->assertFalse($this->list->remove('missing'));
    }

    public function test_append_and_prepend(): void
    {
        $this->list->append('B');
        $this->list->prepend('A');
        $this->list->append('C');

        $this->assertSame(3, $this->list->size());
        $this->assertSame(['A', 'B', 'C'], $this->list->toArray());
        $this->assertFalse($this->list->isEmpty());
    }

    public function test_get_at_indices(): void
    {
        $this->list->append('Zero');
        $this->list->append('One');
        $this->list->append('Two');

        $this->assertSame('Zero', $this->list->getAt(0));
        $this->assertSame('One', $this->list->getAt(1));
        $this->assertSame('Two', $this->list->getAt(2));
        $this->assertNull($this->list->getAt(3)); // Out of bounds
        $this->assertNull($this->list->getAt(-1)); // Negative index
    }

    public function test_find_scalar_and_record(): void
    {
        $this->list->append(['id' => 10, 'title' => 'Leave #1']);
        $this->list->append(['id' => 20, 'title' => 'Leave #2']);
        $this->list->append('Simple String');

        $foundRecord = $this->list->find(20);
        $this->assertIsArray($foundRecord);
        $this->assertSame('Leave #2', $foundRecord['title']);

        $foundScalar = $this->list->find('Simple String');
        $this->assertSame('Simple String', $foundScalar);

        $this->assertNull($this->list->find(999));
    }

    public function test_remove_head_middle_tail_and_only_node(): void
    {
        // 1. Single node removal
        $single = new LinkedList();
        $single->append('Solo');
        $this->assertTrue($single->remove('Solo'));
        $this->assertTrue($single->isEmpty());
        $this->assertSame([], $single->toArray());

        // 2. Head removal
        $this->list->append('A');
        $this->list->append('B');
        $this->list->append('C');
        $this->list->append('D');
        $this->assertTrue($this->list->remove('A'));
        $this->assertSame(['B', 'C', 'D'], $this->list->toArray());

        // 3. Middle node removal
        $this->assertTrue($this->list->remove('C'));
        $this->assertSame(['B', 'D'], $this->list->toArray());

        // 4. Tail removal
        $this->assertTrue($this->list->remove('D'));
        $this->assertSame(['B'], $this->list->toArray());

        // 5. Missing removal
        $this->assertFalse($this->list->remove('NonExistent'));
        $this->assertSame(1, $this->list->size());
    }

    public function test_clear_resets_list(): void
    {
        $this->list->append(1);
        $this->list->append(2);
        $this->assertSame(2, $this->list->size());

        $this->list->clear();
        $this->assertTrue($this->list->isEmpty());
        $this->assertSame(0, $this->list->size());
        $this->assertSame([], $this->list->toArray());
    }
}

