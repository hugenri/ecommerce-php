<?php
/*
 * Dropdown del usuario autenticado.
 *
 * Muestra el nombre y correo del usuario junto con el formulario de
 * "Cerrar sesión" (POST + CSRF). Se renderiza únicamente en el Topbar.
 */
$userName = $userName ?? '';
$userEmail = $userEmail ?? '';
$csrfToken = $csrfToken ?? '';
?>
<div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-person-circle me-1"></i>
        <?= htmlspecialchars($userName) ?>
    </button>

    <ul class="dropdown-menu dropdown-menu-end">

        <li>
            <span class="dropdown-item-text text-muted small">
                <?= htmlspecialchars($userEmail) ?>
            </span>
        </li>

        <li>
            <hr class="dropdown-divider">
        </li>

        <li>
            <?php require $sharedViewsPath . '/Components/LogoutForm.php'; ?>
        </li>

    </ul>
</div>