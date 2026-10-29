<?php
/**
 * Recursion Helper & Tree Traversal Demonstrator
 * Demonstrates recursive execution frames, base cases, and call stacks
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Structures\BSTNode;

class RecursionHelper
{
    /**
     * Recursive In-Order Traversal (Left, Root, Right).
     * For a BST, this produces keys in ascending sorted order.
     * Time: O(n), Auxiliary Space: O(h) call stack where h is tree height.
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public static function inOrderTraversal(?BSTNode $root): array
    {
        $result = [];
        self::inOrderHelper($root, $result);
        return $result;
    }

    private static function inOrderHelper(?BSTNode $node, array &$result): void
    {
        // Base case: empty subtree
        if ($node === null) {
            return;
        }

        // 1. Traverse left subtree
        self::inOrderHelper($node->left, $result);

        // 2. Visit current node
        $result[] = ['key' => $node->key, 'value' => $node->value];

        // 3. Traverse right subtree
        self::inOrderHelper($node->right, $result);
    }

    /**
     * Recursive Pre-Order Traversal (Root, Left, Right).
     * Useful for tree serialization or copying.
     * Time: O(n), Auxiliary Space: O(h) call stack.
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public static function preOrderTraversal(?BSTNode $root): array
    {
        $result = [];
        self::preOrderHelper($root, $result);
        return $result;
    }

    private static function preOrderHelper(?BSTNode $node, array &$result): void
    {
        if ($node === null) {
            return;
        }

        // 1. Visit current node
        $result[] = ['key' => $node->key, 'value' => $node->value];

        // 2. Traverse left subtree
        self::preOrderHelper($node->left, $result);

        // 3. Traverse right subtree
        self::preOrderHelper($node->right, $result);
    }

    /**
     * Recursive Post-Order Traversal (Left, Right, Root).
     * Useful for bottom-up evaluations or tree deallocations.
     * Time: O(n), Auxiliary Space: O(h) call stack.
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public static function postOrderTraversal(?BSTNode $root): array
    {
        $result = [];
        self::postOrderHelper($root, $result);
        return $result;
    }

    private static function postOrderHelper(?BSTNode $node, array &$result): void
    {
        if ($node === null) {
            return;
        }

        // 1. Traverse left subtree
        self::postOrderHelper($node->left, $result);

        // 2. Traverse right subtree
        self::postOrderHelper($node->right, $result);

        // 3. Visit current node
        $result[] = ['key' => $node->key, 'value' => $node->value];
    }

    /**
     * Calculate the height of a binary tree recursively.
     * Time: O(n), Space: O(h).
     */
    public static function treeHeight(?BSTNode $node): int
    {
        if ($node === null) {
            return 0;
        }
        return 1 + max(self::treeHeight($node->left), self::treeHeight($node->right));
    }

    /**
     * Calculate factorial of non-negative integer n recursively.
     */
    public static function factorial(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }
        return $n * self::factorial($n - 1);
    }

    /**
     * Calculate nth Fibonacci number recursively.
     */
    public static function fibonacci(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }
        if ($n === 1) {
            return 1;
        }
        return self::fibonacci($n - 1) + self::fibonacci($n - 2);
    }

    /**
     * Recursive Binary Search implementation over ordered array.
     */
    public static function binarySearchRecursive(array $arr, mixed $target, int $low, int $high, ?string $attribute = null): int|false
    {
        if ($low > $high) {
            return false;
        }

        $mid = $low + intdiv($high - $low, 2);
        $val = $attribute !== null ? ($arr[$mid][$attribute] ?? null) : $arr[$mid];

        if ($val === $target) {
            return $mid;
        }

        if ($val < $target) {
            return self::binarySearchRecursive($arr, $target, $mid + 1, $high, $attribute);
        }

        return self::binarySearchRecursive($arr, $target, $low, $mid - 1, $attribute);
    }
}
