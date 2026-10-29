<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\BinarySearchTree;
use PHPUnit\Framework\TestCase;

class BinarySearchTreeTest extends TestCase
{
    private BinarySearchTree $bst;

    protected function setUp(): void
    {
        $this->bst = new BinarySearchTree();
    }

    public function test_empty_tree_behavior(): void
    {
        $this->assertTrue($this->bst->isEmpty());
        $this->assertSame(0, $this->bst->size());
        $this->assertNull($this->bst->search('missing'));
        $this->assertFalse($this->bst->contains('missing'));
        $this->assertFalse($this->bst->delete('missing'));
        $this->assertNull($this->bst->getRoot());
        $this->assertSame([], $this->bst->inOrderTraversal());
        $this->assertSame([], $this->bst->preOrderTraversal());
        $this->assertSame([], $this->bst->postOrderTraversal());
    }

    public function test_insert_and_search_single_and_multiple(): void
    {
        $this->bst->insert('EMP-050', 'Employee 50');
        $this->bst->insert('EMP-025', 'Employee 25');
        $this->bst->insert('EMP-075', 'Employee 75');

        $this->assertFalse($this->bst->isEmpty());
        $this->assertSame(3, $this->bst->size());
        $this->assertTrue($this->bst->contains('EMP-050'));
        $this->assertTrue($this->bst->contains('EMP-025'));
        $this->assertTrue($this->bst->contains('EMP-075'));
        $this->assertFalse($this->bst->contains('EMP-999'));

        $this->assertSame('Employee 25', $this->bst->search('EMP-025'));
        $this->assertSame('Employee 75', $this->bst->search('EMP-075'));
        $this->assertNull($this->bst->search('EMP-999'));
    }

    public function test_duplicate_key_updates_value_without_increasing_size(): void
    {
        $this->bst->insert('EMP-010', 'Version 1');
        $this->assertSame(1, $this->bst->size());

        $this->bst->insert('EMP-010', 'Version 2');
        $this->assertSame(1, $this->bst->size());
        $this->assertSame('Version 2', $this->bst->search('EMP-010'));
    }

    public function test_traversals(): void
    {
        // Insert keys: 50, 25, 75, 10, 30
        $this->bst->insert(50, 'Fifty');
        $this->bst->insert(25, 'TwentyFive');
        $this->bst->insert(75, 'SeventyFive');
        $this->bst->insert(10, 'Ten');
        $this->bst->insert(30, 'Thirty');

        // In-Order Traversal (LNR) -> Ascending Sorted Order: 10, 25, 30, 50, 75
        $inOrder = $this->bst->inOrderTraversal();
        $this->assertSame([10, 25, 30, 50, 75], array_column($inOrder, 'key'));

        // Pre-Order Traversal (NLR) -> 50, 25, 10, 30, 75
        $preOrder = $this->bst->preOrderTraversal();
        $this->assertSame([50, 25, 10, 30, 75], array_column($preOrder, 'key'));

        // Post-Order Traversal (LRN) -> 10, 30, 25, 75, 50
        $postOrder = $this->bst->postOrderTraversal();
        $this->assertSame([10, 30, 25, 75, 50], array_column($postOrder, 'key'));
    }

    public function test_delete_leaf_single_child_and_two_children(): void
    {
        // Build tree:
        //        50
        //       /  \
        //     25    75
        //    /  \     \
        //   10  30     85
        $this->bst->insert(50, 'Fifty');
        $this->bst->insert(25, 'TwentyFive');
        $this->bst->insert(75, 'SeventyFive');
        $this->bst->insert(10, 'Ten');
        $this->bst->insert(30, 'Thirty');
        $this->bst->insert(85, 'EightyFive');
        $this->assertSame(6, $this->bst->size());

        // 1. Delete leaf node (10)
        $this->assertTrue($this->bst->delete(10));
        $this->assertSame(5, $this->bst->size());
        $this->assertFalse($this->bst->contains(10));
        $this->assertSame([25, 30, 50, 75, 85], array_column($this->bst->inOrderTraversal(), 'key'));

        // 2. Delete single-child node (75 has right child 85)
        $this->assertTrue($this->bst->delete(75));
        $this->assertSame(4, $this->bst->size());
        $this->assertFalse($this->bst->contains(75));
        $this->assertTrue($this->bst->contains(85));
        $this->assertSame([25, 30, 50, 85], array_column($this->bst->inOrderTraversal(), 'key'));

        // 3. Delete two-child root node (50 has left 25 and right 85)
        $this->assertTrue($this->bst->delete(50));
        $this->assertSame(3, $this->bst->size());
        $this->assertFalse($this->bst->contains(50));
        $this->assertSame([25, 30, 85], array_column($this->bst->inOrderTraversal(), 'key'));
    }

    public function test_clear(): void
    {
        $this->bst->insert('K1', 'V1');
        $this->bst->insert('K2', 'V2');
        $this->assertSame(2, $this->bst->size());

        $this->bst->clear();
        $this->assertTrue($this->bst->isEmpty());
        $this->assertSame(0, $this->bst->size());
        $this->assertNull($this->bst->search('K1'));
    }
}

