<?php

declare(strict_types=1);

namespace App\Core\Security;

final class TokenRateLimiter
{
    public const WINDOW_SECONDS = 60;

    public function wasSentRecently(?\DateTimeImmutable $sentAt, int $seconds = self::WINDOW_SECONDS): bool
    {
        if (!$sentAt) {
            return false;
        }

        $cutoff = (new \DateTimeImmutable())->modify("-{$seconds} seconds");

        return $sentAt >= $cutoff;
    }
}