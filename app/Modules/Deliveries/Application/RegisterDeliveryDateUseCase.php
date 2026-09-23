<?php

declare(strict_types=1);

namespace App\Modules\Deliveries\Application;

use App\Modules\Deliveries\Domain\DeliveryRepositoryInterface;

class RegisterDeliveryDateUseCase
{
    public function __construct(
        private DeliveryRepositoryInterface $deliveryRepository,
    ) {}

    public function execute(int $deliveryId, string $date): void
    {
        $normalized = $this->normalizeDate($date);
        if ($normalized === null) {
            throw new \DomainException('Fecha de entrega inválida.');
        }

        $delivery = $this->deliveryRepository->findById($deliveryId);
        if (!$delivery) {
            throw new \DomainException('Entrega no encontrada.');
        }

        if (!empty($delivery['shipping_date']) && $normalized < $delivery['shipping_date']) {
            throw new \DomainException('La fecha de entrega debe ser mayor o igual a la fecha de envío.');
        }

        $this->deliveryRepository->registerDeliveryDate($deliveryId, $normalized);
    }

    private function normalizeDate(string $date): ?string
    {
        $normalized = str_replace('T', ' ', $date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
            $normalized .= ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            $normalized .= ':00';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized)) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }
}
