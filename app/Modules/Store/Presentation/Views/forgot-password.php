<?php
$pageTitle = 'Recuperar contraseña - Tienda';
$viewCss = ['/public/css/auth.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js', '/public/js/customers/forgot-password.js'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">Recuperar contraseña</h3>

                    <?php if (!empty($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                        <?php unset($_SESSION['flash_success']); ?>
                    <?php endif; ?>

                    <div id="alertBox" class="form-alert" role="alert" hidden></div>

                    <p class="text-muted small">
                        Escribe tu correo y te enviaremos un enlace para restablecer tu contraseña.
                    </p>

                    <form method="POST" action="/reset-password" id="forgotForm" data-validate novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                   aria-describedby="email-feedback"
                                   placeholder="tu@email.com" autofocus>
                            <div id="email-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnSend" class="btn btn-primary">Enviar enlace de recuperación</button>
                        </div>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        ¿Recordaste tu contraseña? <a href="/login">Inicia sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';