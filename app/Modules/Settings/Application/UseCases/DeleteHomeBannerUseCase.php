<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;

class DeleteHomeBannerUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(int $id): bool
    {
        if (count($this->settingsService->listBanners()) <= 1) {
            throw new \DomainException('No se puede eliminar el único banner del Home.');
        }

        $deleted = $this->settingsService->deleteBanner($id);
        $this->settingsService->clearCache();

        return $deleted;
    }
}