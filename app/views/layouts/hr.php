<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(config('config.app_name', 'HRIS Portal')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .content-container {
            padding: 1.5rem 2rem;
            max-width: 1300px;
            margin: 0 auto;
        }
        .action-btns {
            display: inline-flex;
            gap: 0.35rem;
            align-items: center;
        }
        .search-bar-wrap {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal-card {
            background: #FFFFFF;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 1.75rem;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar Navigation -->
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <div class="main-content">
            <!-- Top Navigation Bar -->
            <?php require __DIR__ . '/../partials/navbar.php'; ?>

            <main class="content-container" role="main">
                <!-- Flash Alerts -->
                <?php require __DIR__ . '/../partials/alerts.php'; ?>

                <!-- Page Content Slot -->
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <script>
        // Mobile Sidebar Drawer Toggle
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.app-sidebar');
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
