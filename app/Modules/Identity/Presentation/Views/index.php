<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/public/css/layout.css" rel="stylesheet">
    <link href="/public/css/form-validation.css" rel="stylesheet">
    <link rel="icon" href="data:,"> <!-- Se agrega un favicon vacío para evitar errores de carga -->
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-sm-8 col-md-5 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">Iniciar sesion</h3>

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
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                                        aria-describedby="password-feedback"
                                        placeholder="Tu contraseña">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                        title="Mostrar contraseña" aria-label="Mostrar contraseña">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="password-feedback" class="field-feedback" role="alert" hidden
                                    data-mensaje="La contraseña no es válida."></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" id="btnLogin" class="btn btn-primary">Login</button>
                            </div>

                            <div class="text-center mt-2">
                                <a href="/access/reset-password">¿Olvidaste tu contraseña?</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/public/js/form-validation.js"></script>
    <script src="/public/js/login.js"></script>
</body>

</html>