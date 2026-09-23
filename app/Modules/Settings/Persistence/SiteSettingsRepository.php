<?php

declare(strict_types=1);

namespace App\Modules\Settings\Persistence;

use App\Core\Database\Database;
use App\Modules\Settings\Domain\SiteSettings;
use App\Modules\Settings\Domain\SiteSettingsRepositoryInterface;

class SiteSettingsRepository implements SiteSettingsRepositoryInterface
{
    protected string $table = 'site_settings';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get(): ?SiteSettings
    {
        $sql = "SELECT * FROM {$this->table} WHERE setting_id = 1 LIMIT 1";
        $row = $this->db->selectOne($sql);
        return $row ? $this->hydrate($row) : null;
    }

    public function save(SiteSettings $settings): SiteSettings
    {
        $data = $this->dehydrate($settings);
        unset($data['setting_id']);

        $this->db->update($this->table, $data, ['setting_id' => 1]);

        return $settings->withSettingId(1);
    }

    private function hydrate(array $row): SiteSettings
    {
        return new SiteSettings(
            settingId: (int) $row['setting_id'],
            storeName: $row['store_name'],
            logo: $row['logo'] ?? null,
            favicon: $row['favicon'] ?? null,
            slogan: $row['slogan'] ?? null,
            contactEmail: $row['contact_email'] ?? null,
            phone: $row['phone'] ?? null,
            whatsapp: $row['whatsapp'] ?? null,
            address: $row['address'] ?? null,
            businessHours: $row['business_hours'] ?? null,
            facebookUrl: $row['facebook_url'] ?? null,
            instagramUrl: $row['instagram_url'] ?? null,
            tiktokUrl: $row['tiktok_url'] ?? null,
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    private function dehydrate(SiteSettings $settings): array
    {
        return [
            'setting_id' => $settings->getSettingId(),
            'store_name' => $settings->getStoreName(),
            'logo' => $settings->getLogo(),
            'favicon' => $settings->getFavicon(),
            'slogan' => $settings->getSlogan(),
            'contact_email' => $settings->getContactEmail(),
            'phone' => $settings->getPhone(),
            'whatsapp' => $settings->getWhatsapp(),
            'address' => $settings->getAddress(),
            'business_hours' => $settings->getBusinessHours(),
            'facebook_url' => $settings->getFacebookUrl(),
            'instagram_url' => $settings->getInstagramUrl(),
            'tiktok_url' => $settings->getTiktokUrl(),
            'created_at' => $settings->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $settings->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}