<?php
/*
 * Layout compartido del panel de administración.
 *
 * Es la única fuente del HTML estructural (head, sidebar, main, scripts).
 * Las vistas únicamente proveen: $content, $pageTitle, $pageActive,
 * $viewCss, $viewJs y (opcionalmente) $viewInlineJs.
 */
$pageTitle = $pageTitle ?? 'Admin';
$pageActive = $pageActive ?? '';
$content = $content ?? '';
$viewCss = $viewCss ?? [];
$viewJs = $viewJs ?? [];
$viewInlineJs = $viewInlineJs ?? '';
$topbarTitle = $topbarTitle ?? $pageTitle;
$topbarActions = $topbarActions ?? '';
$userName = $userName ?? '';
$userEmail = $userEmail ?? '';
$csrfToken = $csrfToken ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/public/css/layout.css" rel="stylesheet">
    <link href="/public/css/components.css" rel="stylesheet">
    <link href="/public/css/form-validation.css" rel="stylesheet">
    <?php foreach ((array) $viewCss as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
</head>

<body>
    <?php require $sharedViewsPath . '/Components/LoadingOverlay.php'; ?>
    <?php require $sharedViewsPath . '/Components/AlertContainer.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php require $sharedViewsPath . '/Components/AdminSidebar.php'; ?>

            <div class="col-12 col-lg-10 p-0">
                <?php require $sharedViewsPath . '/Components/Topbar.php'; ?>

                <main class="px-md-4 py-4">
                    <?= $content ?>
                </main>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.CSRF_TOKEN = '<?= htmlspecialchars($csrfToken) ?>';</script>
    <script src="/public/js/shared/status-translator.js"></script>
    <script src="/public/js/shared/offcanvas-nav.js"></script>
    <script src="/public/js/shared/filter-bar.js"></script>
    <script src="/public/js/form-validation.js"></script>
    <script src="/public/js/logout.js"></script>
    <?php foreach ((array) $viewJs as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
    <?= $viewInlineJs ?>
</body>

</html>