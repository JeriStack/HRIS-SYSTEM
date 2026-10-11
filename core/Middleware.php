<?php
/**
 * Core Base Middleware Contract
 * Employee HRIS & Leave Management System
 */

namespace Core;

abstract class Middleware
{
    /**
     * Handle an incoming HTTP request before passing execution to the controller action.
     *
     * @return bool True if the request is permitted to proceed; false if blocked/redirected.
     */
    abstract public function handle(): bool;
}
