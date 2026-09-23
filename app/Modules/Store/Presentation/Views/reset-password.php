<?php
$pageTitle = 'Nueva contraseña - Tienda';
$viewCss = ['/public/css/auth.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js', '/public/js/customers/reset-password.js'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">Nueva contraseña</h3>

                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>

                    <div id="alertBox" class="form-alert" role="alert" hidden></div>

                    <form method="POST" action="/reset-password" id="resetForm" data-validate novalidate>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                   aria-describedby="password-feedback"
                                   placeholder="Mín. 8 caracteres">
                            <div id="password-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="La contraseña no es válida."></div>
                            <div class="form-text">Entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" class="form-control" id="password_confirmation"
                                   name="password_confirmation" required
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                   aria-describedby="password_confirmation-feedback"
                                   placeholder="Repite tu contraseña">
                            <div id="password_confirmation-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="Las contraseñas no coinciden o no son válidas."></div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnReset" class="btn btn-primary">Restablecer contraseña</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';