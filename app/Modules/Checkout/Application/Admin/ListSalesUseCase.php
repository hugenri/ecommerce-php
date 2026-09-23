<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Admin;

use App\Modules\Checkout\Domain\SaleRepositoryInterface;

class ListSalesUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'sale_date',
        string $sortDir = 'DESC',
        array $filters = [],
    ): array {
        return $this->saleRepository->findAll(
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
            filters: $filters,
        );
    }
}
