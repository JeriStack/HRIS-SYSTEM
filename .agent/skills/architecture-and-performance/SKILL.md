---
name: architecture-and-performance
description: >-
  Principal Systems Architect and Performance Engineering blueprint for NEXORA HR.
  Use when designing new modules or database schemas, auditing system architecture,
  reviewing PRs for premature optimization vs. foundational flaws, or setting up
  and executing a profiling and bottleneck-isolation loop across frontend, PHP/Apache,
  Nginx, MySQL 8.0, Ratchet WebSocket, and queue/cron workers.
---

# Architecture & Performance Engineering Blueprint (NEXORA HR)

Balance high development velocity and clean, maintainable code against avoiding
irreversible structural flaws — **get the critical 3% right upfront, write modular
code for the other 97%, and optimize only with profiling evidence.**

---

## 1. Architectural Sanity Checks (The "3%")

Foundational decisions that are expensive or risky to reverse later **must** be
sound from day one. Distinguish them using the **Reversibility Test**:
- **Foundational (Do Now):** Affects persisted data shape, external contracts,
  security/consistency boundaries, or asymptotic scaling (`O(n²)` / `O(n)` full scans).
- **Micro-optimization (Defer):** Local to a single function/class behind a stable
  interface and only affects constant factors (`2x` tweaks, syntax tricks).

### Day-One Checklist for NEXORA HR

1. **Data Model & Indexing Strategy (MySQL 8.0)**
   - Enforce primary keys, foreign keys, `NOT NULL` constraints, and unique indexes
     at the schema level (canonical parity across `database/schema.sql`,
     `database/migrations/`, and `docker/mysql/init/01-schema.sql`).
   - Add composite indexes aligned to actual `WHERE` + `ORDER BY` predicates
     (equality columns first, range/sort columns last) — e.g., `(user_id, created_at)`,
     `(status, available_at)` for `queue_jobs`, `(employee_id, start_date, end_date)`
     for leave overlap checks.
   - Use **explicit pagination** (`LIMIT` + offset or keyset cursor) on every list
     endpoint; never allow unbounded `SELECT *` on growing tables (`activity_logs`,
     `notifications`, `leave_requests`, `support_tickets`).

2. **Transactional Integrity & Concurrency Boundaries**
   - Wrap multi-step state mutations (leave filing/approval + balance deduction +
     outbox insert) in explicit ACID transactions (`TransactionManager`) with row
     locks (`SELECT ... FOR UPDATE` / `SKIP LOCKED`) where races exist.
   - Decouple side effects (SMTP email, bulk announcement fan-out, CSV export) from
     HTTP request transactions via the **transactional outbox** + MySQL `queue_jobs`
     worker (`dispatch_job()`), never blocking request threads on network I/O.

3. **Asymptotic Complexity & Query Shape (No Hidden `O(n²)` / N+1)**
   - Eliminate **N+1 SQL queries** upfront using `JOIN`s or batched `IN (...)` lookups.
   - Choose appropriate data structures/algorithms when collections can grow
     (e.g., `HashTable`/map lookup instead of nested scans, `MergeSort` when
     stability + `O(n log n)` worst-case matter).

4. **Network, Protocol & Security Contracts**
   - Keep HTTP responses slim (select only needed columns; return proper HTTP status
     codes and cache headers for static assets in Nginx).
   - Isolate realtime push traffic onto the dedicated Ratchet WebSocket service
     (`/ws` via Nginx) with short-lived tickets, channel RBAC, origin validation,
     and connection/message caps rather than polling PHP-Apache workers.
   - Keep security boundaries fail-closed on the server side (RBAC/DRBAC, CSRF,
     secret redaction in `AuditService`, signed/validated downloads).

5. **Runtime & Environment Parity**
   - Keep PHP runtime version aligned across `Dockerfile`, `docker/scheduler/Dockerfile`,
     `composer.json`, and GitHub Actions workflows so dev, test, and prod behave identically.

---

## 2. Premature Optimization Red Flags

Stop and simplify whenever you see these patterns **without a profiling trace proving a bottleneck**:

| Red Flag | Why It Hurts | Do This Instead |
| :--- | :--- | :--- |
| **Caching everything upfront** | Introduces stale-data bugs, invalidation complexity, and hides broken queries. | Fix the SQL query/index first. Add caching only for proven hot, read-heavy paths with explicit invalidation or short TTLs. |
| **Micro-tweaking PHP syntax** (bitwise hacks, `++$i` vs `$i++`, manual array pre-sizing, avoiding clean helpers) | Zero measurable impact next to a single 2 ms SQL query; tanks readability. | Write clear, idiomatic typed PHP 8.5 (`declare(strict_types=1)`). |
| **Denormalizing tables before load exists** | Creates dual-write drift and painful migrations. | Normalize to 3NF first; denormalize or add materialized summaries only when `EXPLAIN ANALYZE` proves a join bottleneck. |
| **Hand-rolling raw SQL/mega-methods bypassing Services/Repositories** | Duplicates business rules, breaks testability, and makes real optimization harder later. | Keep queries behind repository/model methods so one clean optimization upgrades all callers. |
| **Over-engineering async/sharding/microservices early** | Multiplies operational failure modes for modest HRIS traffic. | Stay on the modular monolith + MySQL queue/outbox until metrics prove saturation. |
| **Skipping pagination/streaming while optimizing loops** | Optimizing in-memory loops over 50,000 rows that shouldn't have been loaded at all. | Bound the working set at the DB (`LIMIT`, aggregation in SQL, streaming CSV writes). |

---

## 3. The "Make It Right" Standard (Design for Future Optimization)

Write clean, swappable code so that **when profiling identifies a hot path, you can optimize inside one seam without rewriting callers**:

1. **Program to Narrow Interfaces & Ports**
   - Follow the repo's existing Clean/Hexagonal pattern (`app/Domain/*/Repositories`,
     `app/Application/*/UseCases`, `app/Infrastructure/Persistence/*`).
   - Controllers parse/validate HTTP input, enforce auth/CSRF, delegate to a
     Service/UseCase, and return a view/JSON response — zero raw SQL or business
     logic in controllers or templates.

2. **Isolate I/O Behind Cohesive Repositories & Services**
   - Keep all SQL inside Models/Repositories and all external I/O (SMTP, storage,
     WebSocket outbox) inside dedicated Services.
   - Swapping a slow query for a covering index, read-through cache, or batch query
     becomes a single-file change with existing unit/feature tests as a safety net.

3. **Separate Pure Domain Logic from Persistence**
   - Keep calculators (`LeaveCalculator`, `EmployeeTenureCalculator`, `LeavePolicyEngine`)
     pure and deterministic so they can be unit-tested in milliseconds and
     benchmarked in isolation.

4. **Preserve Observability Hooks**
   - Keep correlation/request IDs (`AuditService::getRequestId()`), structured logs,
     and clean error propagation so bottlenecks can be traced end-to-end when they appear.

5. **Enforce Automated Regression Nets**
   - Before optimizing any hot path, ensure PHPUnit (`make test`) covers its
     behavior and run static checks (`make lint`) afterward so optimizations never
     introduce silent regressions.

---

## 4. Profiling & Bottleneck Analysis Strategy

Never guess where time goes. Follow this closed loop **after the feature works correctly**:

```text
Define SLOs -> Measure Baseline -> Isolate Layer -> Profile Hot Spot -> Change One Thing -> Re-Measure & Verify Tests
```

### Step 1: Define Realistic SLAs / SLOs (HRIS Baseline Targets)

| Workload | p50 Target | p95 Target | p99 Target | Error Budget |
| :--- | :--- | :--- | :--- | :--- |
| Standard SSR / HTML pages (Dashboards, Lists) | < 120 ms | < 300 ms | < 600 ms | < 0.5% 5xx |
| JSON API / AJAX endpoints | < 60 ms | < 150 ms | < 300 ms | < 0.2% 5xx |
| Single DB query (indexed OLTP) | < 5 ms | < 20 ms | < 50 ms | 0 deadlocks |
| WebSocket push delivery (outbox -> browser) | < 250 ms | < 1,000 ms | < 2,000 ms | < 1% drops |
| Async queue jobs (email/export) | < 2 s | < 10 s | < 30 s | 0 silent drops (DLQ tracked) |
| Frontend Core Web Vitals (CWV) | LCP < 2.5 s | INP < 200 ms | CLS < 0.1 | 0 console errors |

*(Scale target: size load tests to 3x–5x expected peak concurrency — e.g., Monday
09:00 login/leave-filing burst — not hypothetical millions of users.)*

### Step 2: Isolate the Bottleneck Layer (Top-Down)

Measure **end-to-end wall time**, then split it across layers before touching code:

1. **Frontend vs. Network/Backend (Browser)**
   - Use **Chrome DevTools -> Network & Performance** (or Lighthouse / `debug-optimize-lcp` skill):
     - High **TTFB** (Time to First Byte) -> bottleneck is **Nginx / PHP / MySQL**.
     - Low TTFB, slow render/interaction -> bottleneck is **Frontend** (large assets, layout thrashing, unoptimized JS/DOM).
2. **Proxy vs. App Runtime (Nginx & Prometheus/Grafana)**
   - Inspect **Prometheus** (`:9090`) and **Grafana** (`:3001`) dashboards (`nginx-exporter`, `mysqld-exporter`, `app` metrics) and `/admin/system-health` to separate Nginx queuing from Apache/PHP worker saturation or MySQL load.
   - Check request timing logs (e.g., Nginx `$request_time` vs `$upstream_response_time`).
3. **PHP Application vs. Database (App + MySQL)**
   - Compare total PHP request duration against cumulative SQL time:
     - **DB-bound (> 50% time in SQL):** Check query count (N+1?) and slowest queries.
     - **CPU-bound (PHP time >> SQL time):** Profile PHP functions/loops/serialization.
     - **I/O-bound (waiting on SMTP/disk/HTTP):** Move work to `queue_jobs` (`worker`).

### Step 3: Recommended Profiling Tools & Patterns (NEXORA HR Stack)

- **Database (MySQL 8.0):**
  - Run `EXPLAIN ANALYZE <query>` inside `docker compose exec db mysql ...` to verify index usage, actual row counts, and temp-table/filesort overhead.
  - Inspect slow queries via MySQL `performance_schema.events_statements_summary_by_digest` or the slow query log (`long_query_time = 0.2`).
  - Monitor `mysqld-exporter` metrics in Prometheus/Grafana (InnoDB buffer pool hit rate, lock waits, threads running, slow query rate).
- **PHP Runtime:**
  - Use **Xdebug Profiler** (already wired via `INSTALL_XDEBUG=true` and `docker/php/xdebug.ini`) with `xdebug.mode=profile` selectively on slow endpoints, and inspect `.cachegrind` output in QCacheGrind / Webgrind / VS Code.
  - For algorithmic benchmarks, reuse/extend `app/dsa/benchmarks/` with `hrtime(true)` and `memory_get_peak_usage(true)`.
- **Load & Regression Testing:**
  - Generate repeatable local load against `http://localhost:8080` using `k6`, `hey`, or `wrk` (recording p50/p95/p99 and RPS before and after any change).
- **Frontend:**
  - Chrome DevTools Performance trace, Coverage tab, and Lighthouse Core Web Vitals (LCP, INP, CLS).

### Step 4: Optimization Discipline Rules
1. **Record baseline metrics** (p50/p95/p99 + query count + `EXPLAIN ANALYZE`) before editing.
2. **Change one bottleneck at a time** (e.g., add one composite index or batch one N+1 query).
3. **Re-measure with the same workload** and keep the change **only** if it measurably moves the target metric without degrading correctness or readability.
4. **Run verification gates:** `make check` (`lint` + `audit` + `test`) and relevant E2E tests (`make e2e`).
