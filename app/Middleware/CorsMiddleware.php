<?php

namespace App\Middleware;

/**
 * Middleware CORS opcional.
 *
 * Decisión (hallazgo INFO de la auditoría de seguridad): NO está registrado en
 * el pipeline a propósito. La aplicación es de un solo origen y ningún cliente
 * (JS/PHP) consume endpoints cross-origin, por lo que el estado actual ya es el
 * seguro: no se emiten cabeceras CORS. No registrar este middleware salvo que
 * exista un endpoint legítimo consumido desde otro origen; en ese caso, acotarlo
 * por ruta/grupo con una lista blanca de orígenes auditada.
 */
class CorsMiddleware extends Middleware
{
    private array $allowedOrigins = [
        'http://localhost:3000',
    ];

    public function handle(array $request)
    {
        // Permitir solo desde orígenes configurados
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            if (in_array($origin, $this->allowedOrigins, true)) {
                header("Access-Control-Allow-Origin: {$origin}");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
            }
        }

        // Headers de acceso permitidos
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }

            exit(0);
        }

        return true;
    }
}