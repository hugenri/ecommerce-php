<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;

// Hallazgo BAJO de la auditoría: el Router aplicaba el header
// X-HTTP-Method-Override a los POST, permitiendo "convertir" el método de una
// solicitud en GET/POST/DELETE/etc. (confusión de método/smuggling, aunque sin
// bypass real de CSRF porque el middleware valida el REQUEST_METHOD real).
// El header no lo usa ningún flujo del proyecto, así que se eliminó el soporte.

$failed = 0;
$results = [];

$check = function (string $label, bool $ok) use (&$failed, &$results): void {
    $results[] = ($ok ? 'PASS' : 'FAIL') . ": $label";
    if (!$ok) {
        $failed++;
    }
};

$handlers = [
    'GET'    => static fn (): string => 'GET_OK',
    'POST'   => static fn (): string => 'POST_OK',
    'PUT'    => static fn (): string => 'PUT_OK',
    'PATCH'  => static fn (): string => 'PATCH_OK',
    'DELETE' => static fn (): string => 'DELETE_OK',
];

function dispatchWith(array $server, array $handlers): mixed
{
    $_SERVER = array_merge($server, ['REQUEST_URI' => '/test']);
    $router = new Router();
    foreach ($handlers as $method => $handler) {
        $router->add($method, '/test', $handler);
    }
    return $router->dispatch();
}

// El override ya no tiene efecto: se enruta por el método HTTP real
$result = dispatchWith(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'GET'], $handlers);
$check('POST + override GET se enruta como POST (override ignorado)', $result === 'POST_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'POST'], $handlers);
$check('POST + override POST se enruta como POST', $result === 'POST_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'], $handlers);
$check('POST + override DELETE se enruta como POST (override ignorado)', $result === 'POST_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'PATCH'], $handlers);
$check('POST + override PATCH se enruta como POST (override ignorado)', $result === 'POST_OK');

// Métodos HTTP reales siguen funcionando (flujos admin vía fetch)
$result = dispatchWith(['REQUEST_METHOD' => 'PUT'], $handlers);
$check('PUT real alcanza su ruta', $result === 'PUT_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'PATCH'], $handlers);
$check('PATCH real alcanza su ruta', $result === 'PATCH_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'DELETE'], $handlers);
$check('DELETE real alcanza su ruta', $result === 'DELETE_OK');

$result = dispatchWith(['REQUEST_METHOD' => 'GET'], $handlers);
$check('GET real alcanza su ruta', $result === 'GET_OK');

foreach ($results as $line) {
    echo $line . "\n";
}

echo "\n=== X-HTTP-METHOD-OVERRIDE ELIMINADO DEL ROUTER (hallazgo BAJO auditoría) OK ===\n";

if ($failed > 0) {
    exit(1);
}