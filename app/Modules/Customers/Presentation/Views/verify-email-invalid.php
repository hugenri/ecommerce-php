<?php
$pageTitle = 'Enlace no válido - Tienda';
$viewCss = ['/public/css/auth.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js', '/public/js/customers/resend-verification.js'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4 text-center">
                    <h3 class="card-title mb-4">El enlace no es válido o ha expirado</h3>

                    <div class="d-grid">
                        <a href="#requestResend" class="btn btn-primary" data-bs-toggle="collapse" role="button">
                            Solicitar un nuevo correo de verificación
                        </a>
                    </div>

                    <div class="collapse mt-3" id="requestResend">
                        <div id="resendAlert" class="form-alert" role="alert" hidden></div>
                        <form method="POST" action="/customer/resend-verification"
                              data-resend-verification data-alert="resendAlert" data-validate novalidate>
                            <div class="input-group">
                                <input type="email" class="form-control" name="email"
                                       id="resendEmail" required
                                       pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                       aria-describedby="resendEmail-feedback"
                                       placeholder="Tu email">
                                <div id="resendEmail-feedback" class="field-feedback w-100" role="alert" hidden
                                     data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                                <button class="btn btn-outline-primary" type="submit">Enviar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';