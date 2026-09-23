<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain;

class SiteSettings
{
    private ?int $settingId;

    public function __construct(
        ?int $settingId,
        private string $storeName,
        private ?string $logo = null,
        private ?string $favicon = null,
        private ?string $slogan = null,
        private ?string $contactEmail = null,
        private ?string $phone = null,
        private ?string $whatsapp = null,
        private ?string $address = null,
        private ?string $businessHours = null,
        private ?string $facebookUrl = null,
        private ?string $instagramUrl = null,
        private ?string $tiktokUrl = null,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->settingId = $settingId;
    }

    public function getSettingId(): ?int
    {
        return $this->settingId;
    }

    public function getStoreName(): string
    {
        return $this->storeName;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getFavicon(): ?string
    {
        return $this->favicon;
    }

    public function getSlogan(): ?string
    {
        return $this->slogan;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getBusinessHours(): ?string
    {
        return $this->businessHours;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function getTiktokUrl(): ?string
    {
        return $this->tiktokUrl;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function withSettingId(int $settingId): self
    {
        $clone = clone $this;
        $clone->settingId = $settingId;
        return $clone;
    }
}