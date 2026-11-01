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
        $this->assertSame(0, $this->table->getCollisionCount());
        $this->assertNull($this->table->get('missing'));
        $this->assertNull($this->table->search('missing'));
        $this->assertFalse($this->table->containsKey('missing'));
        $this->assertFalse($this->table->remove('missing'));
        $this->assertFalse($this->table->delete('missing'));
        $this->assertSame([], $this->table->keys());
        $this->assertSame([], $this->table->values());
        $this->assertSame([], $this->table->toArray());
    }

    public function test_set_insert_get_search_and_delete(): void
    {
        $this->table->set('EMP-001', ['name' => 'Alice']);
        $this->table->insert('EMP-002', ['name' => 'Bob']);

        $this->assertFalse($this->table->isEmpty());
        $this->assertSame(2, $this->table->size());
        $this->assertTrue($this->table->containsKey('EMP-001'));
        $this->assertTrue($this->table->containsKey('EMP-002'));
        $this->assertSame(['name' => 'Alice'], $this->table->get('EMP-001'));
        $this->assertSame(['name' => 'Bob'], $this->table->search('EMP-002'));
        $this->assertCount(2, $this->table->keys());
        $this->assertCount(2, $this->table->values());

        $this->assertTrue($this->table->delete('EMP-001'));
        $this->assertFalse($this->table->containsKey('EMP-001'));
    }

    public function test_update_existing_key(): void
    {
        $this->table->set('key1', 'Initial Value');
        $this->assertSame(1, $this->table->size());
        $this->assertSame('Initial Value', $this->table->get('key1'));

        $this->table->set('key1', 'Updated Value');
        $this->assertSame(1, $this->table->size());
        $this->assertSame('Updated Value', $this->table->get('key1'));
    }

    public function test_stored_null_disambiguation(): void
    {
        $this->table->set('null_key', null);

        $this->assertSame(1, $this->table->size());
        $this->assertNull($this->table->get('null_key'));
        $this->assertTrue($this->table->containsKey('null_key'));
        $this->assertFalse($this->table->containsKey('never_set'));
    }

    public function test_collision_handling_and_removal(): void
    {
        $this->table->set('alpha', 100);
        $this->table->set('beta', 200);
        $this->table->set('gamma', 300);

        $this->assertTrue($this->table->remove('beta'));
        $this->assertFalse($this->table->containsKey('beta'));
        $this->assertNull($this->table->get('beta'));

        $this->assertSame(100, $this->table->get('alpha'));
        $this->assertSame(300, $this->table->get('gamma'));
        $this->assertSame(2, $this->table->size());
    }

    public function test_dynamic_resizing_and_rehashing(): void
    {
        $initialCapacity = $this->table->getCapacity();

        for ($i = 1; $i <= 10; $i++) {
            $this->table->set("user_{$i}", "data_{$i}");
        }

        $this->assertGreaterThan($initialCapacity, $this->table->getCapacity());
        $this->assertSame(10, $this->table->size());

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
