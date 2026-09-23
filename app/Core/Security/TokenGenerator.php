<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Framework\Security\TokenGeneratorInterface;

class TokenGenerator implements TokenGeneratorInterface
{
    private int $length = 32;

    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes($this->length));
    }

    public function generateEmailVerificationToken(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
        ];
    }
}
