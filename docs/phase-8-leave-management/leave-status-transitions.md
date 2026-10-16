# Leave Request Status Transitions & State Machine

## 1. Status Vocabulary

The system recognizes four authoritative lifecycle states defined by `ENUM('Pending','Approved','Rejected','Cancelled')` in MySQL:

- `Pending`: Initial state for all newly filed leave requests awaiting HR review.
- `Approved`: Terminal state indicating authorization by an HR or Administrator user. Consumes leave balance entitlement.
- `Rejected`: Terminal state indicating denial by an HR or Administrator user. Preserves entitlement.
- `Cancelled`: Terminal state indicating voluntary cancellation by the filing employee (or HR). Preserves entitlement.

---

## 2. Permitted Transitions Matrix

| From State | To State | Triggered By | Balance Impact | Notification Event |
|---|---|---|---|---|
| **[New Filing]** | `Pending` | Employee Submission | No deduction | Informs HR & Admin |
| `Pending` | `Approved` | HR Officer Approval | Atomically deducts `used_days` & updates `remaining_days` | Informs Requester |
| `Pending` | `Rejected` | HR Officer Rejection | No balance change | Informs Requester with reason |
| `Pending` | `Cancelled` | Employee Cancellation | No balance change | Informs HR Officer |
| `Approved` | *(Any)* | **Prohibited** | N/A | Terminal state immutable |
| `Rejected` | *(Any)* | **Prohibited** | N/A | Terminal state immutable |
| `Cancelled` | *(Any)* | **Prohibited** | N/A | Terminal state immutable |

---

## 3. Defense Against Race Conditions & Stale Actions

All status updates check `WHERE id = :id AND status = 'Pending'` inside a transactional boundary:
- If a reviewer attempts to approve an already approved or cancelled request, the database query matches zero rows and the operation safely returns `false`.
- Repeated button clicks or multiple reviewers reviewing simultaneously cannot cause duplicate balance deductions or conflicting state overrides.
