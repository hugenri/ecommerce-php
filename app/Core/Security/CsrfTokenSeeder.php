<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Framework\Security\TokenGeneratorInterface;
use App\Framework\Session\SessionManagerInterface;

/**
 * Garantiza que toda sesión (incluidas las de invitado) tenga token CSRF.
 *
 * El token se siembra una sola vez al inicio de la sesión; login y checkout
 * reutilizan el existente, evitando invalidar formularios ya renderizados.
 */
class CsrfTokenSeeder
{
    public function __construct(
        private SessionManagerInterface $sessionManager,
        private TokenGeneratorInterface $tokenGenerator
    ) {
    }

    public function ensure(): void
    {
        if ($this->sessionManager->get('csrf_token')) {
            return;
        }

        $this->sessionManager->set('csrf_token', $this->tokenGenerator->generateCsrfToken());
    }
}