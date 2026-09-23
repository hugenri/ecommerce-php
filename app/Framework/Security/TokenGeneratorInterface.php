<?php

declare(strict_types=1);

namespace App\Framework\Security;

interface TokenGeneratorInterface
{
    public function generateCsrfToken(): string;
}
