<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;

class ListHomeBannersUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(): array
    {
        return $this->settingsService->listBanners();
    }
}