<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(config('config.app_name', 'Employee HRIS')) ?></title>
    
    <!-- Enterprise Design System Stylesheets -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
    <div class="app-shell">
        <!-- Accessible Role-Aware Sidebar Navigation -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <div class="app-container main-content">
            <!-- Top Application Header -->
            <?php require __DIR__ . '/../partials/navbar.php'; ?>

            <main class="app-content content-container" role="main" id="mainContent">
                <!-- Global Flash Status Feedback -->
                <?php require __DIR__ . '/../partials/alerts.php'; ?>

                <!-- Page Content Region -->
                <?= $content ?? '' ?>
            </main>

            <!-- Standard Enterprise Footer -->
            <footer class="app-footer">
                <div>&copy; <?= date('Y') ?> <?= e(config('config.app_name', 'Employee HRIS & Leave Management System')) ?></div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                    Production Environment &bull; Security Verified (WCAG 2.2 AA)
                </div>
            </footer>
        </div>
    </div>

    <!-- Production JavaScript Modules -->
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/navigation.js') ?>"></script>
    <script src="<?= asset('js/forms.js') ?>"></script>
</body>
</html>
