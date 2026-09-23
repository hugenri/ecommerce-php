<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain;

interface HomeBannerRepositoryInterface
{
    /** @return array<int, HomeBanner> */
    public function all(): array;

    public function findActive(): ?HomeBanner;

    public function findById(int $id): ?HomeBanner;

    public function create(HomeBanner $banner): HomeBanner;

    public function update(HomeBanner $banner): HomeBanner;

    public function delete(int $id): bool;
}