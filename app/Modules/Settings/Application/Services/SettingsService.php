<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\Services;

use App\Modules\Settings\Domain\HomeBanner;
use App\Modules\Settings\Domain\HomeBannerRepositoryInterface;
use App\Modules\Settings\Domain\SiteSettings;
use App\Modules\Settings\Domain\SiteSettingsRepositoryInterface;

/**
 * Único punto de acceso a la configuración del sitio.
 *
 * Capa intermedia entre los Use Cases y los Repositories, con un cache en
 * memoria (por petición) para las lecturas públicas (SiteSettings y HeroBanner).
 * Sustituible por Redis sin modificar Controllers, Use Cases ni Views.
 */
class SettingsService
{
    private ?SiteSettings $siteSettings = null;

    private ?HomeBanner $heroBanner = null;

    public function __construct(
        private SiteSettingsRepositoryInterface $siteSettingsRepository,
        private HomeBannerRepositoryInterface $homeBannerRepository
    ) {}

    public function getSiteSettings(): ?SiteSettings
    {
        if ($this->siteSettings === null) {
            $this->siteSettings = $this->siteSettingsRepository->get();
        }

        return $this->siteSettings;
    }

    public function getHeroBanner(): ?HomeBanner
    {
        if ($this->heroBanner === null) {
            $this->heroBanner = $this->homeBannerRepository->findActive();
        }

        return $this->heroBanner;
    }

    /** @return array<int, HomeBanner> */
    public function listBanners(): array
    {
        return $this->homeBannerRepository->all();
    }

    public function findBanner(int $id): ?HomeBanner
    {
        return $this->homeBannerRepository->findById($id);
    }

    public function saveSiteSettings(SiteSettings $settings): SiteSettings
    {
        return $this->siteSettingsRepository->save($settings);
    }

    public function createBanner(HomeBanner $banner): HomeBanner
    {
        return $this->homeBannerRepository->create($banner);
    }

    public function updateBanner(HomeBanner $banner): HomeBanner
    {
        return $this->homeBannerRepository->update($banner);
    }

    public function deleteBanner(int $id): bool
    {
        return $this->homeBannerRepository->delete($id);
    }

    public function clearCache(): void
    {
        $this->siteSettings = null;
        $this->heroBanner = null;
    }
}