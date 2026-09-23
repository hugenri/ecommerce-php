<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Router;
use App\Core\Security\CsrfTokenSeeder;
use App\Framework\Session\SessionManagerInterface;
use App\Middleware\CsrfMiddleware;
use App\Modules\Customers\Presentation\Controllers\CustomerAuthController;
use App\Modules\Store\Presentation\Controllers\CartController;
use App\Providers\CoreServiceProvider;
use App\Providers\SessionServiceProvider;

// Un invitado NUNCA llega sin token CSRF: la siembra ocurre al arrancar la
// sesión (no solo en /checkout o al hacer login). Sin esto, eliminar o vaciar
// el carrito siendo invitado fallaría SIEMPRE con "Token CSRF inválido o
// expirado": el header X-CSRF-Token se envía vacío porque csrf_token no existe.
//
// Este bloque se ejecuta ANTES de cualquier salida: en CLI, una vez que la
// aplicación emite texto, la sesión ya no puede iniciarse ("headers already
// sent"). use_cookies=0 desactiva el setcookie de destroy() en este entorno.
ini_set('session.use_cookies', '0');

$failed = 0;
$guestChecks = [];

$container = new Container();
CoreServiceProvider::register($container);
SessionServiceProvider::register($container);

$session = $container->make(SessionManagerInterface::class);
$session->start([
    'name' => 'CSRF_GUEST',
    'force_secure' => false,
]);

$container->make(CsrfTokenSeeder::class)->ensure();

$guestToken = $session->get('csrf_token');

$guestCheck = function (string $label, bool $ok) use (&$failed, &$guestChecks): void {
    $guestChecks[] = ($ok ? 'PASS' : 'FAIL') . ": $label";
    if (!$ok) {
        $failed++;
    }
};

$guestCheck('Sesión de invitado obtiene csrf_token tras el arranque de sesión',
    is_string($guestToken) && $guestToken !== '');
$guestCheck('El token es estable entre peticiones (no se regenera por request)',
    $guestToken === $session->get('csrf_token'));

$before = $session->getId();
$session->regenerateIdForced(true);
$after = $session->getId();
$guestCheck('Rotar el ID de sesión conserva el mismo csrf_token',
    $before !== $after && $session->get('csrf_token') === $guestToken);

$session->destroy();

foreach ($guestChecks as $line) {
    echo $line . "\n";
}

echo "\n=== Sesión de invitado SIEMPRE con csrf_token (bug carrito) OK ===\n";

// Hallazgo MEDIO de la auditoría: cart/remove, cart/clear y logout eran rutas mutantes vía GET
// sin protección CSRF. Se migran a POST + CsrfMiddleware y se elimina la variante GET.
$router = new Router();
require __DIR__ . '/../routes/routes.php';

$routes = $router->getRoutes();

function routeMatches(array $routes, string $method, string $uri, string $class, string $methodName): bool
{
    foreach ($routes as $route) {
        if (
            $route['method'] === $method
            && $route['uri'] === $uri
            && is_array($route['handler'])
            && $route['handler'][0] === $class
            && $route['handler'][1] === $methodName
        ) {
            return true;
        }
    }
    return false;
}

function routeHasMiddleware(array $routes, string $method, string $uri, string $middleware): bool
{
    foreach ($routes as $route) {
        if ($route['method'] === $method && $route['uri'] === $uri) {
            return in_array($middleware, (array) ($route['middleware'] ?? []), true);
        }
    }
    return false;
}

function routeRemoved(array $routes, string $method, string $uri): bool
{
    foreach ($routes as $route) {
        if ($route['method'] === $method && $route['uri'] === $uri) {
            return false;
        }
    }
    return true;
}

$check = function (string $label, bool $ok) use (&$failed): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failed++;
    }
};

// cart/remove y cart/clear ahora POST + CSRF
$check('POST /cart/remove/{id} -> CartController@removeFromCart',
    routeMatches($routes, 'POST', '/cart/remove/{id}', CartController::class, 'removeFromCart'));
$check('POST /cart/remove/{id} protegida con CsrfMiddleware',
    routeHasMiddleware($routes, 'POST', '/cart/remove/{id}', CsrfMiddleware::class));
$check('GET /cart/remove/{id} eliminada (ya no registrada)',
    routeRemoved($routes, 'GET', '/cart/remove/{id}'));

$check('POST /cart/clear -> CartController@clearCart',
    routeMatches($routes, 'POST', '/cart/clear', CartController::class, 'clearCart'));
$check('POST /cart/clear protegida con CsrfMiddleware',
    routeHasMiddleware($routes, 'POST', '/cart/clear', CsrfMiddleware::class));
$check('GET /cart/clear eliminada (ya no registrada)',
    routeRemoved($routes, 'GET', '/cart/clear'));

// logout: única variante POST + CSRF, eliminada la GET
$check('POST /logout -> CustomerAuthController@logout',
    routeMatches($routes, 'POST', '/logout', CustomerAuthController::class, 'logout'));
$check('POST /logout protegida con CsrfMiddleware',
    routeHasMiddleware($routes, 'POST', '/logout', CsrfMiddleware::class));
$check('GET /logout eliminada (ya no registrada)',
    routeRemoved($routes, 'GET', '/logout'));

echo "\n=== Rutas mutantes GET -> POST+CSRF (hallazgo MEDIO auditoría) OK ===\n";

if ($failed > 0) {
    exit(1);
}
