<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Admin;

use App\Modules\Checkout\Domain\SaleRepositoryInterface;

class UpdateSaleStatusUseCase
{
    private const ALLOWED_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function __construct(
        private SaleRepositoryInterface $saleRepository,
    ) {}

    public function execute(int $saleId, string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \DomainException('Estado inválido.');
        }

        $sale = $this->saleRepository->findSaleById($saleId);
        if (!$sale) {
            throw new \DomainException('Venta no encontrada.');
        }

        if ($sale->getStatus() === 'cancelled') {
            throw new \DomainException('No se puede cambiar el estado de una venta cancelada.');
        }

        if ($sale->getStatus() === 'delivered') {
            throw new \DomainException('No se puede cambiar el estado de una venta entregada.');
        }

        $this->saleRepository->updateSaleStatus($saleId, $status);
    }
}
