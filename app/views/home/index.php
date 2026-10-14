<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> — Enterprise HRIS & DSA</title>
    <meta name="description" content="A modern Employee Human Resource Information System and Leave Management System designed to organize employee records, streamline leave requests, and demonstrate practical Data Structures & Algorithms in PHP.">
    <link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
</head>
<body>
    <header class="site-header" id="navbar">
        <div class="container nav-container">
            <a href="#home" class="brand-wrapper" aria-label="HRIS Home">
                <div class="brand-icon-box"><span>H</span></div>
                <div class="brand-text-block">
                    <span class="brand-title">HRIS</span>
                    <span class="brand-subtitle">Employee & Leave Management</span>
                </div>
            </a>

            <nav class="desktop-nav" aria-label="Main Navigation">
                <ul class="site-nav-links">
                    <li><a href="#home" class="nav-link active">Home</a></li>
                    <li><a href="#features" class="nav-link">Features</a></li>
                    <li><a href="#how-it-works" class="nav-link">How It Works</a></li>
                    <li><a href="#dsa-lab" class="nav-link">DSA Laboratory</a></li>
                    <li><a href="#roles" class="nav-link">Roles</a></li>
                    <li><a href="#about" class="nav-link">About</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <?php if (!empty($dbConnected)): ?>
                    <span class="badge badge-success" title="Database Connected (<?= e($dbName) ?>)" style="display: none;">
                        ● <?= e($dbName) ?>
                    </span>
                <?php endif; ?>
                <a href="<?= e(url('/login')) ?>" class="btn btn-primary" id="headerSignInBtn">
                    <span>Sign In</span>
                    <span aria-hidden="true">→</span>
                </a>
                <button type="button" id="mobileMenuBtn" class="mobile-menu-btn" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
            </div>
        </div>
    </header>

    <div id="mobileDrawer" class="mobile-drawer" aria-label="Mobile Navigation">
        <a href="#home" class="mobile-nav-link">Home</a>
        <a href="#features" class="mobile-nav-link">Features</a>
        <a href="#how-it-works" class="mobile-nav-link">How It Works</a>
        <a href="#dsa-lab" class="mobile-nav-link">DSA Laboratory</a>
        <a href="#roles" class="mobile-nav-link">Role-Based Access</a>
        <a href="#about" class="mobile-nav-link">About the Project</a>
        <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="<?= e(url('/login')) ?>" class="btn btn-primary" style="width: 100%;">Sign In to HRIS</a>
        </div>
    </div>

    <section class="hero-section" id="home">
        <div class="container hero-grid">
            <div class="hero-content">
                <div class="hero-eyebrow"><span>⚡</span> EMPLOYEE MANAGEMENT • LEAVE MANAGEMENT • DSA</div>
                <h1 class="hero-title">Simplify HR Management.<br>Organize Every Record.</h1>
                <p class="hero-paragraph">An Employee Human Resource Information System designed to organize employee records, manage leave requests, track leave balances, and demonstrate the practical application of Data Structures and Algorithms.</p>
                <div class="hero-actions">
                    <a href="<?= e(url('/login')) ?>" class="btn btn-primary btn-lg"><span>Sign In</span><span aria-hidden="true">→</span></a>
                    <a href="#features" class="btn btn-secondary btn-lg">Explore Features</a>
                </div>
                <div class="hero-meta-line">
                    <span>Built with</span>
                    <code>Native PHP 8.x</code>
                    <span>•</span>
                    <code>MySQL PDO</code>
                    <span>•</span>
                    <code>Custom DSA</code>
                </div>
            </div>

            <div class="hero-preview-wrapper">
                <div class="browser-mockup" aria-label="Interactive Product Interface Preview">
                    <div class="browser-header">
                        <div class="browser-controls">
                            <span class="browser-dot dot-red"></span>
                            <span class="browser-dot dot-yellow"></span>
                            <span class="browser-dot dot-green"></span>
                        </div>
                        <div class="browser-address-bar">
                            <span>https://hris.local/hr/dashboard</span>
                            <span style="color: var(--color-success); font-weight: bold;">🔒 Verified</span>
                        </div>
                    </div>
                    <div class="browser-body">
                        <aside class="mockup-sidebar" aria-hidden="true">
                            <div style="font-size: 0.75rem; font-weight: 800; color: #FFFFFF; padding: 0.25rem 0.5rem 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 0.25rem;">🏢 HR PORTAL</div>
                            <a href="#mock" class="mockup-nav-item active">📊 Dashboard</a>
                            <a href="#mock" class="mockup-nav-item">👥 Employees</a>
                            <a href="#mock" class="mockup-nav-item">⏳ Leave Queue</a>
                            <a href="#mock" class="mockup-nav-item">💳 Balances</a>
                            <a href="#mock" class="mockup-nav-item">📈 Reports</a>
                            <a href="#mock" class="mockup-nav-item">🧪 DSA Lab</a>
                        </aside>
                        <div class="mockup-content">
                            <div class="mockup-header-strip">
                                <div class="mockup-title">HR Management Portal (Executive View)</div>
                                <div class="mockup-user-pill">
                                    <span style="width: 20px; height: 20px; border-radius: 50%; background: var(--color-primary); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: bold;">HR</span>
                                    <span>HR Officer</span>
                                </div>
                            </div>
                            <div class="mockup-stats-row">
                                <div class="mockup-stat-card">
                                    <div class="mockup-stat-label">Total Staff</div>
                                    <div class="mockup-stat-value"><?= e((string)($stats['total_employees'] ?? 8)) ?></div>
                                </div>
                                <div class="mockup-stat-card">
                                    <div class="mockup-stat-label">Active Staff</div>
                                    <div class="mockup-stat-value" style="color: var(--color-success);"><?= e((string)($stats['active_employees'] ?? 7)) ?></div>
                                </div>
                                <div class="mockup-stat-card">
                                    <div class="mockup-stat-label">Pending Leaves</div>
                                    <div class="mockup-stat-value" style="color: var(--color-warning);"><?= e((string)($stats['pending_leaves'] ?? 3)) ?></div>
                                </div>
                            </div>
                            <div class="mockup-table-card">
                                <div class="mockup-table-title">
                                    <span>Pending Leave Applications</span>
                                    <span class="badge badge-warning" style="font-size: 0.65rem;"><?= e((string)($stats['pending_leaves'] ?? 3)) ?> Awaiting Decision</span>
                                </div>
                                <table class="mockup-table">
                                    <thead>
                                        <tr>
                                            <th>Ref</th>
                                            <th>Employee</th>
                                            <th>Type</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td><code>LR001</code></td><td><strong>Maria Santos</strong></td><td>Vacation</td><td>5d</td><td><span class="badge badge-warning" style="font-size: 0.65rem;">Pending</span></td></tr>
                                        <tr><td><code>LR002</code></td><td><strong>David Cruz</strong></td><td>Sick</td><td>2d</td><td><span class="badge badge-warning" style="font-size: 0.65rem;">Pending</span></td></tr>
                                        <tr><td><code>LR004</code></td><td><strong>David Cruz</strong></td><td>Vacation</td><td>3d</td><td><span class="badge badge-success" style="font-size: 0.65rem;">Approved</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mockup-watermark">* Live application data and role-based access controls are enforced on the backend.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="trust-section" aria-label="Core Value Highlights">
        <div class="container">
            <div class="trust-grid">
                <div class="trust-card"><div class="trust-icon-box">👥</div><div><h3>Organized Employee Records</h3><p>Keep profiles, departments, job positions, and employment status structured.</p></div></div>
                <div class="trust-card"><div class="trust-icon-box">⏳</div><div><h3>Streamlined Leave Management</h3><p>Support leave filing, review, approvals, and leave-history tracking.</p></div></div>
                <div class="trust-card"><div class="trust-icon-box">💳</div><div><h3>Clear Leave Balances</h3><p>Present allocated, used, and remaining days in an understandable quota matrix.</p></div></div>
                <div class="trust-card"><div class="trust-icon-box">🧪</div><div><h3>Practical DSA Learning</h3><p>Connect searching, sorting, and in-memory structures to real HR operations.</p></div></div>
            </div>
        </div>
    </section>

    <section class="section-padding features-section" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Enterprise Capabilities</div>
                <h2 class="section-title">Everything Organized in One HRIS</h2>
                <p class="section-desc">Explore the system modules designed to support employee administration, leave management, reporting, and algorithm demonstrations.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <h3 class="feature-title">Employee Directory</h3>
                    <p class="feature-desc">Search, maintain, and review employee records with stable organization and access restrictions.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📣</div>
                    <h3 class="feature-title">Leave Workflows</h3>
                    <p class="feature-desc">Track request volume, analyze balances, and keep approval decisions aligned with business rules.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚙️</div>
                    <h3 class="feature-title">Department & Roles</h3>
                    <p class="feature-desc">Manage job structures, role assignments, and workforce configuration with controlled access.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Operational Reporting</h3>
                    <p class="feature-desc">Monitor workforce trends, leave patterns, and activity logs using real data from the application.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding dsa-section" id="dsa-lab">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Data Structures & Algorithms</div>
                <h2 class="section-title">Real Algorithmic Patterns Applied to HR Workflows</h2>
                <p class="section-desc">The application uses efficient in-memory structures to model workforce records, approval queues, and algorithmic data retrieval.</p>
            </div>
            <div class="dsa-grid">
                <div class="dsa-card"><div><div class="dsa-card-header"><span class="dsa-card-icon">📚</span><span class="dsa-complexity-pill">O(n) Sequential Read</span></div><h3 class="dsa-card-title">Arrays & Lists</h3><p class="dsa-card-desc">Store employee, leave, and department datasets for structured retrieval and list-based iteration.</p></div><span class="dsa-tech-tag">EmployeeList • Array</span></div>
                <div class="dsa-card"><div><div class="dsa-card-header"><span class="dsa-card-icon">📤</span><span class="dsa-complexity-pill">FIFO • O(1) Enqueue</span></div><h3 class="dsa-card-title">FIFO Queue</h3><p class="dsa-card-desc">Demonstrates the First-In, First-Out principle processing incoming leave applications chronologically to prevent queue starvation.</p></div><span class="dsa-tech-tag">LeaveRequestQueue • FIFO</span></div>
                <div class="dsa-card"><div><div class="dsa-card-header"><span class="dsa-card-icon">🔑</span><span class="dsa-complexity-pill">O(1) Avg Hash Lookup</span></div><h3 class="dsa-card-title">Linked List & Hash Table</h3><p class="dsa-card-desc">Illustrates sequential data node chaining and constant-time employee record retrieval using bucket hashing with collision handling.</p></div><span class="dsa-tech-tag">HashTable • SinglyLinkedList</span></div>
                <div class="dsa-card"><div><div class="dsa-card-header"><span class="dsa-card-icon">🌳</span><span class="dsa-complexity-pill">O(log n) Search</span></div><h3 class="dsa-card-title">Binary Search Tree (BST)</h3><p class="dsa-card-desc">Hierarchical employee tree indexing demonstrating recursive In-Order, Pre-Order, and Post-Order algorithmic traversals.</p></div><span class="dsa-tech-tag">BinarySearchTree • Recursion</span></div>
            </div>
            <div class="dsa-cta-box">
                <a href="<?= e(url('/login')) ?>" class="btn btn-primary btn-lg" style="background-color: #38BDF8; color: #0F172A; border-color: #38BDF8;"><span>🧪 Explore the DSA Laboratory</span><span aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>

    <section class="section-padding roles-section" id="roles">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">User Experience</div>
                <h2 class="section-title">Tailored Experiences for Every Role</h2>
                <p class="section-desc">The system delivers distinct, role-specific interfaces protected by server-side authentication and authorization controls.</p>
            </div>
            <div class="roles-grid">
                <div class="role-card">
                    <div>
                        <div class="role-card-header">
                            <div class="role-icon-box">🛡️</div>
                            <h3 class="role-title">Human Resources (HR)</h3>
                        </div>
                        <p class="role-desc">Manage employee records, organizational departments, job positions, user accounts & roles, pending leave approvals, leave policies, and system audit logs.</p>
                        <ul class="role-features-list">
                            <li class="role-feature-item"><span class="check">✓</span> Master workforce directory with search & filters</li>
                            <li class="role-feature-item"><span class="check">✓</span> User account and role governance console</li>
                            <li class="role-feature-item"><span class="check">✓</span> FIFO pending leave approval queue with balance check</li>
                            <li class="role-feature-item"><span class="check">✓</span> Department & position configuration</li>
                            <li class="role-feature-item"><span class="check">✓</span> Annual employee leave balance matrix</li>
                            <li class="role-feature-item"><span class="check">✓</span> Workforce, leave, and department reports</li>
                            <li class="role-feature-item"><span class="check">✓</span> Security activity audit logs & DSA lab access</li>
                        </ul>
                    </div>
                    <a href="<?= e(url('/hr/dashboard')) ?>" class="btn btn-secondary" style="width: 100%;">Open HR Dashboard</a>
                </div>
                <div class="role-card">
                    <div>
                        <div class="role-card-header">
                            <div class="role-icon-box">👤</div>
                            <h3 class="role-title">Authenticated Employee</h3>
                        </div>
                        <p class="role-desc">View personal employment details, inspect real-time leave balances, file leave applications, and track application status.</p>
                        <ul class="role-features-list">
                            <li class="role-feature-item"><span class="check">✓</span> Personal profile summary and employment status</li>
                            <li class="role-feature-item"><span class="check">✓</span> Leave balances organized by category with visual indicators</li>
                            <li class="role-feature-item"><span class="check">✓</span> Interactive leave filing with working-day calculator</li>
                            <li class="role-feature-item"><span class="check">✓</span> Real-time pending application tracking</li>
                            <li class="role-feature-item"><span class="check">✓</span> Self-cancellation of unreviewed applications</li>
                            <li class="role-feature-item"><span class="check">✓</span> Complete historical leave application log</li>
                        </ul>
                    </div>
                    <a href="<?= e(url('/employee/dashboard')) ?>" class="btn btn-secondary" style="width: 100%;">Open Employee View</a>
                </div>
            </div>
            <div style="text-align: center; margin-top: 2rem; font-size: 0.875rem; color: var(--color-text-secondary);">
                <em>* Access control is enforced by the PHP backend session middleware on every protected route and database query.</em>
            </div>
        </div>
    </section>

    <section class="section-padding about-section" id="about">
        <div class="container">
            <div class="section-header">
                <div class="section-eyebrow">Academic Rationale</div>
                <h2 class="section-title">Designed for Practical HRIS and DSA Learning</h2>
                <p class="section-desc">A dual-purpose software architecture combining enterprise workforce management with computer science educational outcomes.</p>
            </div>
            <div class="about-grid">
                <div class="about-box">
                    <h3 class="about-box-title"><span>🏢</span> Enterprise HRIS Purpose</h3>
                    <p class="about-box-desc">Provide organizations with a reliable, structured platform to maintain employee information, manage department rosters, calculate Monday–Friday working-day leave deductions accurately, and preserve audit histories without data loss.</p>
                </div>
                <div class="about-box">
                    <h3 class="about-box-title"><span>🎓</span> Academic Computer Science Purpose</h3>
                    <p class="about-box-desc">Bridge the gap between theoretical algorithm concepts and real enterprise software by demonstrating custom data structures (Stacks, Queues, Hash Tables, BSTs) and comparative algorithm benchmarking implemented directly in PHP.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container cta-container">
            <h2 class="cta-title">Your HRIS Workspace Starts Here.</h2>
            <p class="cta-desc">Sign in to access the employee and leave-management features available to your assigned role.</p>
            <div class="cta-actions">
                <a href="<?= e(url('/login')) ?>" class="btn btn-primary btn-lg"><span>Sign In to HRIS</span><span aria-hidden="true">→</span></a>
                <a href="<?= e(url('/hr/dashboard')) ?>" class="btn btn-outline btn-lg"><span>🚀 Open HR Console</span></a>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand-title">HRIS Portal</div>
                    <p class="footer-brand-desc">Employee Human Resource Information System and Leave Management System using PHP, MySQL, and Data Structures & Algorithms.</p>
                    <div style="margin-top: 1rem; font-size: 0.8125rem; color: #475569;">Database: <code><?= e($dbName) ?></code> (MySQL/InnoDB)</div>
                </div>
                <div>
                    <div class="footer-col-title">Navigation</div>
                    <ul class="footer-links-list">
                        <li><a href="#home" class="footer-link">Home</a></li>
                        <li><a href="#features" class="footer-link">Features</a></li>
                        <li><a href="#how-it-works" class="footer-link">How It Works</a></li>
                        <li><a href="#dsa-lab" class="footer-link">DSA Laboratory</a></li>
                        <li><a href="#roles" class="footer-link">Role-Based Access</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-title">Access</div>
                    <ul class="footer-links-list">
                        <li><a href="<?= e(url('/login')) ?>" class="footer-link">Sign In</a></li>
                        <li><a href="<?= e(url('/hr/dashboard')) ?>" class="footer-link">HR Dashboard</a></li>
                        <li><a href="<?= e(url('/employee/dashboard')) ?>" class="footer-link">Employee Dashboard</a></li>
                    </ul>
                </div>
                <div>
                    <div class="footer-col-title">Technical Foundation</div>
                    <p style="font-size: 0.8125rem; line-height: 1.6; color: #64748B; margin-bottom: 0.75rem;">Built with Native PHP, MySQL, HTML5, CSS3, JavaScript, and Data Structures & Algorithms.</p>
                    <div style="font-size: 0.75rem; color: #4ADE80; font-family: var(--font-mono);">PHP 8.x • MySQL PDO • Secure MVC</div>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= e(asset('js/landing.js')) ?>"></script>
</body>
</html>