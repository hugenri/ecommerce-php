<form action="/access/logout" method="POST" class="d-grid" id="logoutForm">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger" id="logoutBtn">
        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
    </button>
</form>