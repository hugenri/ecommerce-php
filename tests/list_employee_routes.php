<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$container = new App\Core\Container();
$router = new App\Core\Router($container);
require __DIR__ . '/../routes/routes.php';

foreach ($router->getRoutes() as $route) {
    if (str_contains($route['uri'], '/employee')) {
        $handler = is_array($route['handler']) ? $route['handler'][1] : $route['handler'];
        echo $route['method'] . ' ' . $route['uri'] . ' -> ' . $handler . "\n";
    }
}
