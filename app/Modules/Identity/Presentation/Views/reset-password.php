<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña - Administración</title>
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
                        <h3 class="card-title text-center mb-4">Nueva contraseña</h3>

                        <div id="alertBox" class="form-alert" role="alert" hidden></div>

                        <form method="POST" action="/access/reset-password" id="resetForm" data-validate novalidate>
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">Nueva contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                    pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                    aria-describedby="password-feedback"
                                    placeholder="Mín. 8 caracteres" autofocus>
                                <div id="password-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="La contraseña no es válida."></div>
                                <div class="form-text">Entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" required
                                    pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                    aria-describedby="password-confirmation-feedback"
                                    placeholder="Repite tu contraseña">
                                <div id="password-confirmation-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="La contraseña no es válida."></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Restablecer contraseña</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/form-validation.js"></script>
    <script src="/public/js/identity/reset-password.js"></script>
</body>

</html>