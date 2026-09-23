<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Framework\Session\SessionManagerInterface;

class EmployeeMiddleware extends Middleware
{
    public function __construct(
        private SessionManagerInterface $sessionManager
    ) {}

    public function handle(array $request)
    {
        $user = $this->sessionManager->get('user');

        if (!$user || $user['role'] !== 'employee') {
            header('Location: /');
            exit;
        }

        return true;
    }
}
