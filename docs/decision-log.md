# Project Decision & Assumptions Log
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Cross-Phase Tracking (Phase 1 & Phase 2)  
**Date:** 2026-10-11  
**Status:** Active Tracking  

---

## 1. Decision Log Summary

This document records architectural, business, and algorithmic decisions made during Phases 1 and 2, specifically identifying unconfirmed requirements, proposed baseline defaults, and items awaiting team or instructor ratification.

---

## 2. Decision Log Registry

| Decision ID | Topic / Item | Context & Unresolved Issue | Proposed Baseline Default | Technical Rationale & Impact | Approval Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| `DEC-001` | Public Holiday Exclusion | The project plan establishes working days (excluding weekends) but does not define a public holiday calendar. | **Exclude public holidays in Version 2.** Only exclude Saturdays and Sundays from working day calculations. | Building a static or dynamic holiday calendar adds schema and administrative overhead outside initial scope. Can be added as a documented enhancement. | **Proposed Default (Awaiting Confirmation)** |
| `DEC-002` | Admin vs. HR Role Separation | The plan groups "Administrator / HR" together in early specifications, but separate roles exist in `users.role` enum (`admin`, `hr`, `employee`). | **Treat Administrator and HR as a single permission tier in Phase 1 & 2.** Both share administrative access. | Keeps authorization matrix simple while allowing future granular separation in later phases without schema breaking changes. | **Approved Baseline** |
| `DEC-003` | Employee Deletion Strategy | Permanent SQL deletion (`DELETE FROM employees`) breaks foreign key integrity in historical leave and audit records. | **Enforce Soft Deactivation (`employment_status = 'Inactive'`).** Hard SQL delete is prohibited. | Preserves all historical leave logs, activity logs, and balance records. Active queries filter `WHERE employment_status = 'Active'`. | **Approved Baseline** |
| `DEC-004` | Session Timeout Threshold | Plan requires automatic session expiration but does not prescribe an exact inactivity duration. | **Set inactivity timeout to 30 minutes (1800 seconds).** | Standard industry practice for workforce enterprise applications balancing security and usability. | **Approved Baseline** |
| `DEC-005` | Custom Queue Data Structure | Native PHP `array_shift()` has an $O(N)$ re-indexing cost, obscuring true FIFO queue behavior. | **Implement Queue using moving front/rear pointer indices or circular array.** | Ensures true $O(1)$ amortized operations and validates Computer Science concepts for academic defense. | **Approved Baseline** |
| `DEC-006` | Hash Table Collision Handling | Must avoid merely wrapping PHP associative arrays (`$arr[$k]`) as the demonstrated Hash Table. | **Implement manual polynomial hashing with separate chaining via linked bucket nodes.** | Guarantees authentic academic demonstration of buckets, hash functions, collision chains, and $O(1)$ avg vs $O(N)$ worst complexity. | **Approved Baseline** |
| `DEC-007` | Notification Delivery Channel | Multi-channel notifications (SMS/Email) require external API keys, credentials, and network dependencies. | **Confine notifications strictly to an internal database feed (`notifications` table).** | Eliminates external failure modes and offline testing barriers in local XAMPP environments. | **Approved Baseline** |
| `DEC-008` | Benchmark Dataset Scaling | Extreme dataset sizes ($N > 50,000$) cause memory exhaustion or severe execution lag on quadratic sorts ($O(N^2)$). | **Cap benchmark test sizes at $N \in \{10, 100, 500, 1000, 5000, 10000\}$.** Bubble/Selection sorts skip $N=10,000$. | Prevents Apache/PHP timeouts while providing sufficient variance to visually illustrate Big-O curves. | **Approved Baseline** |
| `DEC-009` | Timing Precision Tool | Standard `microtime(true)` is vulnerable to NTP clock adjustments and operating system jitter. | **Utilize PHP's high-resolution timer `hrtime(true)`.** | Delivers nanosecond precision for accurate micro-benchmarking of in-memory search and sort passes. | **Approved Baseline** |
| `DEC-010` | Database Transaction Isolation | Race conditions can occur if two HR admins approve leaves concurrently, risking negative balances. | **Use `SELECT ... FOR UPDATE` row locks inside PDO transactions for approval revalidation.** | Guarantees strict ACID isolation and prevents balance overdrafts. | **Approved Baseline** |
| `DEC-011` | In-System Notification Storage Extension | Approved plan sections 8 & 9 establish eight minimum core tables without a dedicated notifications table, while Section 38 specifies an in-system notification mechanism. | **Implement `notifications` as an approved schema extension table.** | Solves persistent in-system notification delivery (`FR-NOTIF-001`) cleanly without altering or overloading `activity_logs`. Preserves the 8 core tables while accommodating operational alert requirements. | **Approved Baseline Extension** |

---

## 3. Items Requiring Instructor / Team Confirmation

1. **Confirmation of Public Holiday Policy:** Does the instructor or client require a formal Philippine / national holiday table in the initial delivery, or is Monday–Friday weekend-only exclusion acceptable?
2. **Confirmation of Student Team Allocation:** Confirm team member mapping for `ROLE-COORD` through `ROLE-QA` defined in `team-responsibilities.md`.

