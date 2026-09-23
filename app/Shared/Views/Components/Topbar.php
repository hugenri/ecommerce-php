<?php
/*
 * Barra superior (Topbar) del panel.
 *
 * Contiene: botón hamburguesa (visible únicamente en < lg) que abre el
 * Offcanvas del menú lateral, el título de la página, las acciones del
 * módulo ($topbarActions) y el dropdown del usuario. Es el único lugar
 * donde aparecen el botón del Offcanvas y el UserDropdown.
 */
$topbarTitle = $topbarTitle ?? '';
$topbarActions = $topbarActions ?? '';
?>
<nav class="topbar d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 bg-white border-bottom">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button class="btn btn-outline-secondary d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu"
                aria-controls="sidebarMenu" aria-label="Abrir menú" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="h5 mb-0"><?= htmlspecialchars($topbarTitle) ?></h1>
        <?= $topbarActions ?>
    </div>
    <div class="ms-auto">
        <?php require $sharedViewsPath . '/Components/UserDropdown.php'; ?>
    </div>
</nav>