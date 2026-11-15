-- =============================================================================
-- Seed Data: Employee HRIS & Leave Management System
-- Purpose: Initial operational seed data for testing & development demonstration
-- Security: Passwords hashed via BCRYPT. Plaintext credentials provided in comments.
-- Documentation: docs/phase-3-database/database-design.md
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Departments Seed
-- -----------------------------------------------------------------------------
INSERT INTO `departments` (`id`, `department_name`, `description`, `created_at`) VALUES
(1, 'Executive Management', 'Executive leadership and company administration', NOW()),
(2, 'Human Resources', 'Talent acquisition, employee welfare, and compliance', NOW()),
(3, 'Information Technology', 'Software engineering, infrastructure, and IT support', NOW()),
(4, 'Finance & Accounting', 'Corporate accounting, budgeting, and payroll tracking', NOW()),
(5, 'Marketing & Sales', 'Brand management, communications, and customer outreach', NOW())
ON DUPLICATE KEY UPDATE `department_name` = VALUES(`department_name`);

-- -----------------------------------------------------------------------------
-- 2. Positions Seed
-- -----------------------------------------------------------------------------
INSERT INTO `positions` (`id`, `position_name`, `department_id`, `description`, `created_at`) VALUES
(1, 'Chief Executive Officer', 1, 'Top executive responsible for strategic direction', NOW()),
(2, 'HR Director', 2, 'Head of Human Resources department', NOW()),
(3, 'HR Officer', 2, 'Handles employee records and leave review', NOW()),
(4, 'Senior Software Engineer', 3, 'Full-stack application architecture and backend lead', NOW()),
(5, 'Junior Software Engineer', 3, 'Web development and technical maintenance', NOW()),
(6, 'Systems Administrator', 3, 'Server infrastructure, network, and security', NOW()),
(7, 'Finance Manager', 4, 'Financial planning and corporate reporting', NOW()),
(8, 'Accountant', 4, 'Day-to-day bookkeeping and reconciliation', NOW()),
(9, 'Marketing Specialist', 5, 'Campaign management and digital outreach', NOW())
ON DUPLICATE KEY UPDATE `position_name` = VALUES(`position_name`);

-- -----------------------------------------------------------------------------
-- 3. Employees Seed (Active & Inactive Records)
-- -----------------------------------------------------------------------------
INSERT INTO `employees` (`id`, `employee_no`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `address`, `birth_date`, `gender`, `department_id`, `position_id`, `date_hired`, `employment_status`, `created_at`) VALUES
(1, 'EMP001', 'Eleanor', 'Rose', 'Vance', 'eleanor.vance@company.local', '+63 917 111 2222', '123 Pioneer St, Pasig City', '1985-04-12', 'Female', 1, 1, '2018-01-15', 'Active', NOW()),
(2, 'EMP002', 'Marcus', 'Aurelio', 'Tan', 'marcus.tan@company.local', '+63 918 222 3333', '456 Ayala Ave, Makati City', '1988-09-23', 'Male', 2, 2, '2019-03-01', 'Active', NOW()),
(3, 'EMP003', 'Sofia', 'Maria', 'Reyes', 'sofia.reyes@company.local', '+63 919 333 4444', '789 Ortigas Center, Mandaluyong City', '1992-11-05', 'Female', 2, 3, '2020-07-15', 'Active', NOW()),
(4, 'EMP004', 'David', 'James', 'Cruz', 'david.cruz@company.local', '+63 920 444 5555', '321 BGC High St, Taguig City', '1990-02-18', 'Male', 3, 4, '2021-02-01', 'Active', NOW()),
(5, 'EMP005', 'Maria', 'Clara', 'Santos', 'maria.santos@company.local', '+63 921 555 6666', '654 Katipunan Ave, Quezon City', '1995-06-30', 'Female', 3, 5, '2022-08-15', 'Active', NOW()),
(6, 'EMP006', 'Juan', 'Carlos', 'Dela Cruz', 'juan.delacruz@company.local', '+63 922 666 7777', '987 Taft Ave, Manila City', '1993-01-14', 'Male', 3, 6, '2021-11-10', 'Active', NOW()),
(7, 'EMP007', 'Beatrice', 'Anne', 'Gomez', 'beatrice.gomez@company.local', '+63 923 777 8888', '147 Alabang-Zapote Rd, Muntinlupa City', '1989-12-08', 'Female', 4, 7, '2020-01-10', 'Active', NOW()),
(8, 'EMP008', 'Ricardo', 'Luis', 'Mercado', 'ricardo.mercado@company.local', '+63 924 888 9999', '258 Shaw Blvd, Mandaluyong City', '1991-08-20', 'Male', 5, 9, '2021-05-15', 'Inactive', NOW())
ON DUPLICATE KEY UPDATE `employee_no` = VALUES(`employee_no`);

-- -----------------------------------------------------------------------------
-- 4. Users Seed (Authentication Accounts with Registered Email)
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `employee_id`, `username`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(2, 2, 'hr01', 'marcus.tan@company.local', '$2y$12$UM/S23UvIdPCDYRejfw2I.rmSwbOgJq5d0WTJRI0gH12yt70Z2eGW', 'hr', 'Active', NOW()),
(3, 3, 'hr02', 'sofia.reyes@company.local', '$2y$12$UM/S23UvIdPCDYRejfw2I.rmSwbOgJq5d0WTJRI0gH12yt70Z2eGW', 'hr', 'Active', NOW()),
(4, 4, 'emp004', 'david.cruz@company.local', '$2y$12$Rn6ZSP6XUZESPhtJMn1woeH7QCGhpMjiOy6SlKlDjhT5qa6BmwNvK', 'employee', 'Active', NOW()),
(5, 5, 'emp005', 'maria.santos@company.local', '$2y$12$Rn6ZSP6XUZESPhtJMn1woeH7QCGhpMjiOy6SlKlDjhT5qa6BmwNvK', 'employee', 'Active', NOW()),
(6, 6, 'emp006', 'juan.delacruz@company.local', '$2y$12$Rn6ZSP6XUZESPhtJMn1woeH7QCGhpMjiOy6SlKlDjhT5qa6BmwNvK', 'employee', 'Active', NOW()),
(7, 7, 'emp007', 'beatrice.gomez@company.local', '$2y$12$Rn6ZSP6XUZESPhtJMn1woeH7QCGhpMjiOy6SlKlDjhT5qa6BmwNvK', 'employee', 'Active', NOW())
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `username` = VALUES(`username`);

-- -----------------------------------------------------------------------------
-- 5. Leave Types Seed
-- -----------------------------------------------------------------------------
INSERT INTO `leave_types` (`id`, `leave_name`, `leave_code`, `description`, `classification`, `benefit_category`, `entitlement_model`, `legal_reference`, `default_days`, `default_unit`, `is_paid`, `is_confidential`, `statutory_protected`, `status`, `created_at`) VALUES
(1, 'Vacation Leave', 'VL', 'Annual rest and personal vacation leave', 'Company', 'CompanyAnnual', 'annual', 'Company Policy (Satisfies Labor Code Art. 95 SIL equivalent when >= 5 paid days)', 15, 'working_days', 1, 0, 0, 'Active', NOW()),
(2, 'Sick Leave', 'SL', 'Medical rest due to illness, injury, or clinical consult', 'Company', 'CompanyAnnual', 'annual', 'Company Policy', 10, 'working_days', 1, 0, 0, 'Active', NOW()),
(3, 'Emergency Leave', 'EL', 'Unplanned personal emergencies or urgent family matters', 'Company', 'CompanyAnnual', 'annual', 'Company Policy (Distinct from RA 8972/11861 Solo Parent Leave)', 5, 'working_days', 1, 0, 0, 'Active', NOW()),
(4, 'Maternity Leave', 'MATERNITY', 'Statutory expanded maternal leave (105d live birth, 120d solo parent, 60d miscarriage/ETP)', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 11210 (Expanded Maternity Leave Law)', 105, 'calendar_days', 1, 0, 1, 'Active', NOW()),
(5, 'Paternity Leave', 'PATERNITY', 'Statutory paternal leave for up to first 4 deliveries of lawful spouse', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 8187 (Paternity Leave Act, First 4 Deliveries of Lawful Spouse)', 7, 'working_days', 1, 0, 1, 'Active', NOW()),
(6, 'Service Incentive Leave', 'SIL', 'Five (5) paid working days after at least 1 year of service under Article 95 of the Labor Code (unless satisfied by equivalent paid vacation leave).', 'Statutory', 'StatutoryAnnual', 'annual', 'Labor Code Article 95', 5, 'working_days', 1, 0, 1, 'Active', NOW()),
(7, 'Parental Leave for Solo Parents', 'SOLO_PARENT', 'Up to seven (7) paid working days per year for qualified Solo Parents with at least 6 months of service and a valid Solo Parent ID Card (SPIC).', 'Statutory', 'StatutoryAnnual', 'annual', 'RA 8972 as amended by RA 11861', 7, 'working_days', 1, 0, 1, 'Active', NOW()),
(8, 'Special Leave for Women', 'SLW', 'Up to two (2) months (60 calendar days) full pay following surgery caused by gynecological disorders for female employees with at least 6 months aggregate service in the last 12 months.', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 9710 Section 18 (Magna Carta of Women)', 60, 'calendar_days', 1, 1, 1, 'Active', NOW()),
(9, 'VAWC Leave', 'VAWC', 'Up to ten (10) paid working days (extendable by protection order) for victims of violence against women and their children under strict confidentiality.', 'Statutory', 'StatutoryEvent', 'event_based', 'RA 9262 Section 43 (Anti-VAWC Act)', 10, 'working_days', 1, 1, 1, 'Active', NOW()),
(10, 'Bereavement Leave', 'BEREAVEMENT', 'Compassionate leave upon the death of an immediate family member per company policy.', 'Company', 'CompanyEvent', 'event_based', 'Company Bereavement Policy', 3, 'working_days', 1, 0, 0, 'Active', NOW()),
(11, 'Leave Without Pay', 'LWOP', 'Unpaid authorized absence tracked separately without deducting paid leave credits.', 'Company', 'Unpaid', 'unpaid', 'Company Unpaid Leave Policy', 0, 'working_days', 0, 0, 0, 'Active', NOW())
ON DUPLICATE KEY UPDATE `leave_name` = VALUES(`leave_name`), `leave_code` = VALUES(`leave_code`), `classification` = VALUES(`classification`), `benefit_category` = VALUES(`benefit_category`), `entitlement_model` = VALUES(`entitlement_model`);

-- -----------------------------------------------------------------------------
-- 6. Leave Balances Seed (Fiscal Year 2026)
-- -----------------------------------------------------------------------------
INSERT INTO `leave_balances` (`id`, `employee_id`, `leave_type_id`, `year`, `allocated_days`, `used_days`, `remaining_days`, `created_at`) VALUES
-- David Cruz (EMP004)
(1, 4, 1, 2026, 15, 3, 12, NOW()),
(2, 4, 2, 2026, 10, 1, 9, NOW()),
(3, 4, 3, 2026, 5, 0, 5, NOW()),
-- Maria Santos (EMP005)
(4, 5, 1, 2026, 15, 5, 10, NOW()),
(5, 5, 2, 2026, 10, 0, 10, NOW()),
(6, 5, 3, 2026, 5, 0, 5, NOW()),
-- Juan Dela Cruz (EMP006)
(7, 6, 1, 2026, 15, 0, 15, NOW()),
(8, 6, 2, 2026, 10, 2, 8, NOW()),
(9, 6, 3, 2026, 5, 0, 5, NOW()),
-- Beatrice Gomez (EMP007)
(10, 7, 1, 2026, 15, 4, 11, NOW()),
(11, 7, 2, 2026, 10, 0, 10, NOW()),
(12, 7, 3, 2026, 5, 1, 4, NOW())
ON DUPLICATE KEY UPDATE `allocated_days` = VALUES(`allocated_days`);

-- -----------------------------------------------------------------------------
-- 7. Leave Requests Seed (Lifecycle Demonstrations)
-- -----------------------------------------------------------------------------
INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `number_of_days`, `reason`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
-- Pending Requests (Waiting in FIFO Queue for HR Approval)
(1, 5, 1, '2026-11-02', '2026-11-06', 5, 'Family vacation trip out of town', 'Pending', NULL, NULL, '2026-10-01 09:15:00'),
(2, 4, 2, '2026-11-09', '2026-11-10', 2, 'Scheduled medical procedure and recovery', 'Pending', NULL, NULL, '2026-10-02 11:30:00'),
(3, 6, 1, '2026-11-16', '2026-11-18', 3, 'Attending family reunion event', 'Pending', NULL, NULL, '2026-10-03 14:00:00'),
-- Approved Requests (Balance was deducted)
(4, 4, 1, '2026-08-10', '2026-08-12', 3, 'Annual rest and personal travel', 'Approved', 2, '2026-08-01 10:00:00', '2026-07-28 08:30:00'),
(5, 5, 1, '2026-09-14', '2026-09-18', 5, 'Semester break vacation', 'Approved', 2, '2026-09-05 15:45:00', '2026-09-01 11:20:00'),
-- Rejected Request (No balance deducted)
(6, 7, 1, '2026-09-21', '2026-09-25', 5, 'Trip conflicts with fiscal audit schedule', 'Rejected', 2, '2026-09-15 16:00:00', '2026-09-10 13:10:00'),
-- Cancelled Request (Self-cancelled while pending; no balance deducted)
(7, 4, 3, '2026-10-05', '2026-10-05', 1, 'Personal issue resolved, leave no longer needed', 'Cancelled', NULL, NULL, '2026-10-01 10:00:00')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- -----------------------------------------------------------------------------
-- 8. Activity Logs Seed
-- -----------------------------------------------------------------------------
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'SEED_DATABASE', 'System database initialized with baseline seed records', 'SYSTEM', '0', '127.0.0.1', 'CLI Seed Runner', NOW()),
(2, 2, 'APPROVE_LEAVE', 'Approved 3 days Vacation Leave for EMP004 (David Cruz)', 'leave_requests', '4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(3, 2, 'APPROVE_LEAVE', 'Approved 5 days Vacation Leave for EMP005 (Maria Santos)', 'leave_requests', '5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(4, 2, 'REJECT_LEAVE', 'Rejected 5 days Vacation Leave for EMP007 (Beatrice Gomez) due to audit conflict', 'leave_requests', '6', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(5, 5, 'LEAVE_FILED', 'Filed 5 days Vacation Leave application for 2026-11-02 to 2026-11-06', 'leave_requests', '1', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW())
ON DUPLICATE KEY UPDATE `action` = VALUES(`action`);

-- -----------------------------------------------------------------------------
-- 9. Notifications Seed
-- -----------------------------------------------------------------------------
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 'New Leave Application', 'Employee EMP005 (Maria Santos) submitted a Vacation Leave request (5 days).', 'info', 0, NOW()),
(2, 4, 'Leave Request Approved', 'Your Vacation Leave request from 2026-08-10 to 2026-08-12 was approved.', 'success', 1, NOW()),
(3, 5, 'Leave Request Approved', 'Your Vacation Leave request from 2026-09-14 to 2026-09-18 was approved.', 'success', 1, NOW()),
(4, 7, 'Leave Request Rejected', 'Your Vacation Leave request for 2026-09-21 was rejected by HR.', 'warning', 0, NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- -----------------------------------------------------------------------------
-- 10. Canonical V1 Leave Policy Versions & Ledger Backfill
-- -----------------------------------------------------------------------------
INSERT INTO `leave_policy_versions` (
    `leave_type_id`, `version_number`, `policy_code`, `effective_from`, `approval_status`,
    `classification`, `entitlement_model`, `benefit_tier`, `sector_scope`, `legal_reference`,
    `entitlement_days`, `entitlement_unit`, `is_paid`, `allocation_frequency`,
    `carry_over_allowed`, `max_carry_over_days`, `cash_convertible`, `cash_conversion_max_days`,
    `min_service_months`, `service_window_months`, `counts_as_sil_equivalent`, `stack_with_equivalent`,
    `max_events_lifetime`, `max_extension_days`, `requires_documentation`, `document_requirements`,
    `requires_qualification`, `qualification_type`, `is_confidential`, `change_notes`
)
SELECT
    lt.id,
    1,
    CONCAT(COALESCE(lt.leave_code, CONCAT('LT', lt.id)), '-V1'),
    '2025-01-01',
    'Active',
    lt.classification,
    lt.entitlement_model,
    CASE
        WHEN lt.leave_code IN ('SIL', 'SOLO_PARENT', 'MATERNITY', 'PATERNITY', 'SLW', 'VAWC') THEN 'statutory_minimum'
        WHEN lt.entitlement_model = 'unpaid' THEN 'unpaid'
        ELSE 'company_benefit'
    END,
    'all',
    lt.legal_reference,
    lt.default_days,
    lt.default_unit,
    lt.is_paid,
    CASE
        WHEN lt.entitlement_model = 'event_based' THEN 'per_event'
        WHEN lt.entitlement_model = 'unpaid' THEN 'unlimited_unpaid'
        ELSE 'annual'
    END,
    0,
    0.00,
    CASE WHEN lt.leave_code = 'SIL' THEN 1 ELSE 0 END,
    CASE WHEN lt.leave_code = 'SIL' THEN 5.00 ELSE 0.00 END,
    CASE
        WHEN lt.leave_code = 'SIL' THEN 12
        WHEN lt.leave_code IN ('SOLO_PARENT', 'SLW') THEN 6
        ELSE 0
    END,
    CASE WHEN lt.leave_code = 'SLW' THEN 12 ELSE NULL END,
    CASE WHEN lt.leave_code = 'VL' THEN 1 ELSE 0 END,
    0,
    CASE WHEN lt.leave_code = 'PATERNITY' THEN 4 ELSE NULL END,
    CASE WHEN lt.leave_code IN ('MATERNITY', 'VAWC') THEN 30.00 ELSE 0.00 END,
    CASE WHEN lt.leave_code IN ('SOLO_PARENT', 'MATERNITY', 'PATERNITY', 'SLW', 'VAWC', 'BEREAVEMENT') THEN 1 ELSE 0 END,
    CASE lt.leave_code
        WHEN 'SOLO_PARENT' THEN 'Valid DSWD/LGU Solo Parent Identification Card (SPIC) verified by HR.'
        WHEN 'MATERNITY' THEN 'Obstetric medical certificate / birth certificate or miscarriage clinical certification.'
        WHEN 'PATERNITY' THEN 'Marriage certificate / cohabitation proof and notification of spouse pregnancy/delivery.'
        WHEN 'SLW' THEN 'Certified gynecological surgeon medical certificate.'
        WHEN 'VAWC' THEN 'Barangay/Court Protection Order or prosecutor/clerk/medical certification.'
        WHEN 'BEREAVEMENT' THEN 'Death certificate or official notice establishing immediate family relationship.'
        ELSE 'Optional supporting documentation.'
    END,
    CASE WHEN lt.leave_code IN ('SOLO_PARENT', 'PATERNITY', 'SIL') THEN 1 ELSE 0 END,
    CASE lt.leave_code
        WHEN 'SOLO_PARENT' THEN 'solo_parent_spic'
        WHEN 'PATERNITY' THEN 'paternity_marriage'
        WHEN 'SIL' THEN 'sil_exclusion'
        ELSE NULL
    END,
    lt.is_confidential,
    'Canonical seed policy V1'
FROM `leave_types` lt
WHERE NOT EXISTS (
    SELECT 1 FROM `leave_policy_versions` lpv
    WHERE lpv.leave_type_id = lt.id AND lpv.version_number = 1
);

UPDATE `leave_types` lt
INNER JOIN `leave_policy_versions` lpv ON lpv.leave_type_id = lt.id AND lpv.approval_status = 'Active'
SET lt.active_policy_version_id = lpv.id
WHERE lt.active_policy_version_id IS NULL;

INSERT INTO `leave_balance_transactions` (
    `employee_id`, `leave_type_id`, `leave_balance_id`, `policy_version_id`,
    `balance_year`, `transaction_type`, `days_delta`, `balance_before`, `balance_after`,
    `conversion_scope`, `remarks`, `is_confidential`
)
SELECT
    lb.employee_id, lb.leave_type_id, lb.id, lt.active_policy_version_id,
    lb.year, 'initial_allocation', lb.allocated_days, 0.00, lb.allocated_days,
    'none', CONCAT('Seed initial annual allocation for year ', lb.year), 0
FROM `leave_balances` lb
INNER JOIN `leave_types` lt ON lt.id = lb.leave_type_id
WHERE NOT EXISTS (
    SELECT 1 FROM `leave_balance_transactions` lbt
    WHERE lbt.leave_balance_id = lb.id AND lbt.transaction_type = 'initial_allocation'
);

SET FOREIGN_KEY_CHECKS = 1;
