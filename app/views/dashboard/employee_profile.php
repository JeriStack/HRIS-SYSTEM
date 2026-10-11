<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'My Profile') ?></title>
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
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .profile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .profile-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #F1F5F9;
        }
        .profile-label {
            width: 200px;
            font-weight: 600;
            color: #64748B;
            font-size: 0.875rem;
        }
        .profile-val {
            flex: 1;
            color: #1E293B;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div>
            <a href="<?= url('/employee/dashboard') ?>" style="text-decoration: none; color: #4F46E5; font-weight: 600; font-size: 0.875rem;">← Back to Portal</a>
            <h1 style="font-size: 1.25rem; margin: 0.25rem 0 0 0; color: #1E293B;">My Employee Profile</h1>
        </div>
        <div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" style="background: #F1F5F9; border: 1px solid #CBD5E1; padding: 0.4rem 0.85rem; border-radius: 0.375rem; cursor: pointer;">Sign Out</button>
            </form>
        </div>
    </header>

    <main class="container">
        <div class="profile-card">
            <h2 style="margin: 0 0 1.5rem 0; font-size: 1.25rem; color: #1E293B;">Employee Details (Ownership Verified)</h2>

            <?php if (!empty($profile)): ?>
                <div class="profile-row">
                    <div class="profile-label">Employee Number</div>
                    <div class="profile-val"><code><?= e($profile['employee_no']) ?></code></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Full Name</div>
                    <div class="profile-val"><strong><?= e($profile['first_name'] . ' ' . ($profile['middle_name'] ? $profile['middle_name'] . ' ' : '') . $profile['last_name']) ?></strong></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Email Address</div>
                    <div class="profile-val"><?= e($profile['email']) ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Contact Phone</div>
                    <div class="profile-val"><?= e($profile['phone'] ?? '—') ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Department</div>
                    <div class="profile-val"><?= e($profile['department_name'] ?? '—') ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Position</div>
                    <div class="profile-val"><?= e($profile['position_name'] ?? '—') ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Date Hired</div>
                    <div class="profile-val"><?= e($profile['date_hired']) ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-label">Employment Status</div>
                    <div class="profile-val"><span style="color: #059669; font-weight: 600;"><?= e($profile['employment_status']) ?></span></div>
                </div>
            <?php else: ?>
                <p style="color: #64748B;">No employee record linked to this user account.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
