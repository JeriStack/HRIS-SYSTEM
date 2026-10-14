<?php
/**
 * Guest Middleware
 * Prevents authenticated users from accessing login or guest-only pages
 * Employee HRIS & Leave Management System
 */

namespace Core;

class GuestMiddleware extends Middleware
{
    public function handle(): bool
    {
        if (isAuthenticated()) {
            redirect('/dashboard');
            return false;
        }

        return true;
    }
}
