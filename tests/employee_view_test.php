<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\SessionManager;
use App\Core\Session\Store\SessionStore;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;

// Render test: la vista recibe datos (summary etc.) y no debe lanzar errores de PHP.
function render_view(string $view, array $data): string
{
    $data['csrfToken'] = 'test-csrf-token';
    $data['sharedViewsPath'] = __DIR__ . '/../app/Shared/Views';
    extract($data);
    ob_start();
    require __DIR__ . '/../app/Modules/EmployeeDashboard/Presentation/Views/' . $view . '.php';
    return ob_get_clean();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$summary = ['pending' => 1, 'assigned' => 0, 'preparing' => 0, 'shipped_today' => 0, 'delivered_today' => 0];
$csrfTokenValue = 'test-csrf-token';
$html = render_view('dashboard', [
    'userName' => 'Empleado Test',
    'userEmail' => 'employee@example.com',
    'userRole' => 'employee',
    'userId' => 99,
    'summary' => $summary,
]);

$appJsPath = __DIR__ . '/../public/js/employee-dashboard/app.js';
$viewHasAppJs = is_file($appJsPath) && str_contains($html, '/public/js/employee-dashboard/app.js');
$appJs = is_file($appJsPath) ? file_get_contents($appJsPath) : '';

$checks = [
    'Titulo Panel Empleado' => str_contains($html, 'Panel Empleado'),
    'Tarjeta pendientes' => str_contains($html, 'Entregas pendientes'),
    'Tarjeta asignadas' => str_contains($html, 'Entregas asignadas'),
    'Tarjeta preparacion' => str_contains($html, 'En preparacion'),
    'Tarjeta enviadas hoy' => str_contains($html, 'Enviadas hoy'),
    'Tarjeta completadas hoy' => str_contains($html, 'Completadas hoy'),
    'Tabla Mi trabajo' => str_contains($html, 'Mi trabajo'),
    'Tabla Pedidos pendientes' => str_contains($html, 'Pedidos pendientes'),
    'CSRF token' => str_contains($html, $csrfTokenValue),
    'Fetch my-deliveries' => $viewHasAppJs && str_contains($appJs, '/employee/my-deliveries'),
    'Fetch pending' => $viewHasAppJs && str_contains($appJs, '/employee/pending'),
];

$allPass = true;
foreach ($checks as $name => $pass) {
    echo ($pass ? 'PASS' : 'FAIL') . ' - ' . $name . "\n";
    if (!$pass) {
        $allPass = false;
    }
}

echo $allPass ? "\n=== VISTA OK ===\n" : "\n=== VISTA CON ERRORES ===\n";
exit($allPass ? 0 : 1);
