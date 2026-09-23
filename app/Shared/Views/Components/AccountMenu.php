<?php
$headerIsLoggedIn = $headerIsLoggedIn ?? false;
$headerCustomerName = $headerCustomerName ?? '';
$accountMenuClass = $accountMenuClass ?? '';
?>
<ul class="dropdown-menu account-menu <?= $accountMenuClass ?>">
    <?php if ($headerIsLoggedIn): ?>
        <li>
            <div class="account-menu-header">
                <div class="account-menu-title">Mi Cuenta</div>
                <?php if ($headerCustomerName !== ''): ?>
                    <div class="account-menu-subtitle"><?= htmlspecialchars($headerCustomerName) ?></div>
                <?php endif; ?>
            </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="/account">
                <i class="bi bi-person me-1" aria-hidden="true"></i> Mi cuenta
                <small class="d-block text-muted account-menu-desc">Consulta y actualiza tu información</small>
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="/account?section=orders">
                <i class="bi bi-box me-1" aria-hidden="true"></i> Mis órdenes
                <small class="d-block text-muted account-menu-desc">Seguimiento y detalle de tus pedidos</small>
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="/account?section=addresses">
                <i class="bi bi-geo-alt me-1" aria-hidden="true"></i> Mis direcciones
                <small class="d-block text-muted account-menu-desc">Administra tus direcciones de envío</small>
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form action="/logout" method="POST" class="px-1">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <button type="submit" class="dropdown-item">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Salir
                </button>
            </form>
        </li>
    <?php else: ?>
<?php
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
?>
<li>
            <div class="account-menu-header">
                <div class="account-menu-title">Mi Cuenta</div>
                <div class="account-menu-subtitle">Inicia sesión para gestionar tu cuenta, pedidos y direcciones.</div>
            </div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <div class="d-grid gap-2 px-2 pb-2">
                <a class="btn btn-primary" href="/login?redirect=<?= rawurlencode($currentUrl) ?>"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Iniciar sesión</a>
                <a class="btn btn-outline-secondary" href="/register"><i class="bi bi-person-plus me-1" aria-hidden="true"></i> Crear una cuenta</a>
            </div>
        </li>
    <?php endif; ?>
</ul>