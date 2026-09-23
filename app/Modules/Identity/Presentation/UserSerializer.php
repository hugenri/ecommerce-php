<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Modules\Identity\Domain\User;

class UserSerializer
{
    public function toArray(User $user): array
    {
        return [
            'user_id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'is_active' => $user->isActive(),
            'avatar' => $user->getAvatar() ?? '',
            'avatar_url' => $this->getAvatarUrl($user),
            'phone' => $user->getPhone() ?? '',
            'last_login' => $user->getLastLogin()?->format('Y-m-d H:i:s'),
            'login_attempts' => $user->getLoginAttempts(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function getAvatarUrl(User $user): string
    {
        if ($user->getAvatar()) {
            return '/storage/avatars/' . $user->getAvatar();
        }
        $hash = md5(strtolower(trim($user->getEmail())));
        return "https://www.gravatar.com/avatar/{$hash}?s=200&d=mp";
    }

    public function getMemberSince(User $user): string
    {
        $createdAt = $user->getCreatedAt();
        if ($createdAt === null) {
            return 'Desconocido';
        }
        $now = new \DateTimeImmutable();
        $interval = $createdAt->diff($now);
        if ($interval->y > 0) {
            return $interval->y . ' año' . ($interval->y > 1 ? 's' : '');
        }
        if ($interval->m > 0) {
            return $interval->m . ' mes' . ($interval->m > 1 ? 'es' : '');
        }
        return $interval->d . ' día' . ($interval->d > 1 ? 's' : '');
    }
}
