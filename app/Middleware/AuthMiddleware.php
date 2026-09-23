<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Framework\Session\SessionManagerInterface;

class AuthMiddleware extends Middleware
{
    protected array $publicRoutes = [
        '/',
        '/login',
        '/register',
        '/access/login',
        '/password/reset',
        '/api/auth/*'
    ];

    protected array $apiRoutes = [
        '/api/*'
    ];

    public function __construct(
        private SessionManagerInterface $sessionManager
    ) {}

    public function handle(array $request)
    {
        if (!$this->sessionManager->validate()) {
            $this->sessionManager->destroy();
            $this->unauthorized('Sesión inválida o manipulada');
        }

        if ($this->isPublicRoute($request['uri'] ?? '/')) {
            return true;
        }

        if (!$this->sessionManager->has('user')) {
            $this->unauthorized('No autenticado');
        }

        $user = $this->sessionManager->get('user');
        if (isset($user['is_active']) && !$user['is_active']) {
            $this->unauthorized('Cuenta desactivada');
        }

        if ($this->sessionManager->isExpired()) {
            $this->sessionManager->destroy();
            $this->unauthorized('Sesión expirada');
        }

        $this->sessionManager->set('user.last_activity', time());

        return true;
    }

    protected function isPublicRoute(string $uri): bool
    {
        foreach ($this->publicRoutes as $route) {
            if (str_contains($route, '*')) {
                $pattern = str_replace('/', '\/', $route);
                $pattern = str_replace('*', '.*', $pattern);
                $pattern = '/^' . $pattern . '$/';
                if (preg_match($pattern, $uri)) {
                    return true;
                }
            } elseif ($route === $uri) {
                return true;
            }
        }
        return false;
    }

    protected function isApiRoute(string $uri): bool
    {
        foreach ($this->apiRoutes as $route) {
            if (str_starts_with($uri, $route)) {
                return true;
            }
        }
        return false;
    }

    protected function unauthorized(string $message = 'No autorizado'): void
    {
        $isApiRoute = $this->isApiRoute($_SERVER['REQUEST_URI'] ?? '');

        if ($isApiRoute) {
            header('Content-Type: application/json');
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'code' => 401,
                'timestamp' => time()
            ]);
        } else {
            header('HTTP/1.1 401 Unauthorized');
            if (!headers_sent()) {
                header('Location: /access/login?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            }
            echo '<html><body>';
            echo '<h1>401 Unauthorized</h1>';
            echo '<p>' . htmlspecialchars($message) . '</p>';
            echo '<p><a href="/access/login">Iniciar sesión</a></p>';
            echo '</body></html>';
        }
        exit;
    }
}
