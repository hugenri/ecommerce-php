<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\SiteSettings;

class UpdateSiteSettingsUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(array $data): SiteSettings
    {
        $current = $this->settingsService->getSiteSettings();

        $settings = new SiteSettings(
            settingId: $current?->getSettingId(),
            storeName: trim((string) $data['store_name']),
            logo: $this->nullable($data['logo'] ?? null),
            favicon: $this->nullable($data['favicon'] ?? null),
            slogan: $this->nullable($data['slogan'] ?? null),
            contactEmail: $this->nullable($data['contact_email'] ?? null),
            phone: $this->nullable($data['phone'] ?? null),
            whatsapp: $this->nullable($data['whatsapp'] ?? null),
            address: $this->nullable($data['address'] ?? null),
            businessHours: $this->nullable($data['business_hours'] ?? null),
            facebookUrl: $this->nullable($data['facebook_url'] ?? null),
            instagramUrl: $this->nullable($data['instagram_url'] ?? null),
            tiktokUrl: $this->nullable($data['tiktok_url'] ?? null),
            createdAt: $current?->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
        );

        $saved = $this->settingsService->saveSiteSettings($settings);
        $this->settingsService->clearCache();

        return $saved;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}