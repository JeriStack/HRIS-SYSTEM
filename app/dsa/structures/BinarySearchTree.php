<?php
/**
 * Binary Search Tree (BST) Data Structure
 * Used for logarithmic employee search by identifier
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

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
        if ($newNode->key < $node->key) {
            if ($node->left === null) {
                $node->left = $newNode;
                $this->count++;
            } else {
                $this->insertNode($node->left, $newNode);
            }
        } elseif ($newNode->key > $node->key) {
            if ($node->right === null) {
                $node->right = $newNode;
                $this->count++;
            } else {
                $this->insertNode($node->right, $newNode);
            }
        } else {
            $node->value = $newNode->value; // Update existing
        }
    }

    public function search(string|int $key): mixed
    {
        return $this->searchNode($this->root, $key);
    }

    private function searchNode(?BSTNode $node, string|int $key): mixed
    {
        if ($node === null) {
            return null;
        }

        if ($key < $node->key) {
            return $this->searchNode($node->left, $key);
        } elseif ($key > $node->key) {
            return $this->searchNode($node->right, $key);
        } else {
            return $node->value;
        }
    }

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

        if ($key < $node->key) {
            $node->left = $this->deleteNode($node->left, $key, $deleted);
        } elseif ($key > $node->key) {
            $node->right = $this->deleteNode($node->right, $key, $deleted);
        } else {
            $deleted = true;

            // Case 1 & 2: Node with zero or one child
            if ($node->left === null) {
                return $node->right;
            } elseif ($node->right === null) {
                return $node->left;
            }

            // Case 3: Node with two children, get in-order successor (smallest in the right subtree)
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

    public function size(): int
    {
        return $this->count;
    }

    public function inOrderTraversal(): array
    {
        $result = [];
        $this->traverseInOrder($this->root, $result);
        return $result;
    }

    private function traverseInOrder(?BSTNode $node, array &$result): void
    {
        if ($node !== null) {
            $this->traverseInOrder($node->left, $result);
            $result[] = ['key' => $node->key, 'value' => $node->value];
            $this->traverseInOrder($node->right, $result);
        }
    }
}
