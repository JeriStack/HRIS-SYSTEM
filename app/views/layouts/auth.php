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
</head>
<body class="auth-layout">
    <?= $content ?? '' ?>

    <!-- Production JavaScript Modules -->
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/forms.js') ?>"></script>
</body>
</html>
