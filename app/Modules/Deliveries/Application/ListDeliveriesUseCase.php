<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class ListDeliveriesUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $page = 1, int $perPage = 10, string $search = '', array $filters = []): array
    {
        return $this->deliveryRepository->findAll($page, $perPage, $search, $filters);
    }
}
