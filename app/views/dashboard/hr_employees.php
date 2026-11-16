<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Employee Directory (HR Access)') ?></title>
    <meta name="color-scheme" content="light dark">
    <script src="<?= asset('js/theme-init.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        body {
            background-color: var(--color-bg-subtle, #F8FAFC);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--color-text-main, #0F172A);
        }
        .app-header {
            background: var(--color-bg-surface, #FFFFFF);
            border-bottom: 1px solid var(--color-border, #E2E8F0);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .content-card {
            background: var(--color-bg-surface, #FFFFFF);
            border: 1px solid var(--color-border, #E2E8F0);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border-subtle, #F1F5F9);
        }
        th {
            background: var(--color-bg-subtle, #F8FAFC);
            color: var(--color-text-body, #475569);
            font-weight: 600;
        }
        .badge-active {
            background: var(--color-success-bg, #ECFDF5);
            color: var(--color-success, #065F46);
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-inactive {
            background: var(--color-error-bg, #FEF2F2);
            color: var(--color-error, #991B1B);
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div>
            <a href="<?= url('/hr/dashboard') ?>" style="text-decoration: none; color: #4F46E5; font-weight: 600; font-size: 0.875rem;">← Back to HR Dashboard</a>
            <h1 style="font-size: 1.25rem; margin: 0.25rem 0 0 0; color: var(--color-text-main, #1E293B);">HR Employee Directory</h1>
        </div>
        <div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" style="background: var(--color-bg-muted, #F1F5F9); border: 1px solid var(--color-border-dark, #CBD5E1); padding: 0.4rem 0.85rem; border-radius: 0.375rem; cursor: pointer;">Sign Out</button>
            </form>
        </div>
    </header>

    <main class="container">
        <div class="content-card">
            <h2>All Employee Records (Protected HR View)</h2>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Employee No</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><code><?= e($emp['employee_no']) ?></code></td>
                            <td><strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></td>
                            <td><?= e($emp['email']) ?></td>
                            <td><?= e($emp['department_name'] ?? '—') ?></td>
                            <td><?= e($emp['position_name'] ?? '—') ?></td>
                            <td>
                                <span class="<?= $emp['employment_status'] === 'Active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= e($emp['employment_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
</body>
</html>
