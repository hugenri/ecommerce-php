<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Admin;

use App\Modules\Checkout\Domain\SaleRepositoryInterface;
use App\Modules\Inventory\Application\UseCases\RegisterSaleMovementUseCase;

class CancelSaleUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private RegisterSaleMovementUseCase $registerSaleMovement,
    ) {}

    public function execute(int $saleId, ?int $userId = null): void
    {
        $sale = $this->saleRepository->findSaleById($saleId);
        if (!$sale) {
            throw new \DomainException('Venta no encontrada.');
        }

        if ($sale->getStatus() === 'cancelled') {
            throw new \DomainException('La venta ya está cancelada.');
        }

        if (!in_array($sale->getStatus(), ['pending', 'processing'], true)) {
            throw new \DomainException('Solo se puede cancelar pedidos pendientes o en proceso.');
        }

        $this->saleRepository->transaction(function () use ($saleId, $sale, $userId) {
            $details = $this->saleRepository->getDetailsForCancel($saleId);
            foreach ($details as $detail) {
                $this->registerSaleMovement->restoreStock(
                    productId: (int) $detail['product_id'],
                    quantity: (int) $detail['quantity'],
                    saleId: $saleId,
                    userId: $userId,
                    reason: 'Cancelación de venta ' . $sale->getSaleCode(),
                );
            }

            $this->saleRepository->updateSaleStatus($saleId, 'cancelled');
            $this->saleRepository->cancelDeliveryForSale($saleId);
        });
    }
}
