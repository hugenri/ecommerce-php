<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\Authentication;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\PasswordHasherInterface;
use App\Modules\Identity\Application\DTO\AuthenticationResult;
use App\Framework\Session\SessionManagerInterface;
use App\Framework\Session\Store\SessionStoreInterface;
use App\Framework\Security\TokenGeneratorInterface;

class AuthenticateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private SessionManagerInterface $sessionManager,
        private SessionStoreInterface $sessionStore,
        private TokenGeneratorInterface $tokenGenerator,
    ) {}

    public function execute(string $email, string $password, string $ipAddress = '0.0.0.0', string $userAgent = 'Unknown'): ?AuthenticationResult
    {
        $user = $this->userRepository->findByEmailIncludingInactive($email);

        if (!$user) {
            error_log("Failed login attempt for email: $email");
            return null;
        }

        if ($user->isBlocked()) {
            throw new \DomainException('Cuenta bloqueada temporalmente. Intenta nuevamente en 30 minutos.');
        }

        if (!$user->verifyPassword($password, $this->passwordHasher)) {
            $user = $user->recordFailedLogin();
            $this->userRepository->save($user);
            error_log("Failed login attempt for email: $email");
            return null;
        }

        if (!$user->isActive()) {
            throw new \DomainException('Tu cuenta se encuentra desactivada. Comunícate con el administrador del sistema.');
        }

        $user = $user->recordSuccessfulLogin();
        $this->userRepository->save($user);

        $this->sessionManager->regenerateId();

        $sessionData = [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getRole(),
            'is_active' => $user->isActive(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'last_login' => date('Y-m-d H:i:s'),
            'session_id' => $this->sessionManager->getId(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];

        $this->sessionManager->set('user', $sessionData);

        $csrfToken = $this->tokenGenerator->generateCsrfToken();
        $this->sessionManager->set('csrf_token', $csrfToken);

        $this->sessionStore->save($user->getId(), [
            'session_id' => $this->sessionManager->getId(),
            'csrf_token' => $csrfToken,
            'ip_address' => $ipAddress,
            'user_agent_hash' => md5($userAgent),
            'expires_at' => date('Y-m-d H:i:s', time() + 7200),
        ]);

        return new AuthenticationResult($user, $sessionData);
    }
}
