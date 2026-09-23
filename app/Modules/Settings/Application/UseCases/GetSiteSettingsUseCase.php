<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\SiteSettings;

class GetSiteSettingsUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(): ?SiteSettings
    {
        return $this->settingsService->getSiteSettings();
    }
}