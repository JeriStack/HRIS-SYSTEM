<?php

/**
 * Custom Binary Search Tree (BST) Data Structure
 * Employee HRIS & Leave Management System
 *
 * Duplicate Key Policy:
 * - Keys are unique per node. Inserting a key that already exists in the tree
 *   updates that node's payload ($value) in place without incrementing size()
 *   or creating duplicate key nodes.
 *
 * Complexity & Balancing Notes:
 * - Insert / Search / Delete:
 *   - Average Case (reasonably balanced tree): O(log n) time, O(log n) recursion stack space
 *   - Worst Case (monotonically ordered / skewed input): O(n) time, O(n) recursion stack space
 *   - Because this academic BST is not self-balancing (unlike AVL or Red-Black trees),
 *     inserting pre-sorted keys produces a degenerate linked-list-like tree of height n.
 * - Traversals (inOrder, preOrder, postOrder): O(n) time, O(n) output space.
 */

namespace App\Dsa\Structures;

use App\Dsa\Arrays\ArrayUtils;

class BSTNode
{
    public mixed $key;
    public mixed $value;
    public ?BSTNode $left = null;
    public ?BSTNode $right = null;

    public function __construct(mixed $key, mixed $value = null)
    {
        $this->key = $key;
        $this->value = $value ?? $key;
    }
}

class BinarySearchTree
{
    private ?BSTNode $root = null;
    private int $count = 0;

    /**
     * Insert a key-value pair into the Binary Search Tree.
     * If the key already exists, updates its value in place (Duplicate Policy).
     *
     * Time Complexity: O(log n) average, O(n) worst case (skewed tree)
     * Space Complexity: O(h) recursion stack where h is tree height
     */
    public function insert(mixed $key, mixed $value = null): void
    {
        $this->root = $this->insertNode($this->root, $key, $value ?? $key);
    }

    private function insertNode(?BSTNode $node, mixed $key, mixed $value): BSTNode
    {
        if ($node === null) {
            $this->count++;
            return new BSTNode($key, $value);
        }

        $cmp = ArrayUtils::compareValues($key, $node->key);
        if ($cmp < 0) {
            $node->left = $this->insertNode($node->left, $key, $value);
        } elseif ($cmp > 0) {
            $node->right = $this->insertNode($node->right, $key, $value);
        } else {
            // Duplicate key policy: update existing node's value in place
            $node->value = $value;
        }

        return $node;
    }

    /**
     * Search for a node by key and return its associated value, or null if not found.
     *
     * Time Complexity: O(log n) average, O(n) worst case
     * Space Complexity: O(1) (iterative search)
     */
    public function search(mixed $key): mixed
    {
        $node = $this->findNode($this->root, $key);
        return $node?->value;
    }

    /**
     * Check whether a key exists in the BST.
     *
     * Time Complexity: O(log n) average, O(n) worst case
     * Space Complexity: O(1)
     */
    public function contains(mixed $key): bool
    {
        return $this->findNode($this->root, $key) !== null;
    }

    private function findNode(?BSTNode $node, mixed $key): ?BSTNode
    {
        $current = $node;
        while ($current !== null) {
            $cmp = ArrayUtils::compareValues($key, $current->key);
            if ($cmp === 0) {
                return $current;
            }
            $current = $cmp < 0 ? $current->left : $current->right;
        }
        return null;
    }

    /**
     * Delete a node by key from the BST.
     * Handles all three structural cases:
     *   1. Leaf node (no children)
     *   2. Node with one child (left or right)
     *   3. Node with two children (replaced with in-order successor — minimum in right subtree)
     *
     * Time Complexity: O(log n) average, O(n) worst case
     */
    public function delete(mixed $key): bool
    {
        $deleted = false;
        $this->root = $this->deleteNode($this->root, $key, $deleted);
        if ($deleted) {
            $this->count--;
        }
        return $deleted;
    }

    private function deleteNode(?BSTNode $node, mixed $key, bool &$deleted): ?BSTNode
    {
        if ($node === null) {
            return null;
        }

        $cmp = ArrayUtils::compareValues($key, $node->key);
        if ($cmp < 0) {
            $node->left = $this->deleteNode($node->left, $key, $deleted);
        } elseif ($cmp > 0) {
            $node->right = $this->deleteNode($node->right, $key, $deleted);
        } else {
            $deleted = true;

            // Case 1 & 2: Node with zero or one child
            if ($node->left === null) {
                return $node->right;
            }
            if ($node->right === null) {
                return $node->left;
            }

            // Case 3: Node with two children — replace with in-order successor
            $successor = $this->findMin($node->right);
            $node->key = $successor->key;
            $node->value = $successor->value;

            $dummy = false;
            $node->right = $this->deleteNode($node->right, $successor->key, $dummy);
        }

        return $node;
    }

    private function findMin(BSTNode $node): BSTNode
    {
        $current = $node;
        while ($current->left !== null) {
            $current = $current->left;
        }
        return $current;
    }

    /**
     * In-Order Traversal (Left -> Root -> Right).
     * Produces elements sorted in ascending key order.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function inOrderTraversal(): array
    {
        $result = [];
        $this->inOrderRecursive($this->root, $result);
        return $result;
    }

    /**
     * Alias for inOrderTraversal().
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function inOrder(): array
    {
        return $this->inOrderTraversal();
    }

    private function inOrderRecursive(?BSTNode $node, array &$result): void
    {
        if ($node === null) {
            return;
        }
        $this->inOrderRecursive($node->left, $result);
        $result[] = ['key' => $node->key, 'value' => $node->value];
        $this->inOrderRecursive($node->right, $result);
    }

    /**
     * Pre-Order Traversal (Root -> Left -> Right).
     * Useful for cloning/serializing tree hierarchy from root downward.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function preOrderTraversal(): array
    {
        $result = [];
        $this->preOrderRecursive($this->root, $result);
        return $result;
    }

    /**
     * Alias for preOrderTraversal().
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function preOrder(): array
    {
        return $this->preOrderTraversal();
    }

    private function preOrderRecursive(?BSTNode $node, array &$result): void
    {
        if ($node === null) {
            return;
        }
        $result[] = ['key' => $node->key, 'value' => $node->value];
        $this->preOrderRecursive($node->left, $result);
        $this->preOrderRecursive($node->right, $result);
    }

    /**
     * Post-Order Traversal (Left -> Right -> Root).
     * Processes child subtrees before their parent node.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function postOrderTraversal(): array
    {
        $result = [];
        $this->postOrderRecursive($this->root, $result);
        return $result;
    }

    /**
     * Alias for postOrderTraversal().
     *
     * @return list<array{key: mixed, value: mixed}>
     */
    public function postOrder(): array
    {
        return $this->postOrderTraversal();
    }

    private function postOrderRecursive(?BSTNode $node, array &$result): void
    {
        if ($node === null) {
            return;
        }
        $this->postOrderRecursive($node->left, $result);
        $this->postOrderRecursive($node->right, $result);
        $result[] = ['key' => $node->key, 'value' => $node->value];
    }

    /**
     * Compute the height of the BST (0 for empty tree, 1 for single root node).
     * Time Complexity: O(n) | Space Complexity: O(h)
     */
    public function height(): int
    {
        return $this->computeHeight($this->root);
    }

    private function computeHeight(?BSTNode $node): int
    {
        if ($node === null) {
            return 0;
        }
        return 1 + max($this->computeHeight($node->left), $this->computeHeight($node->right));
    }

    public function size(): int
    {
        return $this->count;
    }

    public function isEmpty(): bool
    {
        return $this->root === null;
    }

    public function clear(): void
    {
        $this->root = null;
        $this->count = 0;
    }

    public function getRoot(): ?BSTNode
    {
        return $this->root;
    }
}
