<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain;

interface SiteSettingsRepositoryInterface
{
    public function get(): ?SiteSettings;

    public function save(SiteSettings $settings): SiteSettings;
}