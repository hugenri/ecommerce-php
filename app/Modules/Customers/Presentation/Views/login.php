<?php
$pageTitle = 'Iniciar sesión - Tienda';
$viewCss = ['/public/css/auth.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js', '/public/js/customers/login.js', '/public/js/customers/resend-verification.js'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">Iniciar sesión</h3>

                    <div id="alertBox" class="form-alert" role="alert" hidden></div>

                    <?php if (!empty($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                        <?php unset($_SESSION['flash_success']); ?>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>

                    <form id="loginForm" data-validate novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                   aria-describedby="email-feedback"
                                   placeholder="tu@email.com" autofocus>
                            <div id="email-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                   aria-describedby="password-feedback"
                                   placeholder="Tu contraseña">
                            <div id="password-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="La contraseña no es válida."></div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnLogin" class="btn btn-primary">Iniciar sesión</button>
                        </div>

                        <div class="text-center mt-2">
                            <a href="/reset-password">¿Olvidaste tu contraseña?</a>
                        </div>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        ¿No tienes cuenta? <a href="/register">Regístrate</a>
                    </p>

                    <?php if (!empty($showVerification)): ?>
                        <div class="text-center mt-3">
                            <div id="resendAlert" class="form-alert" role="alert" hidden></div>
                            <form method="POST" action="/customer/resend-verification"
                                  data-resend-verification data-alert="resendAlert" data-validate novalidate>
                                <input type="email" class="form-control" name="email"
                                       id="resendEmail" required
                                       pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                       aria-describedby="resendEmail-feedback"
                                       placeholder="Tu email">
                                <div id="resendEmail-feedback" class="field-feedback text-center" role="alert" hidden
                                     data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                                <button type="submit" class="btn btn-outline-primary mt-2">
                                    Reenviar correo de verificación
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';
