<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Framework\Session\SessionManagerInterface;

class AdminMiddleware extends Middleware
{
    public function __construct(
        private SessionManagerInterface $sessionManager
    ) {}

    public function handle(array $request)
    {
        if (!$this->sessionManager->has('user')) {
            header('Location: /');
            exit;
        }

        $userRole = $this->sessionManager->get('user.role');

        if ($userRole !== 'admin') {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title>'
                . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
                . '</head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">'
                . '<div class="text-center"><h1 class="display-4 text-danger">403</h1>'
                . '<p class="lead">No tienes permisos para acceder a esta pagina.</p>'
                . '<a href="/" class="btn btn-primary">Volver al inicio</a></div>'
                . '</body></html>';
            exit;
        }

        return true;
    }
}
