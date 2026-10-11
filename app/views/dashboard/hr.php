<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'HR & Administration Dashboard') ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        body {
            background-color: #F8FAFC;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #0F172A;
        }
        .app-header {
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .app-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.15rem;
            color: #1E293B;
        }
        .app-badge {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .user-nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .user-info {
            text-align: right;
            font-size: 0.875rem;
        }
        .user-name {
            font-weight: 600;
            color: #1E293B;
        }
        .user-role {
            font-size: 0.75rem;
            color: #64748B;
            text-transform: uppercase;
        }
        .btn-logout {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            padding: 0.4rem 0.85rem;
            border-radius: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-logout:hover {
            background: #FEE2E2;
            border-color: #FCA5A5;
            color: #B91C1C;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1E293B;
        }
        .content-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .content-card h2 {
            font-size: 1.15rem;
            margin: 0 0 1rem 0;
            color: #1E293B;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #F1F5F9;
        }
        th {
            background: #F8FAFC;
            color: #475569;
            font-weight: 600;
        }
        .auth-proof-banner {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="app-brand">
            <span style="font-size: 1.5rem;">🏢</span>
            <span>Employee HRIS & Leave Management System</span>
            <span class="app-badge">HR PORTAL</span>
        </div>
        <div class="user-nav">
            <div class="user-info">
                <div class="user-name"><?= e($user['name'] ?? $user['username']) ?></div>
                <div class="user-role">Role: <?= e(strtoupper($user['role'] ?? 'HR')) ?></div>
            </div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </header>

    <main class="container">
        <div class="auth-proof-banner">
            <strong>✓ Server-Side Authorization Verified:</strong> You are authenticated as <code><?= e($user['username']) ?></code> with role <code><?= e($user['role']) ?></code>. The <code>RoleMiddleware(['admin', 'hr'])</code> verified your authorization server-side before rendering this portal.
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Active Employees</div>
                <div class="stat-value"><?= e((string)($metrics['total_employees'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Departments</div>
                <div class="stat-value"><?= e((string)($metrics['total_departments'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Positions</div>
                <div class="stat-value"><?= e((string)($metrics['total_positions'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Pending Leave Requests</div>
                <div class="stat-value" style="color: #D97706;"><?= e((string)($metrics['pending_leaves'] ?? 0)) ?></div>
            </div>
        </div>

        <div class="content-card">
            <h2>
                <span>Workforce Overview</span>
                <a href="<?= url('/hr/employees') ?>" style="font-size: 0.8125rem; color: #4F46E5; text-decoration: none;">View All Employees →</a>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hired Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentEmployees)): ?>
                        <?php foreach ($recentEmployees as $emp): ?>
                            <tr>
                                <td><code><?= e($emp['employee_no']) ?></code></td>
                                <td><strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                                <td><?= e($emp['email']) ?></td>
                                <td><?= e($emp['department_name'] ?? '—') ?></td>
                                <td><?= e($emp['position_name'] ?? '—') ?></td>
                                <td><?= e($emp['date_hired']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; color: #94A3B8;">No employee records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
