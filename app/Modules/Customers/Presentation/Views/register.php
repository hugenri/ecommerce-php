<?php
$pageTitle = 'Registrarse - Tienda';
$viewCss = ['/public/css/auth.css', '/public/css/form-validation.css'];
$viewJs = ['/public/js/form-validation.js', '/public/js/customers/register.js'];
ob_start();
?>

<div class="container flex-grow-1 d-flex align-items-center py-4">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">Crear cuenta</h3>

                    <div id="alertBox" class="form-alert" role="alert" hidden></div>

                    <form method="POST" action="/register" id="registerForm" data-validate novalidate>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required
                                    pattern="^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ ]+$" minlength="3" maxlength="20"
                                    aria-describedby="first_name-feedback"
                                    placeholder="Juan">
                                <div id="first_name-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="El nombre solo puede contener letras."></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name_paternal" class="form-label">Apellido Paterno</label>
                                <input type="text" class="form-control" id="last_name_paternal" name="last_name_paternal" required
                                    pattern="^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ ]+$" minlength="3" maxlength="20"
                                    aria-describedby="last_name_paternal-feedback"
                                    placeholder="Pérez">
                                <div id="last_name_paternal-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="El apellido paterno solo puede contener letras."></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name_maternal" class="form-label">Apellido Materno</label>
                                <input type="text" class="form-control" id="last_name_maternal" name="last_name_maternal"
                                    pattern="^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ ]+$" minlength="3" maxlength="20"
                                    aria-describedby="last_name_maternal-feedback"
                                    placeholder="López">
                                <div id="last_name_maternal-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="El apellido materno solo puede contener letras."></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                aria-describedby="email-feedback"
                                placeholder="tu@email.com">
                            <div id="email-feedback" class="field-feedback" role="alert" hidden
                                data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                pattern="[0-9]{10}"
                                maxlength="10"
                                inputmode="numeric"
                                aria-describedby="phone-feedback"
                                placeholder="5581234567">
                            <div id="phone-feedback" class="field-feedback" role="alert" hidden
                                data-mensaje="El teléfono solo puede contener números."></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required
                                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                aria-describedby="password-feedback"
                                placeholder="Mín. 8 caracteres">
                            <div id="password-feedback" class="field-feedback" role="alert" hidden
                                data-mensaje="La contraseña no es válida."></div>
                            <div class="form-text">Debe tener entre 8 y 16 caracteres, mayúscula, minúscula, número y carácter especial.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnRegister" class="btn btn-primary">Crear cuenta</button>
                        </div>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        ¿Ya tienes cuenta? <a href="/login">Inicia sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';
