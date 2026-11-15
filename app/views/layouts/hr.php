<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="base-url" content="<?= e(url('/')) ?>">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(config('config.app_name', 'NEXORA HR')) ?></title>
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('nexora_theme') || localStorage.getItem('hris_theme');
                if (savedTheme === 'dark' || savedTheme === 'light') {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
                if (window.innerWidth >= 768 && localStorage.getItem('nexora_sidebar_collapsed') === '1') {
                    document.documentElement.setAttribute('data-sidebar-collapsed', '1');
                }
            } catch (e) {}
        })();
    </script>

    <!-- Enterprise Design System Stylesheets -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/skeleton.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/realtime.css') ?>">
</head>
<body>
    <a href="#mainContent" class="skip-link">Skip to main content</a>
    <div class="app-shell">
        <script>
            (function () {
                if (document.documentElement.getAttribute('data-sidebar-collapsed') === '1') {
                    var shell = document.currentScript && document.currentScript.parentElement;
                    if (shell) {
                        shell.classList.add('sidebar-collapsed');
                    }
                }
            })();
        </script>
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
    <script src="<?= asset('js/profile.js') ?>"></script>
    <script src="<?= asset('js/skeleton.js') ?>"></script>
</body>
</html>
