<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\HashTable;
use PHPUnit\Framework\TestCase;

class HashTableTest extends TestCase
{
    private HashTable $table;

    protected function setUp(): void
    {
        $this->table = new HashTable(8); // Small capacity to force collisions and resizing
    }

    public function test_empty_table_behavior(): void
    {
        $this->assertTrue($this->table->isEmpty());
        $this->assertSame(0, $this->table->size());
        $this->assertNull($this->table->get('missing'));
        $this->assertFalse($this->table->containsKey('missing'));
        $this->assertFalse($this->table->remove('missing'));
        $this->assertSame([], $this->table->toArray());
    }

    public function test_set_and_get(): void
    {
        $this->table->set('EMP-001', ['name' => 'Alice']);
        $this->table->set('EMP-002', ['name' => 'Bob']);

        $this->assertFalse($this->table->isEmpty());
        $this->assertSame(2, $this->table->size());
        $this->assertTrue($this->table->containsKey('EMP-001'));
        $this->assertTrue($this->table->containsKey('EMP-002'));
        $this->assertSame(['name' => 'Alice'], $this->table->get('EMP-001'));
        $this->assertSame(['name' => 'Bob'], $this->table->get('EMP-002'));
    }

    public function test_update_existing_key(): void
    {
        $this->table->set('key1', 'Initial Value');
        $this->assertSame(1, $this->table->size());
        $this->assertSame('Initial Value', $this->table->get('key1'));

        $this->table->set('key1', 'Updated Value');
        $this->assertSame(1, $this->table->size()); // Size does not increment on update
        $this->assertSame('Updated Value', $this->table->get('key1'));
    }

    public function test_stored_null_disambiguation(): void
    {
        $this->table->set('null_key', null);

        $this->assertSame(1, $this->table->size());
        $this->assertNull($this->table->get('null_key'));
        // containsKey distinguishes stored null from absent key
        $this->assertTrue($this->table->containsKey('null_key'));
        $this->assertFalse($this->table->containsKey('never_set'));
    }

    public function test_collision_handling_and_removal(): void
    {
        // Insert items that share or differ in buckets
        $this->table->set('alpha', 100);
        $this->table->set('beta', 200);
        $this->table->set('gamma', 300);

        $this->assertTrue($this->table->remove('beta'));
        $this->assertFalse($this->table->containsKey('beta'));
        $this->assertNull($this->table->get('beta'));

        // Other keys in table remain accessible
        $this->assertSame(100, $this->table->get('alpha'));
        $this->assertSame(300, $this->table->get('gamma'));
        $this->assertSame(2, $this->table->size());
    }

    public function test_dynamic_resizing_and_rehashing(): void
    {
        $initialCapacity = $this->table->getCapacity();

        // Insert enough items to exceed 0.75 load factor (8 * 0.75 = 6)
        for ($i = 1; $i <= 10; $i++) {
            $this->table->set("user_{$i}", "data_{$i}");
        }

        $this->assertGreaterThan($initialCapacity, $this->table->getCapacity());
        $this->assertSame(10, $this->table->size());

        // Verify all keys remain accessible after rehashing
        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($this->table->containsKey("user_{$i}"));
            $this->assertSame("data_{$i}", $this->table->get("user_{$i}"));
        }
    }

    public function test_clear(): void
    {
        $this->table->set('k1', 'v1');
        $this->table->set('k2', 'v2');
        $this->assertSame(2, $this->table->size());

        $this->table->clear();
        $this->assertTrue($this->table->isEmpty());
        $this->assertSame(0, $this->table->size());
        $this->assertNull($this->table->get('k1'));
        $this->assertSame([], $this->table->toArray());
    }
}

