<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

class Customer
{
    public function __construct(
        private readonly ?int $customerId,
        private string $firstName,
        private string $lastNamePaternal,
        private string $email,
        private string $password,
        private ?string $lastNameMaternal = null,
        private ?string $phone = null,
        private bool $active = true,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
        private ?\DateTimeImmutable $lastLogin = null,
        private ?\DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public function getCustomerId(): ?int { return $this->customerId; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastNamePaternal(): string { return $this->lastNamePaternal; }
    public function getLastNameMaternal(): ?string { return $this->lastNameMaternal; }
    public function getFullName(): string
    {
        $name = $this->firstName . ' ' . $this->lastNamePaternal;
        if ($this->lastNameMaternal) {
            $name .= ' ' . $this->lastNameMaternal;
        }
        return $name;
    }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getPhone(): ?string { return $this->phone; }
    public function isActive(): bool { return $this->active; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getLastLogin(): ?\DateTimeImmutable { return $this->lastLogin; }
    public function getEmailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }
    public function isEmailVerified(): bool { return $this->emailVerifiedAt !== null; }

    public function withProfile(string $firstName, string $lastNamePaternal, ?string $lastNameMaternal, ?string $phone): self
    {
        return $this->cloneWith([
            'firstName' => $firstName,
            'lastNamePaternal' => $lastNamePaternal,
            'lastNameMaternal' => $lastNameMaternal,
            'phone' => $phone,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function setPassword(string $hashedPassword): self
    {
        return $this->cloneWith([
            'password' => $hashedPassword,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function verifyPassword(string $password, \App\Modules\Identity\Domain\PasswordHasherInterface $hasher): bool
    {
        return $hasher->verify($password, $this->password);
    }

    public function recordSuccessfulLogin(): self
    {
        return $this->cloneWith([
            'lastLogin' => new \DateTimeImmutable(),
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function activate(): self
    {
        return $this->cloneWith([
            'active' => true,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function deactivate(): self
    {
        return $this->cloneWith([
            'active' => false,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            customerId: $overrides['customerId'] ?? $this->customerId,
            firstName: $overrides['firstName'] ?? $this->firstName,
            lastNamePaternal: $overrides['lastNamePaternal'] ?? $this->lastNamePaternal,
            email: $overrides['email'] ?? $this->email,
            password: $overrides['password'] ?? $this->password,
            lastNameMaternal: $overrides['lastNameMaternal'] ?? $this->lastNameMaternal,
            phone: $overrides['phone'] ?? $this->phone,
            active: $overrides['active'] ?? $this->active,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
            lastLogin: $overrides['lastLogin'] ?? $this->lastLogin,
            emailVerifiedAt: $overrides['emailVerifiedAt'] ?? $this->emailVerifiedAt,
        );
    }
}
