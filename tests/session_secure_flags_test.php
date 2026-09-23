<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\SessionManager;

// Hallazgo BAJO de la auditoría: el flag Secure de la cookie de sesión se decidía
// solo con $_SERVER['HTTPS'] === 'on'; detrás del proxy/terminador TLS de
// InfinityFree puede no reflejar HTTPS real. Ahora Secure se resuelve con:
// 1) force_secure (config explícita / producción), 2) HTTPS directo,
// 3) header X-Forwarded-Proto. SameSite se confirma intacto en 'Strict'.

$failed = 0;
$results = [];

$check = function (string $label, bool $ok) use (&$failed, &$results): void {
    $results[] = ($ok ? 'PASS' : 'FAIL') . ": $label";
    if (!$ok) {
        $failed++;
    }
};

function runScenario(array $server, array $config): array
{
    $_SERVER = $server;
    $manager = new SessionManager();
    $manager->start($config);
    $params = session_get_cookie_params();
    $id = $manager->getId();
    $manager->destroy();
    return [
        'secure' => (bool) $params['secure'],
        'samesite' => (string) $params['samesite'],
        'httponly' => (bool) $params['httponly'],
        'id' => $id,
    ];
}

// 1. force_secure explícito fuerza Secure aunque no haya señales de HTTPS
$result = runScenario([], ['name' => 'T1', 'force_secure' => true]);
$check('force_secure=true activa Secure sin señales HTTPS', $result['secure'] === true);
$check('SameSite se mantiene Strict con force_secure', $result['samesite'] === 'Strict');

// 2. HTTPS directo (sin proxy) activa Secure
$result = runScenario(['HTTPS' => 'on'], ['name' => 'T2']);
$check('HTTPS=on activa Secure (sin force)', $result['secure'] === true);

// 3. Proxy: X-Forwarded-Proto=https activa Secure
$result = runScenario(['HTTP_X_FORWARDED_PROTO' => 'https'], ['name' => 'T3']);
$check('X-Forwarded-Proto=https activa Secure (proxied)', $result['secure'] === true);

// 4. Local sin HTTPS (no headers, sin force) NO fuerza Secure
$result = runScenario([], ['name' => 'T4']);
$check('Entorno local HTTP: Secure permanece false (no se rompe el flujo)', $result['secure'] === false);
$check('SameSite se mantiene Strict en local', $result['samesite'] === 'Strict');
$check('httponly se mantiene true', $result['httponly'] === true);

// 5. regenerateIdForced regenera el ID de sesión inmediatamente después de start()
$_SERVER = [];
$manager = new SessionManager();
$manager->start(['name' => 'T5']);
$before = $manager->getId();
$manager->regenerateIdForced(true);
$after = $manager->getId();
$check('regenerateIdForced(true) cambia el ID de sesión tras el inicio', $before !== $after);
$manager->destroy();

foreach ($results as $line) {
    echo $line . "\n";
}

echo "\n=== COOKIE DE SESIÓN SECURE DETRÁS DE PROXY (hallazgo BAJO auditoría) OK ===\n";

if ($failed > 0) {
    exit(1);
}