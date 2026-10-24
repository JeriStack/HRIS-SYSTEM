<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(config('config.app_name', 'Employee HRIS')) ?></title>
    
    <!-- Enterprise Design System Stylesheets -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/realtime.css') ?>">
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
            <?php require __DIR__ . '/../partials/footer.php'; ?>
        </div>
    </div>

    <!-- Production JavaScript Modules -->
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/navigation.js') ?>"></script>
    <script src="<?= asset('js/forms.js') ?>"></script>
    <script src="<?= asset('js/realtime.js') ?>"></script>
</body>
</html>
