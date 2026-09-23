<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\UseCases;

use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\HomeBanner;

class CreateHomeBannerUseCase
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    public function execute(array $data): HomeBanner
    {
        $this->validateLength('Título', $data['title'] ?? null, 255);
        $this->validateLength('Subtítulo', $data['subtitle'] ?? null, 1000);

        $banner = new HomeBanner(
            bannerId: null,
            title: $this->nullable($data['title'] ?? null),
            subtitle: $this->nullable($data['subtitle'] ?? null),
            image: trim((string) $data['image']),
            altText: $this->nullable($data['alt_text'] ?? null),
            startsAt: $this->dateTime($data['starts_at'] ?? null),
            endsAt: $this->dateTime($data['ends_at'] ?? null),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            isActive: ($data['is_active'] ?? '1') === '1',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $created = $this->settingsService->createBanner($banner);
        $this->settingsService->clearCache();

        return $created;
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