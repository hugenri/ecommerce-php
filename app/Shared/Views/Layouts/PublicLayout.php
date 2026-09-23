<?php
$pageTitle = $pageTitle ?? 'Tienda';
$pageActive = $pageActive ?? '';
$viewCss = $viewCss ?? [];
$viewJs = $viewJs ?? [];
$content = $content ?? '';
$siteSettings = $siteSettings ?? null;
$favicon = $siteSettings?->getFavicon() ?? '/public/images/favicon.ico';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/public.css">
    <link rel="stylesheet" href="/public/css/mini-cart.css">
    <link rel="stylesheet" href="/public/css/search.css">
    <link rel="stylesheet" href="/public/css/catalog.css">
    <?php foreach ((array) $viewCss as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
</head>

<body>
    <?php require $sharedViewsPath . '/Components/Header.php'; ?>

    <main class="flex-grow-1">
        <?= $content ?>
    </main>

    <?php require $sharedViewsPath . '/Components/Footer.php'; ?>

    <?php require $sharedViewsPath . '/Components/CartOffcanvas.php'; ?>

    <?php require $sharedViewsPath . '/Components/BottomNav.php'; ?>

    <?php require $sharedViewsPath . '/Components/CategoriesDrilldownPanel.php'; ?>

    <script>window.CSRF_TOKEN = <?= json_encode($csrfToken ?? '') ?>;</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/shared/status-translator.js"></script>
    <script src="/public/js/cart-counter.js"></script>
    <script src="/public/js/quantity.js"></script>
    <script src="/public/js/mini-cart.js"></script>
    <script src="/public/js/search.js"></script>
    <script src="/public/js/products/catalog-categories-dropdown.js"></script>
    <?php foreach ((array) $viewJs as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
</body>

</html>
