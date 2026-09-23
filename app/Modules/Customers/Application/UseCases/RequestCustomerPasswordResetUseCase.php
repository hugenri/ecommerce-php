<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Config\AppConfig;
use App\Core\Mail\MailService;
use App\Core\Security\TokenGenerator;
use App\Core\Security\TokenRateLimiter;
use App\Modules\Customers\Domain\CustomerRepositoryInterface;
use App\Modules\Customers\Domain\CustomerToken;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;

class RequestCustomerPasswordResetUseCase
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CustomerTokenRepositoryInterface $customerTokenRepository,
        private TokenGenerator $tokenGenerator,
        private MailService $mailService,
        private TokenRateLimiter $rateLimiter,
        private AppConfig $appConfig,
    ) {}

    public function execute(string $email): void
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (!$customer || !$customer->isActive()) {
            return;
        }

        $customerId = (int) $customer->getCustomerId();
        $type = CustomerToken::PASSWORD_RESET;

        $latest = $this->customerTokenRepository->findLatestToken($customerId, $type);

        if ($this->rateLimiter->wasSentRecently($latest?->getSentAt())) {
            return;
        }

        $this->customerTokenRepository->deleteActiveTokens($customerId, $type);

        $verification = $this->tokenGenerator->generateEmailVerificationToken();

        $this->customerTokenRepository->createToken(
            $customerId,
            $type,
            $verification['hash'],
            (new \DateTimeImmutable())->modify('+24 hours'),
        );

        $base = rtrim($this->appConfig->appUrl(), '/');
        $link = $base . '/reset-password?token=' . urlencode($verification['token']);

        $this->mailService->sendTemplate(
            $customer->getEmail(),
            'Restablece tu contraseña',
            'reset_password',
            ['link' => $link, 'name' => $customer->getFirstName()],
        );
    }
}