<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Modules\Checkout\Presentation\Controllers\CheckoutController;
use App\Middleware\CsrfMiddleware;
use App\Middleware\CustomerAuthMiddleware;

// Hallazgo MEDIO de la auditoría: POST /checkout/address/new no tenía CSRF.
// La ruta homóloga /account/address/new sí lo declara; replicar ese patrón.
$router = new Router();
require __DIR__ . '/../routes/routes.php';

$routes = $router->getRoutes();

$failed = 0;

function assertRouteProtected(array $routes, string $method, string $uri, string $class, string $methodName, string $middleware): bool
{
    foreach ($routes as $route) {
        if (
            $route['method'] === $method
            && $route['uri'] === $uri
            && is_array($route['handler'])
            && $route['handler'][0] === $class
            && $route['handler'][1] === $methodName
            && in_array($middleware, (array) ($route['middleware'] ?? []), true)
        ) {
            return true;
        }
    }
    return false;
}

$check = fn (string $label, bool $ok): int => (function (string $label, bool $ok) use (&$failed): int {
    echo ($ok ? 'PASS' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failed++;
    }
    return $failed;
})($label, $ok);

$check('POST /checkout/address/new -> CheckoutController@addAddress',
    assertRouteProtected($routes, 'POST', '/checkout/address/new', CheckoutController::class, 'addAddress', CsrfMiddleware::class));
$check('POST /checkout/address/new autenticada (CustomerAuthMiddleware)',
    assertRouteProtected($routes, 'POST', '/checkout/address/new', CheckoutController::class, 'addAddress', CustomerAuthMiddleware::class));

echo "\n=== CSRF /checkout/address/new (hallazgo MEDIO auditoría) OK ===\n";

if ($failed > 0) {
    exit(1);
}
