<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\Stack;
use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{
    private Stack $stack;

    protected function setUp(): void
    {
        $this->stack = new Stack();
    }

    public function test_empty_stack_behavior(): void
    {
        $this->assertTrue($this->stack->isEmpty());
        $this->assertSame(0, $this->stack->size());
        $this->assertNull($this->stack->pop());
        $this->assertNull($this->stack->peek());
        $this->assertSame([], $this->stack->toArray());
    }

    public function test_push_and_pop_single_item(): void
    {
        $this->stack->push('Task 1');
        $this->assertFalse($this->stack->isEmpty());
        $this->assertSame(1, $this->stack->size());
        $this->assertSame('Task 1', $this->stack->peek());
        $this->assertSame(1, $this->stack->size()); // Peek does not remove

        $item = $this->stack->pop();
        $this->assertSame('Task 1', $item);
        $this->assertTrue($this->stack->isEmpty());
    }

    public function test_lifo_ordering(): void
    {
        $this->stack->push('A');
        $this->stack->push('B');
        $this->stack->push('C');

        $this->assertSame(3, $this->stack->size());
        $this->assertSame('C', $this->stack->pop());
        $this->assertSame('B', $this->stack->pop());
        $this->assertSame('A', $this->stack->pop());
        $this->assertTrue($this->stack->isEmpty());
    }

    public function test_clear(): void
    {
        $this->stack->push('Item 1');
        $this->stack->push('Item 2');
        $this->assertSame(2, $this->stack->size());

        $this->stack->clear();
        $this->assertTrue($this->stack->isEmpty());
        $this->assertSame(0, $this->stack->size());
        $this->assertNull($this->stack->pop());
    }

    public function test_to_array_preserves_bottom_to_top_order(): void
    {
        $this->stack->push(10);
        $this->stack->push(20);
        $this->stack->push(30);

        $this->assertSame([10, 20, 30], $this->stack->toArray());
    }
}

