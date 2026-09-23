<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\Authentication;

use App\Config\AppConfig;
use App\Core\Mail\MailService;
use App\Core\Security\TokenGenerator;
use App\Core\Security\TokenRateLimiter;
use App\Modules\Identity\Domain\UserToken;
use App\Modules\Identity\Domain\UserTokenRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;

class RequestPasswordResetUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserTokenRepositoryInterface $userTokenRepository,
        private TokenGenerator $tokenGenerator,
        private MailService $mailService,
        private TokenRateLimiter $rateLimiter,
        private AppConfig $appConfig,
    ) {}

    public function execute(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->isAccountActive()) {
            return;
        }

        $userId = (int) $user->getId();
        $type = UserToken::PASSWORD_RESET;

        $latest = $this->userTokenRepository->findLatestToken($userId, $type);

        if ($this->rateLimiter->wasSentRecently($latest?->getSentAt())) {
            return;
        }

        $this->userTokenRepository->deleteActiveTokens($userId, $type);

        $verification = $this->tokenGenerator->generateEmailVerificationToken();

        $this->userTokenRepository->createToken(
            $userId,
            $type,
            $verification['hash'],
            (new \DateTimeImmutable())->modify('+24 hours'),
        );

        $base = rtrim($this->appConfig->appUrl(), '/');
        $link = $base . '/access/reset-password?token=' . urlencode($verification['token']);

        $this->mailService->sendTemplate(
            $user->getEmail(),
            'Restablece tu contraseña',
            'reset_password',
            ['link' => $link, 'name' => $user->getName()],
        );
    }
}