<?php
$pageTitle = 'Mi cuenta - Tienda';
$viewCss = ['/public/css/account.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js'];
if (in_array($section, ['profile', 'password'])) {
    $viewJs[] = '/public/js/customers/profile.js?v=3';
    $viewJs[] = '/public/js/customers/account-password.js';
} elseif ($section === 'addresses') {
    $viewJs[] = '/public/js/customers/addresses.js?v=2';
}
ob_start();
?>

<div class="container py-4">
    <h3 class="mb-4"><i class="bi bi-person-circle"></i> Mi cuenta</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card sidebar">
                <div class="card-body p-3">
                    <nav class="nav flex-column">
                        <a class="nav-link <?= in_array($section, ['profile', 'password']) ? 'active' : '' ?>" href="/account?section=profile">
                            <i class="bi bi-person"></i> Perfil
                        </a>
                        <a class="nav-link <?= $section === 'addresses' ? 'active' : '' ?>" href="/account?section=addresses">
                            <i class="bi bi-geo-alt"></i> Direcciones
                        </a>
                        <a class="nav-link <?= $section === 'orders' ? 'active' : '' ?>" href="/account?section=orders">
                            <i class="bi bi-box"></i> Mis pedidos
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <?php if (in_array($section, ['profile', 'password'])): ?>
                <?php include __DIR__ . '/account_profile.php'; ?>
            <?php elseif ($section === 'addresses'): ?>
                <?php include __DIR__ . '/account_addresses.php'; ?>
            <?php elseif ($section === 'orders'): ?>
                <?php include __DIR__ . '/account_orders.php'; ?>
            <?php elseif ($section === 'order-detail'): ?>
                <?php include __DIR__ . '/account_order_detail.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/account_profile.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';
