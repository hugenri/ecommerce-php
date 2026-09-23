<?php

declare(strict_types=1);

namespace App\Modules\Settings\Persistence;

use App\Core\Database\Database;
use App\Modules\Settings\Domain\HomeBanner;
use App\Modules\Settings\Domain\HomeBannerRepositoryInterface;

class HomeBannerRepository implements HomeBannerRepositoryInterface
{
    protected string $table = 'home_banners';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $sql = "SELECT banner_id, title, subtitle, image, alt_text, starts_at, ends_at, sort_order, is_active, created_at, updated_at FROM {$this->table} ORDER BY sort_order ASC, banner_id ASC";
        return array_map(fn(array $row) => $this->hydrate($row), $this->db->select($sql));
    }

    public function findActive(): ?HomeBanner
    {
        $sql = "SELECT banner_id, title, subtitle, image, alt_text, starts_at, ends_at, sort_order, is_active, created_at, updated_at FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, banner_id ASC LIMIT 1";
        $row = $this->db->selectOne($sql);
        return $row ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?HomeBanner
    {
        $sql = "SELECT banner_id, title, subtitle, image, alt_text, starts_at, ends_at, sort_order, is_active, created_at, updated_at FROM {$this->table} WHERE banner_id = :id LIMIT 1";
        $row = $this->db->selectOne($sql, ['id' => $id]);
        return $row ? $this->hydrate($row) : null;
    }

    public function create(HomeBanner $banner): HomeBanner
    {
        $data = $this->dehydrate($banner);
        unset($data['banner_id']);

        if ((int) $data['sort_order'] < 1) {
            $data['sort_order'] = $this->nextSortOrder();
        }

        $id = $this->db->insert($this->table, $data);
        return $banner->withBannerId((int) $id);
    }

    public function update(HomeBanner $banner): HomeBanner
    {
        $data = $this->dehydrate($banner);
        $this->db->update($this->table, $data, ['banner_id' => $banner->getBannerId()]);
        return $banner;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete($this->table, ['banner_id' => $id]) > 0;
    }

    private function nextSortOrder(): int
    {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) AS max_order FROM {$this->table}";
        $row = $this->db->selectOne($sql);
        return ((int) ($row['max_order'] ?? 0)) + 1;
    }

    private function hydrate(array $row): HomeBanner
    {
        return new HomeBanner(
            bannerId: (int) $row['banner_id'],
            title: $row['title'] ?? null,
            subtitle: $row['subtitle'] ?? null,
            image: $row['image'],
            altText: $row['alt_text'] ?? null,
            startsAt: isset($row['starts_at']) && $row['starts_at'] !== null
                ? new \DateTimeImmutable($row['starts_at'])
                : null,
            endsAt: isset($row['ends_at']) && $row['ends_at'] !== null
                ? new \DateTimeImmutable($row['ends_at'])
                : null,
            sortOrder: (int) $row['sort_order'],
            isActive: (bool) $row['is_active'],
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable($row['created_at'])
                : null,
            updatedAt: isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    private function dehydrate(HomeBanner $banner): array
    {
        return [
            'banner_id' => $banner->getBannerId(),
            'title' => $banner->getTitle(),
            'subtitle' => $banner->getSubtitle(),
            'image' => $banner->getImage(),
            'alt_text' => $banner->getAltText(),
            'starts_at' => $banner->getStartsAt()?->format('Y-m-d H:i:s'),
            'ends_at' => $banner->getEndsAt()?->format('Y-m-d H:i:s'),
            'sort_order' => $banner->getSortOrder(),
            'is_active' => $banner->isActive() ? 1 : 0,
            'created_at' => $banner->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $banner->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}