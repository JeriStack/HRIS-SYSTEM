# Screen Wireframe Specification: Notifications Screen
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Route:** `GET /notifications`  
**Access Guard:** `auth`  
**Status:** Approved for Implementation  

---

## 1. Screen Layout & Wireframe

```
+-----------------------------------------------------------------------------------+
|  Notification Inbox | System & Workflow Alerts               [ Mark All as Read ] |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ NOTIFICATION FEED ]                                                            |
|  +-----------------------------------------------------------------------------+  |
|  | [✓] LEAVE REQUEST APPROVED                                   • 15 mins ago  |  |
|  | Your Vacation Leave request from 2026-08-10 to 2026-08-12 was APPROVED by   |  |
|  | Marcus Tan (HR Director). 3 days deducted from balance.                     |  |
|  | [ View Leave Details ]                                           (UNREAD)   |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | [ℹ] LEAVE APPLICATION SUBMITTED                              • 2 hours ago  |  |
|  | Your Sick Leave application (2 days: Nov 09-10) is currently pending review |  |
|  | in the HR approval queue.                                                   |  |
|  | [ View Leave Status ]                                              (READ)   |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  | [!] LEAVE REQUEST REJECTED                                   • Oct 05, 2026 |  |
|  | Your Vacation Leave application for Sep 21 was REJECTED by HR.              |  |
|  | Reason: "Trip conflicts with corporate fiscal audit schedule."              |  |
|  | [ View History ]                                                   (READ)   |  |
|  +-----------------------------------------------------------------------------+  |
|                                                                                   |
|  Showing 3 notifications                                                          |
+-----------------------------------------------------------------------------------+
```

---

## 2. Empty State Specification

When a user has zero unread or historical notifications:

```
+-------------------------------------------------------------------+
|                                                                   |
|                         📭 No Notifications                        |
|                                                                   |
|              You are completely caught up! No active workflow     |
|              alerts or status updates are pending at this time.   |
|                                                                   |
|              [ Return to Dashboard ]                              |
|                                                                   |
+-------------------------------------------------------------------+
```
