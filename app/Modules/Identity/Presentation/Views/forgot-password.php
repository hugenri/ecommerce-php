<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/public/css/layout.css" rel="stylesheet">
    <link href="/public/css/form-validation.css" rel="stylesheet">
    <link rel="icon" href="data:,">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-8 col-md-5 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">Recuperar contraseña</h3>

                        <div id="alertBox" class="form-alert" role="alert" hidden></div>

                        <p class="text-muted small">
                            Escribe tu correo y te enviaremos un enlace para restablecer tu contraseña.
                        </p>

                        <form method="POST" action="/access/reset-password" id="forgotForm" data-validate novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                    pattern="^[^@\s]+@[^@\s]+\.[^@\s]+$"
                                    aria-describedby="email-feedback"
                                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                    placeholder="tu@email.com" autofocus>
                                <div id="email-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="Ingrese un correo electrónico con formato válido"></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Enviar enlace de recuperación</button>
                            </div>
                        </form>

                        <p class="text-center mt-3 mb-0">
                            ¿Recordaste tu contraseña? <a href="/access/login">Inicia sesión</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/form-validation.js"></script>
    <script src="/public/js/identity/forgot-password.js"></script>
</body>

</html>