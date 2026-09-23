<?php
/** @var string $link */
/** @var string|null $name */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu correo</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px;">
        <tr>
            <td align="center" style="font-family:Arial,Helvetica,sans-serif;">
                <div style="max-width:480px;background:#ffffff;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <h1 style="margin:0 0 16px;font-size:22px;color:#111827;">Verifica tu correo</h1>
                    <?php if (!empty($name)): ?>
                        <p style="margin:0 0 16px;color:#374151;">Hola <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>,</p>
                    <?php endif; ?>
                    <p style="margin:0 0 16px;color:#374151;">
                        Gracias por crear tu cuenta. Para activarla confirma tu correo electrónico.
                    </p>
                    <p style="margin:0 0 24px;">
                        <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
                           style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;">
                            Verificar mi correo
                        </a>
                    </p>
                    <p style="margin:0;color:#6b7280;font-size:13px;">
                        Este enlace es válido por 24 horas y solo puede usarse una vez.
                    </p>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>