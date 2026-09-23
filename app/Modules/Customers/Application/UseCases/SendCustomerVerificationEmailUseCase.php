<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\UseCases;

use App\Config\AppConfig;
use App\Core\Mail\MailService;
use App\Core\Security\TokenGenerator;
use App\Core\Security\TokenRateLimiter;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Customers\Domain\CustomerToken;
use App\Modules\Customers\Domain\CustomerTokenRepositoryInterface;

class SendCustomerVerificationEmailUseCase
{
    public function __construct(
        private CustomerTokenRepositoryInterface $customerTokenRepository,
        private TokenGenerator $tokenGenerator,
        private MailService $mailService,
        private TokenRateLimiter $rateLimiter,
        private AppConfig $appConfig,
    ) {}

    public function execute(Customer $customer): void
    {
        $customerId = (int) $customer->getCustomerId();
        $type = CustomerToken::EMAIL_VERIFICATION;

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

        $this->sendVerificationEmail($customer->getEmail(), $customer->getFirstName(), $verification['token']);
    }

    private function sendVerificationEmail(string $email, string $name, string $token): void
    {
        $base = rtrim($this->appConfig->appUrl(), '/');
        $link = $base . '/customer/verify-email?token=' . urlencode($token);

        $this->mailService->sendTemplate(
            $email,
            'Verifica tu correo electrónico',
            'email_verification',
            ['link' => $link, 'name' => $name],
        );
    }
}