<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Core\Http\Response;
use App\Framework\Session\SessionManagerInterface;

class CsrfMiddleware extends Middleware
{
    protected const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXCLUDED_ROUTES = [
        '/webhooks/*',
        '/login',
        '/register',
    ];

    public function __construct(
        private SessionManagerInterface $sessionManager,
        private Request $request,
        private Response $response
    ) {}

    public function handle(array $request)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            return true;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach (self::EXCLUDED_ROUTES as $route) {
            if (fnmatch($route, $uri)) {
                return true;
            }
        }

        $token = $this->getTokenFromRequest();

        $storedToken = $this->sessionManager->get('csrf_token');
        if (!$token || !$storedToken || !hash_equals($storedToken, $token)) {
            $this->forbidden('Token CSRF inválido o expirado');
        }

        return true;
    }

    protected function getTokenFromRequest(): ?string
    {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);

        if (isset($headers['x-csrf-token'])) {
            return $headers['x-csrf-token'];
        }

        if (isset($headers['x-xsrf-token'])) {
            return $headers['x-xsrf-token'];
        }

        $postData = $this->request->post();
        if (isset($postData['_csrf_token'])) {
            return $postData['_csrf_token'];
        }

        return null;
    }

    protected function forbidden(string $message = 'La solicitud no pudo ser validada.'): void
    {
        $this->response->error($message, 403);
        exit;
    }
}
