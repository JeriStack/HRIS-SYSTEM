-- Non-destructive migration: Add rejection_reason column to leave_requests table
-- Phase 8: Complete Leave Management System

ALTER TABLE `leave_requests` 
ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `reason`;
