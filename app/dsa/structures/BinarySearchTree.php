<?php
/**
 * Binary Search Tree (BST) Data Structure
 * Used for logarithmic employee search by identifier and ordered traversal
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Dsa\Structures;

use App\Dsa\Algorithms\RecursionHelper;
use App\Dsa\Arrays\ArrayUtils;

class BSTNode
{
    public string|int $key;
    public mixed $value;
    public ?BSTNode $left = null;
    public ?BSTNode $right = null;

    public function __construct(string|int $key, mixed $value = null)
    {
        $this->key = $key;
        $this->value = $value;
    }
}

class BinarySearchTree
{
    private ?BSTNode $root = null;
    private int $count = 0;

    /**
     * Insert a key-value pair into the BST.
     * Duplicate keys update the existing node's value.
     * Average O(log n), Worst O(n).
     */
    public function insert(string|int $key, mixed $value = null): void
    {
        $newNode = new BSTNode($key, $value);
        if ($this->root === null) {
            $this->root = $newNode;
            $this->count++;
            return;
        }

        $this->insertNode($this->root, $newNode);
    }

    private function insertNode(BSTNode $node, BSTNode $newNode): void
    {
        $cmp = ArrayUtils::compareValues($newNode->key, $node->key);

        if ($cmp < 0) {
            if ($node->left === null) {
                $node->left = $newNode;
                $this->count++;
            } else {
                $this->insertNode($node->left, $newNode);
            }
        } elseif ($cmp > 0) {
            if ($node->right === null) {
                $node->right = $newNode;
                $this->count++;
            } else {
                $this->insertNode($node->right, $newNode);
            }
        } else {
            // Duplicate key policy: update stored value
            $node->value = $newNode->value;
        }
    }

    /**
     * Search for a value by key in the BST.
     * Returns the stored value or null if absent.
     * Average O(log n), Worst O(n).
     */
    public function search(string|int $key): mixed
    {
        return $this->searchNode($this->root, $key);
    }

    /**
     * Check whether a key exists in the BST.
     * Average O(log n), Worst O(n).
     */
    public function contains(string|int $key): bool
    {
        return $this->containsNode($this->root, $key);
    }

    private function searchNode(?BSTNode $node, string|int $key): mixed
    {
        if ($node === null) {
            return null;
        }

        $cmp = ArrayUtils::compareValues($key, $node->key);

        if ($cmp < 0) {
            return $this->searchNode($node->left, $key);
        } elseif ($cmp > 0) {
            return $this->searchNode($node->right, $key);
        } else {
            return $node->value;
        }
    }

    private function containsNode(?BSTNode $node, string|int $key): bool
    {
        if ($node === null) {
            return false;
        }

        $cmp = ArrayUtils::compareValues($key, $node->key);

        if ($cmp < 0) {
            return $this->containsNode($node->left, $key);
        } elseif ($cmp > 0) {
            return $this->containsNode($node->right, $key);
        } else {
            return true;
        }
    }

    /**
     * Delete a node by key from the BST.
     * Handles leaf, single-child, and two-child nodes (using in-order successor).
     * Returns true if deleted; false if absent.
     * Average O(log n), Worst O(n).
     */
    public function delete(string|int $key): bool
    {
        $deleted = false;
        $this->root = $this->deleteNode($this->root, $key, $deleted);
        if ($deleted) {
            $this->count--;
        }
        return $deleted;
    }

    private function deleteNode(?BSTNode $node, string|int $key, bool &$deleted): ?BSTNode
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

            // Case 1 & 2: Leaf node or single-child node
            if ($node->left === null) {
                return $node->right;
            } elseif ($node->right === null) {
                return $node->left;
            }

            // Case 3: Two children -> replace with in-order successor (min in right subtree)
            $minRight = $this->findMin($node->right);
            $node->key = $minRight->key;
            $node->value = $minRight->value;
            $node->right = $this->deleteNode($node->right, $minRight->key, $deleted);
        }

        return $node;
    }

    private function findMin(BSTNode $node): BSTNode
    {
        while ($node->left !== null) {
            $node = $node->left;
        }
        return $node;
    }

    /**
     * Return the total node count.
     * O(1) time complexity.
     */
    public function size(): int
    {
        return $this->count;
    }

    /**
     * Check whether the tree has no nodes.
     * O(1) time complexity.
     */
    public function isEmpty(): bool
    {
        return $this->root === null;
    }

    /**
     * Clear all nodes from the tree.
     * O(1) pointer reset.
     */
    public function clear(): void
    {
        $this->root = null;
        $this->count = 0;
    }

    /**
     * Get root node reference for external traversals.
     */
    public function getRoot(): ?BSTNode
    {
        return $this->root;
    }

    /**
     * Recursive In-Order Traversal (LNR) producing sorted entries.
     * Delegates to RecursionHelper.
     * Time: O(n), Space: O(h).
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public function inOrderTraversal(): array
    {
        return RecursionHelper::inOrderTraversal($this->root);
    }

    /**
     * Recursive Pre-Order Traversal (NLR).
     * Delegates to RecursionHelper.
     * Time: O(n), Space: O(h).
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public function preOrderTraversal(): array
    {
        return RecursionHelper::preOrderTraversal($this->root);
    }

    /**
     * Recursive Post-Order Traversal (LRN).
     * Delegates to RecursionHelper.
     * Time: O(n), Space: O(h).
     *
     * @return array<int, array{key: string|int, value: mixed}>
     */
    public function postOrderTraversal(): array
    {
        return RecursionHelper::postOrderTraversal($this->root);
    }
}
