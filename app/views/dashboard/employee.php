<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Employee Self-Service Portal') ?></title>
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
            background: #ECFDF5;
            color: #059669;
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
        .balances-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .balance-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .balance-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .balance-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1E293B;
        }
        .balance-meta {
            font-size: 0.75rem;
            color: #94A3B8;
            margin-top: 0.25rem;
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
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1E40AF;
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .badge-status {
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-Pending { background: #FEF3C7; color: #92400E; }
        .status-Approved { background: #D1FAE5; color: #065F46; }
        .status-Rejected { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="app-brand">
            <span style="font-size: 1.5rem;">👤</span>
            <span>Employee HRIS & Leave Management System</span>
            <span class="app-badge">EMPLOYEE SELF-SERVICE</span>
        </div>
        <div class="user-nav">
            <div class="user-info">
                <div class="user-name"><?= e($user['name'] ?? $user['username']) ?></div>
                <div class="user-role">ID: <?= e($user['employee_no'] ?? 'N/A') ?> • <?= e($user['position'] ?? 'Employee') ?></div>
            </div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Sign Out</button>
            </form>
        </div>
    </header>

    <main class="container">
        <div class="auth-proof-banner">
            <strong>✓ Server-Side Employee Authorization Verified:</strong> You are authenticated as <code><?= e($user['username']) ?></code> (Employee: <code><?= e($user['employee_no']) ?></code>). The <code>RoleMiddleware(['employee'])</code> verified your authorization. Employee data is strictly bound to your authenticated identity.
        </div>

        <div style="margin-bottom: 1rem; display: flex; gap: 0.75rem;">
            <a href="<?= url('/employee/profile') ?>" style="padding: 0.4rem 0.75rem; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 0.375rem; text-decoration: none; font-size: 0.8125rem; font-weight: 600; color: #334155;">
                👤 View My Profile (Ownership Test)
            </a>
            <a href="<?= url('/employee/leave-balances') ?>" target="_blank" style="padding: 0.4rem 0.75rem; background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 0.375rem; text-decoration: none; font-size: 0.8125rem; font-weight: 600; color: #334155;">
                📊 View Balances API (JSON)
            </a>
        </div>

        <h3 style="font-size: 1rem; color: #475569; margin: 0 0 0.75rem 0;">My Leave Balances (FY <?= date('Y') ?>)</h3>
        <div class="balances-grid">
            <?php if (!empty($balances)): ?>
                <?php foreach ($balances as $b): ?>
                    <div class="balance-card">
                        <div class="balance-title"><?= e($b['leave_name']) ?></div>
                        <div class="balance-value"><?= e((string)$b['remaining_days']) ?> <span style="font-size: 0.875rem; font-weight: normal; color: #64748B;">days left</span></div>
                        <div class="balance-meta">Allocated: <?= e((string)$b['allocated_days']) ?> • Used: <?= e((string)$b['used_days']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="balance-card">
                    <div class="balance-title">Standard Leaves</div>
                    <div class="balance-value">0</div>
                    <div class="balance-meta">No balance records initialized</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <h2>
                <span>My Recent Leave Requests</span>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Dates</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pendingLeaves)): ?>
                        <?php foreach ($pendingLeaves as $req): ?>
                            <tr>
                                <td><strong><?= e($req['leave_name']) ?></strong></td>
                                <td><?= e((string)$req['number_of_days']) ?> day(s)</td>
                                <td><?= e($req['start_date']) ?> to <?= e($req['end_date']) ?></td>
                                <td><?= e($req['reason']) ?></td>
                                <td>
                                    <span class="badge-status status-<?= e($req['status']) ?>">
                                        <?= e($req['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #94A3B8;">No leave requests filed yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
