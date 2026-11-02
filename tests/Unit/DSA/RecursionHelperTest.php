<?php

namespace Tests\Unit\DSA;

use App\Dsa\Algorithms\RecursionHelper;
use App\Dsa\Structures\BSTNode;
use PHPUnit\Framework\TestCase;

class RecursionHelperTest extends TestCase
{
    public function test_empty_tree_traversals(): void
    {
        $this->assertSame([], RecursionHelper::inOrderTraversal(null));
        $this->assertSame([], RecursionHelper::preOrderTraversal(null));
        $this->assertSame([], RecursionHelper::postOrderTraversal(null));
        $this->assertSame(0, RecursionHelper::treeHeight(null));
    }

    public function test_single_node_tree(): void
    {
        $root = new BSTNode('ROOT', 'Single');

        $this->assertSame([['key' => 'ROOT', 'value' => 'Single']], RecursionHelper::inOrderTraversal($root));
        $this->assertSame([['key' => 'ROOT', 'value' => 'Single']], RecursionHelper::preOrderTraversal($root));
        $this->assertSame([['key' => 'ROOT', 'value' => 'Single']], RecursionHelper::postOrderTraversal($root));
        $this->assertSame(1, RecursionHelper::treeHeight($root));
    }

    public function test_balanced_tree_traversals(): void
    {
        //       B (2)
        //      /     \
        //    A (1)   C (3)
        $root = new BSTNode(2, 'B');
        $root->left = new BSTNode(1, 'A');
        $root->right = new BSTNode(3, 'C');

        $inOrder = RecursionHelper::inOrderTraversal($root);
        $this->assertSame([1, 2, 3], array_column($inOrder, 'key'));

        $preOrder = RecursionHelper::preOrderTraversal($root);
        $this->assertSame([2, 1, 3], array_column($preOrder, 'key'));

        $postOrder = RecursionHelper::postOrderTraversal($root);
        $this->assertSame([1, 3, 2], array_column($postOrder, 'key'));

        $this->assertSame(2, RecursionHelper::treeHeight($root));
    }

    public function test_skewed_trees(): void
    {
        // Left-skewed: 3 -> 2 -> 1
        $leftSkewed = new BSTNode(3, 'Three');
        $leftSkewed->left = new BSTNode(2, 'Two');
        $leftSkewed->left->left = new BSTNode(1, 'One');

        $this->assertSame([1, 2, 3], array_column(RecursionHelper::inOrderTraversal($leftSkewed), 'key'));
        $this->assertSame([3, 2, 1], array_column(RecursionHelper::preOrderTraversal($leftSkewed), 'key'));
        $this->assertSame([1, 2, 3], array_column(RecursionHelper::postOrderTraversal($leftSkewed), 'key'));
        $this->assertSame(3, RecursionHelper::treeHeight($leftSkewed));

        // Right-skewed: 10 -> 20 -> 30
        $rightSkewed = new BSTNode(10, 'Ten');
        $rightSkewed->right = new BSTNode(20, 'Twenty');
        $rightSkewed->right->right = new BSTNode(30, 'Thirty');

        $this->assertSame([10, 20, 30], array_column(RecursionHelper::inOrderTraversal($rightSkewed), 'key'));
        $this->assertSame([10, 20, 30], array_column(RecursionHelper::preOrderTraversal($rightSkewed), 'key'));
        $this->assertSame([30, 20, 10], array_column(RecursionHelper::postOrderTraversal($rightSkewed), 'key'));
        $this->assertSame(3, RecursionHelper::treeHeight($rightSkewed));
    }

    public function test_mathematical_recursion_helpers(): void
    {
        $this->assertSame(1, RecursionHelper::factorial(0));
        $this->assertSame(1, RecursionHelper::factorial(1));
        $this->assertSame(120, RecursionHelper::factorial(5));

        $this->assertSame(0, RecursionHelper::fibonacci(0));
        $this->assertSame(1, RecursionHelper::fibonacci(1));
        $this->assertSame(55, RecursionHelper::fibonacci(10));
    }
}

