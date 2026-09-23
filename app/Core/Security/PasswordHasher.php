<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Modules\Identity\Domain\PasswordHasherInterface;

class PasswordHasher implements PasswordHasherInterface
{
    private int $cost = 12;
    private string $algorithm = PASSWORD_BCRYPT;

    public function hash(string $password): string
    {
        $hash = password_hash($password, $this->algorithm, ['cost' => $this->cost]);

        if ($hash === false) {
            throw new \RuntimeException('Error al hashear la contraseña');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, ['cost' => $this->cost]);
    }
}
