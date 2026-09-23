<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Framework\Session\SessionManagerInterface;
use App\Shared\Support\IntendedUrl;

class CustomerAuthMiddleware extends Middleware
{
    protected array $publicRoutes = [
        '/',
        '/login',
        '/register',
    ];

    public function __construct(
        private SessionManagerInterface $sessionManager
    ) {}

    public function handle(array $request)
    {
        if (!$this->sessionManager->validate()) {
            $this->sessionManager->destroy();
            $this->redirectToLogin();
        }

        if ($this->isPublicRoute($request['uri'] ?? '/')) {
            return true;
        }

        if (!$this->sessionManager->has('customer')) {
            $this->redirectToLogin();
        }

        $customer = $this->sessionManager->get('customer');
        if (isset($customer['is_active']) && !$customer['is_active']) {
            $this->sessionManager->remove('customer');
            $this->redirectToLogin('Cuenta desactivada');
        }

        if ($this->sessionManager->isExpired()) {
            $this->sessionManager->destroy();
            $this->redirectToLogin('Sesión expirada');
        }

        $this->sessionManager->set('customer.last_activity', time());

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

    protected function redirectToLogin(string $message = ''): void
    {
        IntendedUrl::remember();

        if ($message) {
            $_SESSION['flash_error'] = $message;
        }
        header('HTTP/1.1 401 Unauthorized');
        header('Location: /login');
        exit;
    }
}
