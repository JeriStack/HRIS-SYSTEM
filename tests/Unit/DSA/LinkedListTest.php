<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\LinkedList;
use App\Dsa\Structures\Node;
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
        $this->assertNull($this->list->search('anything'));
        $this->assertFalse($this->list->indexOf('anything'));
        $this->assertFalse($this->list->remove('missing'));
        $this->assertNull($this->list->removeAt(0));
        $this->assertFalse($this->list->deleteAt(0));
    }

    public function test_node_class_is_used(): void
    {
        $this->list->append('First');
        $this->assertInstanceOf(Node::class, $this->list->getHead());
        $this->assertSame('First', $this->list->getHead()?->value);
    }

    public function test_append_prepend_and_insert_at(): void
    {
        $this->list->append('B');
        $this->list->prepend('A');
        $this->list->append('D');
        $this->assertTrue($this->list->insertAt(2, 'C'));

        $this->assertSame(4, $this->list->size());
        $this->assertSame(['A', 'B', 'C', 'D'], $this->list->toArray());

        // Insert at head (0) and tail (size)
        $this->assertTrue($this->list->insertAt(0, 'START'));
        $this->assertTrue($this->list->insertAt(5, 'END'));
        $this->assertSame(['START', 'A', 'B', 'C', 'D', 'END'], $this->list->toArray());

        // Invalid positions return false safely
        $this->assertFalse($this->list->insertAt(-1, 'X'));
        $this->assertFalse($this->list->insertAt(99, 'X'));
    }

    public function test_get_at_indices_and_traverse(): void
    {
        $this->list->insertLast('Zero');
        $this->list->insertLast('One');
        $this->list->insertLast('Two');

        $this->assertSame('Zero', $this->list->getAt(0));
        $this->assertSame('One', $this->list->getAt(1));
        $this->assertSame('Two', $this->list->getAt(2));
        $this->assertNull($this->list->getAt(3));
        $this->assertNull($this->list->getAt(-1));

        $upper = $this->list->traverse(fn(string $v, int $i): string => "{$i}:" . strtoupper($v));
        $this->assertSame(['0:ZERO', '1:ONE', '2:TWO'], $upper);
    }

    public function test_find_and_search_scalar_and_record(): void
    {
        $this->list->append(['id' => 10, 'employee_no' => 'EMP-010', 'title' => 'Leave #1']);
        $this->list->append(['id' => 20, 'employee_no' => 'EMP-020', 'title' => 'Leave #2']);
        $this->list->append('Simple String');

        $foundRecord = $this->list->find(20);
        $this->assertIsArray($foundRecord);
        $this->assertSame('Leave #2', $foundRecord['title']);

        $searchedRecord = $this->list->search('EMP-020', 'employee_no');
        $this->assertIsArray($searchedRecord);
        $this->assertSame('Leave #2', $searchedRecord['title']);
        $this->assertSame('Simple String', $this->list->search('Simple String'));
        $this->assertNull($this->list->search(999));
        $this->assertNull($this->list->find(999));

        $this->assertSame(1, $this->list->indexOf('EMP-020', 'employee_no'));
        $this->assertSame(2, $this->list->indexOf('Simple String'));
        $this->assertFalse($this->list->indexOf(999));
    }

    public function test_remove_by_value_and_position(): void
    {
        // 1. Single node removal
        $single = new LinkedList();
        $single->append('Solo');
        $this->assertTrue($single->remove('Solo'));
        $this->assertTrue($single->isEmpty());
        $this->assertSame([], $single->toArray());

        // 2. Head, middle, tail removal by value and position
        $this->list->append('A');
        $this->list->append('B');
        $this->list->append('C');
        $this->list->append('D');
        $this->list->append('E');

        $this->assertTrue($this->list->remove('A'));
        $this->assertSame(['B', 'C', 'D', 'E'], $this->list->toArray());

        // removeAt middle (index 1 -> 'C')
        $this->assertSame('C', $this->list->removeAt(1));
        $this->assertSame(['B', 'D', 'E'], $this->list->toArray());

        // deleteAt tail (index 2 -> 'E')
        $this->assertTrue($this->list->deleteAt(2));
        $this->assertSame(['B', 'D'], $this->list->toArray());
        $this->assertSame('D', $this->list->getTail()?->value);

        // Missing removal
        $this->assertFalse($this->list->remove('NonExistent'));
        $this->assertNull($this->list->removeAt(99));
        $this->assertFalse($this->list->deleteAt(-1));
        $this->assertSame(2, $this->list->size());
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
