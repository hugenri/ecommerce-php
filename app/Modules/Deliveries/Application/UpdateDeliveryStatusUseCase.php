<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class UpdateDeliveryStatusUseCase
{
    private const ALLOWED_STATUSES = ['pending', 'preparing', 'shipped', 'delivered', 'cancelled'];

    private const SALE_STATUS_BY_DELIVERY_STATUS = [
        'pending' => 'pending',
        'preparing' => 'processing',
        'shipped' => 'shipped',
        'delivered' => 'delivered',
        'cancelled' => 'cancelled',
    ];

    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $deliveryId, string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \DomainException('Estado de entrega inválido.');
        }

        $delivery = $this->deliveryRepository->findById($deliveryId);
        if (!$delivery) {
            throw new \DomainException('Entrega no encontrada.');
        }

        if (($delivery['sale_status'] ?? '') === 'cancelled') {
            throw new \DomainException('No se puede modificar la entrega de una venta cancelada.');
        }

        $current = $delivery['status'];
        if ($status === $current) {
            throw new \DomainException('La entrega ya tiene ese estado.');
        }

        if ($current === 'delivered' && $status === 'pending') {
            throw new \DomainException('Una entrega entregada no puede volver a estado pendiente.');
        }

        if ($current === 'cancelled' && $status === 'shipped') {
            throw new \DomainException('Una entrega cancelada no puede enviarse.');
        }

        if ($status === 'shipped' && ($delivery['payment_status'] ?? '') !== 'paid') {
            throw new \DomainException('Solo las ventas pagadas pueden enviarse.');
        }

        $this->deliveryRepository->updateStatus($deliveryId, $status);

        $saleStatus = self::SALE_STATUS_BY_DELIVERY_STATUS[$status];
        if (($delivery['sale_status'] ?? '') !== $saleStatus) {
            $this->deliveryRepository->syncSaleStatus((int) $delivery['sale_id'], $saleStatus);
        }
    }
}
