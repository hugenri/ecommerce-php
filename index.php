<?php

declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\AppConfig;
use App\Core\Container;
use App\Core\ErrorHandler;
use App\Core\Maintenance\GuestCartMaintenance;
use App\Core\Router;
use App\Core\Security\CsrfTokenSeeder;
use App\Framework\Session\SessionManagerInterface;
use App\Providers\AppServiceProvider;
use App\Providers\RouteServiceProvider;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$container = new Container();

AppServiceProvider::register($container);

$appConfig = $container->make(AppConfig::class);

ErrorHandler::register($appConfig->debug());

// Iniciar sesión
$session = $container->make(SessionManagerInterface::class);
$session->start([
    'name' => $appConfig->sessionName(),
    'lifetime' => $appConfig->sessionLifetime(),
    'force_secure' => $appConfig->sessionForceSecure(),
    'httponly' => true,
    'samesite' => 'Strict',
]);

// Garantizar token CSRF en toda sesión (invitados incluidos) desde el primer request
$container->make(CsrfTokenSeeder::class)->ensure();

// Limpieza diaria de carritos de invitado inactivos (TTL 30 días)
$container->make(GuestCartMaintenance::class)->runDaily();

$router = new Router($container);
$router->setBasePath($appConfig->basePath());

RouteServiceProvider::register($router);

$router->dispatch();

$session->save();