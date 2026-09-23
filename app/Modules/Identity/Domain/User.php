<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

class User
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private string $email,
        private string $password,
        private string $role = 'employee',
        private bool $isActive = true,
        private ?string $avatar = null,
        private ?string $phone = null,
        private ?\DateTimeImmutable $lastLogin = null,
        private int $loginAttempts = 0,
        private ?\DateTimeImmutable $lastAttempt = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getRole(): string { return $this->role; }
    public function isActive(): bool { return $this->isActive; }
    public function getAvatar(): ?string { return $this->avatar; }
    public function getPhone(): ?string { return $this->phone; }
    public function getLastLogin(): ?\DateTimeImmutable { return $this->lastLogin; }
    public function getLoginAttempts(): int { return $this->loginAttempts; }
    public function getLastAttempt(): ?\DateTimeImmutable { return $this->lastAttempt; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAccountActive(): bool
    {
        return $this->isActive;
    }

    public function isBlocked(): bool
    {
        if ($this->loginAttempts < 5) {
            return false;
        }
        if ($this->lastAttempt === null) {
            return false;
        }
        return time() - $this->lastAttempt->getTimestamp() < 1800;
    }

    public function verifyPassword(string $password, PasswordHasherInterface $hasher): bool
    {
        return $hasher->verify($password, $this->password);
    }

    // ──── Persistence helpers ────────────────────────

    public function withId(int $id): self
    {
        return $this->cloneWith(['id' => $id]);
    }

    public function withUpdatedTimestamp(): self
    {
        return $this->cloneWith(['updatedAt' => new \DateTimeImmutable()]);
    }

    // ──── Domain methods ────────────────────────────

    public function recordSuccessfulLogin(): self
    {
        return $this->cloneWith([
            'lastLogin' => new \DateTimeImmutable(),
            'loginAttempts' => 0,
            'lastAttempt' => null,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function recordFailedLogin(): self
    {
        return $this->cloneWith([
            'loginAttempts' => $this->loginAttempts + 1,
            'lastAttempt' => new \DateTimeImmutable(),
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function setPassword(string $hashedPassword): self
    {
        return $this->cloneWith([
            'password' => $hashedPassword,
            'loginAttempts' => 0,
            'lastAttempt' => null,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function updateProfile(
        string $name,
        string $email,
        ?string $phone = null,
        ?string $avatar = null
    ): self {
        return $this->cloneWith([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'avatar' => $avatar,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function changeRole(string $role): self
    {
        return $this->cloneWith([
            'role' => $role,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function activate(): self
    {
        return $this->cloneWith([
            'isActive' => true,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function deactivate(): self
    {
        return $this->cloneWith([
            'isActive' => false,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            id: $overrides['id'] ?? $this->id,
            name: $overrides['name'] ?? $this->name,
            email: $overrides['email'] ?? $this->email,
            password: $overrides['password'] ?? $this->password,
            role: $overrides['role'] ?? $this->role,
            isActive: $overrides['isActive'] ?? $this->isActive,
            avatar: $overrides['avatar'] ?? $this->avatar,
            phone: $overrides['phone'] ?? $this->phone,
            lastLogin: $overrides['lastLogin'] ?? $this->lastLogin,
            loginAttempts: $overrides['loginAttempts'] ?? $this->loginAttempts,
            lastAttempt: $overrides['lastAttempt'] ?? $this->lastAttempt,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}
