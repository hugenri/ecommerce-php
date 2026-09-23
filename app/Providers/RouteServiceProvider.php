<?php

namespace App\Providers;

use App\Core\Router;

class RouteServiceProvider
{
    public static function register(Router $router): void
    {
        require __DIR__ . '/../../routes/routes.php';
    }
}
