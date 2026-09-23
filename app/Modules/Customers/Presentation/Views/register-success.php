<?php
$pageTitle = 'Cuenta creada - Tienda';
$viewCss = ['/public/css/auth.css'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4 text-center">
                    <h3 class="card-title mb-4">Tu cuenta fue creada correctamente</h3>

                    <p class="mb-1">
                        Hemos enviado un correo para verificar tu dirección de email.
                    </p>
                    <p class="mb-4">
                        Debes verificar tu cuenta antes de iniciar sesión.
                    </p>

                    <div class="d-grid">
                        <a href="/login" class="btn btn-primary">Ir al inicio de sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';