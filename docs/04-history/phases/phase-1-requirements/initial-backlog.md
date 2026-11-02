# Initial Project Backlog & GitHub Setup
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 1 — Requirements and Project Initiation  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Project Work Breakdown Backlog

This backlog provides an actionable, prioritized inventory of engineering and documentation tasks for Phases 1 and 2, and outlines downstream future phases.

### 1.1 Phase 1 & Phase 2 Active Backlog Items

| Issue ID | Title | Description | Acceptance Criteria | Priority | Phase | Dependency | Owner Role | Status |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| `BL-001` | Compile SRS Document | Draft the functional and non-functional requirements specification with unique identifiers. | Complete `requirements-document.md` covering all 8 functional domains and 10 NFR categories. | Critical | Phase 1 | None | `ROLE-COORD` | **Done** |
| `BL-002` | Specify Roles & Permissions | Define Administrator/HR and Employee actor permissions and security rules. | Produce comprehensive role-permission matrix in `user-roles-and-permissions.md`. | Critical | Phase 1 | `BL-001` | `ROLE-ARCH` | **Done** |
| `BL-003` | Document Business Rules & Policies | Formalize workforce rules, leave filing rules, and transaction-safe approval protocols. | Produce `business-rules-and-leave-policies.md` with explicit IDs (`BR-001` to `BR-LEAVE-015`). | Critical | Phase 1 | `BL-001` | `ROLE-HRIS` | **Done** |
| `BL-004` | Define Scope, Assumptions & Risks | Document in-scope and out-of-scope boundaries and record the 10 baseline assumptions. | Produce `scope-assumptions-and-limitations.md`. | High | Phase 1 | `BL-001` | `ROLE-ARCH` | **Done** |
| `BL-005` | Formulate RACI Matrix | Establish team role definitions and project breakdown assignments. | Produce `team-responsibilities.md` with placeholder roles. | Medium | Phase 1 | None | `ROLE-COORD` | **Done** |
| `BL-006` | Document Initial Backlog | Compile structured backlog and GitHub project board configuration guide. | Produce `initial-backlog.md` with labels and instructions. | High | Phase 1 | None | `ROLE-QA` | **Done** |
| `BL-007` | Detail System Use Cases | Detail preconditions, main flows, alternate flows, and exception flows for all primary actions. | Complete `use-cases.md` with 15+ specifications. | Critical | Phase 2 | `BL-002` | `ROLE-ARCH` | **In Progress** |
| `BL-008` | Render Use-Case Diagram | Author text-based Mermaid use-case diagram for HR and Employee actors. | Produce valid Mermaid diagram in `use-case-diagram.mmd`. | High | Phase 2 | `BL-007` | `ROLE-ARCH` | **In Progress** |
| `BL-009` | Model System Flowchart | Construct end-to-end flowchart from user entry to database transactions and feedback. | Produce valid Mermaid flowchart in `system-flowchart.mmd`. | High | Phase 2 | `BL-003` | `ROLE-ARCH` | **In Progress** |
| `BL-010` | Formulate Layered Architecture | Specify Presentation, Application, Data Access, and Database layer boundaries. | Produce `architecture.md` and `architecture-diagram.mmd`. | Critical | Phase 2 | `BL-001` | `ROLE-ARCH` | **In Progress** |
| `BL-011` | Application Module Specs | Formulate detailed specifications for all 15 system modules. | Produce `module-specification.md` with inputs, outputs, rules, and owners. | Critical | Phase 2 | `BL-010` | `ROLE-ARCH` | **In Progress** |
| `BL-012` | Model Authentication Workflow | Model login, password verification, session regeneration, timeout, and role routing. | Produce valid Mermaid sequence/flowchart in `authentication-workflow.mmd`. | High | Phase 2 | `BL-002` | `ROLE-AUTH` | **In Progress** |
| `BL-013` | Model Employee Workflows | Create detailed workflow diagrams for employee and HR operations. | Produce valid Mermaid workflow in `employee-workflows.mmd`. | High | Phase 2 | `BL-007` | `ROLE-HRIS` | **In Progress** |
| `BL-014` | Model Leave Approval Workflow | Detail filing, validation, queueing, and transaction-safe approval/rejection. | Produce valid Mermaid diagram in `leave-management-workflow.mmd`. | Critical | Phase 2 | `BL-003` | `ROLE-HRIS` | **In Progress** |
| `BL-015` | Formulate DSA-to-HRIS Mapping | Map custom PHP algorithms and structures to real HRIS features and laboratory tests. | Complete `dsa-to-hris-mapping.md`. | Critical | Phase 2 | `BL-001` | `ROLE-DSA` | **In Progress** |
| `BL-016` | Design Navigation Maps | Map site architecture, authenticated routes, and role access barriers. | Produce `navigation-map.md`. | High | Phase 2 | `BL-002` | `ROLE-UIUX` | **In Progress** |
| `BL-017` | Formulate UI Screen Specs | Write wireframe/screen specifications for all 14 major application views and states. | Produce `ui-screen-specifications.md`. | High | Phase 2 | `BL-016` | `ROLE-UIUX` | **In Progress** |
| `BL-018` | Compile Traceability Matrix | Map all requirements to modules, architecture layers, diagrams, and future test cases. | Produce `requirements-traceability.md`. | High | Phase 1 & 2 | All | `ROLE-COORD` | **In Progress** |
| `BL-019` | Compile Project Decision Log | Maintain unconfirmed decisions, proposed defaults, and review status. | Produce `decision-log.md`. | High | Phase 1 & 2 | All | `ROLE-COORD` | **In Progress** |

---

### 1.2 Downstream Roadmap Backlog (Phases 3 to 16)

* **`BL-020` (Phase 3 — Database Design):** Design normalized MySQL relational database schema, author DDL migration `database.sql`, construct ERD diagram.
* **`BL-021` (Phase 4 — UI Implementation):** Construct responsive HTML5/CSS3 layout templates, CSS design tokens, components, and validation feedback states.
* **`BL-022` (Phase 5 — Authentication Engine):** Implement `core/Database.php`, `core/Router.php`, `core/Middleware.php`, and `AuthController.php`.
* **`BL-023` (Phase 6 — Core HRIS CRUD):** Implement `EmployeeController.php`, `DepartmentController.php`, `PositionController.php`, and associated models.
* **`BL-024` (Phase 7 — Leave Management Engine):** Implement `LeaveController.php`, leave validation routines, and transactional approval pipeline.
* **`BL-025` (Phase 8 — Searching & Sorting DSA):** Implement custom `LinearSearch`, `BinarySearch`, `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, and `QuickSort`.
* **`BL-026` (Phase 9 — Advanced Structures DSA):** Implement custom `Stack`, pointer `Queue`, `LinkedList`, chained `HashTable`, and `BinarySearchTree`.
* **`BL-027` (Phase 10 — System Integration):** Integrate DSA structures with HRIS views and connect notifications and activity audit logs.
* **`BL-028` (Phase 11 — Testing & QA):** Execute functional and unit test cases, document defect logs and regression testing results.
* **`BL-029` (Phase 12 — DSA Performance Benchmarking):** Execute empirical benchmark laboratory across $N = 10$ to $10,000$ and record metrics.
* **`BL-030` (Phase 13-16 — Documentation & Defense):** Produce User Manual, Technical Defense Presentation, and Demo Video script.

---

## 2. GitHub Setup and Issue Labeling Scheme

To maintain standardized project tracking on GitHub, the team should configure repository labels, project boards, and branching policies according to the following guidelines.

### 2.1 Recommended GitHub Labels

| Label Name | Color Code | Description |
| :--- | :--- | :--- |
| `phase-1` | `#0E8A16` (Green) | Requirements and project initiation tasks. |
| `phase-2` | `#1D76DB` (Blue) | System analysis and architectural design tasks. |
| `documentation` | `#0075CA` (Dark Blue) | Project documentation, specifications, and README updates. |
| `requirements` | `#D93F0B` (Orange) | Functional and non-functional requirement definitions. |
| `architecture` | `#5319E7` (Purple) | Software architecture, layers, and class designs. |
| `diagram` | `#C5DEF5` (Light Blue) | Flowcharts, workflows, and Mermaid diagrams. |
| `decision-needed`| `#B60205` (Red) | Unconfirmed business rules or technical decisions awaiting approval. |
| `security` | `#D4C5F9` (Lavender) | Authentication, authorization, CSRF, and data security. |
| `dsa` | `#FBCA04` (Yellow) | Custom data structures and algorithmic implementations. |
| `database` | `#C2E0C6` (Light Green) | Schema design, migrations, and transactional integrity. |

### 2.2 GitHub Project Board Setup Instructions

1. **Board Structure (Kanban / Automated Project):**
   * Columns: `Backlog` -> `Ready for Review` -> `In Progress` -> `QA Verification` -> `Done`.
2. **Issue Creation Procedure:**
   * Create an issue for each `BL-xxx` item above.
   * Attach the appropriate labels (`phase-1`, `phase-2`, `requirements`, etc.).
   * Assign an issue milestone corresponding to the project development week.
3. **Branching & Commit Convention:**
   * Trunk branch: `main` (stable production-ready releases).
   * Integration branch: `develop` or `development`.
   * Feature branch naming: `feature/<module-name>` (e.g., `feature/leave-management`, `feature/dsa-search`).
   * Commit message standard: `<type>(<scope>): <summary>` (e.g., `docs(phase-1): finalize system requirements specification`).
