<?php

/**
 * Singly Linked List Node (Node)
 * Employee HRIS & Leave Management System
 *
 * Represents a dynamic memory node holding a payload ($value) and a pointer
 * reference ($next) to the subsequent node in a Singly Linked List.
 */

namespace App\Dsa\Structures;

class Node
{
    public mixed $value;
    public ?Node $next = null;

    public function __construct(mixed $value, ?Node $next = null)
    {
        $this->value = $value;
        $this->next  = $next;
    }
}
