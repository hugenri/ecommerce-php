<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class SearchDeliveriesUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(string $term, int $page = 1, int $perPage = 10): array
    {
        return $this->deliveryRepository->search($term, $page, $perPage);
    }
}
