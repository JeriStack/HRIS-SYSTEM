-- =============================================================================
-- Migration: Add Admin Role to Users Table
-- Purpose: Enhance the application role model from two roles ('hr', 'employee')
--          to three roles ('admin', 'hr', 'employee').
--          Preserves all existing user records, passwords, roles, and status.
-- Date: 2026-11-06
-- =============================================================================

ALTER TABLE `users` 
    MODIFY COLUMN `role` ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee';

