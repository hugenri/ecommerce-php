<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain;

class HomeBanner
{
    public function __construct(
        private readonly ?int $bannerId,
        private ?string $title = null,
        private ?string $subtitle = null,
        private string $image = '',
        private ?string $altText = null,
        private ?\DateTimeImmutable $startsAt = null,
        private ?\DateTimeImmutable $endsAt = null,
        private int $sortOrder = 1,
        private bool $isActive = true,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getBannerId(): ?int
    {
        return $this->bannerId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withBannerId(int $bannerId): self
    {
        return $this->cloneWith(['bannerId' => $bannerId]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            bannerId: $overrides['bannerId'] ?? $this->bannerId,
            title: $overrides['title'] ?? $this->title,
            subtitle: $overrides['subtitle'] ?? $this->subtitle,
            image: $overrides['image'] ?? $this->image,
            altText: $overrides['altText'] ?? $this->altText,
            startsAt: $overrides['startsAt'] ?? $this->startsAt,
            endsAt: $overrides['endsAt'] ?? $this->endsAt,
            sortOrder: $overrides['sortOrder'] ?? $this->sortOrder,
            isActive: $overrides['isActive'] ?? $this->isActive,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}