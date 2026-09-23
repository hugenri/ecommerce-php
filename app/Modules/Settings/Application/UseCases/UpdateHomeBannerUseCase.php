<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\HomeBanner;

class UpdateHomeBannerUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(int $id, array $data): ?HomeBanner
    {
        $banner = $this->settingsService->findBanner($id);

        if (!$banner) {
            return null;
        }

        $this->validateLength('Título', $data['title'] ?? $banner->getTitle(), 255);
        $this->validateLength('Subtítulo', $data['subtitle'] ?? $banner->getSubtitle(), 1000);

        $updated = new HomeBanner(
            bannerId: $banner->getBannerId(),
            title: $this->nullable($data['title'] ?? $banner->getTitle()),
            subtitle: $this->nullable($data['subtitle'] ?? $banner->getSubtitle()),
            image: trim((string) ($data['image'] ?? $banner->getImage())),
            altText: $this->nullable($data['alt_text'] ?? $banner->getAltText()),
            startsAt: $this->dateTime($data['starts_at'] ?? null) ?? $banner->getStartsAt(),
            endsAt: $this->dateTime($data['ends_at'] ?? null) ?? $banner->getEndsAt(),
            sortOrder: (int) ($data['sort_order'] ?? $banner->getSortOrder()),
            isActive: isset($data['is_active'])
                ? $data['is_active'] === '1'
                : $banner->isActive(),
            createdAt: $banner->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
        );

        $saved = $this->settingsService->updateBanner($updated);
        $this->settingsService->clearCache();

        return $saved;
    }

    private function validateLength(string $field, mixed $value, int $max): void
    {
        $value = $this->nullable($value);
        if ($value !== null && mb_strlen($value) > $max) {
            throw new \DomainException("El campo {$field} no puede exceder {$max} caracteres.");
        }
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function dateTime(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->nullable($value);
        if ($value === null) {
            return null;
        }
        return new \DateTimeImmutable($value);
    }
}